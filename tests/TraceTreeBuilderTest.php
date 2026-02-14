<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Support\TraceTreeBuilder;

beforeEach(function () {
    EventLog::truncate();
});

it('builds nested tree from flat events', function () {
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'correlation_id' => 'cor-tree',
        'happened_at' => now(),
    ]);

    $child = EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\\Events\\PaymentCharged',
        'listener_name' => 'App\\Listeners\\ChargePayment',
        'happened_at' => now()->addMilliseconds(10),
    ]);

    $grandchild = EventLog::factory()->childOf($child)->create([
        'event_name' => 'App\\Events\\ReceiptSent',
        'listener_name' => 'App\\Listeners\\SendReceipt',
        'happened_at' => now()->addMilliseconds(20),
    ]);

    $events = EventLog::forCorrelation('cor-tree')->orderBy('happened_at')->get();
    $tree = TraceTreeBuilder::build($events);

    expect($tree)->toHaveCount(1)
        ->and($tree[0]->event_id)->toBe($root->event_id)
        ->and($tree[0]->children)->toHaveCount(1)
        ->and($tree[0]->children[0]->event_id)->toBe($child->event_id)
        ->and($tree[0]->children[0]->children)->toHaveCount(1)
        ->and($tree[0]->children[0]->children[0]->event_id)->toBe($grandchild->event_id);
});

it('marks descendant errors correctly', function () {
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'correlation_id' => 'cor-err',
        'happened_at' => now(),
    ]);

    $child = EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\\Events\\PaymentCharged',
        'listener_name' => 'App\\Listeners\\ChargePayment',
        'happened_at' => now()->addMilliseconds(10),
    ]);

    EventLog::factory()->childOf($child)->withException('Payment failed')->create([
        'event_name' => 'App\\Events\\GatewayCall',
        'listener_name' => 'App\\Listeners\\CallGateway',
        'happened_at' => now()->addMilliseconds(20),
    ]);

    $events = EventLog::forCorrelation('cor-err')->orderBy('happened_at')->get();
    $tree = TraceTreeBuilder::build($events);

    // Root and child should have has_descendant_error = true
    expect($tree[0]->has_descendant_error)->toBeTrue()
        ->and($tree[0]->children[0]->has_descendant_error)->toBeTrue()
        // Grandchild (the one with error) should NOT have descendant error (it IS the error)
        ->and($tree[0]->children[0]->children[0]->has_descendant_error)->toBeFalse();
});
