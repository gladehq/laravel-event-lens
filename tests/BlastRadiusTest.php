<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\BlastRadiusService;

beforeEach(function () {
    EventLog::truncate();
});

it('calculates risk score per listener', function () {
    // A listener with some children and moderate duration
    $parent = EventLog::factory()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\ProcessOrder',
        'parent_event_id' => 'dispatch-root',
        'execution_time_ms' => 200,
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\Events\InventoryUpdated',
        'listener_name' => 'App\Listeners\UpdateInventory',
        'parent_event_id' => $parent->event_id,
        'correlation_id' => $parent->correlation_id,
        'execution_time_ms' => 50,
        'happened_at' => now(),
    ]);

    $service = app(BlastRadiusService::class);
    $results = $service->calculate();

    expect($results)->toHaveCount(2);

    $processOrder = $results->firstWhere('listener_name', 'App\Listeners\ProcessOrder');
    expect($processOrder)->not->toBeNull();
    expect($processOrder->risk_score)->toBeGreaterThan(0);
    expect($processOrder->avg_children)->toBe(1.0);
    expect($processOrder->total_executions)->toBe(1);
});

it('sorts by risk score descending', function () {
    // High risk: listener with errors and children
    $parent = EventLog::factory()->withException('fail')->create([
        'event_name' => 'App\Events\Risky',
        'listener_name' => 'App\Listeners\RiskyHandler',
        'parent_event_id' => 'root-1',
        'execution_time_ms' => 5000,
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\Events\Child',
        'listener_name' => 'App\Listeners\ChildHandler',
        'parent_event_id' => $parent->event_id,
        'correlation_id' => $parent->correlation_id,
        'execution_time_ms' => 10,
        'happened_at' => now(),
    ]);

    // Low risk: simple listener
    EventLog::factory()->create([
        'event_name' => 'App\Events\Simple',
        'listener_name' => 'App\Listeners\SimpleHandler',
        'parent_event_id' => 'root-2',
        'execution_time_ms' => 5,
        'happened_at' => now(),
    ]);

    $results = app(BlastRadiusService::class)->calculate();

    // RiskyHandler should be first (highest risk score)
    expect($results->first()->listener_name)->toBe('App\Listeners\RiskyHandler');
    expect($results->first()->risk_score)->toBeGreaterThan($results->last()->risk_score);
});

it('handles listener with no children', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\Simple',
        'listener_name' => 'App\Listeners\SimpleHandler',
        'parent_event_id' => 'root-1',
        'execution_time_ms' => 10,
        'happened_at' => now(),
    ]);

    $results = app(BlastRadiusService::class)->calculate();

    expect($results)->toHaveCount(1);
    $handler = $results->first();
    expect($handler->avg_children)->toBe(0.0);
    expect($handler->total_downstream)->toBe(0);
    expect($handler->downstream)->toBe([]);
});

it('categorizes risk as High Medium Low', function () {
    // High risk: lots of errors
    EventLog::factory()->count(3)->withException('fail')->create([
        'event_name' => 'App\Events\Bad',
        'listener_name' => 'App\Listeners\HighRisk',
        'parent_event_id' => 'root',
        'execution_time_ms' => 5000,
        'happened_at' => now(),
    ]);

    // Low risk: no errors, fast
    EventLog::factory()->count(3)->create([
        'event_name' => 'App\Events\Good',
        'listener_name' => 'App\Listeners\LowRisk',
        'parent_event_id' => 'root',
        'execution_time_ms' => 1,
        'happened_at' => now(),
    ]);

    $results = app(BlastRadiusService::class)->calculate();

    $highRisk = $results->firstWhere('listener_name', 'App\Listeners\HighRisk');
    $lowRisk = $results->firstWhere('listener_name', 'App\Listeners\LowRisk');

    expect($highRisk->risk_level)->toBe('High');
    expect($lowRisk->risk_level)->toBe('Low');
});

it('includes downstream listener names', function () {
    $parent = EventLog::factory()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\ProcessOrder',
        'parent_event_id' => 'dispatch-root',
        'execution_time_ms' => 50,
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\Events\InventoryUpdated',
        'listener_name' => 'App\Listeners\UpdateInventory',
        'parent_event_id' => $parent->event_id,
        'correlation_id' => $parent->correlation_id,
        'execution_time_ms' => 10,
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\Events\NotificationSent',
        'listener_name' => 'App\Listeners\SendNotification',
        'parent_event_id' => $parent->event_id,
        'correlation_id' => $parent->correlation_id,
        'execution_time_ms' => 10,
        'happened_at' => now(),
    ]);

    $results = app(BlastRadiusService::class)->calculate();

    $processOrder = $results->firstWhere('listener_name', 'App\Listeners\ProcessOrder');
    expect($processOrder->total_downstream)->toBe(2);
    expect($processOrder->downstream)->toContain('App\Listeners\UpdateInventory');
    expect($processOrder->downstream)->toContain('App\Listeners\SendNotification');
});
