<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use Carbon\Carbon;
use GladeHQ\LaravelEventLens\Models\EventLog;

class FlowMapService
{
    /**
     * Build a directed graph of event->listener relationships.
     *
     * Returns ['nodes' => [...], 'edges' => [...]] for SVG rendering.
     */
    public function buildGraph(?string $timeRange = '24h'): array
    {
        $since = $this->resolveSince($timeRange);

        $flows = EventLog::query()
            ->where('happened_at', '>=', $since)
            ->where('listener_name', '!=', 'Event::dispatch')
            ->selectRaw('event_name, listener_name, COUNT(*) as call_count, AVG(execution_time_ms) as avg_ms, SUM(CASE WHEN exception IS NOT NULL THEN 1 ELSE 0 END) as error_count')
            ->groupBy('event_name', 'listener_name')
            ->get();

        if ($flows->isEmpty()) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        foreach ($flows as $flow) {
            $eventKey = 'event:' . $flow->event_name;
            $listenerKey = 'listener:' . $flow->listener_name;

            if (! isset($nodes[$eventKey])) {
                $nodes[$eventKey] = [
                    'id' => $eventKey,
                    'label' => class_basename($flow->event_name),
                    'full_name' => $flow->event_name,
                    'type' => 'event',
                ];
            }

            if (! isset($nodes[$listenerKey])) {
                $nodes[$listenerKey] = [
                    'id' => $listenerKey,
                    'label' => class_basename($flow->listener_name),
                    'full_name' => $flow->listener_name,
                    'type' => 'listener',
                ];
            }

            $totalCalls = (int) $flow->call_count;
            $errorCount = (int) $flow->error_count;
            $errorRate = $totalCalls > 0 ? round(($errorCount / $totalCalls) * 100, 1) : 0;

            $edges[] = [
                'source' => $eventKey,
                'target' => $listenerKey,
                'count' => $totalCalls,
                'avg_ms' => round((float) $flow->avg_ms, 2),
                'error_count' => $errorCount,
                'error_rate' => $errorRate,
            ];
        }

        // Layout: events column left, listeners column right
        $nodeWidth = 180;
        $nodeSpacing = 56;
        $layerGap = 220;
        $startX = 40;
        $startY = 50;

        $eventNodes = array_values(array_filter($nodes, fn ($n) => $n['type'] === 'event'));
        $listenerNodes = array_values(array_filter($nodes, fn ($n) => $n['type'] === 'listener'));

        foreach ($eventNodes as $i => &$node) {
            $node['x'] = $startX;
            $node['y'] = $startY + $i * (36 + $nodeSpacing);
            $node['width'] = $nodeWidth;
        }
        unset($node);

        foreach ($listenerNodes as $i => &$node) {
            $node['x'] = $startX + $nodeWidth + $layerGap;
            $node['y'] = $startY + $i * (36 + $nodeSpacing);
            $node['width'] = $nodeWidth;
        }
        unset($node);

        $positioned = array_merge($eventNodes, $listenerNodes);
        $nodeMap = [];
        foreach ($positioned as $n) {
            $nodeMap[$n['id']] = $n;
        }

        foreach ($edges as &$edge) {
            $src = $nodeMap[$edge['source']] ?? null;
            $tgt = $nodeMap[$edge['target']] ?? null;
            if ($src && $tgt) {
                $edge['x1'] = $src['x'] + $src['width'];
                $edge['y1'] = $src['y'];
                $edge['x2'] = $tgt['x'];
                $edge['y2'] = $tgt['y'];
            }
        }
        unset($edge);

        $maxX = max(array_map(fn ($n) => $n['x'] + $n['width'], $positioned)) + 60;
        $maxY = max(array_map(fn ($n) => $n['y'] + 36, $positioned)) + 60;

        return [
            'nodes' => $positioned,
            'edges' => $edges,
            'viewBox' => '0 0 ' . max(900, (int) $maxX) . ' ' . max(400, (int) $maxY),
        ];
    }

    protected function resolveSince(string $timeRange): Carbon
    {
        return match ($timeRange) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };
    }
}
