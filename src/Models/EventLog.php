<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $event_id
 * @property string $correlation_id
 * @property string|null $parent_event_id
 * @property string $event_name
 * @property string $listener_name
 * @property array|null $payload
 * @property array|null $side_effects
 * @property array|null $model_changes
 * @property string|null $exception
 * @property string|null $model_type
 * @property int|null $model_id
 * @property float $execution_time_ms
 * @property \Illuminate\Support\Carbon $happened_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
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

    public function model(): MorphTo
    {
        return $this->morphTo();
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
        return $query->when($eventName, function ($q) use ($eventName) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $eventName);

            return $q->whereRaw("event_name LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
        });
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
        $driver = $query->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => $query->whereRaw("(side_effects::json->>'queries')::int >= ?", [$min]),
            default => $query->whereRaw("json_extract(side_effects, '$.queries') >= ?", [$min]),
        };
    }
}
