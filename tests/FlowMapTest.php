<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\FlowMapService;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('builds graph nodes from event log data', function () {
    EventLog::factory()->count(3)->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\ProcessOrder',
        'parent_event_id' => 'some-parent',
        'happened_at' => now()->subHour(),
    ]);

    $service = new FlowMapService();
    $graph = $service->buildGraph('24h');

    expect($graph['nodes'])->toHaveCount(2)
        ->and($graph)->toHaveKey('viewBox')
        ->and(collect($graph['nodes'])->firstWhere('type', 'event'))->not->toBeNull()
        ->and(collect($graph['nodes'])->firstWhere('type', 'event'))->toHaveKeys(['x', 'y', 'width'])
        ->and(collect($graph['nodes'])->firstWhere('type', 'listener'))->not->toBeNull();
});

it('builds edges between events and listeners', function () {
    EventLog::factory()->count(5)->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\ProcessOrder',
        'parent_event_id' => 'some-parent',
        'happened_at' => now()->subHour(),
    ]);

    $service = new FlowMapService();
    $graph = $service->buildGraph('24h');

    expect($graph['edges'])->toHaveCount(1)
        ->and($graph['edges'][0]['count'])->toBe(5)
        ->and($graph['edges'][0]['source'])->toBe('event:App\\Events\\OrderPlaced')
        ->and($graph['edges'][0]['target'])->toBe('listener:App\\Listeners\\ProcessOrder')
        ->and($graph['edges'][0])->toHaveKeys(['x1', 'y1', 'x2', 'y2']);
});

it('filters by time range', function () {
    // Recent event
    EventLog::factory()->create([
        'event_name' => 'App\\Events\\Recent',
        'listener_name' => 'App\\Listeners\\RecentHandler',
        'parent_event_id' => 'some-parent',
        'happened_at' => now()->subMinutes(30),
    ]);

    // Old event (outside 1h range)
    EventLog::factory()->create([
        'event_name' => 'App\\Events\\Old',
        'listener_name' => 'App\\Listeners\\OldHandler',
        'parent_event_id' => 'some-parent',
        'happened_at' => now()->subHours(3),
    ]);

    $service = new FlowMapService();
    $graph = $service->buildGraph('1h');

    // Should only include the recent event
    $eventNames = collect($graph['nodes'])->where('type', 'event')->pluck('full_name')->all();
    expect($eventNames)->toContain('App\\Events\\Recent')
        ->and($eventNames)->not->toContain('App\\Events\\Old');
});

it('renders flow map page', function () {
    get(route('event-lens.flow-map'))
        ->assertOk()
        ->assertSee('Event Flow Map');
});
