@if(!empty($payload))
    <p class="text-xs text-gray-400 truncate">
        @foreach($payload as $key => $value)
            @if(!$loop->first) &middot; @endif
            <span>{{ $key }}: {{ $value }}</span>
        @endforeach
    </p>
@endif
