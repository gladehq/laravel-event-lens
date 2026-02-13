<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Illuminate\Support\Str;

class SlaChecker
{
    /**
     * Resolve the time budget for a given event/listener pair.
     *
     * Priority: exact listener > exact event > wildcard pattern.
     */
    public function resolveBudget(string $eventName, string $listenerName): ?float
    {
        $budgets = config('event-lens.sla_budgets', []);

        if (empty($budgets)) {
            return null;
        }

        // Exact listener match (highest priority)
        if (isset($budgets[$listenerName])) {
            return (float) $budgets[$listenerName];
        }

        // Exact event match
        if (isset($budgets[$eventName])) {
            return (float) $budgets[$eventName];
        }

        // Wildcard pattern match (lowest priority)
        foreach ($budgets as $pattern => $budget) {
            if (Str::is($pattern, $eventName) || Str::is($pattern, $listenerName)) {
                return (float) $budget;
            }
        }

        return null;
    }

    /**
     * Check if execution time exceeds the SLA budget.
     *
     * Returns breach details array if exceeded, null otherwise.
     */
    public function check(string $eventName, string $listenerName, float $durationMs): ?array
    {
        $budget = $this->resolveBudget($eventName, $listenerName);

        if ($budget === null) {
            return null;
        }

        if ($durationMs > $budget) {
            return [
                'budget_ms' => $budget,
                'actual_ms' => $durationMs,
                'exceeded_by_pct' => round(($durationMs - $budget) / $budget * 100, 1),
            ];
        }

        return null;
    }
}
