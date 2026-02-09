<div class="hover:bg-gray-50 transition relative group">
    <div class="px-6 py-4 flex items-center justify-between">
        <div class="w-1/2 flex items-center gap-3" style="padding-left: {{ $depth * 2 }}rem">
            @if($depth > 0)
                <div class="text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            @endif
            
            <div class="overflow-hidden">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                        {{ $node->listener_name }}
                    </span>
                    @if($depth == 0)
                        <span class="text-xs text-gray-400">listening to</span>
                        <span class="font-mono text-xs text-indigo-600 truncate" title="{{ $node->event_name }}">{{ Str::limit($node->event_name, 40) }}</span>
                    @endif
                </div>
                
                {{-- Payload Preview (collapsible ideally, just showing count for now) --}}
                @if(!empty($node->payload))
                    <div class="mt-1 text-xs text-gray-400">
                        Payload: {{ json_encode($node->payload) }}
                    </div>
                @endif

                {{-- Model Changes --}}
                @if(!empty($node->model_changes))
                     <div class="mt-1 text-xs text-amber-600 font-mono">
                         Diff: {{ json_encode($node->model_changes) }}
                     </div>
                @endif
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
        </div>
    </div>
</div>

@if($node->children && $node->children->count())
    @foreach($node->children as $child)
        @include('event-lens::partials.node', ['node' => $child, 'depth' => $depth + 1])
    @endforeach
@endif
