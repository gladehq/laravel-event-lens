<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Support\Str;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use Closure;

class EventLensProxy implements DispatcherContract
{
    protected DispatcherContract $original;
    protected EventRecorder $recorder;

    public function __construct(DispatcherContract $original, EventRecorder $recorder)
    {
        $this->original = $original;
        $this->recorder = $recorder;
    }

    public function listen($events, $listener = null)
    {
        if (! $this->shouldWrap($events)) {
            return $this->original->listen($events, $listener);
        }

        $wrappedListener = $this->wrapListener($events, $listener);
        return $this->original->listen($events, $wrappedListener);
    }

    protected function wrapListener($event, $listener)
    {
        return function (...$payload) use ($event, $listener) {
             $listenerName = $this->resolveListenerName($listener);

             $resolvedEvent = $event;
             if (isset($payload[0]) && is_object($payload[0])) {
                  if (class_exists((string)$event) && $payload[0] instanceof $event) {
                      $resolvedEvent = $payload[0];
                  }
             }
             $eventName = $this->resolveEventName($resolvedEvent);

             return $this->recorder->capture($eventName, $listenerName, $payload, function () use ($listener, $payload) {
                 return $this->callOriginalListener($listener, $payload);
             });
        };
    }

    protected function callOriginalListener($listener, $payload)
    {
        if ($listener instanceof Closure) {
            return $listener(...$payload);
        }

        if (is_array($listener)) {
            [$target, $method] = $listener;
            $instance = is_string($target) ? app($target) : $target;

            return $instance->{$method}(...array_values($payload));
        }

        if (is_string($listener)) {
            // Laravel 12 registers listeners as plain class name strings
            // (e.g. "App\Listeners\OrderListener"). Str::parseCallback
            // splits on '@' and defaults the method to 'handle' — matching
            // exactly what Laravel's own Dispatcher does internally.
            //
            // We invoke the method directly (not via app()->call()) because
            // BoundMethod cannot match numeric-keyed payload arrays to typed
            // method parameters, causing it to resolve the event class from
            // the container — which fails for events with required constructor
            // parameters.
            if (class_exists($listener) || str_contains($listener, '@')) {
                [$class, $method] = Str::parseCallback($listener, 'handle');

                return app($class)->{$method}(...array_values($payload));
            }

            return app()->call($listener, $payload);
        }

        return null;
    }

    public function hasListeners($eventName)
    {
        return $this->original->hasListeners($eventName);
    }

    public function subscribe($subscriber)
    {
        if (is_string($subscriber)) {
            $subscriber = app($subscriber);
        }

        $subscriber->subscribe($this);
    }

    public const ROOT_DISPATCH = 'Event::dispatch';

    public function dispatch($event, $payload = [], $halt = false)
    {
        $eventName = $this->resolveEventName($event);

        if (! $this->shouldWrap($eventName)) {
            return $this->original->dispatch($event, $payload, $halt);
        }

        $contextPayload = is_object($event) ? $event : $payload;

        return $this->recorder->capture($eventName, self::ROOT_DISPATCH, $contextPayload, function () use ($event, $payload, $halt) {
            return $this->original->dispatch($event, $payload, $halt);
        });
    }

    public function until($event, $payload = [])
    {
        return $this->dispatch($event, $payload, true);
    }

    public function push($event, $payload = [])
    {
        return $this->original->push($event, $payload);
    }

    public function flush($event)
    {
        $eventName = $this->resolveEventName($event);

        if (! $this->shouldWrap($eventName)) {
            return $this->original->flush($event);
        }

        return $this->recorder->capture($eventName, 'Event::flush', $event, function () use ($event) {
            return $this->original->flush($event);
        });
    }

    public function forget($event)
    {
        return $this->original->forget($event);
    }

    public function forgetPushed()
    {
        return $this->original->forgetPushed();
    }

    protected function shouldWrap($events): bool
    {
        $namespaces = config('event-lens.namespaces', []);

        if (empty($namespaces)) {
            return false;
        }

        foreach ((array) $events as $event) {
            $eventName = $this->resolveEventName($event);

            if ($eventName === '*') {
                return true;
            }

            if (Str::is($namespaces, $eventName)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveListenerName($listener): string
    {
        if (is_string($listener)) {
            return str_ends_with($listener, '@handle') ? substr($listener, 0, -7) : $listener;
        }
        if ($listener instanceof Closure) {
            return 'Closure';
        }
        if (is_array($listener)) {
            return (is_object($listener[0]) ? get_class($listener[0]) : $listener[0]).'@'.$listener[1];
        }

        return 'Unknown';
    }

    protected function resolveEventName($event): string
    {
        if (is_object($event)) {
            return get_class($event);
        }
        return (string) $event;
    }

    /**
     * Delegate any non-interface method calls to the original dispatcher.
     *
     * Packages like Telescope call concrete Dispatcher methods (e.g. getListeners())
     * that aren't part of the DispatcherContract interface.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->original->{$method}(...$parameters);
    }
}
