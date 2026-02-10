<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use GladeHQ\LaravelEventLens\Models\EventLog;

class PruneEventLensCommand extends Command implements Isolatable
{
    protected $signature = 'event-lens:prune
        {--days= : The number of days to retain events}
        {--dry-run : Show how many events would be pruned without deleting}';

    protected $description = 'Prune old events from the EventLens database';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('event-lens.prune_after_days', 7));
        $dryRun = $this->option('dry-run');

        $this->info("Pruning events older than {$days} days...");

        $cutoff = now()->subDays($days);

        if ($dryRun) {
            $count = EventLog::where('happened_at', '<', $cutoff)->count();
            $this->info("Would prune {$count} events (dry run).");
            return self::SUCCESS;
        }

        $count = 0;

        do {
            $ids = EventLog::where('happened_at', '<', $cutoff)
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = EventLog::whereIn('id', $ids)->delete();
            $count += $deleted;
            $this->output->write('.');
        } while (true);

        $this->newLine();
        $this->info("Pruned {$count} events.");

        return self::SUCCESS;
    }
}
