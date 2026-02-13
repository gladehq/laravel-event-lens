<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Closure;
use GladeHQ\LaravelEventLens\Collectors\EventCollector;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventRecorder
{
    protected array $callStack = [];
    protected array $correlationContext = [];
    protected array $stormCounters = [];
    protected WatcherManager $watcher;
    protected EventLensBuffer $buffer;
    protected EventCollector $collector;
    protected RequestContextResolver $contextResolver;
    protected ?float $samplingRate = null;
    protected ?bool $captureBacktrace = null;
    protected ?int $stormThreshold = null;

    public function __construct(WatcherManager $watcher, EventLensBuffer $buffer, EventCollector $collector, RequestContextResolver $contextResolver)
    {
        $this->watcher = $watcher;
        $this->buffer = $buffer;
        $this->collector = $collector;
        $this->contextResolver = $contextResolver;
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

        $stormKey = "{$correlationId}:{$eventName}";
        $this->stormCounters[$stormKey] = ($this->stormCounters[$stormKey] ?? 0) + 1;
        $threshold = $this->stormThreshold ??= (int) config('event-lens.storm_threshold', 50);
        $isStorm = $this->stormCounters[$stormKey] > $threshold;
        $stormCount = $this->stormCounters[$stormKey];

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

            $this->persist($eventId, $correlationId, $parentEventId, $eventName, $listenerName, $eventPayload, $sideEffects, $duration, $backtrace, $exception, $isStorm, $stormCount);
        }
    }

    public function reset(): void
    {
        $this->callStack = [];
        $this->correlationContext = [];
        $this->stormCounters = [];
        $this->contextResolver->reset();
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

    protected function persist(
        string $eventId,
        string $correlationId,
        ?string $parentEventId,
        string $eventName,
        string $listenerName,
        mixed $eventPayload,
        array $sideEffects,
        float $duration,
        ?string $backtrace,
        ?string $exception = null,
        bool $isStorm = false,
        int $stormCount = 0,
    )
    {
        try {
            $eventObj = is_array($eventPayload) && isset($eventPayload[0]) ? $eventPayload[0] : $eventPayload;

            $collectedPayload = $this->collector->collectPayload($eventObj, $eventPayload);

            if ($backtrace && is_array($collectedPayload)) {
                $collectedPayload['__context'] = ['file' => $backtrace];
            }

            // Inject request context on root events only
            if ($parentEventId === null) {
                $requestContext = $this->contextResolver->resolve();
                if ($requestContext !== null && is_array($collectedPayload)) {
                    $collectedPayload['__request_context'] = $requestContext;
                }
            }

            $modelInfo = $this->collector->collectModelInfo($eventObj);
            $tags = $this->collector->collectTags($eventObj);

            if ($isStorm) {
                $sideEffects['storm_count'] = $stormCount;
            }

            $record = [
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
                'is_storm' => $isStorm,
            ];

            if ($tags !== null) {
                $record['tags'] = $tags;
            }

            $this->buffer->push($record);
        } catch (\Throwable $e) {
            Log::warning('EventLens: Failed to persist event', [
                'error' => $e->getMessage(),
                'event' => $eventName,
            ]);
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
