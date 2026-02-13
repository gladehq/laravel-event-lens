<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use GladeHQ\LaravelEventLens\Services\AuditService;
use Illuminate\Console\Command;

class AuditCommand extends Command
{
    protected $signature = 'event-lens:audit';

    protected $description = 'Audit event/listener registrations against runtime data';

    public function handle(AuditService $audit): int
    {
        $dead = $audit->deadListeners();
        $orphans = $audit->orphanEvents();
        $stale = $audit->staleListeners();

        $this->info('Dead Listeners (registered but never executed):');

        if ($dead->isEmpty()) {
            $this->line('  None found.');
        } else {
            $this->table(
                ['Listener', 'Event'],
                $dead->map(fn ($d) => [$d->listener_name, $d->event_name])->all()
            );
        }

        $this->newLine();
        $this->info('Orphan Events (dispatched with no listeners):');

        if ($orphans->isEmpty()) {
            $this->line('  None found.');
        } else {
            $this->table(
                ['Event', 'Fire Count', 'Last Seen'],
                $orphans->map(fn ($o) => [$o->event_name, $o->fire_count, $o->last_seen])->all()
            );
        }

        $this->newLine();
        $this->info('Stale Listeners (inactive beyond threshold):');

        if ($stale->isEmpty()) {
            $this->line('  None found.');
        } else {
            $this->table(
                ['Listener', 'Event', 'Last Executed', 'Days Stale'],
                $stale->map(fn ($s) => [$s->listener_name, $s->event_name, $s->last_executed_at, $s->days_stale])->all()
            );
        }

        return self::SUCCESS;
    }
}
