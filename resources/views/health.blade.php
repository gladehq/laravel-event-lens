@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Health</h1>
        <p class="text-sm text-gray-500 mt-1">Audit your event-listener wiring and monitor listener performance.</p>
    </div>

    {{-- Tabbed Content --}}
    <div x-data="{
        activeTab: window.location.hash.slice(1) || 'audit',
        init() {
            this.$watch('activeTab', value => {
                window.history.replaceState(null, '', '#' + value);
            });
        }
    }">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <button @click="activeTab = 'audit'"
                        :class="activeTab === 'audit' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'audit'">
                    Audit
                </button>
                <button @click="activeTab = 'scores'"
                        :class="activeTab === 'scores' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'scores'">
                    Listener Health
                </button>
                <button @click="activeTab = 'sla'"
                        :class="activeTab === 'sla' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'sla'">
                    SLA Compliance
                </button>
                <button @click="activeTab = 'blast'"
                        :class="activeTab === 'blast' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'blast'">
                    Blast Radius
                </button>
            </nav>
        </div>

        {{-- Audit Tab --}}
        <div x-show="activeTab === 'audit'" x-cloak>
            <div class="space-y-6">
                {{-- Dead Listeners --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-200 bg-amber-50 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-amber-700">Dead Listeners</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $audit['dead_listeners']->count() }}</span>
                    </div>
                    @if($audit['dead_listeners']->isNotEmpty())
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener</th>
                                    <th class="px-5 py-3 text-left">Event</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($audit['dead_listeners'] as $dead)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm font-mono text-gray-900">{{ $dead->listener_name }}</td>
                                        <td class="px-5 py-3 text-sm font-mono text-gray-600">{{ $dead->event_name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">No dead listeners found.</div>
                    @endif
                </div>

                {{-- Orphan Events --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-orange-200 bg-orange-50 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-orange-700">Orphan Events</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">{{ $audit['orphan_events']->count() }}</span>
                    </div>
                    @if($audit['orphan_events']->isNotEmpty())
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Event</th>
                                    <th class="px-5 py-3 text-right">Fire Count</th>
                                    <th class="px-5 py-3 text-right">Last Seen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($audit['orphan_events'] as $orphan)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm">
                                            <a href="{{ route('event-lens.index', ['event' => $orphan->event_name]) }}" class="font-mono text-indigo-600 hover:underline">{{ $orphan->event_name }}</a>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($orphan->fire_count) }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-500 text-right">{{ \Carbon\Carbon::parse($orphan->last_seen)->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">No orphan events found.</div>
                    @endif
                </div>

                {{-- Stale Listeners --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-300 bg-gray-50 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700">Stale Listeners</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">{{ $audit['stale_listeners']->count() }}</span>
                    </div>
                    @if($audit['stale_listeners']->isNotEmpty())
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener</th>
                                    <th class="px-5 py-3 text-left">Event</th>
                                    <th class="px-5 py-3 text-right">Last Executed</th>
                                    <th class="px-5 py-3 text-right">Days Stale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($audit['stale_listeners'] as $stale)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm font-mono text-gray-900">{{ $stale->listener_name }}</td>
                                        <td class="px-5 py-3 text-sm">
                                            <a href="{{ route('event-lens.index', ['event' => $stale->event_name]) }}" class="font-mono text-indigo-600 hover:underline">{{ $stale->event_name }}</a>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-500 text-right">{{ \Carbon\Carbon::parse($stale->last_executed_at)->diffForHumans() }}</td>
                                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 text-right">{{ $stale->days_stale }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">No stale listeners found.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Listener Health Tab --}}
        <div x-show="activeTab === 'scores'" x-cloak>
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700">Listener Health Scores</h2>
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> 80-100 Healthy</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> 50-79 Warning</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> 0-49 Critical</span>
                        </div>
                    </div>
                    @if($healthScores->isNotEmpty())
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener</th>
                                    <th class="px-5 py-3 text-right">Health Score</th>
                                    <th class="px-5 py-3 text-right">Error Rate</th>
                                    <th class="px-5 py-3 text-right">P95 Latency</th>
                                    <th class="px-5 py-3 text-right">Avg Queries</th>
                                    <th class="px-5 py-3 text-right">Executions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($healthScores as $score)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm">
                                            <a href="{{ route('event-lens.index', ['listener' => $score->listener_name]) }}" class="font-mono text-indigo-600 hover:underline">{{ $score->listener_name }}</a>
                                            <p class="text-xs text-gray-400 font-mono">{{ $score->event_name }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-right font-bold {{ $score->score >= 80 ? 'text-green-600' : ($score->score >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $score->score }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-right {{ $score->error_rate > 5 ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                            {{ $score->error_rate }}%
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($score->p95_latency, 2) }} ms</td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ $score->avg_queries }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($score->execution_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">No listener data available for this period.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- SLA Compliance Tab (Placeholder) --}}
        <div x-show="activeTab === 'sla'" x-cloak>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <p class="text-sm text-gray-500">SLA Compliance monitoring coming soon.</p>
            </div>
        </div>

        {{-- Blast Radius Tab (Placeholder) --}}
        <div x-show="activeTab === 'blast'" x-cloak>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <p class="text-sm text-gray-500">Blast Radius analysis coming soon.</p>
            </div>
        </div>
    </div>
@endsection
