@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">&larr; Back to Trace</a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $event->listener_name }}</h1>
        <p class="text-sm text-gray-500 mt-1">listening to <span class="font-mono">{{ $event->event_name }}</span></p>
        <p class="text-xs font-mono text-gray-400 mt-1">{{ $event->event_id }}</p>

        {{-- Prev/Next Sibling Navigation --}}
        @if($prevEvent || $nextEvent)
            <div class="flex items-center justify-between mt-4 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex-1 min-w-0">
                    @if($prevEvent)
                        <a href="{{ route('event-lens.detail', $prevEvent->event_id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400">Previous</p>
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $prevEvent->listener_name }}</p>
                            </div>
                        </a>
                    @else
                        <div class="px-4 py-3"></div>
                    @endif
                </div>
                <div class="border-l border-gray-200 h-12 shrink-0"></div>
                <div class="flex-1 min-w-0">
                    @if($nextEvent)
                        <a href="{{ route('event-lens.detail', $nextEvent->event_id) }}" class="flex items-center justify-end gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <div class="min-w-0 text-right">
                                <p class="text-xs text-gray-400">Next</p>
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $nextEvent->listener_name }}</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <div class="px-4 py-3"></div>
                    @endif
                </div>
            </div>
        @endif
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
                    <dd class="text-sm font-mono text-gray-900 flex items-center gap-2">
                        <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="text-indigo-600 hover:underline">{{ $event->correlation_id }}</a>
                        <button x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText('{{ $event->correlation_id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                            title="Copy Correlation ID">
                            <template x-if="!copied">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </template>
                            <template x-if="copied">
                                <span class="text-xs font-medium text-green-600">Copied!</span>
                            </template>
                        </button>
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

    {{-- Tags --}}
    @if(!empty($event->tags))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Tags</h2>
            </div>
            <div class="p-5">
                <dl class="divide-y divide-gray-100">
                    @foreach($event->tags as $key => $value)
                        <div class="px-2 py-3 flex items-center justify-between">
                            <dt class="text-sm font-medium text-gray-500">{{ e($key) }}</dt>
                            <dd class="text-sm font-mono text-gray-900">{{ e($value ?? 'null') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif

    {{-- Exception (Expandable) --}}
    @if($event->exception)
        @php
            $exceptionParts = explode("\n", $event->exception, 2);
            $exceptionSummary = $exceptionParts[0];
            $exceptionTrace = $exceptionParts[1] ?? null;
        @endphp
        <div class="bg-red-50 border border-red-200 rounded-lg shadow-sm overflow-hidden mb-6" x-data="{ showTrace: false }">
            <div class="px-5 py-4 border-b border-red-200">
                <h2 class="text-sm font-semibold text-red-700">Exception</h2>
            </div>
            <div class="p-5">
                <p class="text-sm font-semibold text-red-800">{{ e($exceptionSummary) }}</p>
                @if($exceptionTrace)
                    <button @click="showTrace = !showTrace" class="mt-2 text-xs text-red-600 hover:text-red-800 underline" x-text="showTrace ? 'Hide stack trace' : 'Show stack trace'"></button>
                    <pre x-show="showTrace" x-cloak class="text-xs font-mono text-red-700 bg-red-100 rounded-lg p-4 overflow-x-auto mt-2">{{ e($exceptionTrace) }}</pre>
                @endif
            </div>
        </div>
    @endif

    {{-- Payload --}}
    <div x-data="{ showPayload: true, copiedPayload: false }" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700 cursor-pointer" @click="showPayload = !showPayload">Payload</h2>
            <div class="flex items-center gap-2">
                @if($event->payload)
                    <button @click.stop="navigator.clipboard.writeText({{ e(json_encode(json_encode(collect($event->payload)->except('__context')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), JSON_UNESCAPED_SLASHES)) }}); copiedPayload = true; setTimeout(() => copiedPayload = false, 1500)"
                        class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                        title="Copy payload JSON">
                        <template x-if="!copiedPayload">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </template>
                        <template x-if="copiedPayload">
                            <span class="text-xs font-medium text-green-600">Copied!</span>
                        </template>
                    </button>
                @endif
                <svg @click="showPayload = !showPayload" :class="showPayload ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
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

    {{-- Model Changes (Diff View) --}}
    @if(!empty($event->model_changes))
        @php
            $hasDiffStructure = isset($event->model_changes['before']) && isset($event->model_changes['after'])
                && is_array($event->model_changes['before']) && is_array($event->model_changes['after']);
        @endphp
        <div x-data="{ showChanges: true, showRawJson: false }" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between cursor-pointer" @click="showChanges = !showChanges">
                <h2 class="text-sm font-semibold text-gray-700">Model Changes</h2>
                <svg :class="showChanges ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div x-show="showChanges" x-cloak class="p-5">
                @if($hasDiffStructure)
                    <div x-show="!showRawJson">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Field</th>
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Old Value</th>
                                    <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">New Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $allFields = collect(array_keys($event->model_changes['before']))
                                        ->merge(array_keys($event->model_changes['after']))
                                        ->unique();
                                @endphp
                                @foreach($allFields as $field)
                                    @php
                                        $oldVal = $event->model_changes['before'][$field] ?? null;
                                        $newVal = $event->model_changes['after'][$field] ?? null;
                                        $changed = $oldVal !== $newVal;
                                    @endphp
                                    @if($changed)
                                        <tr>
                                            <td class="py-2 px-3 font-mono text-xs text-gray-700">{{ $field }}</td>
                                            <td class="py-2 px-3 font-mono text-xs {{ $changed ? 'text-red-600 bg-red-50' : 'text-gray-600' }}">{{ is_null($oldVal) ? 'null' : (is_bool($oldVal) ? ($oldVal ? 'true' : 'false') : (is_array($oldVal) ? json_encode($oldVal) : $oldVal)) }}</td>
                                            <td class="py-2 px-3 font-mono text-xs {{ $changed ? 'text-green-600 bg-green-50' : 'text-gray-600' }}">{{ is_null($newVal) ? 'null' : (is_bool($newVal) ? ($newVal ? 'true' : 'false') : (is_array($newVal) ? json_encode($newVal) : $newVal)) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div x-show="showRawJson" x-cloak>
                        <pre class="text-xs font-mono text-amber-800 bg-amber-50 rounded-lg p-4 overflow-x-auto max-h-96">{{ json_encode($event->model_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    <button @click="showRawJson = !showRawJson" class="mt-3 text-xs text-indigo-600 hover:text-indigo-800 underline" x-text="showRawJson ? 'Show diff table' : 'Show raw JSON'"></button>
                @else
                    <pre class="text-xs font-mono text-amber-800 bg-amber-50 rounded-lg p-4 overflow-x-auto max-h-96">{{ json_encode($event->model_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </div>
        </div>
    @endif
@endsection
