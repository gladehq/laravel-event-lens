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

    {{-- Daily Timeline (CSS bar chart with error overlay) --}}
    @if($stats['timeline']->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-8">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Event Volume</h2>
            @php $maxCount = $stats['timeline']->max('count') ?: 1; @endphp
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
                        <div class="absolute -top-6 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                            {{ $day->date }}: {{ $day->count }} events{{ $day->error_count > 0 ? ", {$day->error_count} errors" : '' }}
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

    {{-- Two-column grid: Top Events & Slowest Events --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Top Events by Frequency (with expandable listener breakdown) --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Top Events by Frequency</h2>
            </div>
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
                            <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($row->count) }}</td>
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
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Heaviest Events by Query Load</h2>
            </div>
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
                            <td class="px-5 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($heavy->total_queries) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Error Breakdown --}}
    @if($stats['error_breakdown']->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-red-200 overflow-hidden mb-6">
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
    @endif
@endsection
