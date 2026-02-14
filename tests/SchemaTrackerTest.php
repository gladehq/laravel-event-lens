<?php

use GladeHQ\LaravelEventLens\Models\SchemaBaseline;
use GladeHQ\LaravelEventLens\Services\SchemaTracker;

beforeEach(function () {
    SchemaBaseline::truncate();
    $this->tracker = new SchemaTracker();
});

it('generates consistent fingerprint for same payload structure', function () {
    $payload = ['name' => 'Alice', 'age' => 30, 'active' => true];

    $fp1 = $this->tracker->fingerprint($payload);
    $fp2 = $this->tracker->fingerprint($payload);

    expect($fp1)->toBe($fp2);
});

it('generates different fingerprint for different structures', function () {
    $fp1 = $this->tracker->fingerprint(['name' => 'Alice', 'age' => 30]);
    $fp2 = $this->tracker->fingerprint(['name' => 'Alice', 'email' => 'a@b.com']);

    expect($fp1)->not->toBe($fp2);
});

it('stores baseline on first encounter and returns null', function () {
    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100, 'customer' => 'Alice']);

    expect($result)->toBeNull()
        ->and(SchemaBaseline::where('event_class', 'App\Events\OrderPlaced')->exists())->toBeTrue();
});

it('returns null when fingerprint matches baseline', function () {
    $payload = ['amount' => 100, 'customer' => 'Alice'];

    // First call stores baseline
    $this->tracker->detectDrift('App\Events\OrderPlaced', $payload);

    // Second call with same structure
    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', $payload);

    expect($result)->toBeNull();
});

it('detects added keys as drift', function () {
    $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100]);

    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100, 'discount' => 10]);

    expect($result)->not->toBeNull()
        ->and($result['changes'])->toContain('Added key: discount');
});

it('detects removed keys as drift', function () {
    $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100, 'customer' => 'Alice']);

    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100]);

    expect($result)->not->toBeNull()
        ->and($result['changes'])->toContain('Removed key: customer');
});

it('detects type changes as drift', function () {
    $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => '100']);

    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', ['amount' => 100]);

    expect($result)->not->toBeNull()
        ->and($result['changes'])->toContain('Type changed: amount (string → integer)');
});

it('handles nested payload structures', function () {
    $this->tracker->detectDrift('App\Events\OrderPlaced', [
        'customer' => ['name' => 'Alice', 'email' => 'a@b.com'],
    ]);

    $result = $this->tracker->detectDrift('App\Events\OrderPlaced', [
        'customer' => ['name' => 'Alice', 'phone' => '555'],
    ]);

    expect($result)->not->toBeNull()
        ->and($result['changes'])->toContain('Added key: customer.phone')
        ->and($result['changes'])->toContain('Removed key: customer.email');
});

it('handles empty payloads', function () {
    $result = $this->tracker->detectDrift('App\Events\Empty', []);

    expect($result)->toBeNull()
        ->and(SchemaBaseline::where('event_class', 'App\Events\Empty')->exists())->toBeTrue();
});

it('skips __context and __request_context keys', function () {
    $payload = [
        'amount' => 100,
        '__context' => ['file' => 'test.php:42'],
        '__request_context' => ['type' => 'http', 'path' => '/orders'],
    ];

    $schema = $this->tracker->buildSchema($payload);

    expect($schema)->toHaveKey('amount')
        ->and($schema)->not->toHaveKey('__context')
        ->and($schema)->not->toHaveKey('__request_context')
        ->and($schema)->not->toHaveKey('__context.file')
        ->and($schema)->not->toHaveKey('__request_context.type');
});

it('caches baseline in memory across calls', function () {
    $tracker = new SchemaTracker();

    // Enable query logging
    \Illuminate\Support\Facades\DB::enableQueryLog();

    // First call: stores baseline (triggers DB write + read)
    $tracker->detectDrift('App\\Events\\CachedTest', ['name' => 'Alice']);

    // Count queries so far
    $initialCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

    // Second call with same structure: should use cache, no new SELECT
    $tracker->detectDrift('App\\Events\\CachedTest', ['name' => 'Bob']);

    $finalCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

    // The second call should NOT issue a new SELECT for the baseline
    // Since fingerprint matches baseline, detectDrift returns null without calling storeBaseline
    // So no new queries at all
    expect($finalCount - $initialCount)->toBe(0);

    \Illuminate\Support\Facades\DB::disableQueryLog();
});

it('clears cache on reset', function () {
    $tracker = new SchemaTracker();

    \Illuminate\Support\Facades\DB::enableQueryLog();

    // First call: stores baseline
    $tracker->detectDrift('App\\Events\\ResetTest', ['name' => 'Alice']);
    $afterFirst = count(\Illuminate\Support\Facades\DB::getQueryLog());

    // Reset the tracker
    $tracker->reset();

    // Third call: should re-fetch from DB since cache was cleared
    $tracker->detectDrift('App\\Events\\ResetTest', ['name' => 'Alice']);
    $afterReset = count(\Illuminate\Support\Facades\DB::getQueryLog());

    // After reset, it should have issued at least one new SELECT query
    expect($afterReset)->toBeGreaterThan($afterFirst);

    \Illuminate\Support\Facades\DB::disableQueryLog();
});
