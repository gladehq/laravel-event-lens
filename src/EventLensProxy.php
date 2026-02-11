<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use Closure;

class EventLensProxy implements DispatcherContract
{
    protected DispatcherContract $original;
    protected EventRecorder $recorder;

    public function __construct(DispatcherContract $original)
    {
        $this->original = $original;
        $this->recorder = app(EventRecorder::class);
    }

    public function listen($events, $listener = null)
    {
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

        if (is_string($listener) || is_array($listener)) {
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

    protected function resolveListenerName($listener): string
    {
        if (is_string($listener)) {
            return $listener;
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
}
