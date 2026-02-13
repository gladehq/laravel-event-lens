<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

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

it('logs warning when flush fails', function () {
    Log::spy();

    // Use a buffer with data that will fail to insert (invalid table scenario)
    $buffer = new EventLensBuffer();
    $buffer->push([
        'event_id' => 'e-1',
        'correlation_id' => 'c-1',
        'event_name' => 'Test',
        'listener_name' => 'Closure',
        'execution_time_ms' => 1.0,
        'happened_at' => now()->format('Y-m-d H:i:s'),
        'INVALID_COLUMN' => 'will cause failure',
    ]);

    $buffer->flush();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains($msg, 'Failed to flush'))
        ->once();
});

it('logs warning when persist fails in recorder', function () {
    Log::spy();

    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['event.*']);

    // Mock the buffer to throw on push
    $buffer = Mockery::mock(EventLensBuffer::class);
    $buffer->shouldReceive('push')->andThrow(new \RuntimeException('DB gone'));

    // Build a fresh recorder with the mocked buffer
    $recorder = new EventRecorder(
        app(\GladeHQ\LaravelEventLens\Watchers\WatcherManager::class),
        $buffer,
        app(\GladeHQ\LaravelEventLens\Collectors\EventCollector::class),
        app(\GladeHQ\LaravelEventLens\Services\RequestContextResolver::class),
        app(\GladeHQ\LaravelEventLens\Services\SlaChecker::class),
        app(\GladeHQ\LaravelEventLens\Services\SchemaTracker::class),
        app(\GladeHQ\LaravelEventLens\Services\NplusOneDetector::class),
    );

    $recorder->capture('event.test', 'Closure', [], fn () => true);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains($msg, 'Failed to persist'))
        ->once();
});
