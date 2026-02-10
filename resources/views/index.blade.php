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
    <form method="GET" action="{{ route('event-lens.index') }}" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Event Name</label>
                <input type="text" name="event" value="{{ request('event') }}" placeholder="App\Events\..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Correlation ID</label>
                <input type="text" name="correlation" value="{{ request('correlation') }}" placeholder="uuid..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
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
        <div class="mt-3 flex items-center gap-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="slow" value="1" {{ request('slow') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Slow events only (&gt;100ms)</span>
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
                                    <p class="text-sm font-medium text-indigo-600 truncate" x-text="event.event_name"></p>
                                    <p class="text-xs text-gray-500">
                                        <span x-text="event.happened_at"></span> &middot; <span x-text="event.correlation_id"></span>
                                    </p>
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
                                    <p class="text-sm font-medium text-indigo-600 truncate">{{ $event->event_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $event->happened_at->diffForHumans() }} &middot; {{ $event->correlation_id }}</p>
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
