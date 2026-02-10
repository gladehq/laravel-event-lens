<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Queue;

use GladeHQ\LaravelEventLens\Services\EventRecorder;

class EventLensJobMiddleware
{
    public function handle($job, $next)
    {
        $correlationId = $job->eventLensCorrelationId ?? null;

        if ($correlationId) {
            app(EventRecorder::class)->pushCorrelationContext($correlationId);
        }

        try {
            return $next($job);
        } finally {
            if ($correlationId) {
                app(EventRecorder::class)->popCorrelationContext();
            }
        }
    }
}
