<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('returns correct JSON structure from latest endpoint', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.api.latest'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'event_id',
                'correlation_id',
                'event_name',
                'listener_name',
                'execution_time_ms',
                'is_slow',
                'happened_at',
                'happened_at_human',
                'url',
            ]],
        ]);
});

it('marks slow events with is_slow flag', function () {
    EventLog::insert([
        ['event_id' => 'fast', 'correlation_id' => 'c1', 'event_name' => 'Fast', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'slow', 'correlation_id' => 'c2', 'event_name' => 'Slow', 'listener_name' => 'Closure', 'execution_time_ms' => 500, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = get(route('event-lens.api.latest'))->assertOk();
    $data = $response->json('data');

    $fast = collect($data)->firstWhere('event_id', 'fast');
    $slow = collect($data)->firstWhere('event_id', 'slow');

    expect($fast['is_slow'])->toBeFalse();
    expect($slow['is_slow'])->toBeTrue();
});

it('caches statistics responses', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'execution_time_ms' => 50, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // First call populates cache
    get(route('event-lens.statistics'))->assertOk();

    // Add more data
    EventLog::insert([
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\Test2', 'listener_name' => 'Closure', 'execution_time_ms' => 100, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Second call should return cached result (still 1 event)
    $response = get(route('event-lens.statistics'))->assertOk();
    $response->assertViewHas('stats');
});
