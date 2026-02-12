@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Statistics</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} &mdash; {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    {{-- Date Range Filter --}}
    <form method="GET" action="{{ route('event-lens.statistics') }}" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ \Carbon\Carbon::parse($startDate)->format('Y-m-d') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">End Date</label>
                <input type="date" name="end_date" value="{{ \Carbon\Carbon::parse($endDate)->format('Y-m-d') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Apply
                </button>
                <a href="{{ route('event-lens.statistics', ['start_date' => now()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')]) }}"
                   class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md hover:bg-gray-50">Today</a>
                <a href="{{ route('event-lens.statistics', ['start_date' => now()->subDays(6)->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')]) }}"
                   class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md hover:bg-gray-50">7d</a>
                <a href="{{ route('event-lens.statistics', ['start_date' => now()->subDays(29)->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')]) }}"
                   class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md hover:bg-gray-50">30d</a>
            </div>
        </div>
    </form>

    {{-- Summary Cards (Row 1) --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Events</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_events']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Avg Execution Time</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['avg_execution_time'] }} <span class="text-base font-normal text-gray-500">ms</span></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-amber-200 p-5">
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Slow Events (&gt;{{ $slowThreshold }}ms)</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($stats['slow_count']) }}</p>
            <p class="text-xs text-gray-500 mt-1">
                {{ $stats['total_events'] > 0 ? round(($stats['slow_count'] / $stats['total_events']) * 100, 1) : 0 }}% of total
            </p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-red-200 p-5">
            <p class="text-xs font-semibold text-red-600 uppercase tracking-wider">Errors</p>
            <p class="text-3xl font-bold text-red-600 mt-1">{{ number_format($stats['error_count']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $stats['error_rate'] }}% error rate</p>
        </div>
    </div>

    {{-- Summary Cards (Row 2) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-blue-200 p-5">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Total DB Queries</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($stats['total_queries']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-green-200 p-5">
            <p class="text-xs font-semibold text-green-600 uppercase tracking-wider">Total Mails Sent</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ number_format($stats['total_mails']) }}</p>
        </div>
    </div>

    {{-- Tabbed Content --}}
    <div x-data="{
        activeTab: window.location.hash.slice(1) || 'overview',
        init() {
            this.$watch('activeTab', value => {
                window.history.replaceState(null, '', '#' + value);
            });
        }
    }">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <button @click="activeTab = 'overview'"
                        :class="activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'overview'">
                    Overview
                </button>
                <button @click="activeTab = 'performance'"
                        :class="activeTab === 'performance' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'performance'">
                    Performance
                </button>
                <button @click="activeTab = 'errors'"
                        :class="activeTab === 'errors' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        role="tab" :aria-selected="activeTab === 'errors'">
                    Errors
                    @if($stats['error_count'] > 0)
                        <span class="ml-2 py-0.5 px-2 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ $stats['error_count'] }}</span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Overview Tab --}}
        <div x-show="activeTab === 'overview'" x-cloak>
            <div class="space-y-6">
                {{-- Daily Timeline (CSS bar chart with error rate dots) --}}
                @if($stats['timeline']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Event Volume</h2>
                        @php
                            $maxCount = $stats['timeline']->max('count') ?: 1;
                            $maxErrorRate = $stats['timeline']->max(fn($d) => $d->count > 0 ? ($d->error_count / $d->count) * 100 : 0) ?: 1;
                        @endphp
                        <div class="flex items-end gap-1" style="height: 120px;">
                            @foreach($stats['timeline'] as $day)
                                @php
                                    $totalPct = max(4, ($day->count / $maxCount) * 100);
                                    $errorPct = $day->count > 0 ? ($day->error_count / $day->count) * $totalPct : 0;
                                @endphp
                                <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                    <div class="w-full rounded-t overflow-hidden flex flex-col justify-end" style="height: {{ $totalPct }}%;">
                                        @if($errorPct > 0)
                                            <div class="w-full bg-red-500" style="height: {{ ($errorPct / $totalPct) * 100 }}%;"></div>
                                        @endif
                                        <div class="w-full bg-indigo-500 flex-1 transition-all group-hover:bg-indigo-600"></div>
                                    </div>
                                    @if($day->error_count > 0)
                                        @php $errorRate = ($day->error_count / $day->count) * 100; @endphp
                                        <div class="absolute w-2 h-2 bg-red-600 rounded-full border border-white shadow-sm z-10 group-hover:scale-125 transition-transform"
                                             style="bottom: {{ ($errorRate / $maxErrorRate) * 100 }}%;"></div>
                                    @endif
                                    <div class="absolute -top-6 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                        {{ $day->date }}: {{ $day->count }} events{{ $day->error_count > 0 ? ", {$day->error_count} errors (" . number_format(($day->error_count / $day->count) * 100, 1) . "%)" : '' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mt-2">
                            <span>{{ $stats['timeline']->first()->date }}</span>
                            <span>{{ $stats['timeline']->last()->date }}</span>
                        </div>
                    </div>
                @endif

                {{-- Execution Time Distribution Histogram --}}
                @php
                    $dist = $stats['execution_distribution'];
                    $buckets = [
                        ['label' => '0-10ms',   'count' => (int) $dist->bucket_0_10,   'color' => 'bg-emerald-500'],
                        ['label' => '10-50ms',  'count' => (int) $dist->bucket_10_50,  'color' => 'bg-green-500'],
                        ['label' => '50-100ms', 'count' => (int) $dist->bucket_50_100, 'color' => 'bg-yellow-500'],
                        ['label' => '100-500ms','count' => (int) $dist->bucket_100_500,'color' => 'bg-orange-500'],
                        ['label' => '500ms+',   'count' => (int) $dist->bucket_500_plus,'color' => 'bg-red-500'],
                    ];
                    $maxBucket = max(array_column($buckets, 'count')) ?: 1;
                    $totalBucket = array_sum(array_column($buckets, 'count'));
                @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-700">Execution Time Distribution</h2>
                    <p class="text-xs text-gray-400 mb-4">Event count per latency bucket</p>
                    @if($totalBucket > 0)
                        <div class="flex items-end gap-3" style="height: 120px;">
                            @foreach($buckets as $bucket)
                                @php $pct = max(4, ($bucket['count'] / $maxBucket) * 100); @endphp
                                <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                    <div class="w-full rounded-t {{ $bucket['color'] }} transition-all group-hover:opacity-80"
                                         style="height: {{ $pct }}%;"></div>
                                    <div class="absolute -top-6 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                        {{ number_format($bucket['count']) }} events ({{ $totalBucket > 0 ? number_format(($bucket['count'] / $totalBucket) * 100, 1) : 0 }}%)
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-3 mt-2">
                            @foreach($buckets as $bucket)
                                <div class="flex-1 text-center text-xs text-gray-500">{{ $bucket['label'] }}</div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center justify-center text-gray-400 text-sm" style="height: 120px;">
                            No events recorded in this period.
                        </div>
                    @endif
                </div>

                {{-- Event Mix Composition Bar --}}
                @if($stats['events_by_type']->isNotEmpty())
                    @php
                        $topEvents = $stats['events_by_type']->take(5);
                        $topCount = $topEvents->sum('count');
                        $otherCount = $stats['total_events'] - $topCount;
                        $mixColors = ['bg-indigo-500', 'bg-blue-500', 'bg-purple-500', 'bg-pink-500', 'bg-cyan-500'];
                    @endphp
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <h2 class="text-sm font-semibold text-gray-700">Event Mix</h2>
                        <p class="text-xs text-gray-400 mb-4">Top event types by volume</p>
                        <div class="h-3 rounded-full overflow-hidden flex">
                            @foreach($topEvents as $i => $evt)
                                @php $evtPct = $stats['total_events'] > 0 ? ($evt->count / $stats['total_events']) * 100 : 0; @endphp
                                <div class="group relative {{ $mixColors[$i] }}" style="width: {{ $evtPct }}%;">
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                        {{ $evt->event_name }}: {{ number_format($evt->count) }} ({{ number_format($evtPct, 1) }}%)
                                    </div>
                                </div>
                            @endforeach
                            @if($otherCount > 0)
                                @php $otherPct = ($otherCount / $stats['total_events']) * 100; @endphp
                                <div class="group relative bg-gray-300" style="width: {{ $otherPct }}%;">
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                        Other: {{ number_format($otherCount) }} ({{ number_format($otherPct, 1) }}%)
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2 mt-4">
                            @foreach($topEvents as $i => $evt)
                                @php $evtPct = $stats['total_events'] > 0 ? ($evt->count / $stats['total_events']) * 100 : 0; @endphp
                                <div class="flex items-center gap-2 text-xs text-gray-700 min-w-0">
                                    <div class="w-3 h-3 rounded-sm {{ $mixColors[$i] }} shrink-0"></div>
                                    <span class="truncate">{{ $evt->event_name }}</span>
                                    <span class="text-gray-400 shrink-0">{{ number_format($evtPct, 1) }}%</span>
                                </div>
                            @endforeach
                            @if($otherCount > 0)
                                @php $otherPct = ($otherCount / $stats['total_events']) * 100; @endphp
                                <div class="flex items-center gap-2 text-xs text-gray-700 min-w-0">
                                    <div class="w-3 h-3 rounded-sm bg-gray-300 shrink-0"></div>
                                    <span class="truncate">Other</span>
                                    <span class="text-gray-400 shrink-0">{{ number_format($otherPct, 1) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Performance Tab --}}
        <div x-show="activeTab === 'performance'" x-cloak>
            <div class="space-y-6">
                {{-- Two-column grid: Top Events & Slowest Events --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Top Events by Frequency (with expandable listener breakdown) --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-sm font-semibold text-gray-700">Top Events by Frequency</h2>
                        </div>
                        @php $maxEventCount = $stats['events_by_type']->max('count') ?: 1; @endphp
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Event Name</th>
                                    <th class="px-5 py-3 text-right">Count</th>
                                    <th class="px-5 py-3 text-right">Avg Time</th>
                                </tr>
                            </thead>
                            @forelse($stats['events_by_type'] as $row)
                                @php
                                    $listeners = $stats['listener_breakdown'][$row->event_name] ?? collect();
                                    $hasMultipleListeners = $listeners->count() > 1;
                                @endphp
                                <tbody x-data="{ expanded: false }" class="divide-y divide-gray-100">
                                    <tr class="hover:bg-gray-50 {{ $hasMultipleListeners ? 'cursor-pointer' : '' }}"
                                        @if($hasMultipleListeners) @click="expanded = !expanded" @endif>
                                        <td class="px-5 py-3 text-sm font-mono truncate max-w-xs">
                                            <span class="flex items-center gap-2">
                                                @if($hasMultipleListeners)
                                                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                @endif
                                                <a href="{{ route('event-lens.index', ['event' => $row->event_name]) }}" class="text-indigo-600 hover:underline" @click.stop>
                                                    {{ $row->event_name }}
                                                </a>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right relative">
                                            <div class="absolute inset-0 flex items-center pointer-events-none">
                                                <div class="ml-auto h-full bg-indigo-50 rounded-sm" style="width: {{ ($row->count / $maxEventCount) * 100 }}%;"></div>
                                            </div>
                                            <span class="relative z-10">{{ number_format($row->count) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-600 text-right">{{ number_format($row->avg_time, 2) }} ms</td>
                                    </tr>
                                    @if($hasMultipleListeners)
                                        <template x-if="expanded">
                                            <tr>
                                                <td colspan="3" class="p-0">
                                                    <table class="w-full">
                                                        @foreach($listeners as $listener)
                                                            <tr class="bg-gray-50 hover:bg-gray-100">
                                                                <td class="pl-10 pr-5 py-2 text-xs font-mono text-gray-500 truncate max-w-xs">{{ $listener->listener_name }}</td>
                                                                <td class="px-5 py-2 text-xs text-gray-500 text-right">{{ number_format($listener->count) }}</td>
                                                                <td class="px-5 py-2 text-xs text-gray-500 text-right">{{ number_format($listener->avg_time, 2) }} ms</td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </td>
                                            </tr>
                                        </template>
                                    @endif
                                </tbody>
                            @empty
                                <tbody>
                                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">No data for this period.</td></tr>
                                </tbody>
                            @endforelse
                        </table>
                    </div>

                    {{-- Slowest Events --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-sm font-semibold text-gray-700">Slowest Events</h2>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Event Name</th>
                                    <th class="px-5 py-3 text-right">Duration</th>
                                    <th class="px-5 py-3 text-right">When</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($stats['slowest_events'] as $slow)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('event-lens.index', ['event' => $slow->event_name]) }}" class="text-sm font-mono text-indigo-600 hover:underline truncate block max-w-xs">
                                                {{ $slow->event_name }}
                                            </a>
                                        </td>
                                        <td class="px-5 py-3 text-sm font-semibold text-right {{ $slow->execution_time_ms > $slowThreshold ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ number_format($slow->execution_time_ms, 2) }} ms
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-500 text-right">{{ $slow->happened_at }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">No data for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Heaviest Events (by Query Load) --}}
                @if($stats['heaviest_events']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-sm font-semibold text-gray-700">Heaviest Events by Query Load</h2>
                        </div>
                        @php $maxQueryCount = $stats['heaviest_events']->max('total_queries') ?: 1; @endphp
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Event Name</th>
                                    <th class="px-5 py-3 text-right">Fires</th>
                                    <th class="px-5 py-3 text-right">Avg Queries</th>
                                    <th class="px-5 py-3 text-right">Total Queries</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($stats['heaviest_events'] as $heavy)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('event-lens.index', ['event' => $heavy->event_name]) }}" class="text-sm font-mono text-indigo-600 hover:underline truncate block max-w-xs">
                                                {{ $heavy->event_name }}
                                            </a>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($heavy->count) }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600 text-right">{{ number_format($heavy->avg_queries, 1) }}</td>
                                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 text-right relative">
                                            <div class="absolute inset-0 flex items-center pointer-events-none">
                                                <div class="ml-auto h-full bg-blue-50 rounded-sm" style="width: {{ ($heavy->total_queries / $maxQueryCount) * 100 }}%;"></div>
                                            </div>
                                            <span class="relative z-10">{{ number_format($heavy->total_queries) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Errors Tab --}}
        <div x-show="activeTab === 'errors'" x-cloak>
            <div class="space-y-6">
                @if($stats['error_breakdown']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-red-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-red-200 bg-red-50">
                            <h2 class="text-sm font-semibold text-red-700">Top Errors</h2>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 text-left">Event</th>
                                    <th class="px-5 py-3 text-left">Exception</th>
                                    <th class="px-5 py-3 text-right">Count</th>
                                    <th class="px-5 py-3 text-right">Last Seen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($stats['error_breakdown'] as $err)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3 text-sm font-mono text-gray-900 truncate max-w-[10rem]">{{ $err->event_name }}</td>
                                        <td class="px-5 py-3 text-sm text-red-600 truncate max-w-xs">{{ $err->exception_summary }}</td>
                                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($err->count) }}</td>
                                        <td class="px-5 py-3 text-xs text-gray-500 text-right">{{ \Carbon\Carbon::parse($err->last_seen)->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No errors recorded in this period.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
