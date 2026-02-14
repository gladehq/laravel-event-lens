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
                $otlpEnabled = !empty(config('event-lens.otlp_endpoint'));
            @endphp
            <div class="flex items-start gap-4">
                @if($otlpEnabled)
                    <div x-data="{ confirmExport: false }" class="shrink-0">
                        <button @click="confirmExport = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Export Trace
                        </button>
                        <div x-show="confirmExport" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="confirmExport = false">
                            <div class="bg-white rounded-lg shadow-xl border border-gray-200 p-6 max-w-md mx-4">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Export this trace?</h3>
                                <p class="text-sm text-gray-600 mb-1">This will send {{ $events->count() }} spans to your configured OTLP endpoint.</p>
                                <p class="text-xs text-gray-400 mb-4">{{ config('event-lens.otlp_endpoint') }}</p>
                                <div class="flex justify-end gap-3">
                                    <button @click="confirmExport = false" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                                    <form method="POST" action="{{ route('event-lens.export', request()->route('correlationId')) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Export</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @if(session('export_success'))
                <div class="mt-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg">{{ session('export_success') }}</div>
            @endif
            @if(session('export_error'))
                <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg">{{ session('export_error') }}</div>
            @endif
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
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">HTTP Calls</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalHttpCalls }}</p>
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
