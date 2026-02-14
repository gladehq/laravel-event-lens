@extends('event-lens::layout')

@section('content')
<div x-data="flowMap()" x-init="init()">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Event Flow Map</h1>
        <div class="flex items-center gap-2">
            @foreach(['1h' => '1 Hour', '6h' => '6 Hours', '24h' => '24 Hours', '7d' => '7 Days'] as $value => $label)
                <a href="{{ route('event-lens.flow-map', ['range' => $value]) }}"
                   class="px-3 py-1.5 text-xs font-medium rounded-md {{ ($range ?? '24h') === $value ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if(empty($graph['nodes']))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <p class="text-gray-500">No event flow data found for the selected time range.</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-indigo-500"></span> Events ({{ collect($graph['nodes'])->where('type', 'event')->count() }})
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-emerald-500"></span> Listeners ({{ collect($graph['nodes'])->where('type', 'listener')->count() }})
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-gray-400"></span> Connections ({{ count($graph['edges']) }})
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="zoomIn()" class="p-1 rounded hover:bg-gray-100 text-gray-500" title="Zoom in">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                    </button>
                    <button @click="zoomOut()" class="p-1 rounded hover:bg-gray-100 text-gray-500" title="Zoom out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/></svg>
                    </button>
                    <button @click="resetView()" class="p-1 rounded hover:bg-gray-100 text-gray-500" title="Reset view">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>
            <div class="overflow-auto" style="max-height: 600px;">
                <svg :viewBox="viewBox" class="w-full" style="min-height: 400px;" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="#9CA3AF"/>
                        </marker>
                        <marker id="arrowhead-red" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="#EF4444"/>
                        </marker>
                    </defs>

                    <!-- Edges -->
                    <template x-for="edge in edges" :key="edge.source + '-' + edge.target">
                        <g>
                            <line :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
                                  :stroke="edge.error_rate > 10 ? '#EF4444' : edge.avg_ms > 200 ? '#F59E0B' : '#9CA3AF'"
                                  :stroke-width="Math.min(4, 1 + Math.log2(edge.count))"
                                  :marker-end="edge.error_rate > 10 ? 'url(#arrowhead-red)' : 'url(#arrowhead)'"
                                  class="transition-all"/>
                            <text :x="(edge.x1 + edge.x2) / 2" :y="(edge.y1 + edge.y2) / 2 - 6"
                                  text-anchor="middle" class="text-[10px] fill-gray-400" x-text="edge.count + 'x'"/>
                        </g>
                    </template>

                    <!-- Nodes -->
                    <template x-for="node in nodes" :key="node.id">
                        <g :transform="'translate(' + node.x + ',' + node.y + ')'" class="cursor-pointer">
                            <rect :width="node.width" :height="36" rx="6" ry="6"
                                  :fill="node.type === 'event' ? '#EEF2FF' : '#ECFDF5'"
                                  :stroke="node.type === 'event' ? '#6366F1' : '#10B981'"
                                  stroke-width="1.5"
                                  x="0" y="-18"/>
                            <text :x="node.width / 2" y="5" text-anchor="middle"
                                  :fill="node.type === 'event' ? '#4338CA' : '#047857'"
                                  class="text-[11px] font-medium" x-text="node.label"/>
                        </g>
                    </template>
                </svg>
            </div>
        </div>

        <!-- Tooltip / Details Table -->
        <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Connection Details</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Event</th>
                        <th class="text-left py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Listener</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Calls</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Avg Time</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Errors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($graph['edges'] as $edge)
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs text-indigo-600">{{ class_basename(str_replace('event:', '', $edge['source'])) }}</td>
                            <td class="py-2 px-4 font-mono text-xs text-emerald-600">{{ class_basename(str_replace('listener:', '', $edge['target'])) }}</td>
                            <td class="py-2 px-4 text-right text-gray-700">{{ number_format($edge['count']) }}</td>
                            <td class="py-2 px-4 text-right {{ $edge['avg_ms'] > 200 ? 'text-amber-600 font-semibold' : 'text-gray-700' }}">{{ number_format($edge['avg_ms'], 1) }}ms</td>
                            <td class="py-2 px-4 text-right {{ $edge['error_count'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $edge['error_count'] }} ({{ $edge['error_rate'] }}%)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function flowMap() {
    return {
        nodes: [],
        edges: [],
        zoom: 1,
        viewBox: '0 0 900 500',
        graphData: @json($graph),

        init() {
            this.layout();
        },

        layout() {
            const data = this.graphData;
            if (!data.nodes.length) return;

            // Separate events and listeners
            const events = data.nodes.filter(n => n.type === 'event');
            const listeners = data.nodes.filter(n => n.type === 'listener');

            const nodeWidth = 140;
            const nodeSpacing = 60;
            const layerGap = 200;
            const startX = 40;
            const startY = 40;

            // Layout: events on left, listeners on right
            const nodeMap = {};

            events.forEach((node, i) => {
                node.x = startX;
                node.y = startY + i * (36 + nodeSpacing);
                node.width = nodeWidth;
                nodeMap[node.id] = node;
            });

            listeners.forEach((node, i) => {
                node.x = startX + layerGap + nodeWidth;
                node.y = startY + i * (36 + nodeSpacing);
                node.width = nodeWidth;
                nodeMap[node.id] = node;
            });

            this.nodes = [...events, ...listeners];

            // Layout edges
            this.edges = data.edges.map(edge => {
                const src = nodeMap[edge.source];
                const tgt = nodeMap[edge.target];
                if (!src || !tgt) return null;
                return {
                    ...edge,
                    x1: src.x + src.width,
                    y1: src.y,
                    x2: tgt.x,
                    y2: tgt.y,
                };
            }).filter(Boolean);

            // Adjust viewBox
            const maxX = Math.max(...this.nodes.map(n => n.x + n.width)) + 60;
            const maxY = Math.max(...this.nodes.map(n => n.y + 36)) + 60;
            this.viewBox = `0 0 ${Math.max(900, maxX)} ${Math.max(400, maxY)}`;
        },

        zoomIn() {
            this.zoom = Math.min(3, this.zoom * 1.2);
            this.updateZoom();
        },

        zoomOut() {
            this.zoom = Math.max(0.3, this.zoom / 1.2);
            this.updateZoom();
        },

        resetView() {
            this.zoom = 1;
            this.layout();
        },

        updateZoom() {
            const parts = this.viewBox.split(' ').map(Number);
            const w = parts[2] / this.zoom;
            const h = parts[3] / this.zoom;
            this.viewBox = `0 0 ${Math.round(w)} ${Math.round(h)}`;
        }
    };
}
</script>
@endpush
