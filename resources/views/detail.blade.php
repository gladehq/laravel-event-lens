@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">&larr; Back to Trace</a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $event->listener_name }}</h1>
        <p class="text-sm text-gray-500 mt-1">listening to <span class="font-mono">{{ $event->event_name }}</span></p>
        <p class="text-xs font-mono text-gray-400 mt-1">{{ $event->event_id }}</p>

        @if(session('replay_success'))
            <div class="mt-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg">{{ session('replay_success') }}</div>
        @endif
        @if(session('replay_error'))
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg">{{ session('replay_error') }}</div>
        @endif

        @if($allowReplay && $event->listener_name === 'Event::dispatch' && class_exists($event->event_name))
            <div x-data="{ confirmReplay: false }" class="mt-3">
                <button @click="confirmReplay = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Replay Event
                </button>
                <div x-show="confirmReplay" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="confirmReplay = false">
                    <div class="bg-white rounded-lg shadow-xl border border-gray-200 p-6 max-w-md mx-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Replay this event?</h3>
                        <p class="text-sm text-gray-600 mb-1">This will re-dispatch <code class="text-xs bg-gray-100 px-1 rounded">{{ class_basename($event->event_name) }}</code> with its original payload.</p>
                        <p class="text-xs text-amber-600 mb-4">All registered listeners will fire again. Use with caution.</p>
                        <div class="flex justify-end gap-3">
                            <button @click="confirmReplay = false" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                            <form method="POST" action="{{ route('event-lens.replay', $event->event_id) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700">Confirm Replay</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                @if($event->is_storm)
                    <div class="px-5 py-3 flex justify-between">
                        <dt class="text-sm text-gray-500">Storm</dt>
                        <dd class="text-sm font-semibold text-red-600">
                            Part of storm ({{ $event->side_effects['storm_count'] ?? '?' }} events)
                        </dd>
                    </div>
                @endif
                @if($event->is_sla_breach)
                    @php $slaBreach = $event->side_effects['sla_breach'] ?? null; @endphp
                    <div class="px-5 py-3 flex justify-between">
                        <dt class="text-sm text-gray-500">SLA Budget</dt>
                        <dd class="text-sm font-semibold text-red-600">
                            @if($slaBreach)
                                {{ number_format($slaBreach['budget_ms'] ?? 0, 1) }}ms budget |
                                {{ number_format($slaBreach['actual_ms'] ?? $event->execution_time_ms, 1) }}ms actual |
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 border border-red-300 ml-1">BREACH</span>
                            @else
                                SLA breached ({{ number_format($event->execution_time_ms, 1) }}ms)
                            @endif
                        </dd>
                    </div>
                @endif
                @if($event->payload['__request_context'] ?? null)
                    @php $detailContext = $event->payload['__request_context']; @endphp
                    <div class="px-5 py-3 flex justify-between">
                        <dt class="text-sm text-gray-500">Trigger Context</dt>
                        <dd class="text-sm text-gray-900">
                            @if(($detailContext['type'] ?? '') === 'http')
                                {{ $detailContext['method'] ?? '' }} {{ $detailContext['path'] ?? '' }}
                                @if($detailContext['user_id'] ?? null)
                                    <span class="text-gray-500">(User #{{ $detailContext['user_id'] }})</span>
                                @endif
                            @elseif(($detailContext['type'] ?? '') === 'cli')
                                artisan {{ $detailContext['command'] ?? '' }}
                            @elseif(($detailContext['type'] ?? '') === 'queue')
                                Queue: {{ $detailContext['job'] ?? '' }}
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Side Effects --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Side Effects</h2>
            </div>
            <div class="p-5 space-y-4">
                @forelse($event->side_effects ?? [] as $key => $value)
                    @if(is_array($value) || is_object($value))
                        @continue
                    @endif
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

    {{-- Schema Drift --}}
    @if($event->has_drift && !empty($event->drift_details))
        <div x-data="{ showDrift: true }" class="bg-orange-50 border border-orange-200 rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-orange-200 flex items-center justify-between cursor-pointer" @click="showDrift = !showDrift">
                <h2 class="text-sm font-semibold text-orange-700">Schema Drift Detected</h2>
                <svg :class="showDrift ? 'rotate-180' : ''" class="w-4 h-4 text-orange-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div x-show="showDrift" x-cloak class="p-5">
                @if(!empty($event->drift_details['changes']))
                    <p class="text-xs text-orange-600 mb-3">The event schema has changed from the recorded baseline.</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-orange-200">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Change</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Field</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-orange-100">
                            @foreach($event->drift_details['changes'] as $change)
                                <tr>
                                    <td class="py-2 px-3 text-xs">
                                        @if(($change['type'] ?? '') === 'added')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Added</span>
                                        @elseif(($change['type'] ?? '') === 'removed')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Removed</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">{{ ucfirst($change['type'] ?? 'Changed') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 font-mono text-xs text-gray-700">{{ $change['field'] ?? '' }}</td>
                                    <td class="py-2 px-3 text-xs text-gray-600">
                                        @if(isset($change['from']) && isset($change['to']))
                                            <span class="text-red-600">{{ $change['from'] }}</span> &rarr; <span class="text-green-600">{{ $change['to'] }}</span>
                                        @elseif(isset($change['detail']))
                                            {{ $change['detail'] }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <pre class="text-xs font-mono text-orange-800 bg-orange-100 rounded-lg p-4 overflow-x-auto">{{ json_encode($event->drift_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </div>
        </div>
    @endif

    {{-- N+1 Detection --}}
    @if($event->is_nplus1)
        @php $nplus1Detail = $event->side_effects['nplus1_detail'] ?? null; @endphp
        <div class="bg-orange-50 border border-orange-200 rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-orange-200">
                <h2 class="text-sm font-semibold text-orange-700">N+1 Query Detected</h2>
            </div>
            <div class="p-5">
                @if($nplus1Detail)
                    <details class="text-sm">
                        <summary class="text-orange-700 font-medium cursor-pointer hover:text-orange-800">View N+1 detail</summary>
                        <pre class="mt-2 text-xs font-mono text-orange-800 bg-orange-100 rounded-lg p-4 overflow-x-auto">{{ e(is_array($nplus1Detail) ? json_encode($nplus1Detail, JSON_PRETTY_PRINT) : $nplus1Detail) }}</pre>
                    </details>
                @else
                    <p class="text-sm text-orange-700">This listener execution was flagged as an N+1 query pattern ({{ $event->side_effects['queries'] ?? '?' }} queries).</p>
                @endif
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

    {{-- Exception Context (Structured) --}}
    @if(!empty($event->side_effects['exception_context']))
        @php $exCtx = $event->side_effects['exception_context']; @endphp
        <div class="bg-red-50 border border-red-200 rounded-lg shadow-sm overflow-hidden mb-6" x-data="{ showCtxTrace: false }">
            <div class="px-5 py-4 border-b border-red-200">
                <h2 class="text-sm font-semibold text-red-700">Exception Context</h2>
            </div>
            <div class="p-5 space-y-2">
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500">Class:</span>
                    <span class="text-xs font-mono font-semibold text-red-800">{{ $exCtx['class'] ?? 'Unknown' }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500">File:</span>
                    <span class="text-xs font-mono text-gray-700">{{ $exCtx['file'] ?? '' }}:{{ $exCtx['line'] ?? '' }}</span>
                </div>
                @if(!empty($exCtx['trace']))
                    <button @click="showCtxTrace = !showCtxTrace" class="mt-2 text-xs text-red-600 hover:text-red-800 underline" x-text="showCtxTrace ? 'Hide trace frames' : 'Show trace frames ({{ count($exCtx['trace']) }})'"></button>
                    <div x-show="showCtxTrace" x-cloak class="mt-2">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-red-200">
                                    <th class="text-left py-1 px-2 text-gray-500">#</th>
                                    <th class="text-left py-1 px-2 text-gray-500">File</th>
                                    <th class="text-left py-1 px-2 text-gray-500">Class::Method</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100">
                                @foreach($exCtx['trace'] as $i => $frame)
                                    <tr>
                                        <td class="py-1 px-2 text-gray-400">{{ $i }}</td>
                                        <td class="py-1 px-2 font-mono text-gray-600">{{ basename($frame['file'] ?? '') }}:{{ $frame['line'] ?? '' }}</td>
                                        <td class="py-1 px-2 font-mono text-gray-700">{{ $frame['class'] ?? '' }}{{ $frame['class'] ? '::' : '' }}{{ $frame['function'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
