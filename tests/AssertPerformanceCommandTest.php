<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('exits 0 when all SLAs pass', function () {
    Config::set('event-lens.sla_budgets', [
        'App\\Events\\FastEvent' => 500,
    ]);

    // All events are fast (10ms, well within 500ms budget)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'event_name' => 'App\\Events\\FastEvent',
            'execution_time_ms' => 10,
            'is_sla_breach' => false,
            'happened_at' => now()->subHours($i + 1),
        ]);
    }

    artisan('event-lens:assert-performance')
        ->assertSuccessful();
});

it('exits 1 when SLA breaches found', function () {
    Config::set('event-lens.sla_budgets', [
        'App\\Events\\SlowEvent' => 100,
    ]);

    // Events that breach the SLA
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'event_name' => 'App\\Events\\SlowEvent',
            'execution_time_ms' => 500,
            'is_sla_breach' => true,
            'happened_at' => now()->subHours($i + 1),
        ]);
    }

    artisan('event-lens:assert-performance')
        ->assertFailed();
});

it('outputs json with --format=json flag', function () {
    Config::set('event-lens.sla_budgets', [
        'App\\Events\\TestEvent' => 500,
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\\Events\\TestEvent',
        'execution_time_ms' => 10,
        'is_sla_breach' => false,
        'happened_at' => now()->subHour(),
    ]);

    artisan('event-lens:assert-performance', ['--format' => 'json'])
        ->assertSuccessful();
});

it('uses configurable lookback period', function () {
    Config::set('event-lens.sla_budgets', [
        'App\\Events\\OldEvent' => 100,
    ]);

    // Old event outside 1h lookback
    EventLog::factory()->create([
        'event_name' => 'App\\Events\\OldEvent',
        'execution_time_ms' => 500,
        'is_sla_breach' => true,
        'happened_at' => now()->subHours(3),
    ]);

    // With 1h period, the old breach is outside the window
    artisan('event-lens:assert-performance', ['--period' => '1h'])
        ->assertSuccessful();
});
