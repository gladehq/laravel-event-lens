<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Illuminate\Support\Facades\Log;
use GladeHQ\LaravelEventLens\Models\EventLog;

class EventLensBuffer
{
    protected array $events = [];

    protected int $maxBufferSize;

    public function __construct()
    {
        $this->maxBufferSize = (int) config('event-lens.buffer_size', 1000);
    }

    public function push(array $eventData): void
    {
        $this->events[] = $eventData;

        if (count($this->events) >= $this->maxBufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->events)) {
            return;
        }

        $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

        try {
            $batch = array_map(function ($event) use ($jsonFlags) {
                if (isset($event['payload']) && is_array($event['payload'])) {
                    $event['payload'] = json_encode($event['payload'], $jsonFlags);
                }
                if (isset($event['side_effects']) && is_array($event['side_effects'])) {
                    $event['side_effects'] = json_encode($event['side_effects'], $jsonFlags);
                }
                if (isset($event['model_changes']) && is_array($event['model_changes'])) {
                    $event['model_changes'] = json_encode($event['model_changes'], $jsonFlags);
                }
                if (isset($event['tags']) && is_array($event['tags'])) {
                    $event['tags'] = json_encode($event['tags'], $jsonFlags);
                }

                if (isset($event['happened_at']) && $event['happened_at'] instanceof \DateTimeInterface) {
                    $event['happened_at'] = $event['happened_at']->format('Y-m-d H:i:s');
                }

                $now = now()->format('Y-m-d H:i:s');
                $event['created_at'] = $now;
                $event['updated_at'] = $now;

                return $event;
            }, $this->events);

            EventLog::insert($batch);

            $this->events = [];
        } catch (\Throwable $e) {
            Log::warning('EventLens: Failed to flush event buffer', [
                'error' => $e->getMessage(),
                'count' => count($this->events),
            ]);
            $this->events = [];
        }
    }

    public function count(): int
    {
        return count($this->events);
    }
}
