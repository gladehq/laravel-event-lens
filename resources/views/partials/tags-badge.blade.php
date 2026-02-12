@if(!empty($tags))
    <span x-data="{ showTags: false }" class="relative inline-flex">
        <button type="button" @click.prevent.stop="showTags = true"
            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 hover:bg-purple-200 cursor-pointer">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
            {{ count($tags) }}
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
                                <p class="text-xs text-gray-500">{{ count($tags) }} {{ Str::plural('tag', count($tags)) }} attached</p>
                            </div>
                        </div>
                        <button type="button" @click.prevent.stop="showTags = false" class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-purple-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="p-4 space-y-2">
                    @foreach($tags as $key => $value)
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                            <span class="text-xs font-medium text-gray-600">{{ e($key) }}</span>
                            <span class="text-xs font-mono font-semibold text-gray-900 bg-white px-2 py-0.5 rounded border border-gray-200">{{ e($value ?? 'null') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                    <p class="text-[11px] text-gray-400">Tags are defined via the <code class="bg-gray-200 px-1 rounded text-gray-500">Taggable</code> interface on the event class.</p>
                </div>
            </div>
        </div>
    </span>
@endif
