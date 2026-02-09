<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use Illuminate\Console\Command;
use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

class PruneEventLensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event-lens:prune {--days= : The number of days to retain events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old events from the EventLens database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days') ?? config('event-lens.prune_after_days', 7);
        
        $this->info("Pruning events older than {$days} days...");

        $cutoff = now()->subDays($days);
        
        $count = 0;
        
        // Chunk deletion
        do {
            $deleted = EventLog::where('happened_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            
            $count += $deleted;
            
            if ($deleted > 0) {
                $this->output->write('.');
            }
            
        } while ($deleted > 0);

        $this->newLine();
        $this->info("Pruned {$count} events.");
    }
}
