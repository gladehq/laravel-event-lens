<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('displays trace tree for correlation id', function () {
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'correlation_id' => 'cor-trace-test',
        'happened_at' => now(),
    ]);

    EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\ProcessOrder',
        'happened_at' => now()->addMilliseconds(10),
    ]);

    artisan('event-lens:trace', ['correlationId' => 'cor-trace-test'])
        ->assertSuccessful()
        ->expectsOutputToContain('OrderPlaced');
});

it('outputs json with --json flag', function () {
    EventLog::factory()->root()->create([
        'correlation_id' => 'cor-json-test',
        'happened_at' => now(),
    ]);

    artisan('event-lens:trace', ['correlationId' => 'cor-json-test', '--json' => true])
        ->assertSuccessful();
});

it('shows error when correlation id not found', function () {
    artisan('event-lens:trace', ['correlationId' => 'non-existent'])
        ->assertFailed();
});
