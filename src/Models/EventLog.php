<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

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
 * @property array|null $tags
 * @property bool $is_storm
 * @property bool $is_sla_breach
 * @property bool $has_drift
 * @property array|null $drift_details
 * @property float $execution_time_ms
 * @property \Illuminate\Support\Carbon $happened_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read array $payload_summary
 */
class EventLog extends Model
{
    use HasFactory;

    protected $table = 'event_lens_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'side_effects' => 'array',
        'model_changes' => 'array',
        'tags' => 'array',
        'is_storm' => 'boolean',
        'is_sla_breach' => 'boolean',
        'has_drift' => 'boolean',
        'drift_details' => 'array',
        'happened_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('event-lens.database_connection') ?? parent::getConnectionName();
    }

    protected static function newFactory(): \GladeHQ\LaravelEventLens\Database\Factories\EventLogFactory
    {
        return \GladeHQ\LaravelEventLens\Database\Factories\EventLogFactory::new();
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

    // -- Accessors --

    public function getPayloadSummaryAttribute(): array
    {
        if ($this->payload === null) {
            return [];
        }

        $summary = [];

        foreach ($this->payload as $key => $value) {
            if ($key === '__context') {
                continue;
            }

            if (is_array($value)) {
                $summary[$key] = array_is_list($value)
                    ? '['.count($value).' items]'
                    : '{Object}';
            } else {
                $summary[$key] = Str::limit((string) $value, 50);
            }
        }

        return $summary;
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
            ->when($startDate, fn ($q) => $q->where('happened_at', '>=', Carbon::parse($startDate)->startOfDay()))
            ->when($endDate, fn ($q) => $q->where('happened_at', '<=', Carbon::parse($endDate)->endOfDay()));
    }

    public function scopeForPayload(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function ($q) use ($term) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);

            return $q->whereRaw("payload LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
        });
    }

    public function scopeForTag(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function ($q) use ($term) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);

            return $q->whereRaw("tags LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
        });
    }

    public function scopeStorms(Builder $query): Builder
    {
        return $query->where('is_storm', true);
    }

    public function scopeSlaBreaches(Builder $query): Builder
    {
        return $query->where('is_sla_breach', true);
    }

    public function scopeWithDrift(Builder $query): Builder
    {
        return $query->where('has_drift', true);
    }

    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereNotNull('exception');
    }

    public function scopeForListener(Builder $query, ?string $listener): Builder
    {
        return $query->when($listener, function ($q) use ($listener) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $listener);

            return $q->whereRaw("listener_name LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
        });
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
