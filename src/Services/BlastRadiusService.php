<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Collection;

class BlastRadiusService
{
    /**
     * Calculate blast radius risk scores for each unique listener.
     *
     * Returns a collection sorted by risk_score descending, each item containing:
     * listener_name, avg_children, error_rate, avg_duration, total_executions,
     * risk_score, risk_level, and downstream (array of downstream listener names).
     */
    public function calculate(): Collection
    {
        $connection = EventLog::query()->getConnection();

        // Aggregate stats per listener (excluding Event::dispatch root entries)
        $listeners = EventLog::query()
            ->where('listener_name', '!=', 'Event::dispatch')
            ->where('listener_name', '!=', 'Closure')
            ->selectRaw('listener_name')
            ->selectRaw('COUNT(*) as total_executions')
            ->selectRaw('AVG(execution_time_ms) as avg_duration')
            ->selectRaw('SUM(CASE WHEN exception IS NOT NULL THEN 1 ELSE 0 END) as error_count')
            ->groupBy('listener_name')
            ->get();

        if ($listeners->isEmpty()) {
            return collect();
        }

        // Get total child count per parent listener
        $childCounts = $connection->table('event_lens_events as parent')
            ->join('event_lens_events as child', 'child.parent_event_id', '=', 'parent.event_id')
            ->where('parent.listener_name', '!=', 'Event::dispatch')
            ->selectRaw('parent.listener_name, COUNT(*) as child_count')
            ->groupBy('parent.listener_name')
            ->pluck('child_count', 'listener_name');

        // Find downstream listeners (which actual listeners appear as descendants)
        // Skip Event::dispatch and Closure entries — show real listener class names only
        $downstream = $connection->table('event_lens_events as parent')
            ->join('event_lens_events as child', 'child.parent_event_id', '=', 'parent.event_id')
            ->where('parent.listener_name', '!=', 'Event::dispatch')
            ->where('child.listener_name', '!=', 'Event::dispatch')
            ->where('child.listener_name', '!=', 'Closure')
            ->selectRaw('parent.listener_name as parent_listener, child.listener_name as child_listener')
            ->distinct()
            ->get()
            ->groupBy('parent_listener')
            ->map(fn ($rows) => $rows->pluck('child_listener')->unique()->values()->all());

        return $listeners->map(function ($listener) use ($childCounts, $downstream) {
            $totalExecutions = (int) $listener->total_executions;
            $errorCount = (int) $listener->error_count;
            $avgDuration = round((float) $listener->avg_duration, 2);
            $errorRate = $totalExecutions > 0
                ? round(($errorCount / $totalExecutions) * 100, 1)
                : 0;
            $avgChildren = $totalExecutions > 0
                ? round(($childCounts[$listener->listener_name] ?? 0) / $totalExecutions, 1)
                : 0;

            $downstreamListeners = $downstream[$listener->listener_name] ?? [];

            $riskScore = (int) min(100, round(
                $avgChildren * 10 + $errorRate * 2 + min(30, $avgDuration / 100)
            ));

            return (object) [
                'listener_name' => $listener->listener_name,
                'avg_children' => $avgChildren,
                'total_downstream' => count($downstreamListeners),
                'error_rate' => $errorRate,
                'avg_duration' => $avgDuration,
                'total_executions' => $totalExecutions,
                'risk_score' => $riskScore,
                'risk_level' => $this->riskLevel($riskScore),
                'downstream' => $downstreamListeners,
            ];
        })->sortByDesc('risk_score')->values();
    }

    protected function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'High',
            $score >= 40 => 'Medium',
            default => 'Low',
        };
    }
}
