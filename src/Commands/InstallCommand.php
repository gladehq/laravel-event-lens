<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'event-lens:install';

    protected $description = 'Install EventLens configuration and run migrations';

    public function handle(): int
    {
        $this->info('Installing EventLens...');

        $this->call('vendor:publish', [
            '--tag' => 'event-lens-config',
        ]);

        $this->call('migrate');

        $this->info('EventLens installed successfully.');

        return self::SUCCESS;
    }
}
