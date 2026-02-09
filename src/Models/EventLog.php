<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventLog extends Model
{
    use HasFactory;

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

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_event_id', 'event_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_event_id', 'event_id');
    }
}
