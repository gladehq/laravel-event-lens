<?php

namespace GladeHQ\LaravelEventLens;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use Closure;

class EventLensProxy implements DispatcherContract
{
    protected $original;
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
        if (! config('event-lens.enabled', true)) {
            return $listener;
        }

        return function (...$payload) use ($event, $listener) {
             $listenerName = $this->resolveListenerName($listener);
             
             // Resolve Event Name logic from payload if possible
             $resolvedEvent = $event;
             if (isset($payload[0]) && is_object($payload[0])) {
                  if (class_exists((string)$event) && $payload[0] instanceof $event) {
                      $resolvedEvent = $payload[0];
                  }
             }
             $eventName = $this->resolveEventName($resolvedEvent);

             // Delegate to Recorder
             // We pass $payload as the event payload context.
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

    // Decorator delegation
    public function hasListeners($eventName) { return $this->original->hasListeners($eventName); }
    public function subscribe($subscriber) { return $this->original->subscribe($subscriber); } 
    
    /**
     * The identifier used for the root event dispatch log.
     */
    public const ROOT_DISPATCH = 'Event::dispatch';

    public function dispatch($event, $payload = [], $halt = false)
    {
        $eventName = $this->resolveEventName($event);
        
        // Context Payload determination for dispatch:
        // If event is object, it IS the payload context.
        // If event is string, $payload is the context.
        $contextPayload = is_object($event) ? $event : $payload;

        /**
         * Capture the ROOT event.
         * 
         * Logic:
         * 1. We record the "Dispatch" action itself as the parent/root.
         * 2. Any listeners triggered by this dispatch will be wrapped by `wrapListener`.
         * 3. The `EventRecorder` maintains a call stack. When this closure executes,
         *    it pushes the Root ID.
         * 4. When listeners execute, they see this Root ID as their parent.
         */
        return $this->recorder->capture($eventName, self::ROOT_DISPATCH, $contextPayload, function () use ($event, $payload, $halt) {
            return $this->original->dispatch($event, $payload, $halt);
        });
    }

    public function until($event, $payload = []) { return $this->original->until($event, $payload); }
    public function push($event, $payload = []) { return $this->original->push($event, $payload); }

    public function flush($event) { return $this->original->flush($event); }
    public function forget($event) { return $this->original->forget($event); }
    public function forgetPushed() { return $this->original->forgetPushed(); }
    
    // Internal helpers
    protected function resolveListenerName($listener): string
    {
        if (is_string($listener)) return $listener;
        if ($listener instanceof Closure) return 'Closure';
        if (is_array($listener)) return (is_object($listener[0]) ? get_class($listener[0]) : $listener[0]).'@'.$listener[1];
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
