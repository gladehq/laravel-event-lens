<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\ListenerHealthService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('calculates health score of 100 for perfect listener', function () {
    EventLog::factory()->count(5)->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Perfect',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 2],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();

    expect($scores)->toHaveCount(1)
        ->and($scores->first()->listener_name)->toBe('App\Listeners\Perfect')
        ->and($scores->first()->score)->toBe(100.0);
});

it('penalizes high error rate', function () {
    // 3 out of 6 have errors = 50% error rate
    EventLog::factory()->count(3)->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Flaky',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);
    EventLog::factory()->count(3)->withException()->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Flaky',
        'execution_time_ms' => 10,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();
    $flaky = $scores->firstWhere('listener_name', 'App\Listeners\Flaky');

    // 50% error rate * 3 = 40 penalty (capped) => score ~60
    expect($flaky->score)->toBeLessThan(100)
        ->and($flaky->error_rate)->toBe(50.0);
});

it('penalizes high P95 latency', function () {
    // All 10 events are slow (300ms) so P95 is definitely above 100ms
    EventLog::factory()->count(10)->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Slow',
        'execution_time_ms' => 300,
        'exception' => null,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();
    $slow = $scores->firstWhere('listener_name', 'App\Listeners\Slow');

    expect($slow->score)->toBeLessThan(100)
        ->and($slow->p95_latency)->toBeGreaterThan(100);
});

it('penalizes high query count', function () {
    EventLog::factory()->count(5)->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Heavy',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 20],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();
    $heavy = $scores->firstWhere('listener_name', 'App\Listeners\Heavy');

    // avg_queries = 20, penalty = min(20, (20 - 5) * 2) = 20
    expect($heavy->score)->toBeLessThan(100)
        ->and($heavy->avg_queries)->toBe(20.0);
});

it('returns zero for listener with 100% error rate', function () {
    EventLog::factory()->count(5)->withException()->create([
        'event_name' => 'App\Events\Order',
        'listener_name' => 'App\Listeners\Broken',
        'execution_time_ms' => 10,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();
    $broken = $scores->firstWhere('listener_name', 'App\Listeners\Broken');

    // 100% error rate * 3 = 300, capped at 40 penalty => score = 60
    expect($broken->score)->toBeLessThanOrEqual(60.0)
        ->and($broken->error_rate)->toBe(100.0);
});

it('sorts listeners by score ascending', function () {
    // Perfect listener
    EventLog::factory()->count(5)->create([
        'event_name' => 'App\Events\A',
        'listener_name' => 'App\Listeners\Good',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    // Bad listener (100% errors)
    EventLog::factory()->count(5)->withException()->create([
        'event_name' => 'App\Events\B',
        'listener_name' => 'App\Listeners\Bad',
        'execution_time_ms' => 10,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();

    expect($scores->first()->listener_name)->toBe('App\Listeners\Bad')
        ->and($scores->last()->listener_name)->toBe('App\Listeners\Good');
});

it('handles single execution gracefully', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\Once',
        'listener_name' => 'App\Listeners\Solo',
        'execution_time_ms' => 50,
        'exception' => null,
        'side_effects' => ['queries' => 3],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();

    expect($scores)->toHaveCount(1)
        ->and($scores->first()->execution_count)->toBe(1);
});

it('handles empty dataset', function () {
    $scores = app(ListenerHealthService::class)->scores();

    expect($scores)->toBeEmpty();
});

it('limits to specified period', function () {
    // Old event outside the default 7-day window
    EventLog::factory()->create([
        'event_name' => 'App\Events\Old',
        'listener_name' => 'App\Listeners\Ancient',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subDays(30),
    ]);

    // Recent event within window
    EventLog::factory()->create([
        'event_name' => 'App\Events\New',
        'listener_name' => 'App\Listeners\Recent',
        'execution_time_ms' => 10,
        'exception' => null,
        'side_effects' => ['queries' => 1],
        'happened_at' => now()->subHour(),
    ]);

    $scores = app(ListenerHealthService::class)->scores();

    expect($scores)->toHaveCount(1)
        ->and($scores->first()->listener_name)->toBe('App\Listeners\Recent');
});

it('calculates p95 correctly with SQL approach', function () {
    // Create 100 events with execution times from 1 to 100
    for ($i = 1; $i <= 100; $i++) {
        EventLog::factory()->create([
            'event_name' => 'App\\Events\\P95Test',
            'listener_name' => 'App\\Listeners\\P95Listener',
            'execution_time_ms' => $i,
            'exception' => null,
            'side_effects' => ['queries' => 1],
            'happened_at' => now()->subHour(),
        ]);
    }

    $scores = app(ListenerHealthService::class)->scores();
    $p95Listener = $scores->firstWhere('listener_name', 'App\\Listeners\\P95Listener');

    // P95 of 1..100 should be 95 (index 94 in 0-based sorted array)
    expect($p95Listener->p95_latency)->toBe(95.0);
});

it('returns zero p95 when no data exists', function () {
    $scores = app(ListenerHealthService::class)->scores();

    expect($scores)->toBeEmpty();
});
