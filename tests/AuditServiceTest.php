<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\AuditService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.stale_threshold_days', 30);
    EventLog::truncate();
});

// -- Dead Listeners --

it('returns dead listeners (registered but never executed)', function () {
    // Register a listener for this event
    Event::listen('App\Events\OrderPlaced', fn () => null);

    // Create only a root dispatch record -- no listener execution records
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'Event::dispatch',
    ]);

    $audit = app(AuditService::class);
    $dead = $audit->deadListeners();

    expect($dead)->toHaveCount(1)
        ->and($dead->first()->event_name)->toBe('App\Events\OrderPlaced');
});

it('returns empty when all registered listeners have records', function () {
    Event::listen('App\Events\OrderPlaced', fn () => null);

    // Root + child listener execution record
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'Event::dispatch',
    ]);

    EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\SendEmail',
    ]);

    $audit = app(AuditService::class);
    $dead = $audit->deadListeners();

    expect($dead)->toHaveCount(0);
});

// -- Orphan Events --

it('returns orphan events (root events with no children)', function () {
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\Lonely',
        'listener_name' => 'Event::dispatch',
        'happened_at' => now(),
    ]);

    $audit = app(AuditService::class);
    $orphans = $audit->orphanEvents();

    expect($orphans)->toHaveCount(1)
        ->and($orphans->first()->event_name)->toBe('App\Events\Lonely')
        ->and((int) $orphans->first()->fire_count)->toBe(1);
});

it('does not flag events with listeners as orphans', function () {
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'Event::dispatch',
    ]);

    EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\SendEmail',
    ]);

    $audit = app(AuditService::class);
    $orphans = $audit->orphanEvents();

    expect($orphans)->toHaveCount(0);
});

// -- Stale Listeners --

it('returns stale listeners based on threshold', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\OldEvent',
        'listener_name' => 'App\Listeners\OldListener',
        'happened_at' => now()->subDays(60),
    ]);

    $audit = app(AuditService::class);
    $stale = $audit->staleListeners(30);

    expect($stale)->toHaveCount(1)
        ->and($stale->first()->listener_name)->toBe('App\Listeners\OldListener')
        ->and($stale->first()->days_stale)->toBeGreaterThanOrEqual(59);
});

it('does not flag recently active listeners as stale', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\RecentEvent',
        'listener_name' => 'App\Listeners\ActiveListener',
        'happened_at' => now()->subDays(5),
    ]);

    $audit = app(AuditService::class);
    $stale = $audit->staleListeners(30);

    expect($stale)->toHaveCount(0);
});
