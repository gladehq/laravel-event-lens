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

        // Fetch execution times per listener for P95 calculation
        $timings = EventLog::query()
            ->select('listener_name', 'event_name', 'execution_time_ms')
            ->where('listener_name', '!=', 'Event::dispatch')
            ->where('happened_at', '>=', $since)
            ->orderBy('listener_name')
            ->orderBy('event_name')
            ->orderBy('execution_time_ms')
            ->get()
            ->groupBy(fn ($row) => $row->listener_name . '|' . $row->event_name);

        return $listeners->map(function ($listener) use ($timings) {
            $key = $listener->listener_name . '|' . $listener->event_name;
            $times = $timings->get($key, collect())->pluck('execution_time_ms')->sort()->values();

            $errorRate = $listener->execution_count > 0
                ? ($listener->error_count / $listener->execution_count) * 100
                : 0;

            $p95 = $this->percentile($times, 95);
            $avgQueries = (float) $listener->avg_queries;

            $errorPenalty = min(40, $errorRate * 3);
            $p95Penalty = $p95 <= 100 ? 0 : min(30, ($p95 - 100) / 30);
            $queryPenalty = $avgQueries <= 5 ? 0 : min(20, ($avgQueries - 5) * 2);

            $score = max(0, round(100 - $errorPenalty - $p95Penalty - $queryPenalty, 1));

            return (object) [
                'listener_name' => $listener->listener_name,
                'event_name' => $listener->event_name,
                'score' => $score,
                'execution_count' => (int) $listener->execution_count,
                'error_rate' => round($errorRate, 1),
                'p95_latency' => round($p95, 2),
                'avg_queries' => round($avgQueries, 1),
            ];
        })
            ->sortBy('score')
            ->values();
    }

    /**
     * Calculate the Nth percentile from a sorted collection of values.
     */
    protected function percentile(Collection $sorted, int $percentile): float
    {
        if ($sorted->isEmpty()) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * $sorted->count()) - 1;
        $index = max(0, min($index, $sorted->count() - 1));

        return (float) $sorted[$index];
    }
}
