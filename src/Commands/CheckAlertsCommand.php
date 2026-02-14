<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\AlertService;
use GladeHQ\LaravelEventLens\Services\RegressionDetector;
use Illuminate\Console\Command;

class CheckAlertsCommand extends Command
{
    protected $signature = 'event-lens:check-alerts';

    protected $description = 'Check for anomalies and fire alerts if needed';

    public function handle(AlertService $alertService, RegressionDetector $regressionDetector): int
    {
        if (! config('event-lens.alerts.enabled', false)) {
            $this->info('EventLens alerts are disabled.');

            return self::SUCCESS;
        }

        // Regression alerts (critical only)
        $regressions = $regressionDetector->detect();
        foreach ($regressions->where('severity', 'critical') as $regression) {
            $alertService->fireIfNeeded('regression', $regression->listener_name, [
                'listener' => $regression->listener_name,
                'event' => $regression->event_name,
                'baseline_avg_ms' => $regression->baseline_avg_ms,
                'recent_avg_ms' => $regression->recent_avg_ms,
                'change_pct' => $regression->change_pct,
            ]);
        }

        // Error spike detection
        $this->checkErrorSpike($alertService);

        $this->info('Alert check complete.');

        return self::SUCCESS;
    }

    protected function checkErrorSpike(AlertService $alertService): void
    {
        $recentStart = now()->subHour();
        $baselineStart = now()->subHours(25);
        $baselineEnd = now()->subHour();

        $recentTotal = EventLog::where('happened_at', '>=', $recentStart)->count();

        if ($recentTotal < 10) {
            return;
        }

        $recentErrors = EventLog::where('happened_at', '>=', $recentStart)
            ->whereNotNull('exception')
            ->count();

        $baselineTotal = EventLog::where('happened_at', '>=', $baselineStart)
            ->where('happened_at', '<', $baselineEnd)
            ->count();

        $baselineErrors = EventLog::where('happened_at', '>=', $baselineStart)
            ->where('happened_at', '<', $baselineEnd)
            ->whereNotNull('exception')
            ->count();

        $recentRate = $recentTotal > 0 ? ($recentErrors / $recentTotal) * 100 : 0;
        $baselineRate = $baselineTotal > 0 ? ($baselineErrors / $baselineTotal) * 100 : 0;

        $absoluteIncrease = $recentRate - $baselineRate;
        $relativeIncrease = $baselineRate > 0 ? $recentRate / $baselineRate : 0;

        if ($absoluteIncrease > 10 || $relativeIncrease > 2) {
            $alertService->fireIfNeeded('error_spike', 'global', [
                'recent_error_rate' => round($recentRate, 1),
                'baseline_error_rate' => round($baselineRate, 1),
                'recent_errors' => $recentErrors,
                'recent_total' => $recentTotal,
            ]);
        }
    }
}
