<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use Illuminate\Console\Command;
use GladeHQ\LaravelEventLens\Models\EventLog;

class ClearCommand extends Command
{
    protected $signature = 'event-lens:clear {--force : Skip confirmation}';

    protected $description = 'Clear all EventLens data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL EventLens data. Continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $count = EventLog::count();
        EventLog::truncate();

        $this->info("Cleared {$count} events.");

        return self::SUCCESS;
    }
}
