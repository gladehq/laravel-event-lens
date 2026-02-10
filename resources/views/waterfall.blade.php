@extends('event-lens::layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('event-lens.index') }}" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">&larr; Back to Stream</a>
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Event Trace</h1>
                <p class="text-sm text-gray-500 font-mono mt-1">{{ request()->route('correlationId') }}</p>
            </div>
            <div class="flex gap-6">
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Total Duration</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($totalDuration, 2) }} ms</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">DB Queries</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalQueries }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Mails Sent</p>
                    <p class="text-xl font-bold text-gray-900">{{ $totalMails }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <div class="w-1/2">Event / Listener</div>
            <div class="w-1/4 text-right">Side Effects</div>
            <div class="w-1/4 text-right">Duration</div>
        </div>
        
        <div class="divide-y divide-gray-200">
            @foreach($tree as $node)
                @include('event-lens::partials.node', ['node' => $node, 'depth' => 0, 'totalDuration' => $totalDuration])
            @endforeach
        </div>
    </div>
@endsection
