@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">&larr; Back to Trace</a>
        <h1 class="text-2xl font-bold text-gray-900">Event Detail</h1>
        <p class="text-sm font-mono text-gray-500 mt-1">{{ $event->event_id }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Metadata --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Metadata</h2>
            </div>
            <dl class="divide-y divide-gray-100">
                <div class="px-5 py-3 flex justify-between">
                    <dt class="text-sm text-gray-500">Event Name</dt>
                    <dd class="text-sm font-mono text-indigo-600">{{ $event->event_name }}</dd>
                </div>
                <div class="px-5 py-3 flex justify-between">
                    <dt class="text-sm text-gray-500">Listener</dt>
                    <dd class="text-sm font-mono text-gray-900">{{ $event->listener_name }}</dd>
                </div>
                <div class="px-5 py-3 flex justify-between">
                    <dt class="text-sm text-gray-500">Correlation ID</dt>
                    <dd class="text-sm font-mono text-gray-900">
                        <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="text-indigo-600 hover:underline">{{ $event->correlation_id }}</a>
                    </dd>
                </div>
                @if($event->parent_event_id)
                    <div class="px-5 py-3 flex justify-between">
                        <dt class="text-sm text-gray-500">Parent Event ID</dt>
                        <dd class="text-sm font-mono text-gray-900">
                            <a href="{{ route('event-lens.detail', $event->parent_event_id) }}" class="text-indigo-600 hover:underline">{{ $event->parent_event_id }}</a>
                        </dd>
                    </div>
                @endif
                <div class="px-5 py-3 flex justify-between">
                    <dt class="text-sm text-gray-500">Execution Time</dt>
                    <dd class="text-sm font-semibold {{ $event->execution_time_ms > $slowThreshold ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($event->execution_time_ms, 4) }} ms
                    </dd>
                </div>
                <div class="px-5 py-3 flex justify-between">
                    <dt class="text-sm text-gray-500">Happened At</dt>
                    <dd class="text-sm text-gray-900">{{ $event->happened_at->format('Y-m-d H:i:s.u') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Side Effects --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Side Effects</h2>
            </div>
            <div class="p-5 space-y-4">
                @forelse($event->side_effects ?? [] as $key => $value)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $value > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ $value }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No side effects recorded.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Exception --}}
    @if($event->exception)
        <div class="bg-red-50 border border-red-200 rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-red-200">
                <h2 class="text-sm font-semibold text-red-700">Exception</h2>
            </div>
            <div class="p-5">
                <pre class="text-xs font-mono text-red-800 bg-red-100 rounded-lg p-4 overflow-x-auto">{{ e($event->exception) }}</pre>
            </div>
        </div>
    @endif

    {{-- Payload --}}
    <div x-data="{ showPayload: true }" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showPayload = !showPayload">
            <h2 class="text-sm font-semibold text-gray-700">Payload</h2>
            <svg :class="showPayload ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div x-show="showPayload" x-cloak class="p-5">
            @if($event->payload)
                <dl class="divide-y divide-gray-100">
                    @foreach($event->payload as $key => $value)
                        @if($key === '__context')
                            @continue
                        @endif
                        <div class="px-2 py-3 flex flex-col gap-1">
                            <dt class="text-xs font-semibold text-gray-500">{{ $key }}</dt>
                            @if(is_array($value))
                                <dd x-data="{ expanded: false }">
                                    <button @click="expanded = !expanded" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        <span x-text="expanded ? 'Collapse' : 'Expand ({{ array_is_list($value) ? count($value) . ' items' : count($value) . ' keys' }})'"></span>
                                    </button>
                                    <pre x-show="expanded" x-cloak class="mt-2 text-xs font-mono text-gray-800 bg-gray-50 rounded-lg p-3 overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </dd>
                            @else
                                <dd class="text-sm font-mono text-gray-900">{{ $value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value) }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
                @if(isset($event->payload['__context']))
                    <p class="mt-3 text-xs text-gray-400 border-t border-gray-100 pt-3">
                        Triggered from: {{ is_array($event->payload['__context']) ? implode(':', $event->payload['__context']) : $event->payload['__context'] }}
                    </p>
                @endif
            @else
                <p class="text-sm text-gray-400">No payload data.</p>
            @endif
        </div>
    </div>

    {{-- Model Changes --}}
    @if(!empty($event->model_changes))
        <div x-data="{ showChanges: true }" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showChanges = !showChanges">
                <h2 class="text-sm font-semibold text-gray-700">Model Changes</h2>
                <svg :class="showChanges ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div x-show="showChanges" x-cloak class="p-5">
                <pre class="text-xs font-mono text-amber-800 bg-amber-50 rounded-lg p-4 overflow-x-auto max-h-96">{{ json_encode($event->model_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif
@endsection
