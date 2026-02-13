@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('event-lens.index') }}" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">&larr; Back to Stream</a>
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Event Trace</h1>
                <p class="text-sm text-gray-500 font-mono mt-1">{{ request()->route('correlationId') }}</p>
            </div>
            @php
                $rootEvent = $events->first(fn ($e) => $e->parent_event_id === null) ?? $events->first();
                $requestContext = $rootEvent?->payload['__request_context'] ?? null;
            @endphp
            <div class="flex gap-6">
                @if($totalErrors > 0)
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Errors</p>
                        <p class="text-xl font-bold text-red-600">{{ $totalErrors }}</p>
                    </div>
                @endif
                @if($totalSlow > 0)
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Slow (&gt;{{ $slowThreshold }}ms)</p>
                        <p class="text-xl font-bold text-amber-600">{{ $totalSlow }}</p>
                    </div>
                @endif
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Total Duration</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($totalDuration, 2) }} ms</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">DB Queries</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalQueries }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Mails Sent</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalMails }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($requestContext)
        <div class="mb-4 bg-purple-50 border border-purple-200 rounded-lg px-5 py-3 flex items-center gap-2">
            <span class="text-sm font-medium text-purple-700">
                @if(($requestContext['type'] ?? '') === 'http')
                    {{ $requestContext['method'] ?? '' }} {{ $requestContext['path'] ?? '' }}
                    @if($requestContext['user_id'] ?? null)
                        <span class="text-purple-500">(User #{{ $requestContext['user_id'] }})</span>
                    @endif
                @elseif(($requestContext['type'] ?? '') === 'cli')
                    artisan {{ $requestContext['command'] ?? '' }}
                @elseif(($requestContext['type'] ?? '') === 'queue')
                    Queue: {{ $requestContext['job'] ?? '' }}
                @endif
            </span>
        </div>
    @endif

    <div x-data x-init="Alpine.store('traceView', { compact: false })" class="bg-white shadow sm:rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="flex text-xs font-semibold text-gray-500 uppercase tracking-wider gap-8">
                    <div class="w-auto">Event / Listener</div>
                </div>
                @if($totalErrors > 0 && $firstErrorEventId)
                    <a href="#error-{{ $firstErrorEventId }}" class="text-sm text-red-600 hover:text-red-800">&darr; Jump to error</a>
                @endif
            </div>
            <div class="flex items-center gap-6">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Side Effects</div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Duration</div>
                <button @click="$store.traceView.compact = !$store.traceView.compact"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium border border-gray-300 text-gray-600 hover:bg-gray-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <span x-text="$store.traceView?.compact ? 'Detailed view' : 'Compact view'"></span>
                </button>
            </div>
        </div>

        <div class="divide-y divide-gray-200">
            @foreach($tree as $node)
                @include('event-lens::partials.node', ['node' => $node, 'depth' => 0, 'totalDuration' => $totalDuration, 'slowThreshold' => $slowThreshold])
            @endforeach
        </div>
    </div>
@endsection
