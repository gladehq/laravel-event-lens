<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\SlaChecker;
use Illuminate\Console\Command;

class AssertPerformanceCommand extends Command
{
    protected $signature = 'event-lens:assert-performance
        {--format=text : Output format (text or json)}
        {--period=24h : Lookback period (1h, 6h, 24h, 7d)}';

    protected $description = 'Assert all SLA budgets are met. Exit code 1 if breaches found. Useful for CI pipelines.';

    public function handle(SlaChecker $slaChecker): int
    {
        $budgets = config('event-lens.sla_budgets', []);

        if (empty($budgets)) {
            $this->info('No SLA budgets configured.');
            return self::SUCCESS;
        }

        $since = $this->resolveSince($this->option('period'));
        $breaches = [];

        foreach ($budgets as $name => $budgetMs) {
            $query = EventLog::query()
                ->where('happened_at', '>=', $since)
                ->where(function ($q) use ($name) {
                    $q->where('listener_name', $name)
                      ->orWhere('event_name', $name);
                });

            $totalExecutions = $query->count();

            if ($totalExecutions === 0) {
                continue;
            }

            $breachCount = (clone $query)->where('is_sla_breach', true)->count();

            // P95 actual
            $offset = (int) floor($totalExecutions * 0.95) - 1;
            $p95 = (float) (clone $query)
                ->orderBy('execution_time_ms')
                ->offset(max(0, $offset))
                ->limit(1)
                ->value('execution_time_ms');

            if ($breachCount > 0) {
                $breaches[] = [
                    'name' => $name,
                    'budget_ms' => (float) $budgetMs,
                    'p95_actual_ms' => round($p95, 2),
                    'breach_count' => $breachCount,
                    'total_executions' => $totalExecutions,
                    'breach_rate' => round(($breachCount / $totalExecutions) * 100, 1),
                ];
            }
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'passed' => empty($breaches),
                'breaches' => $breaches,
                'period' => $this->option('period'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return empty($breaches) ? self::SUCCESS : self::FAILURE;
        }

        if (empty($breaches)) {
            $this->info('All SLA budgets are met.');
            return self::SUCCESS;
        }

        $this->error('SLA breaches detected:');
        $this->newLine();

        foreach ($breaches as $breach) {
            $this->line(sprintf(
                '  %s: P95 %.1fms (budget: %.1fms) — %d/%d breaches (%.1f%%)',
                $breach['name'],
                $breach['p95_actual_ms'],
                $breach['budget_ms'],
                $breach['breach_count'],
                $breach['total_executions'],
                $breach['breach_rate'],
            ));
        }

        return self::FAILURE;
    }

    protected function resolveSince(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };
    }
}
