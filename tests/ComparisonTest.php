<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\ComparisonService;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('calculates throughput delta between periods', function () {
    // Period A: 5 events yesterday
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subDays(2)->addHours($i),
        ]);
    }

    // Period B: 10 events today
    for ($i = 0; $i < 10; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subHours($i + 1),
        ]);
    }

    $service = new ComparisonService();
    $result = $service->compare(
        now()->subDays(3), now()->subDay(),
        now()->subDay(), now()
    );

    expect($result['throughput_delta_pct'])->toBe(100.0)
        ->and($result['period_a']['stats']['total_events'])->toBe(5)
        ->and($result['period_b']['stats']['total_events'])->toBe(10);
});

it('identifies degraded listeners', function () {
    // Period A: fast listener (10ms)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\\Listeners\\SlowDown',
            'execution_time_ms' => 10,
            'happened_at' => now()->subDays(2)->addHours($i),
        ]);
    }

    // Period B: slow listener (100ms)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\\Listeners\\SlowDown',
            'execution_time_ms' => 100,
            'happened_at' => now()->subHours($i + 1),
        ]);
    }

    $service = new ComparisonService();
    $result = $service->compare(
        now()->subDays(3), now()->subDay(),
        now()->subDay(), now()
    );

    $degraded = $result['listeners']->firstWhere('listener_name', 'App\\Listeners\\SlowDown');
    expect($degraded)->not->toBeNull()
        ->and($degraded->status)->toBe('degraded')
        ->and($degraded->avg_delta_pct)->toBeGreaterThan(0);
});

it('identifies improved listeners', function () {
    // Period A: slow listener (100ms)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\\Listeners\\SpeedUp',
            'execution_time_ms' => 100,
            'happened_at' => now()->subDays(2)->addHours($i),
        ]);
    }

    // Period B: fast listener (10ms)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => 'App\\Listeners\\SpeedUp',
            'execution_time_ms' => 10,
            'happened_at' => now()->subHours($i + 1),
        ]);
    }

    $service = new ComparisonService();
    $result = $service->compare(
        now()->subDays(3), now()->subDay(),
        now()->subDay(), now()
    );

    $improved = $result['listeners']->firstWhere('listener_name', 'App\\Listeners\\SpeedUp');
    expect($improved)->not->toBeNull()
        ->and($improved->status)->toBe('improved')
        ->and($improved->avg_delta_pct)->toBeLessThan(0);
});

it('renders comparison page', function () {
    get(route('event-lens.comparison'))
        ->assertOk()
        ->assertSee('Comparison');
});
