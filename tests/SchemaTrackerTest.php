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
