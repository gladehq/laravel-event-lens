@php $hasChildren = $node->children && $node->children->count(); @endphp

<div x-data="{ open: true }" class="hover:bg-gray-50 transition relative group">
    <div class="px-6 py-4 flex items-center justify-between">
        <div class="w-1/2 flex items-center gap-3" style="padding-left: {{ $depth * 2 }}rem">
            @if($hasChildren)
                <button @click="open = !open" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                    <svg :class="open ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @elseif($depth > 0)
                <div class="w-4 h-4 flex items-center justify-center text-gray-300">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="2"/></svg>
                </div>
            @endif

            <div class="overflow-hidden">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                        {{ $node->listener_name }}
                    </span>
                    @if($depth == 0)
                        <span class="text-xs text-gray-400">listening to</span>
                    @endif
                    <a href="{{ route('event-lens.detail', $node->event_id) }}"
                       class="font-mono text-xs text-indigo-600 hover:underline truncate"
                       title="{{ $node->event_name }}">
                        {{ Str::limit($node->event_name, 40) }}
                    </a>
                    @if($node->exception)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700" title="{{ e($node->exception) }}">ERR</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="w-1/4 text-right flex justify-end gap-2">
            @if(($node->side_effects['queries'] ?? 0) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $node->side_effects['queries'] }} Queries
                </span>
            @endif
            @if(($node->side_effects['mails'] ?? 0) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    {{ $node->side_effects['mails'] }} Mails
                </span>
            @endif
        </div>

        <div class="w-1/4 text-right">
            <span class="font-mono text-sm {{ $node->execution_time_ms > 100 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                {{ number_format($node->execution_time_ms, 2) }} ms
            </span>
            {{-- Duration bar relative to total --}}
            @if(isset($totalDuration) && $totalDuration > 0)
                <div class="mt-1 w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ min(100, ($node->execution_time_ms / $totalDuration) * 100) }}%"></div>
                </div>
            @endif
        </div>
    </div>
</div>

@if($hasChildren)
    <div x-show="open" x-cloak class="divide-y divide-gray-100">
        @foreach($node->children as $child)
            @include('event-lens::partials.node', ['node' => $child, 'depth' => $depth + 1, 'totalDuration' => $totalDuration ?? 0])
        @endforeach
    </div>
@endif
