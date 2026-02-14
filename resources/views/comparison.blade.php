@extends('event-lens::layout')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Comparison</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('event-lens.comparison', ['preset' => 'hour']) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md {{ ($preset ?? '') === 'hour' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">
                Last hour vs previous
            </a>
            <a href="{{ route('event-lens.comparison', ['preset' => 'day']) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md {{ ($preset ?? 'day') === 'day' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">
                Today vs yesterday
            </a>
            <a href="{{ route('event-lens.comparison', ['preset' => 'week']) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-md {{ ($preset ?? '') === 'week' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' }}">
                This week vs last
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Throughput Change</p>
            <p class="text-2xl font-bold mt-1 {{ $comparison['throughput_delta_pct'] > 0 ? 'text-green-600' : ($comparison['throughput_delta_pct'] < 0 ? 'text-red-600' : 'text-gray-700') }}">
                {{ $comparison['throughput_delta_pct'] > 0 ? '+' : '' }}{{ $comparison['throughput_delta_pct'] }}%
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $comparison['period_a']['stats']['total_events'] }} → {{ $comparison['period_b']['stats']['total_events'] }} events</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg Time Change</p>
            <p class="text-2xl font-bold mt-1 {{ $comparison['avg_time_delta_pct'] < 0 ? 'text-green-600' : ($comparison['avg_time_delta_pct'] > 0 ? 'text-red-600' : 'text-gray-700') }}">
                {{ $comparison['avg_time_delta_pct'] > 0 ? '+' : '' }}{{ $comparison['avg_time_delta_pct'] }}%
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $comparison['period_a']['stats']['avg_execution_time'] }}ms → {{ $comparison['period_b']['stats']['avg_execution_time'] }}ms</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Error Rate Change</p>
            <p class="text-2xl font-bold mt-1 {{ $comparison['error_rate_delta'] < 0 ? 'text-green-600' : ($comparison['error_rate_delta'] > 0 ? 'text-red-600' : 'text-gray-700') }}">
                {{ $comparison['error_rate_delta'] > 0 ? '+' : '' }}{{ $comparison['error_rate_delta'] }}pp
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $comparison['period_a']['stats']['error_rate'] }}% → {{ $comparison['period_b']['stats']['error_rate'] }}%</p>
        </div>
    </div>

    {{-- Listener Comparison Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Listener Performance Comparison</h2>
        </div>
        @if($comparison['listeners']->isEmpty())
            <div class="p-5 text-center text-gray-400 text-sm">No listener data to compare.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Listener</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Period A Avg</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Period B Avg</th>
                        <th class="text-right py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Change</th>
                        <th class="text-center py-2 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($comparison['listeners'] as $listener)
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs text-gray-700">{{ class_basename($listener->listener_name) }}</td>
                            <td class="py-2 px-4 text-right text-gray-600">{{ $listener->period_a_avg }}ms ({{ $listener->period_a_count }}x)</td>
                            <td class="py-2 px-4 text-right text-gray-600">{{ $listener->period_b_avg }}ms ({{ $listener->period_b_count }}x)</td>
                            <td class="py-2 px-4 text-right font-semibold {{ $listener->avg_delta_pct > 10 ? 'text-red-600' : ($listener->avg_delta_pct < -10 ? 'text-green-600' : 'text-gray-500') }}">
                                {{ $listener->avg_delta_pct > 0 ? '+' : '' }}{{ $listener->avg_delta_pct }}%
                            </td>
                            <td class="py-2 px-4 text-center">
                                @if($listener->status === 'degraded')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Degraded</span>
                                @elseif($listener->status === 'improved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Improved</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Stable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- New / Disappeared Events --}}
    @if($comparison['new_events']->isNotEmpty() || $comparison['disappeared_events']->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($comparison['new_events']->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-green-700">New Events in Period B</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($comparison['new_events'] as $event)
                            <li class="px-5 py-2 text-xs font-mono text-gray-700">{{ $event }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($comparison['disappeared_events']->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-amber-700">Disappeared Events</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($comparison['disappeared_events'] as $event)
                            <li class="px-5 py-2 text-xs font-mono text-gray-700">{{ $event }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
