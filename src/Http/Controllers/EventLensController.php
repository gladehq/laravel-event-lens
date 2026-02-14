<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Http\Resources\EventLogResource;
use GladeHQ\LaravelEventLens\Services\AuditService;
use GladeHQ\LaravelEventLens\Services\BlastRadiusService;
use GladeHQ\LaravelEventLens\Services\ListenerHealthService;
use GladeHQ\LaravelEventLens\Services\OtlpExporter;
use GladeHQ\LaravelEventLens\Services\RegressionDetector;
use GladeHQ\LaravelEventLens\Services\ReplayService;
use GladeHQ\LaravelEventLens\Services\SlaChecker;
use GladeHQ\LaravelEventLens\Support\TraceTreeBuilder;

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
            'sla' => 'nullable|boolean',
            'drift' => 'nullable|boolean',
            'nplus1' => 'nullable|boolean',
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
            ->when($request->boolean('sla'), fn ($q) => $q->slaBreaches())
            ->when($request->boolean('drift'), fn ($q) => $q->withDrift())
            ->when($request->boolean('nplus1'), fn ($q) => $q->nplusOne())
            ->latest('happened_at')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['data' => $events->items(), 'meta' => ['total' => $events->total(), 'per_page' => $events->perPage()]]);
        }

        return view('event-lens::index', compact('events', 'slowThreshold'));
    }

    public function show(Request $request, string $correlationId)
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
        $totalHttpCalls = $events->sum(fn ($e) => $e->side_effects['http_calls'] ?? 0);
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $totalErrors = $events->filter(fn ($e) => $e->exception !== null)->count();
        $totalSlow = $events->filter(fn ($e) => $e->execution_time_ms > $slowThreshold)->count();
        $firstErrorEventId = $events->firstWhere('exception', '!=', null)?->event_id;

        $tree = TraceTreeBuilder::build($events);

        if ($request->wantsJson()) {
            return response()->json([
                'correlation_id' => $correlationId,
                'events' => $events->toArray(),
                'summary' => compact('totalDuration', 'totalQueries', 'totalMails', 'totalHttpCalls', 'totalErrors', 'totalSlow'),
            ]);
        }

        return view('event-lens::waterfall', compact(
            'tree', 'events', 'totalDuration', 'totalQueries', 'totalMails', 'totalHttpCalls',
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

            $httpExpr = match ($driver) {
                'pgsql' => "COALESCE((side_effects::json->>'http_calls')::int, 0)",
                default => "COALESCE(json_extract(side_effects, '$.http_calls'), 0)",
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
                'storm_count' => EventLog::roots()->betweenDates($startDate, $endDate)->storms()->count(),
                'sla_breach_count' => EventLog::roots()->betweenDates($startDate, $endDate)->slaBreaches()->count(),
                'total_queries' => (int) EventLog::roots()->betweenDates($startDate, $endDate)
                    ->selectRaw("SUM({$queriesExpr}) as total")->value('total'),
                'total_mails' => (int) EventLog::roots()->betweenDates($startDate, $endDate)
                    ->selectRaw("SUM({$mailsExpr}) as total")->value('total'),
                'total_http_calls' => (int) EventLog::roots()->betweenDates($startDate, $endDate)
                    ->selectRaw("SUM({$httpExpr}) as total")->value('total'),
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

        if ($request->wantsJson()) {
            return response()->json($stats);
        }

        return view('event-lens::statistics', compact('stats', 'startDate', 'endDate', 'slowThreshold'));
    }

    public function health(Request $request)
    {
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);

        $version = Cache::get('event-lens:cache-version', 0);
        $cacheKey = "event-lens:health:v{$version}";

        $audit = Cache::remember($cacheKey, 300, function () {
            $auditService = app(AuditService::class);

            return [
                'dead_listeners' => $auditService->deadListeners(),
                'orphan_events' => $auditService->orphanEvents(),
                'stale_listeners' => $auditService->staleListeners(),
            ];
        });

        $healthService = app(ListenerHealthService::class);
        $healthScores = $healthService->scores();

        // SLA Compliance data
        $slaCompliance = $this->buildSlaCompliance();

        // Blast Radius data
        $blastRadiusService = app(BlastRadiusService::class);
        $blastRadius = $blastRadiusService->calculate();

        // Regression Detection
        $regressionDetector = app(RegressionDetector::class);
        $regressions = $regressionDetector->detect();

        if ($request->wantsJson()) {
            return response()->json(compact('audit', 'healthScores', 'slaCompliance', 'blastRadius', 'regressions'));
        }

        return view('event-lens::health', compact(
            'audit', 'healthScores', 'slowThreshold', 'slaCompliance', 'blastRadius', 'regressions'
        ));
    }

    public function detail(string $eventId)
    {
        $event = EventLog::where('event_id', $eventId)->firstOrFail();
        $slowThreshold = (float) config('event-lens.slow_threshold', 100.0);
        $allowReplay = (bool) config('event-lens.allow_replay', false);

        $siblings = EventLog::forCorrelation($event->correlation_id)
            ->orderBy('happened_at')
            ->get(['event_id', 'listener_name']);

        $currentIndex = $siblings->search(fn ($e) => $e->event_id === $event->event_id);
        $prevEvent = $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
        $nextEvent = $currentIndex < $siblings->count() - 1 ? $siblings[$currentIndex + 1] : null;

        return view('event-lens::detail', compact('event', 'slowThreshold', 'prevEvent', 'nextEvent', 'allowReplay'));
    }

    public function replay(string $eventId)
    {
        $event = EventLog::where('event_id', $eventId)->firstOrFail();

        $replayService = app(ReplayService::class);
        $result = $replayService->replay($event);

        if ($result['success']) {
            return redirect()->route('event-lens.detail', $eventId)
                ->with('replay_success', 'Event replayed successfully. A new trace has been created.');
        }

        return redirect()->route('event-lens.detail', $eventId)
            ->with('replay_error', $result['error']);
    }

    public function export(string $correlationId)
    {
        $exporter = app(OtlpExporter::class);
        $result = $exporter->export($correlationId);

        if ($result['success']) {
            return redirect()->route('event-lens.show', $correlationId)
                ->with('export_success', 'Trace exported to OTLP endpoint.');
        }

        return redirect()->route('event-lens.show', $correlationId)
            ->with('export_error', $result['error']);
    }

    public function latest(Request $request)
    {
        $request->validate([
            'after_id' => 'nullable|integer|min:0',
        ]);

        $events = EventLog::roots()
            ->when($request->get('after_id'), fn ($q, $id) => $q->where('id', '>', $id))
            ->latest('id')
            ->limit(20)
            ->get();

        return EventLogResource::collection($events);
    }

    public function flowMap(Request $request)
    {
        $range = $request->get('range', '24h');
        $flowMapService = app(\GladeHQ\LaravelEventLens\Services\FlowMapService::class);
        $graph = $flowMapService->buildGraph($range);

        return view('event-lens::flow-map', compact('graph', 'range'));
    }

    public function comparison(Request $request)
    {
        $preset = $request->get('preset', 'day');

        [$periodAStart, $periodAEnd, $periodBStart, $periodBEnd] = match ($preset) {
            'hour' => [now()->subHours(2), now()->subHour(), now()->subHour(), now()],
            'week' => [now()->subWeeks(2), now()->subWeek(), now()->subWeek(), now()],
            default => [now()->subDays(2), now()->subDay(), now()->subDay(), now()],
        };

        $service = app(\GladeHQ\LaravelEventLens\Services\ComparisonService::class);
        $comparison = $service->compare($periodAStart, $periodAEnd, $periodBStart, $periodBEnd);

        if ($request->wantsJson()) {
            return response()->json($comparison);
        }

        return view('event-lens::comparison', compact('comparison', 'preset'));
    }

    public function asset(string $file)
    {
        $allowedFiles = [
            'app.css' => 'text/css',
            'alpine.min.js' => 'application/javascript',
        ];

        if (! isset($allowedFiles[$file])) {
            abort(404);
        }

        $path = __DIR__.'/../../../resources/assets/'.$file;

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $allowedFiles[$file],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Build SLA compliance data from config budgets and actual listener performance.
     */
    protected function buildSlaCompliance(): array
    {
        $budgets = config('event-lens.sla_budgets', []);

        if (empty($budgets)) {
            return ['budgets' => collect(), 'total' => 0, 'compliant' => 0, 'breaches_7d' => 0];
        }

        $sevenDaysAgo = now()->subDays(7);
        $slaChecker = app(SlaChecker::class);

        $compliance = collect($budgets)->map(function ($budgetMs, $name) use ($sevenDaysAgo) {
            // Determine if this is a listener or event pattern
            $query = EventLog::query()
                ->where('happened_at', '>=', $sevenDaysAgo)
                ->where(function ($q) use ($name) {
                    $q->where('listener_name', $name)
                      ->orWhere('event_name', $name);
                });

            $totalExecutions = (clone $query)->count();
            $breachCount = (clone $query)->where('is_sla_breach', true)->count();

            // P95 actual execution time
            $p95 = 0;
            if ($totalExecutions > 0) {
                $offset = (int) ceil($totalExecutions * 0.95) - 1;
                $p95 = (float) (clone $query)
                    ->orderBy('execution_time_ms')
                    ->offset(max(0, $offset))
                    ->limit(1)
                    ->value('execution_time_ms');
            }

            $compliancePct = $totalExecutions > 0
                ? round((($totalExecutions - $breachCount) / $totalExecutions) * 100, 1)
                : 100;

            return (object) [
                'name' => $name,
                'budget_ms' => (float) $budgetMs,
                'p95_actual' => round($p95, 2),
                'breach_count' => $breachCount,
                'total_executions' => $totalExecutions,
                'compliance_pct' => $compliancePct,
            ];
        })->values();

        return [
            'budgets' => $compliance,
            'total' => $compliance->count(),
            'compliant' => $compliance->where('breach_count', 0)->count(),
            'breaches_7d' => $compliance->sum('breach_count'),
        ];
    }

}
