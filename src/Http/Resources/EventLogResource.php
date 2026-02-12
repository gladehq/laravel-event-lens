<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'correlation_id' => $this->correlation_id,
            'parent_event_id' => $this->parent_event_id,
            'event_name' => $this->event_name,
            'listener_name' => $this->listener_name,
            'execution_time_ms' => round($this->execution_time_ms, 2),
            'is_slow' => $this->execution_time_ms > config('event-lens.slow_threshold', 100.0),
            'side_effects' => $this->side_effects,
            'happened_at' => $this->happened_at?->toIso8601String(),
            'happened_at_human' => $this->happened_at?->diffForHumans(),
            'payload_summary' => $this->payload_summary,
            'has_error' => $this->exception !== null,
            'tags' => $this->tags,
            'url' => route('event-lens.show', $this->correlation_id),
        ];
    }
}
