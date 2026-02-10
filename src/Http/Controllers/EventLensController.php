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
        $events = EventLog::roots()
            ->forEvent($request->get('event'))
            ->forCorrelation($request->get('correlation'))
            ->betweenDates($request->get('start_date'), $request->get('end_date'))
            ->when($request->boolean('slow'), fn ($q) => $q->slow(config('event-lens.slow_threshold', 100.0)))
            ->latest('happened_at')
            ->paginate(20);

        return view('event-lens::index', compact('events'));
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
        $tree = $this->buildTree($events);

        return view('event-lens::waterfall', compact('tree', 'events', 'totalDuration', 'totalQueries', 'totalMails'));
    }

    public function statistics(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(7));
        $endDate = $request->get('end_date', now());

        $cacheKey = 'event-lens:stats:' . md5(serialize([$startDate, $endDate]));

        $stats = Cache::remember($cacheKey, 120, function () use ($startDate, $endDate) {
            return [
                'total_events' => EventLog::roots()->betweenDates($startDate, $endDate)->count(),
                'avg_execution_time' => round(
                    (float) EventLog::roots()->betweenDates($startDate, $endDate)->avg('execution_time_ms'),
                    2
                ),
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
                    ->selectRaw('DATE(happened_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ];
        });

        return view('event-lens::statistics', compact('stats', 'startDate', 'endDate'));
    }

    public function detail(string $eventId)
    {
        $event = EventLog::where('event_id', $eventId)->firstOrFail();

        return view('event-lens::detail', compact('event'));
    }

    public function latest(Request $request)
    {
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
            if ($event->parent_event_id == $parentId) {
                $children = $this->buildTree($events, $event->event_id);
                $event->setRelation('children', collect($children));
                $branch[] = $event;
            }
        }

        return $branch;
    }
}
