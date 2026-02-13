<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Collection;

class RegressionDetector
{
    /**
     * Detect performance regressions by comparing recent performance (last 24h)
     * against historical baseline (previous 7 days).
     */
    public function detect(): Collection
    {
        $threshold = (float) config('event-lens.regression_threshold', 2.0);
        $now = now();
        $recentStart = $now->copy()->subHours(24);
        $baselineStart = $now->copy()->subDays(8);
        $baselineEnd = $recentStart;

        // Get listeners with enough data for comparison
        $recentStats = EventLog::query()
            ->where('listener_name', '!=', 'Event::dispatch')
            ->where('listener_name', '!=', 'Closure')
            ->where('happened_at', '>=', $recentStart)
            ->selectRaw('listener_name, event_name, AVG(execution_time_ms) as avg_ms, COUNT(*) as count')
            ->groupBy('listener_name', 'event_name')
            ->having('count', '>=', 3)
            ->get();

        if ($recentStats->isEmpty()) {
            return collect();
        }

        $baselineStats = EventLog::query()
            ->where('listener_name', '!=', 'Event::dispatch')
            ->where('listener_name', '!=', 'Closure')
            ->where('happened_at', '>=', $baselineStart)
            ->where('happened_at', '<', $baselineEnd)
            ->selectRaw('listener_name, event_name, AVG(execution_time_ms) as avg_ms, COUNT(*) as count')
            ->groupBy('listener_name', 'event_name')
            ->having('count', '>=', 3)
            ->get()
            ->keyBy(fn ($row) => $row->listener_name . '::' . $row->event_name);

        return $recentStats->map(function ($recent) use ($baselineStats, $threshold) {
            $key = $recent->listener_name . '::' . $recent->event_name;
            $baseline = $baselineStats[$key] ?? null;

            if ($baseline === null || (float) $baseline->avg_ms <= 0) {
                return null;
            }

            $baselineAvg = round((float) $baseline->avg_ms, 2);
            $recentAvg = round((float) $recent->avg_ms, 2);
            $ratio = $recentAvg / $baselineAvg;

            if ($ratio < $threshold) {
                return null;
            }

            $changePct = round(($ratio - 1) * 100, 1);

            return (object) [
                'listener_name' => $recent->listener_name,
                'event_name' => $recent->event_name,
                'baseline_avg_ms' => $baselineAvg,
                'recent_avg_ms' => $recentAvg,
                'change_pct' => $changePct,
                'severity' => $ratio >= 5.0 ? 'critical' : 'warning',
            ];
        })->filter()->sortByDesc('change_pct')->values();
    }
}
