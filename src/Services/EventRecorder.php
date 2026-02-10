<?php

namespace GladeHQ\LaravelEventLens\Services;

use Closure;
use GladeHQ\LaravelEventLens\Collectors\EventCollector;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;
use Illuminate\Support\Str;

class EventRecorder
{
    protected array $callStack = [];
    protected WatcherManager $watcher;
    protected EventLensBuffer $buffer;

    public function __construct(WatcherManager $watcher, EventLensBuffer $buffer)
    {
        $this->watcher = $watcher;
        $this->buffer = $buffer;
    }

    /**
     * Execute a callback while recording execution metrics and side effects.
     *
     * @param string $eventName
     * @param string $listenerName
     * @param mixed $eventPayload The event object or payload array
     * @param Closure $callback The actual execution block
     * @return mixed
     */
    public function capture(string $eventName, string $listenerName, $eventPayload, Closure $callback)
    {
        // 1. Determine Context (Correlation & Parent)
        $parentContext = end($this->callStack) ?: null;
        $correlationId = $parentContext ? $parentContext['correlation_id'] : (string) Str::uuid();
        $parentEventId = $parentContext ? $parentContext['event_id'] : null;

        // 2. Sampling Check
        if (! $this->shouldRecord($eventName, $correlationId)) {
            return $callback();
        }

        // 3. Prepare Recording
        $eventId = (string) Str::uuid();
        $this->callStack[] = ['event_id' => $eventId, 'correlation_id' => $correlationId];
        
        $this->watcher->start();
        $startTime = microtime(true);
        
        // Backtrace (Opt-in)
        $backtrace = null;
        if (config('event-lens.capture_backtrace', false)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            // We need to find the frame outside of EventRecorder and Proxy
            // This is heuristic.
            foreach ($trace as $frame) {
                if (isset($frame['class']) && str_starts_with($frame['class'], 'GladeHQ\LaravelEventLens')) {
                    continue;
                }
                if (isset($frame['file'])) {
                    $backtrace = $frame['file'] . ':' . $frame['line'];
                    break;
                }
            }
        }

        try {
            // 4. Execute
            return $callback();
        } finally {
            // 5. Finalize Recording
            $duration = (microtime(true) - $startTime) * 1000;
            $sideEffects = $this->watcher->stop();
            array_pop($this->callStack);

            $this->persist($eventId, $correlationId, $parentEventId, $eventName, $listenerName, $eventPayload, $sideEffects, $duration, $backtrace);
        }
    }

    protected function persist($eventId, $correlationId, $parentEventId, $eventName, $listenerName, $eventPayload, $sideEffects, $duration, $backtrace)
    {
        try {
            $collector = new EventCollector();
            
            // Normalize payload for collection
            // If it's a listener wrapper, $eventPayload is passed [0=>eventArg] or just eventArg.
            $collectData = $eventPayload;
            
            // Collector collectPayload($event, $payload)
            // Ideally we pass ($eventName, $eventPayload) if eventName is string?
            // Or ($eventObject, [])?
            // The existing proxy logic was a bit messy. Let's trust Collector to handle specific inputs.
            // But we need to pass strict arguments.
            
            // If eventPayload is array and first item is object, use that as 'event'.
            $eventObj = is_array($eventPayload) && isset($eventPayload[0]) ? $eventPayload[0] : $eventPayload;
            
            $collectedPayload = $collector->collectPayload($eventObj, $eventPayload);

            if ($backtrace && is_array($collectedPayload)) {
                $collectedPayload['__context'] = ['file' => $backtrace];
            }

            $modelChanges = $collector->collectModelChanges($eventObj);

            $this->buffer->push([
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'parent_event_id' => $parentEventId,
                'event_name' => $eventName,
                'listener_name' => $listenerName,
                'payload' => $collectedPayload,
                'side_effects' => $sideEffects,
                'model_changes' => $modelChanges ?: null,
                'execution_time_ms' => $duration,
                'happened_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                report($e);
            }
        }
    }

    public function reset(): void
    {
        $this->callStack = [];
    }

    protected function shouldRecord($name, $id): bool
    {
        if (! Str::is(config('event-lens.namespaces', []), $name)) return false;

        $rate = config('event-lens.sampling_rate', 0.1);
        if ($rate >= 1.0) return true;
        if ($rate <= 0.0) return false;

        return ((abs(crc32($id)) % 100) / 100) <= $rate;
    }
}
