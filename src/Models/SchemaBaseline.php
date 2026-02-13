<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_class
 * @property string $fingerprint
 * @property array $schema
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SchemaBaseline extends Model
{
    protected $table = 'event_lens_schema_baselines';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'array',
    ];

    public function getConnectionName()
    {
        return config('event-lens.database_connection') ?? parent::getConnectionName();
    }
}
