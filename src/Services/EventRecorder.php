<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Closure;
use GladeHQ\LaravelEventLens\Collectors\EventCollector;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;
use Illuminate\Support\Str;

class EventRecorder
{
    protected array $callStack = [];
    protected array $correlationContext = [];
    protected WatcherManager $watcher;
    protected EventLensBuffer $buffer;
    protected EventCollector $collector;
    protected ?float $samplingRate = null;
    protected ?bool $captureBacktrace = null;

    public function __construct(WatcherManager $watcher, EventLensBuffer $buffer, EventCollector $collector)
    {
        $this->watcher = $watcher;
        $this->buffer = $buffer;
        $this->collector = $collector;
    }

    public function capture(string $eventName, string $listenerName, $eventPayload, Closure $callback)
    {
        $parentContext = end($this->callStack) ?: null;
        $contextCorrelation = end($this->correlationContext) ?: null;
        $correlationId = $parentContext ? $parentContext['correlation_id'] : ($contextCorrelation ?? (string) Str::uuid());
        $parentEventId = $parentContext ? $parentContext['event_id'] : null;

        if (! $this->shouldRecord($eventName, $correlationId)) {
            return $callback();
        }

        $eventId = (string) Str::uuid();
        $this->callStack[] = ['event_id' => $eventId, 'correlation_id' => $correlationId];

        $this->watcher->start();
        $startTime = microtime(true);

        $backtrace = null;
        if ($this->captureBacktrace ??= (bool) config('event-lens.capture_backtrace', false)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
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

        $exception = null;

        try {
            return $callback();
        } catch (\Throwable $e) {
            $exception = get_class($e) . ': ' . $e->getMessage();
            throw $e;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;
            $sideEffects = $this->watcher->stop();
            array_pop($this->callStack);

            $this->persist($eventId, $correlationId, $parentEventId, $eventName, $listenerName, $eventPayload, $sideEffects, $duration, $backtrace, $exception);
        }
    }

    public function reset(): void
    {
        $this->callStack = [];
        $this->correlationContext = [];
    }

    public function pushCorrelationContext(string $correlationId): void
    {
        $this->correlationContext[] = $correlationId;
    }

    public function popCorrelationContext(): void
    {
        array_pop($this->correlationContext);
    }

    public function currentCorrelationId(): ?string
    {
        $fromStack = end($this->callStack);
        if ($fromStack) {
            return $fromStack['correlation_id'];
        }

        $fromContext = end($this->correlationContext);
        return $fromContext ?: null;
    }

    protected function persist($eventId, $correlationId, $parentEventId, $eventName, $listenerName, $eventPayload, $sideEffects, $duration, $backtrace, $exception = null)
    {
        try {
            $eventObj = is_array($eventPayload) && isset($eventPayload[0]) ? $eventPayload[0] : $eventPayload;

            $collectedPayload = $this->collector->collectPayload($eventObj, $eventPayload);

            if ($backtrace && is_array($collectedPayload)) {
                $collectedPayload['__context'] = ['file' => $backtrace];
            }

            $modelInfo = $this->collector->collectModelInfo($eventObj);

            $this->buffer->push([
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'parent_event_id' => $parentEventId,
                'event_name' => $eventName,
                'listener_name' => $listenerName,
                'payload' => $collectedPayload,
                'side_effects' => $sideEffects,
                'model_changes' => $modelInfo['model_changes'] ?: null,
                'model_type' => $modelInfo['model_type'],
                'model_id' => $modelInfo['model_id'],
                'exception' => $exception ? substr($exception, 0, 2048) : null,
                'execution_time_ms' => $duration,
                'happened_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                report($e);
            }
        }
    }

    protected function shouldRecord($name, $id): bool
    {
        if (! Str::is(config('event-lens.namespaces', []), $name)) {
            return false;
        }

        $rate = $this->samplingRate ??= (float) config('event-lens.sampling_rate', 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return ((abs(crc32($id)) % 100) / 100) < $rate;
    }
}
