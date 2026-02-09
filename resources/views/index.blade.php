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

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <ul role="list" id="event-list" class="divide-y divide-gray-200" data-last-id="{{ $events->first()?->id ?? 0 }}">
            @forelse($events as $event)
                <li class="hover:bg-gray-50 transition" id="event-{{ $event->id }}">
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
                <li class="px-6 py-12 text-center" id="empty-state">
                    <p class="text-gray-500">No events recorded yet.</p>
                </li>
            @endforelse
        </ul>
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $events->links() }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('event-list');
            let lastId = list.dataset.lastId;
            const emptyState = document.getElementById('empty-state');

            setInterval(async () => {
                try {
                    const response = await fetch(`{{ route('event-lens.api.latest') }}?after_id=${lastId}`);
                    const data = await response.json();

                    if (data.events.length > 0) {
                        if(emptyState) emptyState.remove();

                        // Update lastId to the newest one (first in list)
                        lastId = Math.max(lastId, ...data.events.map(e => e.id));

                        data.events.reverse().forEach(event => { // Reverse to append in correct order (oldest of new batch first, but we prepend)
                             // Actually we want newest on top. So we iterate data (which is desc) and prepend.
                             // Wait, if data is [102, 101], we want 101 then 102 above it? 
                             // No, 102 is top. 
                             
                             const html = `
                                <li class="hover:bg-gray-50 transition bg-yellow-50 duration-1000" id="event-${event.id}">
                                    <a href="${event.url}" class="block px-6 py-5">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-4">
                                                <div class="bg-indigo-100 text-indigo-700 p-2 rounded-lg">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-indigo-600 truncate">${event.event_name}</p>
                                                    <p class="text-xs text-gray-500">${event.happened_at} &middot; ${event.correlation_id}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-semibold text-gray-900">${event.execution_time_ms} ms</p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                             `;
                             
                             list.insertAdjacentHTML('afterbegin', html);
                             
                             // Remove highlight after 2s
                             setTimeout(() => {
                                 document.getElementById(`event-${event.id}`).classList.remove('bg-yellow-50');
                             }, 2000);
                        });
                    }
                } catch (e) {
                    console.error("Polling failed", e);
                }
            }, 5000);
        });
    </script>
@endsection
