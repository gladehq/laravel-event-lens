<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Concerns;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventLens
{
    public function eventLogs(): MorphMany
    {
        return $this->morphMany(EventLog::class, 'model');
    }
}
