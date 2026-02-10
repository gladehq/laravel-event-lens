<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    EventLog::truncate();
});

it('auto-flushes when buffer reaches max size', function () {
    Config::set('event-lens.buffer_size', 5);
    $buffer = new EventLensBuffer();

    for ($i = 1; $i <= 5; $i++) {
        $buffer->push([
            'event_id' => "e-{$i}",
            'correlation_id' => 'c-1',
            'event_name' => 'Test',
            'listener_name' => 'Closure',
            'execution_time_ms' => 1.0,
            'happened_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    // Should have auto-flushed at 5
    expect($buffer->count())->toBe(0);
    expect(EventLog::count())->toBe(5);
});

it('does not auto-flush below limit', function () {
    Config::set('event-lens.buffer_size', 10);
    $buffer = new EventLensBuffer();

    for ($i = 1; $i <= 3; $i++) {
        $buffer->push([
            'event_id' => "e-{$i}",
            'correlation_id' => 'c-1',
            'event_name' => 'Test',
            'listener_name' => 'Closure',
            'execution_time_ms' => 1.0,
            'happened_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    expect($buffer->count())->toBe(3);
    expect(EventLog::count())->toBe(0);
});
