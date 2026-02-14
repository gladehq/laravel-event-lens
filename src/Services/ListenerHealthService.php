<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Carbon\Carbon;
use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Collection;

class ListenerHealthService
{
    /**
     * Compute health scores for all listeners active in the given period.
     *
     * Score = max(0, 100 - error_penalty - p95_penalty - query_penalty)
     */
    public function scores(?Carbon $since = null): Collection
    {
        $since ??= now()->subDays(7);

        $driver = EventLog::query()->getConnection()->getDriverName();

        $queriesExpr = match ($driver) {
            'pgsql' => "COALESCE((side_effects::json->>'queries')::int, 0)",
            default => "COALESCE(json_extract(side_effects, '$.queries'), 0)",
        };

        $listeners = EventLog::query()
            ->select('listener_name', 'event_name')
            ->selectRaw('COUNT(*) as execution_count')
            ->selectRaw('SUM(CASE WHEN exception IS NOT NULL THEN 1 ELSE 0 END) as error_count')
            ->selectRaw("AVG({$queriesExpr}) as avg_queries")
            ->where('listener_name', '!=', 'Event::dispatch')
            ->where('happened_at', '>=', $since)
            ->groupBy('listener_name', 'event_name')
            ->get();

        if ($listeners->isEmpty()) {
            return collect();
        }

        return $listeners->map(function ($listener) use ($since) {
            $count = (int) $listener->execution_count;

            $errorRate = $count > 0
                ? ($listener->error_count / $count) * 100
                : 0;

            // P95 via SQL OFFSET/LIMIT — no memory explosion
            $p95 = 0;
            if ($count > 0) {
                $offset = (int) floor($count * 0.95) - 1;
                $p95 = (float) EventLog::query()
                    ->where('listener_name', $listener->listener_name)
                    ->where('event_name', $listener->event_name)
                    ->where('happened_at', '>=', $since)
                    ->orderBy('execution_time_ms')
                    ->offset(max(0, $offset))
                    ->limit(1)
                    ->value('execution_time_ms');
            }

            $avgQueries = (float) $listener->avg_queries;

            $errorPenalty = min(40, $errorRate * 3);
            $p95Penalty = $p95 <= 100 ? 0 : min(30, ($p95 - 100) / 30);
            $queryPenalty = $avgQueries <= 5 ? 0 : min(20, ($avgQueries - 5) * 2);

            $score = max(0, round(100 - $errorPenalty - $p95Penalty - $queryPenalty, 1));

            return (object) [
                'listener_name' => $listener->listener_name,
                'event_name' => $listener->event_name,
                'score' => $score,
                'execution_count' => $count,
                'error_rate' => round($errorRate, 1),
                'p95_latency' => round($p95, 2),
                'avg_queries' => round($avgQueries, 1),
            ];
        })
            ->sortBy('score')
            ->values();
    }
}
