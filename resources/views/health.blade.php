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
                <button @click="activeTab = 'regressions'"
                        :class="activeTab === 'regressions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'regressions'">
                    Regressions
                    @if($regressions->isNotEmpty())
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ $regressions->count() }}</span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Audit Tab --}}
        <div x-show="activeTab === 'audit'" x-cloak>
            <p class="text-sm text-gray-500 mb-4">Detects dead listeners that are registered but never executed, orphan events dispatched without any listeners, and stale listeners that haven't run in over {{ config('event-lens.stale_threshold_days', 30) }} days.</p>
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
            <p class="text-sm text-gray-500 mb-4">Scores each listener from 0 to 100 based on error rate, P95 latency, and average query count. Lower scores indicate listeners that need attention.</p>
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

        {{-- SLA Compliance Tab --}}
        <div x-show="activeTab === 'sla'" x-cloak>
            <p class="text-sm text-gray-500 mb-4">Tracks whether listeners and events are meeting the time budgets defined in your <code class="text-xs bg-gray-100 px-1 rounded">sla_budgets</code> config. Shows P95 actual latency against each budget over the last 7 days.</p>
            <div class="space-y-6">
                @if($slaCompliance['total'] > 0)
                    {{-- Summary Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Listeners with SLAs</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $slaCompliance['total'] }}</p>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-green-200 p-5">
                            <p class="text-xs font-semibold text-green-600 uppercase tracking-wider">Compliant</p>
                            <p class="text-3xl font-bold text-green-600 mt-1">{{ $slaCompliance['compliant'] }}</p>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-red-200 p-5">
                            <p class="text-xs font-semibold text-red-600 uppercase tracking-wider">Breaches in 7d</p>
                            <p class="text-3xl font-bold text-red-600 mt-1">{{ number_format($slaCompliance['breaches_7d']) }}</p>
                        </div>
                    </div>

                    {{-- Compliance Table --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-sm font-semibold text-gray-700">SLA Budget Compliance</h2>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener / Event</th>
                                    <th class="px-5 py-3 text-right">Budget (ms)</th>
                                    <th class="px-5 py-3 text-right">P95 Actual (ms)</th>
                                    <th class="px-5 py-3 text-right">Breaches (7d)</th>
                                    <th class="px-5 py-3 text-right w-48">Compliance %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($slaCompliance['budgets'] as $budget)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm font-mono text-gray-900">{{ $budget->name }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($budget->budget_ms, 0) }}</td>
                                        <td class="px-5 py-3 text-sm text-right {{ $budget->p95_actual > $budget->budget_ms ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                            {{ number_format($budget->p95_actual, 2) }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-right {{ $budget->breach_count > 0 ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                            {{ number_format($budget->breach_count) }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <div class="w-24 bg-gray-100 rounded-full h-2">
                                                    <div class="h-2 rounded-full {{ $budget->compliance_pct >= 95 ? 'bg-green-500' : ($budget->compliance_pct >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                                         style="width: {{ $budget->compliance_pct }}%"></div>
                                                </div>
                                                <span class="text-xs font-semibold {{ $budget->compliance_pct >= 95 ? 'text-green-600' : ($budget->compliance_pct >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $budget->compliance_pct }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-4">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No SLA budgets configured.</p>
                        <p class="text-xs text-gray-400 mt-1">Add time budgets in <code class="bg-gray-100 px-1 rounded">config/event-lens.php</code> under <code class="bg-gray-100 px-1 rounded">sla_budgets</code>.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Blast Radius Tab --}}
        <div x-show="activeTab === 'blast'" x-cloak>
            <p class="text-sm text-gray-500 mb-4">Maps how far a listener failure can propagate. Risk scores factor in average downstream children, error rate, and execution time to highlight listeners where a single failure could cascade.</p>
            <div class="space-y-6">
                @if($blastRadius->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-700">Listener Blast Radius</h2>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> High (70+)</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Medium (40-69)</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Low (&lt;40)</span>
                            </div>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener</th>
                                    <th class="px-5 py-3 text-right">Avg Children</th>
                                    <th class="px-5 py-3 text-right">Downstream</th>
                                    <th class="px-5 py-3 text-right">Error Rate</th>
                                    <th class="px-5 py-3 text-right">Risk</th>
                                </tr>
                            </thead>
                            @foreach($blastRadius as $item)
                                <tbody x-data="{ expanded: false }" class="divide-y divide-gray-100">
                                        <tr class="hover:bg-gray-50 {{ $item->total_downstream > 0 ? 'cursor-pointer' : '' }}"
                                            @if($item->total_downstream > 0) @click="expanded = !expanded" @endif>
                                            <td class="px-5 py-3 text-sm">
                                                <span class="flex items-center gap-2">
                                                    @if($item->total_downstream > 0)
                                                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    @endif
                                                    <a href="{{ route('event-lens.index', ['listener' => $item->listener_name]) }}" class="font-mono text-indigo-600 hover:underline" @click.stop>
                                                        {{ $item->listener_name }}
                                                    </a>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ $item->avg_children }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ $item->total_downstream }}</td>
                                            <td class="px-5 py-3 text-sm text-right {{ $item->error_rate > 5 ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                                {{ $item->error_rate }}%
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                                    {{ $item->risk_level === 'High' ? 'bg-red-100 text-red-700' : ($item->risk_level === 'Medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                                    {{ $item->risk_level }} ({{ $item->risk_score }})
                                                </span>
                                            </td>
                                        </tr>
                                        @if($item->total_downstream > 0)
                                            <tr x-show="expanded" x-cloak>
                                                <td colspan="5" class="p-0">
                                                    <div class="bg-gray-50 px-10 py-3">
                                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Downstream Listeners</p>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach($item->downstream as $downstream)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-white border border-gray-200 text-gray-700">{{ $downstream }}</span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-4">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No blast radius data available.</p>
                        <p class="text-xs text-gray-400 mt-1">Blast radius analysis requires recorded listener executions.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Regressions Tab --}}
        <div x-show="activeTab === 'regressions'" x-cloak>
            <p class="text-sm text-gray-500 mb-4">Compares each listener's recent performance (last 24h) against its baseline (previous 7 days). Flags listeners where the recent average exceeds the baseline by {{ config('event-lens.regression_threshold', 2.0) }}x or more.</p>
            <div class="space-y-6">
                @if($regressions->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-700">Performance Regressions</h2>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Critical (5x+)</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Warning ({{ config('event-lens.regression_threshold', 2.0) }}x+)</span>
                            </div>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Listener</th>
                                    <th class="px-5 py-3 text-right">Baseline Avg</th>
                                    <th class="px-5 py-3 text-right">Recent Avg</th>
                                    <th class="px-5 py-3 text-right">Change</th>
                                    <th class="px-5 py-3 text-right">Severity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($regressions as $regression)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm">
                                            <a href="{{ route('event-lens.index', ['listener' => $regression->listener_name]) }}" class="font-mono text-indigo-600 hover:underline">{{ $regression->listener_name }}</a>
                                            <p class="text-xs text-gray-400 font-mono">{{ $regression->event_name }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($regression->baseline_avg_ms, 2) }} ms</td>
                                        <td class="px-5 py-3 text-sm text-right font-semibold {{ $regression->severity === 'critical' ? 'text-red-600' : 'text-yellow-600' }}">
                                            {{ number_format($regression->recent_avg_ms, 2) }} ms
                                        </td>
                                        <td class="px-5 py-3 text-sm text-right font-semibold text-red-600">
                                            +{{ $regression->change_pct }}%
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                                {{ $regression->severity === 'critical' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' }}">
                                                {{ ucfirst($regression->severity) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No performance regressions detected.</p>
                        <p class="text-xs text-gray-400 mt-1">Listeners are performing within normal range compared to their 7-day baseline.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
