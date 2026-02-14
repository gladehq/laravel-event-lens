<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Carbon\Carbon;
use GladeHQ\LaravelEventLens\Models\EventLog;

class ComparisonService
{
    /**
     * Compare two time periods and return performance deltas.
     */
    public function compare(Carbon $periodAStart, Carbon $periodAEnd, Carbon $periodBStart, Carbon $periodBEnd): array
    {
        $periodA = $this->aggregatePeriod($periodAStart, $periodAEnd);
        $periodB = $this->aggregatePeriod($periodBStart, $periodBEnd);

        $throughputDelta = $this->percentDelta($periodA['total_events'], $periodB['total_events']);
        $avgTimeDelta = $this->percentDelta($periodA['avg_execution_time'], $periodB['avg_execution_time']);
        $errorRateDelta = $periodB['error_rate'] - $periodA['error_rate'];

        // Listener-level comparison
        $allListeners = collect($periodA['listeners'])->keys()
            ->merge(collect($periodB['listeners'])->keys())
            ->unique();

        $listenerComparison = $allListeners->map(function ($listener) use ($periodA, $periodB) {
            $a = $periodA['listeners'][$listener] ?? null;
            $b = $periodB['listeners'][$listener] ?? null;

            $avgA = $a['avg_ms'] ?? 0;
            $avgB = $b['avg_ms'] ?? 0;
            $countA = $a['count'] ?? 0;
            $countB = $b['count'] ?? 0;

            return (object) [
                'listener_name' => $listener,
                'period_a_avg' => round($avgA, 2),
                'period_b_avg' => round($avgB, 2),
                'period_a_count' => $countA,
                'period_b_count' => $countB,
                'avg_delta_pct' => $avgA > 0 ? round((($avgB - $avgA) / $avgA) * 100, 1) : ($avgB > 0 ? 100 : 0),
                'status' => $avgB > $avgA * 1.1 ? 'degraded' : ($avgB < $avgA * 0.9 ? 'improved' : 'stable'),
            ];
        })->sortByDesc('avg_delta_pct')->values();

        // New and disappeared events
        $eventsA = collect($periodA['event_names']);
        $eventsB = collect($periodB['event_names']);
        $newEvents = $eventsB->diff($eventsA)->values();
        $disappearedEvents = $eventsA->diff($eventsB)->values();

        return [
            'period_a' => ['start' => $periodAStart->toDateTimeString(), 'end' => $periodAEnd->toDateTimeString(), 'stats' => $periodA],
            'period_b' => ['start' => $periodBStart->toDateTimeString(), 'end' => $periodBEnd->toDateTimeString(), 'stats' => $periodB],
            'throughput_delta_pct' => $throughputDelta,
            'avg_time_delta_pct' => $avgTimeDelta,
            'error_rate_delta' => round($errorRateDelta, 1),
            'listeners' => $listenerComparison,
            'new_events' => $newEvents,
            'disappeared_events' => $disappearedEvents,
        ];
    }

    protected function aggregatePeriod(Carbon $start, Carbon $end): array
    {
        $query = EventLog::query()
            ->where('happened_at', '>=', $start)
            ->where('happened_at', '<=', $end);

        $totalEvents = (clone $query)->count();
        $errorCount = (clone $query)->whereNotNull('exception')->count();
        $avgExecutionTime = (float) (clone $query)->avg('execution_time_ms');

        $listeners = (clone $query)
            ->where('listener_name', '!=', 'Event::dispatch')
            ->selectRaw('listener_name, COUNT(*) as count, AVG(execution_time_ms) as avg_ms')
            ->groupBy('listener_name')
            ->get()
            ->keyBy('listener_name')
            ->map(fn ($row) => ['count' => (int) $row->count, 'avg_ms' => (float) $row->avg_ms])
            ->toArray();

        $eventNames = (clone $query)
            ->distinct()
            ->pluck('event_name')
            ->toArray();

        return [
            'total_events' => $totalEvents,
            'error_count' => $errorCount,
            'error_rate' => $totalEvents > 0 ? round(($errorCount / $totalEvents) * 100, 1) : 0,
            'avg_execution_time' => round($avgExecutionTime, 2),
            'listeners' => $listeners,
            'event_names' => $eventNames,
        ];
    }

    protected function percentDelta(float $a, float $b): float
    {
        if ($a == 0) {
            return $b > 0 ? 100 : 0;
        }

        return round((($b - $a) / $a) * 100, 1);
    }
}
