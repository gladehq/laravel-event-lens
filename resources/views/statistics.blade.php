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
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Apply
                </button>
            </div>
        </div>
    </form>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Events</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_events']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Avg Execution Time</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['avg_execution_time'] }} <span class="text-base font-normal text-gray-500">ms</span></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Slowest Event</p>
            @if($stats['slowest_events']->isNotEmpty())
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['slowest_events']->first()->execution_time_ms, 2) }} <span class="text-base font-normal text-gray-500">ms</span></p>
                <p class="text-xs text-gray-500 truncate mt-1">{{ $stats['slowest_events']->first()->event_name }}</p>
            @else
                <p class="text-3xl font-bold text-gray-400 mt-1">&mdash;</p>
            @endif
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-red-200 p-5">
            <p class="text-xs font-semibold text-red-600 uppercase tracking-wider">Errors</p>
            <p class="text-3xl font-bold text-red-600 mt-1">{{ number_format($stats['error_count']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $stats['error_rate'] }}% error rate</p>
        </div>
    </div>

    {{-- Daily Timeline (CSS bar chart) --}}
    @if($stats['timeline']->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-8">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Event Volume</h2>
            @php $maxCount = $stats['timeline']->max('count') ?: 1; @endphp
            <div class="flex items-end gap-1" style="height: 120px;">
                @foreach($stats['timeline'] as $day)
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        <div class="w-full bg-indigo-500 rounded-t transition-all hover:bg-indigo-600"
                             style="height: {{ max(4, ($day->count / $maxCount) * 100) }}%;">
                        </div>
                        <div class="absolute -top-6 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap">
                            {{ $day->date }}: {{ $day->count }} events
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Events by Type --}}
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
                <tbody class="divide-y divide-gray-100">
                    @forelse($stats['events_by_type'] as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-sm font-mono text-indigo-600 truncate max-w-xs">{{ $row->event_name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-900 text-right">{{ number_format($row->count) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 text-right">{{ number_format($row->avg_time, 2) }} ms</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 text-sm">No data for this period.</td></tr>
                    @endforelse
                </tbody>
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
                                <a href="{{ route('event-lens.show', $slow->correlation_id) }}" class="text-sm font-mono text-indigo-600 hover:underline truncate block max-w-xs">
                                    {{ $slow->event_name }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold text-right {{ $slow->execution_time_ms > 100 ? 'text-red-600' : 'text-gray-900' }}">
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
@endsection
