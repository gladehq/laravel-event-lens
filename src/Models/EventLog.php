<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'event_lens_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'side_effects' => 'array',
        'model_changes' => 'array',
        'happened_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('event-lens.database_connection') ?? parent::getConnectionName();
    }

    // -- Relationships --

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_event_id', 'event_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_event_id', 'event_id');
    }

    // -- Query Scopes --

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_event_id');
    }

    public function scopeForCorrelation(Builder $query, ?string $correlationId): Builder
    {
        return $query->when($correlationId, fn ($q) => $q->where('correlation_id', $correlationId));
    }

    public function scopeForEvent(Builder $query, ?string $eventName): Builder
    {
        return $query->when($eventName, fn ($q) => $q->where('event_name', 'like', "%{$eventName}%"));
    }

    public function scopeSlow(Builder $query, float $threshold = 100.0): Builder
    {
        return $query->where('execution_time_ms', '>', $threshold);
    }

    public function scopeBetweenDates(Builder $query, $startDate = null, $endDate = null): Builder
    {
        return $query
            ->when($startDate, fn ($q) => $q->where('happened_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('happened_at', '<=', $endDate));
    }

    public function scopeWithMinQueries(Builder $query, int $min = 1): Builder
    {
        return $query->whereRaw("json_extract(side_effects, '$.queries') >= ?", [$min]);
    }
}
