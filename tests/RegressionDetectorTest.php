<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\RegressionDetector;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.regression_threshold', 2.0);
    EventLog::truncate();

    $this->detector = new RegressionDetector();
});

it('detects regression when recent average exceeds baseline by threshold', function () {
    $listener = 'App\Listeners\SlowListener';
    $event = 'App\Events\OrderPlaced';

    // Baseline: 7 days ago, avg ~50ms
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent: last 24h, avg ~150ms (3x baseline)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 150,
            'happened_at' => now()->subHours(6),
        ]);
    }

    $regressions = $this->detector->detect();

    expect($regressions)->toHaveCount(1)
        ->and($regressions->first()->listener_name)->toBe($listener)
        ->and($regressions->first()->baseline_avg_ms)->toBe(50.0)
        ->and($regressions->first()->recent_avg_ms)->toBe(150.0)
        ->and($regressions->first()->change_pct)->toBe(200.0)
        ->and($regressions->first()->severity)->toBe('warning');
});

it('does not flag when change is below threshold', function () {
    $listener = 'App\Listeners\StableListener';
    $event = 'App\Events\OrderPlaced';

    // Baseline: ~100ms
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 100,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent: ~120ms (1.2x — below 2x threshold)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 120,
            'happened_at' => now()->subHours(6),
        ]);
    }

    expect($this->detector->detect())->toBeEmpty();
});

it('marks critical severity when ratio exceeds 5x', function () {
    $listener = 'App\Listeners\CriticalListener';
    $event = 'App\Events\OrderPlaced';

    // Baseline: ~20ms
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 20,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent: ~120ms (6x baseline)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 120,
            'happened_at' => now()->subHours(6),
        ]);
    }

    $regressions = $this->detector->detect();

    expect($regressions)->toHaveCount(1)
        ->and($regressions->first()->severity)->toBe('critical');
});

it('requires minimum 3 executions in both periods', function () {
    $listener = 'App\Listeners\RareListener';
    $event = 'App\Events\RareEvent';

    // Only 2 baseline records (below minimum of 3)
    for ($i = 0; $i < 2; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // 5 recent records but no qualifying baseline
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 200,
            'happened_at' => now()->subHours(6),
        ]);
    }

    expect($this->detector->detect())->toBeEmpty();
});

it('excludes Event::dispatch and Closure from detection', function () {
    // Only Event::dispatch and Closure records — should be excluded
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'Event::dispatch',
            'event_name' => 'App\Events\Order',
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
        EventLog::factory()->create([
            'listener_name' => 'Event::dispatch',
            'event_name' => 'App\Events\Order',
            'execution_time_ms' => 200,
            'happened_at' => now()->subHours(6),
        ]);
    }

    expect($this->detector->detect())->toBeEmpty();
});

it('returns empty collection when no data exists', function () {
    expect($this->detector->detect())->toBeEmpty();
});

it('sorts regressions by change percentage descending', function () {
    // Listener A: 2x regression
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\Listeners\ListenerA',
            'event_name' => 'App\Events\EventA',
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
        EventLog::factory()->create([
            'listener_name' => 'App\Listeners\ListenerA',
            'event_name' => 'App\Events\EventA',
            'execution_time_ms' => 110,
            'happened_at' => now()->subHours(6),
        ]);
    }

    // Listener B: 4x regression
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\Listeners\ListenerB',
            'event_name' => 'App\Events\EventB',
            'execution_time_ms' => 30,
            'happened_at' => now()->subDays(3),
        ]);
        EventLog::factory()->create([
            'listener_name' => 'App\Listeners\ListenerB',
            'event_name' => 'App\Events\EventB',
            'execution_time_ms' => 130,
            'happened_at' => now()->subHours(6),
        ]);
    }

    $regressions = $this->detector->detect();

    expect($regressions)->toHaveCount(2)
        ->and($regressions->first()->listener_name)->toBe('App\Listeners\ListenerB');
});
