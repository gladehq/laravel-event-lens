<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Http\Controllers;

use Illuminate\Routing\Controller;
use GladeHQ\LaravelEventLens\Models\EventLog;

class EventLensController extends Controller
{
    public function index()
    {
        // Get unique root events (events with no parent, or distinct correlation_ids)
        // Group by correlation_id? 
        // Ideally we show "Requests" or "Root Events".
        
        // Let's list unique Correlation IDs, ordered by latest.
        $events = EventLog::query()
            ->select('correlation_id', 'event_name', 'happened_at', 'execution_time_ms')
            ->whereNull('parent_event_id') // Only root events
            ->orderByDesc('happened_at')
            ->paginate(20);

        return view('event-lens::index', compact('events'));
    }

    public function show($correlationId)
    {
        $events = EventLog::where('correlation_id', $correlationId)
            ->orderBy('happened_at') // Chronological order
            ->get();

        if ($events->isEmpty()) {
            abort(404);
        }

        // Calculate total stats
        $totalDuration = $events->sum('execution_time_ms');
        $totalQueries = $events->sum(fn($e) => $e->side_effects['queries'] ?? 0);

        // Build Tree Structure for Waterfall
        $tree = $this->buildTree($events);

        return view('event-lens::waterfall', compact('tree', 'events', 'totalDuration', 'totalQueries'));
    }

    protected function buildTree($events, $parentId = null)
    {
        $branch = [];
        
        foreach ($events as $event) {
            if ($event->parent_event_id == $parentId) {
                $children = $this->buildTree($events, $event->event_id);
                $event->setRelation('children', $children);
                $branch[] = $event;
            }
        }
        
        return $branch;
    }
    
    public function latest()
    {
        $afterId = request('after_id');
        
        $query = EventLog::query()
            ->select('id', 'correlation_id', 'event_name', 'happened_at', 'execution_time_ms')
            ->whereNull('parent_event_id');
            
        if ($afterId) {
            $query->where('id', '>', $afterId);
        }
            
        $events = $query->orderByDesc('id') // Get newest
            ->limit(20)
            ->get();
            
        // Map for JSON response
        return response()->json([
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'correlation_id' => $event->correlation_id,
                    'event_name' => $event->event_name,
                    'happened_at' => $event->happened_at->diffForHumans(),
                    'execution_time_ms' => number_format($event->execution_time_ms, 2),
                    'url' => route('event-lens.show', $event->correlation_id),
                ];
            })    
        ]);
    }
}
