@extends('event-lens::layout')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Event Stream</h1>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            Live Polling
        </div>
    </div>

    {{-- Search / Filter --}}
    <form method="GET" action="{{ route('event-lens.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Event Name</label>
                    <input type="text" name="event" value="{{ request('event') }}" placeholder="App\Events\..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Listener</label>
                    <input type="text" name="listener" value="{{ request('listener') }}" placeholder="App\Listeners\..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Correlation ID</label>
                    <input type="text" name="correlation" value="{{ request('correlation') }}" placeholder="uuid..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div x-data="{ showHelp: false }">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Payload Contains
                        <button type="button" @click.prevent="showHelp = true" class="inline-flex items-center justify-center w-4 h-4 ml-0.5 rounded-full bg-gray-200 text-gray-500 hover:bg-gray-300 hover:text-gray-700 text-[10px] font-bold leading-none align-middle">?</button>
                    </label>
                    <input type="text" name="payload" value="{{ request('payload') }}" placeholder="search payload..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <div x-show="showHelp" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/30" @click="showHelp = false"></div>
                        <div class="relative bg-white rounded-lg shadow-lg border border-gray-200 p-5 max-w-sm w-full mx-4" @click.stop>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-900">Payload Contains</h3>
                                <button type="button" @click="showHelp = false" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="text-xs text-gray-600 space-y-2">
                                <p>Searches for the term anywhere inside the stored JSON payload using a substring match.</p>
                                <p class="font-medium text-gray-700">Examples:</p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded">Alice</code> matches <code class="bg-gray-100 px-1 rounded">{"customer": "Alice"}</code></li>
                                    <li><code class="bg-gray-100 px-1 rounded">order_id</code> matches any payload containing that key</li>
                                    <li><code class="bg-gray-100 px-1 rounded">42</code> matches any payload containing that number</li>
                                </ul>
                                <p class="text-gray-400">Note: this is a text search, not a key-specific query. A search for "42" will also match "421" or "X42Y".</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div x-data="{ showHelp: false }">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tag Contains
                        <button type="button" @click.prevent="showHelp = true" class="inline-flex items-center justify-center w-4 h-4 ml-0.5 rounded-full bg-gray-200 text-gray-500 hover:bg-gray-300 hover:text-gray-700 text-[10px] font-bold leading-none align-middle">?</button>
                    </label>
                    <input type="text" name="tag" value="{{ request('tag') }}" placeholder="search tags..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <div x-show="showHelp" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/30" @click="showHelp = false"></div>
                        <div class="relative bg-white rounded-lg shadow-lg border border-gray-200 p-5 max-w-sm w-full mx-4" @click.stop>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-900">Tag Contains</h3>
                                <button type="button" @click="showHelp = false" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="text-xs text-gray-600 space-y-2">
                                <p>Searches for the term anywhere inside the stored tags JSON using a substring match. Tags are key-value pairs set by events implementing the <code class="bg-gray-100 px-1 rounded">Taggable</code> interface.</p>
                                <p class="font-medium text-gray-700">Examples:</p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li><code class="bg-gray-100 px-1 rounded">production</code> matches <code class="bg-gray-100 px-1 rounded">{"env": "production"}</code></li>
                                    <li><code class="bg-gray-100 px-1 rounded">priority</code> matches any event tagged with that key</li>
                                    <li><code class="bg-gray-100 px-1 rounded">high</code> matches any tag value containing "high"</li>
                                </ul>
                                <p class="text-gray-400">Note: this is a text search across both keys and values, not a key-specific query.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Start Date</label>
                    <input type="datetime-local" name="start_date" value="{{ request('start_date') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">End Date</label>
                    <input type="datetime-local" name="end_date" value="{{ request('end_date') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
            </div>
        </div>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg flex items-center gap-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="slow" value="1" {{ request('slow') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Slow events only (&gt;{{ $slowThreshold }}ms)</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="errors" value="1" {{ request('errors') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-700">Errors only</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="storm" value="1" {{ request('storm') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-700">Storm events only</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="sla" value="1" {{ request('sla') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-700">SLA breaches</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="drift" value="1" {{ request('drift') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                <span class="text-sm text-gray-700">Schema drift</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="nplus1" value="1" {{ request('nplus1') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                <span class="text-sm text-gray-700">N+1 issues</span>
            </label>
            <div class="flex-1"></div>
            <a href="{{ route('event-lens.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                Filter
            </button>
        </div>
    </form>

    {{-- Event List with Alpine.js polling (XSS-safe via x-text) --}}
    <div x-data="eventStream()" x-init="startPolling()" class="bg-white shadow overflow-hidden sm:rounded-lg">
        <ul role="list" class="divide-y divide-gray-200">
            {{-- New events from polling (prepended) --}}
            <template x-for="event in newEvents" :key="event.id">
                <li class="hover:bg-gray-50 transition bg-yellow-50">
                    <a :href="event.url" class="block px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="bg-indigo-100 text-indigo-700 p-2 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-indigo-600 truncate" x-text="event.event_name"></p>
                                        <template x-if="event.has_error">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">ERR</span>
                                        </template>
                                        <template x-if="event.side_effects && event.side_effects.queries > 0">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" x-text="event.side_effects.queries + 'q'"></span>
                                        </template>
                                        <template x-if="event.side_effects && event.side_effects.mails > 0">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" x-text="event.side_effects.mails + 'm'"></span>
                                        </template>
                                        <template x-if="event.side_effects && event.side_effects.http_calls > 0">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800" x-text="event.side_effects.http_calls + 'h'"></span>
                                        </template>
                                        <template x-if="event.tags && Object.keys(event.tags).length > 0">
                                            <span x-data="{ showTags: false }" class="relative inline-flex">
                                                <button type="button" @click.prevent.stop="showTags = true"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 hover:bg-purple-200 cursor-pointer">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                                    <span x-text="Object.keys(event.tags).length"></span>
                                                </button>
                                                <div x-show="showTags" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                                                    <div class="absolute inset-0 bg-black/30" @click.prevent.stop="showTags = false"></div>
                                                    <div class="relative bg-white rounded-xl shadow-xl border border-gray-200 max-w-sm w-full mx-4 overflow-hidden" @click.prevent.stop>
                                                        <div class="px-5 py-4 bg-purple-50 border-b border-purple-100">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center gap-2">
                                                                    <div class="p-1.5 bg-purple-100 rounded-lg">
                                                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                                                    </div>
                                                                    <div>
                                                                        <h3 class="text-sm font-semibold text-gray-900">Event Tags</h3>
                                                                        <p class="text-xs text-gray-500"><span x-text="Object.keys(event.tags).length"></span> <span x-text="Object.keys(event.tags).length === 1 ? 'tag' : 'tags'"></span> attached</p>
                                                                    </div>
                                                                </div>
                                                                <button type="button" @click.prevent.stop="showTags = false" class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-purple-100">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="p-4 space-y-2">
                                                            <template x-for="(val, key) in event.tags" :key="key">
                                                                <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                                                                    <span class="text-xs font-medium text-gray-600" x-text="key"></span>
                                                                    <span class="text-xs font-mono font-semibold text-gray-900 bg-white px-2 py-0.5 rounded border border-gray-200" x-text="val ?? 'null'"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                                                            <p class="text-[11px] text-gray-400">Tags are defined via the <code class="bg-gray-200 px-1 rounded text-gray-500">Taggable</code> interface on the event class.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </span>
                                        </template>
                                        <template x-if="event.is_storm">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-600 text-white">STORM</span>
                                        </template>
                                        <template x-if="event.is_sla_breach">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 border border-red-300">SLA</span>
                                        </template>
                                        <template x-if="event.has_drift">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-700 border border-orange-300">DRIFT</span>
                                        </template>
                                        <template x-if="event.is_nplus1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-700 border border-orange-300">N+1</span>
                                        </template>
                                        <template x-if="event.request_context">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                                <template x-if="event.request_context.type === 'http'"><span x-text="event.request_context.method + ' ' + event.request_context.path"></span></template>
                                                <template x-if="event.request_context.type === 'cli'"><span x-text="'artisan ' + (event.request_context.command || '')"></span></template>
                                                <template x-if="event.request_context.type === 'queue'"><span x-text="'Queue: ' + (event.request_context.job || '')"></span></template>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-xs text-gray-400 truncate mt-0.5" x-text="event.listener_name"></p>
                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                        <span x-text="event.happened_at_human"></span> &middot; <span x-text="event.correlation_id"></span>
                                        <span x-data="{ copied: false }" class="inline-flex">
                                            <button type="button"
                                                @click.prevent.stop="navigator.clipboard.writeText(event.correlation_id); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="p-0.5 rounded text-gray-400 hover:text-gray-600" title="Copy Correlation ID">
                                                <template x-if="!copied">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                </template>
                                                <template x-if="copied">
                                                    <span class="text-[10px] font-medium text-green-600">&#10003;</span>
                                                </template>
                                            </button>
                                        </span>
                                    </p>
                                    <template x-if="event.payload_summary && Object.keys(event.payload_summary).length > 0">
                                        <p class="text-xs text-gray-400 truncate">
                                            <template x-for="(val, key, idx) in event.payload_summary" :key="key">
                                                <span>
                                                    <span x-show="idx > 0"> &middot; </span>
                                                    <span x-text="key + ': ' + val"></span>
                                                </span>
                                            </template>
                                        </p>
                                    </template>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900"><span x-text="event.execution_time_ms"></span> ms</p>
                            </div>
                        </div>
                    </a>
                </li>
            </template>

            {{-- Server-rendered events --}}
            @forelse($events as $event)
                <li class="hover:bg-gray-50 transition">
                    <a href="{{ route('event-lens.show', $event->correlation_id) }}" class="block px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="bg-indigo-100 text-indigo-700 p-2 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-indigo-600 truncate">{{ $event->event_name }}</p>
                                        @if($event->exception)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">ERR</span>
                                        @endif
                                        @if(($event->side_effects['queries'] ?? 0) > 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $event->side_effects['queries'] }}q</span>
                                        @endif
                                        @if(($event->side_effects['mails'] ?? 0) > 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ $event->side_effects['mails'] }}m</span>
                                        @endif
                                        @if(($event->side_effects['http_calls'] ?? 0) > 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">{{ $event->side_effects['http_calls'] }}h</span>
                                        @endif
                                        @include('event-lens::partials.tags-badge', ['tags' => $event->tags])
                                        @include('event-lens::partials.storm-badge', ['isStorm' => $event->is_storm])
                                        @include('event-lens::partials.sla-badge', ['isBreach' => $event->is_sla_breach])
                                        @include('event-lens::partials.drift-badge', ['hasDrift' => $event->has_drift])
                                        @include('event-lens::partials.nplus1-badge', ['isNplus1' => $event->is_nplus1])
                                        @include('event-lens::partials.context-badge', ['context' => $event->payload['__request_context'] ?? null])
                                    </div>
                                    <p class="text-xs text-gray-400 truncate mt-0.5">{{ $event->listener_name }}</p>
                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                        {{ $event->happened_at->diffForHumans() }} &middot; {{ $event->correlation_id }}
                                        <span x-data="{ copied: false }" class="inline-flex">
                                            <button type="button"
                                                @click.prevent.stop="navigator.clipboard.writeText('{{ $event->correlation_id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="p-0.5 rounded text-gray-400 hover:text-gray-600" title="Copy Correlation ID">
                                                <template x-if="!copied">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                </template>
                                                <template x-if="copied">
                                                    <span class="text-[10px] font-medium text-green-600">&#10003;</span>
                                                </template>
                                            </button>
                                        </span>
                                    </p>
                                    @include('event-lens::partials.payload-summary', ['payload' => $event->payload_summary])
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($event->execution_time_ms, 2) }} ms</p>
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-6 py-12 text-center" x-show="newEvents.length === 0">
                    <p class="text-gray-500">No events recorded yet.</p>
                </li>
            @endforelse
        </ul>
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $events->withQueryString()->links() }}
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function eventStream() {
        return {
            lastId: {{ $events->first()?->id ?? 0 }},
            newEvents: [],
            async startPolling() {
                setInterval(async () => {
                    try {
                        const res = await fetch(`{{ route('event-lens.api.latest') }}?after_id=${this.lastId}`);
                        const data = await res.json();
                        if (data.data && data.data.length > 0) {
                            this.lastId = Math.max(this.lastId, ...data.data.map(e => e.id));
                            // Prepend newest first
                            this.newEvents = [...data.data.reverse(), ...this.newEvents];
                        }
                    } catch (e) {
                        console.error('Polling failed', e);
                    }
                }, 5000);
            }
        }
    }
</script>
@endpush
