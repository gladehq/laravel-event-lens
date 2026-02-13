<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Http\Resources\EventLogResource;

class EventLensController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'event' => 'nullable|string|max:255',
            'correlation' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'listener' => 'nullable|string|max:255',
            'slow' => 'nullable|boolean',
            'errors' => 'nullable|boolean',
            'payload' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'storm' => 'nullable|boolean',
        ]);

        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $events = EventLog::roots()
            ->forEvent($request->get('event'))
            ->forCorrelation($request->get('correlation'))
            ->forListener($request->get('listener'))
            ->forPayload($request->get('payload'))
            ->forTag($request->get('tag'))
            ->betweenDates($request->get('start_date'), $request->get('end_date'))
            ->when($request->boolean('slow'), fn ($q) => $q->slow($slowThreshold))
            ->when($request->boolean('errors'), fn ($q) => $q->withErrors())
            ->when($request->boolean('storm'), fn ($q) => $q->storms())
            ->latest('happened_at')
            ->paginate(20);

        return view('event-lens::index', compact('events', 'slowThreshold'));
    }

    public function show(string $correlationId)
    {
        $events = EventLog::forCorrelation($correlationId)
            ->orderBy('happened_at')
            ->get();

        if ($events->isEmpty()) {
            abort(404, 'Event trace not found.');
        }

        $totalDuration = $events->sum('execution_time_ms');
        $totalQueries = $events->sum(fn ($e) => $e->side_effects['queries'] ?? 0);
        $totalMails = $events->sum(fn ($e) => $e->side_effects['mails'] ?? 0);
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $totalErrors = $events->filter(fn ($e) => $e->exception !== null)->count();
        $totalSlow = $events->filter(fn ($e) => $e->execution_time_ms > $slowThreshold)->count();
        $firstErrorEventId = $events->firstWhere('exception', '!=', null)?->event_id;

        $tree = $this->buildTree($events);
        $tree = $this->markDescendantErrors($tree);

        return view('event-lens::waterfall', compact(
            'tree', 'events', 'totalDuration', 'totalQueries', 'totalMails',
            'slowThreshold', 'totalErrors', 'totalSlow', 'firstErrorEventId',
        ));
    }

    public function statistics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->get('start_date', now()->subDays(7));
        $endDate = $request->get('end_date', now());
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $version = Cache::get('event-lens:cache-version', 0);
        $cacheKey = "event-lens:stats:v{$version}:" . md5(serialize([$startDate, $endDate]));

        $stats = Cache::remember($cacheKey, 120, function () use ($startDate, $endDate, $slowThreshold) {
            $totalEvents = EventLog::roots()->betweenDates($startDate, $endDate)->count();
            $errorCount = EventLog::roots()->betweenDates($startDate, $endDate)->withErrors()->count();

            $driver = EventLog::query()->getConnection()->getDriverName();

            $queriesExpr = match ($driver) {
                'pgsql' => "COALESCE((side_effects::json->>'queries')::int, 0)",
                default => "COALESCE(json_extract(side_effects, '$.queries'), 0)",
            };

            $mailsExpr = match ($driver) {
                'pgsql' => "COALESCE((side_effects::json->>'mails')::int, 0)",
                default => "COALESCE(json_extract(side_effects, '$.mails'), 0)",
            };

            return [
                'total_events' => $totalEvents,
                'error_count' => $errorCount,
                'error_rate' => $totalEvents > 0 ? round(($errorCount / $totalEvents) * 100, 1) : 0,
                'avg_execution_time' => round(
                    (float) EventLog::roots()->betweenDates($startDate, $endDate)->avg('execution_time_ms'),
                    2
                ),
                'slow_count' => EventLog::roots()->betweenDates($startDate, $endDate)->slow($slowThreshold)->count(),
                'total_queries' => (int) EventLog::roots()->betweenDates($startDate, $endDate)
                    ->selectRaw("SUM({$queriesExpr}) as total")->value('total'),
                'total_mails' => (int) EventLog::roots()->betweenDates($startDate, $endDate)
                    ->selectRaw("SUM({$mailsExpr}) as total")->value('total'),
                'slowest_events' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->orderByDesc('execution_time_ms')
                    ->limit(10)
                    ->get(['event_name', 'execution_time_ms', 'correlation_id', 'happened_at']),
                'events_by_type' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->selectRaw('event_name, COUNT(*) as count, AVG(execution_time_ms) as avg_time')
                    ->groupBy('event_name')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
                'timeline' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->selectRaw('DATE(happened_at) as date, COUNT(*) as count, SUM(CASE WHEN exception IS NOT NULL THEN 1 ELSE 0 END) as error_count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                'error_breakdown' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->withErrors()
                    ->selectRaw('event_name, SUBSTR(exception, 1, 120) as exception_summary, COUNT(*) as count, MAX(happened_at) as last_seen')
                    ->groupBy('event_name', 'exception_summary')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
                'heaviest_events' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->selectRaw("event_name, COUNT(*) as count, AVG({$queriesExpr}) as avg_queries, SUM({$queriesExpr}) as total_queries")
                    ->groupBy('event_name')
                    ->havingRaw("SUM({$queriesExpr}) > 0")
                    ->orderByDesc('total_queries')
                    ->limit(10)
                    ->get(),
                'listener_breakdown' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->selectRaw('event_name, listener_name, COUNT(*) as count, AVG(execution_time_ms) as avg_time')
                    ->groupBy('event_name', 'listener_name')
                    ->orderBy('event_name')
                    ->orderByDesc('count')
                    ->get()
                    ->groupBy('event_name'),
                'execution_distribution' => EventLog::roots()
                    ->betweenDates($startDate, $endDate)
                    ->selectRaw("
                        SUM(CASE WHEN execution_time_ms <= 10 THEN 1 ELSE 0 END) as bucket_0_10,
                        SUM(CASE WHEN execution_time_ms > 10 AND execution_time_ms <= 50 THEN 1 ELSE 0 END) as bucket_10_50,
                        SUM(CASE WHEN execution_time_ms > 50 AND execution_time_ms <= 100 THEN 1 ELSE 0 END) as bucket_50_100,
                        SUM(CASE WHEN execution_time_ms > 100 AND execution_time_ms <= 500 THEN 1 ELSE 0 END) as bucket_100_500,
                        SUM(CASE WHEN execution_time_ms > 500 THEN 1 ELSE 0 END) as bucket_500_plus
                    ")->first(),
            ];
        });

        return view('event-lens::statistics', compact('stats', 'startDate', 'endDate', 'slowThreshold'));
    }

    public function detail(string $eventId)
    {
        $event = EventLog::where('event_id', $eventId)->firstOrFail();
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $siblings = EventLog::forCorrelation($event->correlation_id)
            ->orderBy('happened_at')
            ->get(['event_id', 'listener_name']);

        $currentIndex = $siblings->search(fn ($e) => $e->event_id === $event->event_id);
        $prevEvent = $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
        $nextEvent = $currentIndex < $siblings->count() - 1 ? $siblings[$currentIndex + 1] : null;

        return view('event-lens::detail', compact('event', 'slowThreshold', 'prevEvent', 'nextEvent'));
    }

    public function latest(Request $request)
    {
        $request->validate([
            'after_id' => 'nullable|integer|min:1',
        ]);

        $events = EventLog::roots()
            ->when($request->get('after_id'), fn ($q, $id) => $q->where('id', '>', $id))
            ->latest('id')
            ->limit(20)
            ->get();

        return EventLogResource::collection($events);
    }

    protected function buildTree($events, $parentId = null): array
    {
        $branch = [];

        foreach ($events as $event) {
            if ($event->parent_event_id === $parentId) {
                $children = $this->buildTree($events, $event->event_id);
                $event->setRelation('children', collect($children));
                $branch[] = $event;
            }
        }

        return $branch;
    }

    protected function markDescendantErrors(array $nodes): array
    {
        foreach ($nodes as $node) {
            if ($node->children && $node->children->count()) {
                $this->markDescendantErrors($node->children->all());
            }

            $node->has_descendant_error = $this->hasDescendantError($node);
        }

        return $nodes;
    }

    protected function hasDescendantError($node): bool
    {
        if (! $node->children || $node->children->isEmpty()) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child->exception !== null) {
                return true;
            }

            if ($this->hasDescendantError($child)) {
                return true;
            }
        }

        return false;
    }
}
