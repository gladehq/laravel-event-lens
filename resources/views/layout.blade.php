<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventLens</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
    <!-- Ideally this is published to public/vendor/event-lens/event-lens.css -->
    <style> 
        /* Fallback or Inline for dev */ 
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
    {{-- In production, use the published asset --}}
    {{-- <link rel="stylesheet" href="{{ asset('vendor/event-lens/css/event-lens.css') }}"> --}}
    {{-- For this demo/test environment, we will revert to CDN because we cannot easily run 'publish' in the test suite environment without full app boot --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
    <nav class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <a href="{{ route('event-lens.index') }}" class="flex items-center gap-2">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span class="font-bold text-xl tracking-tight text-gray-900">EventLens</span>
        </a>
    </nav>
    
    <main class="max-w-7xl mx-auto px-6 py-8">
        @yield('content')
    </main>
</body>
</html>
