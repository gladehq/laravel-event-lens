<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use Illuminate\Console\Command;
use GladeHQ\LaravelEventLens\Models\EventLog;

class StatusCommand extends Command
{
    protected $signature = 'event-lens:status';

    protected $description = 'Show the current EventLens status and statistics';

    public function handle(): int
    {
        $enabled = config('event-lens.enabled', true);
        $samplingRate = config('event-lens.sampling_rate', 1.0);
        $namespaces = config('event-lens.namespaces', []);

        $count = EventLog::count();
        $oldest = EventLog::oldest('happened_at')->first();
        $newest = EventLog::latest('happened_at')->first();

        $this->table(
            ['Setting', 'Value'],
            [
                ['Enabled', $enabled ? 'Yes' : 'No'],
                ['Sampling Rate', ($samplingRate * 100) . '%'],
                ['Total Events', number_format($count)],
                ['Oldest Event', $oldest?->happened_at?->diffForHumans() ?? 'N/A'],
                ['Newest Event', $newest?->happened_at?->diffForHumans() ?? 'N/A'],
                ['Namespaces', implode(', ', $namespaces) ?: 'None'],
            ]
        );

        return self::SUCCESS;
    }
}
