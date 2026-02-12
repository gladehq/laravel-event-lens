@if(!empty($tags))
    <span x-data="{ showTags: false }" class="relative inline-flex">
        <button type="button" @click.prevent.stop="showTags = true"
            class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 hover:bg-purple-200 cursor-pointer">
            Tags
        </button>
        <div x-show="showTags" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" @click="showTags = false"></div>
            <div class="relative bg-white rounded-lg shadow-lg border border-gray-200 p-5 max-w-sm w-full mx-4" @click.stop>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-900">Tags</h3>
                    <button type="button" @click="showTags = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <dl class="space-y-2">
                    @foreach($tags as $key => $value)
                        <div class="flex items-center justify-between">
                            <dt class="text-xs font-medium text-gray-500">{{ e($key) }}</dt>
                            <dd class="text-xs font-mono text-gray-900">{{ e($value ?? 'null') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </span>
@endif
