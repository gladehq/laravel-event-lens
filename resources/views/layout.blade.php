<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventLens</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <nav class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="{{ route('event-lens.index') }}" class="flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="font-bold text-xl tracking-tight text-gray-900">EventLens</span>
            </a>
            <div class="flex items-center gap-6 text-sm font-medium">
                <a href="{{ route('event-lens.index') }}"
                   class="{{ request()->routeIs('event-lens.index', 'event-lens.show', 'event-lens.detail') ? 'text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">Stream</a>
                <a href="{{ route('event-lens.statistics') }}"
                   class="{{ request()->routeIs('event-lens.statistics') ? 'text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">Statistics</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
