@if($context ?? null)
    @if(($context['type'] ?? '') === 'http')
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-700">{{ $context['method'] ?? '' }}</span>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-600">{{ $context['path'] ?? '' }}</span>
    @elseif(($context['type'] ?? '') === 'cli')
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-700">CLI</span>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-600">{{ $context['command'] ?? '' }}</span>
    @elseif(($context['type'] ?? '') === 'queue')
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-700">Queue</span>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-600">{{ $context['job'] ?? '' }}</span>
    @endif
@endif
