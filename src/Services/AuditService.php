<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function __construct(
        protected DispatcherContract $dispatcher,
    ) {}

    /**
     * Listeners registered in the dispatcher but never seen in the event log.
     *
     * Compares registered event names (keys in the dispatcher) against
     * distinct listener_name values actually recorded. Because our proxy
     * wraps listeners into closures, we look up which event names have
     * registered listeners and check whether any non-root execution
     * exists for that event.
     */
    public function deadListeners(): Collection
    {
        $executedPairs = EventLog::query()
            ->select('event_name', 'listener_name')
            ->where('listener_name', '!=', 'Event::dispatch')
            ->distinct()
            ->get()
            ->groupBy('event_name')
            ->map(fn ($group) => $group->pluck('listener_name')->all());

        $allEventNames = EventLog::query()
            ->select('event_name')
            ->distinct()
            ->pluck('event_name')
            ->all();

        $dead = [];

        foreach ($allEventNames as $eventName) {
            if (! $this->dispatcher->hasListeners($eventName)) {
                continue;
            }

            $executedForEvent = $executedPairs->get($eventName, []);

            if (empty($executedForEvent)) {
                $dead[] = (object) [
                    'listener_name' => '(registered, never executed)',
                    'event_name' => $eventName,
                ];
            }
        }

        return collect($dead);
    }

    /**
     * Root dispatches (Event::dispatch) that produced no child listener records.
     */
    public function orphanEvents(): Collection
    {
        return EventLog::query()
            ->select('event_name')
            ->selectRaw('COUNT(*) as fire_count')
            ->selectRaw('MAX(happened_at) as last_seen')
            ->where('listener_name', 'Event::dispatch')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('event_lens_events as children')
                    ->whereColumn('children.parent_event_id', 'event_lens_events.event_id');
            })
            ->groupBy('event_name')
            ->get();
    }

    /**
     * Listeners with historical records but none within the last N days.
     */
    public function staleListeners(?int $days = null): Collection
    {
        $days ??= (int) config('event-lens.stale_threshold_days', 30);
        $threshold = now()->subDays($days);

        return EventLog::query()
            ->select('listener_name', 'event_name')
            ->selectRaw('MAX(happened_at) as last_executed_at')
            ->where('listener_name', '!=', 'Event::dispatch')
            ->groupBy('listener_name', 'event_name')
            ->havingRaw('MAX(happened_at) < ?', [$threshold])
            ->get()
            ->map(function ($row) {
                $lastExecuted = \Carbon\Carbon::parse($row->last_executed_at);
                $row->days_stale = (int) $lastExecuted->diffInDays(now());

                return $row;
            });
    }
}
