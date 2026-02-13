@if($context ?? null)
    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
        @if(($context['type'] ?? '') === 'http')
            {{ $context['method'] ?? '' }} {{ $context['path'] ?? '' }}
        @elseif(($context['type'] ?? '') === 'cli')
            artisan {{ $context['command'] ?? '' }}
        @elseif(($context['type'] ?? '') === 'queue')
            Queue: {{ $context['job'] ?? '' }}
        @endif
    </span>
@endif
