<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\NplusOneDetector;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

// -- Event pattern detection --

it('detects N+1 event pattern when same class dispatched 5+ times', function () {
    $detector = new NplusOneDetector();

    $stormCounters = [
        'corr-1:App\Events\OrderItem' => 15,
        'corr-1:App\Events\OrderPlaced' => 1,
    ];

    $result = $detector->checkEventPattern('corr-1', $stormCounters);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('event')
        ->and($result['event_class'])->toBe('App\Events\OrderItem')
        ->and($result['count'])->toBe(15);
});

it('does not flag event pattern when count below threshold', function () {
    $detector = new NplusOneDetector();

    $stormCounters = [
        'corr-1:App\Events\OrderItem' => 3,
        'corr-1:App\Events\OrderPlaced' => 1,
    ];

    $result = $detector->checkEventPattern('corr-1', $stormCounters);

    expect($result)->toBeNull();
});

// -- Query pattern detection --

it('detects N+1 query pattern when same fingerprint appears 5+ times', function () {
    $detector = new NplusOneDetector();

    $fingerprints = array_fill(0, 8, 'SELECT * FROM items WHERE order_id = ?');

    $result = $detector->checkQueryPattern($fingerprints);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('query')
        ->and($result['pattern'])->toBe('SELECT * FROM items WHERE order_id = ?')
        ->and($result['count'])->toBe(8);
});

it('does not flag varied query fingerprints', function () {
    $detector = new NplusOneDetector();

    $fingerprints = [
        'SELECT * FROM users WHERE id = ?',
        'SELECT * FROM orders WHERE user_id = ?',
        'SELECT * FROM items WHERE order_id = ?',
        'INSERT INTO logs VALUES (?)',
    ];

    $result = $detector->checkQueryPattern($fingerprints);

    expect($result)->toBeNull();
});

// -- SQL normalization --

it('normalizes SQL by replacing string literals with placeholders', function () {
    $detector = new NplusOneDetector();

    $sql = "SELECT * FROM users WHERE name = 'Alice' AND email = \"bob@example.com\"";
    $normalized = $detector->normalizeQuery($sql);

    expect($normalized)->toBe('SELECT * FROM users WHERE name = ? AND email = ?');
});

it('normalizes SQL by replacing numeric literals with placeholders', function () {
    $detector = new NplusOneDetector();

    $sql = 'SELECT * FROM orders WHERE id = 42 AND total > 99.95';
    $normalized = $detector->normalizeQuery($sql);

    expect($normalized)->toBe('SELECT * FROM orders WHERE id = ? AND total > ?');
});

// -- Model integration --

it('marks is_nplus1 on persisted EventLog record via factory', function () {
    $event = EventLog::factory()->nplus1()->create();

    expect($event->is_nplus1)->toBeTrue()
        ->and($event->side_effects['nplus1_detail'])->toBe('10x SELECT * FROM users WHERE id = ? (query)');
});

it('scopes to N+1 flagged events', function () {
    EventLog::insert([
        ['event_id' => 'normal', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Normal', 'listener_name' => 'Closure', 'is_nplus1' => false, 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'nplus1', 'correlation_id' => 'c2', 'event_name' => 'App\Events\Heavy', 'listener_name' => 'Closure', 'is_nplus1' => true, 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::nplusOne()->count())->toBe(1)
        ->and(EventLog::nplusOne()->first()->event_id)->toBe('nplus1');
});

// -- Reset --

it('resets detector state', function () {
    $detector = new NplusOneDetector();

    // reset() should not throw on a stateless detector
    $detector->reset();

    // Detector should still work after reset
    $result = $detector->checkQueryPattern([]);
    expect($result)->toBeNull();
});
