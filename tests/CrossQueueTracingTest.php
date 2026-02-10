<?php

use GladeHQ\LaravelEventLens\Services\EventRecorder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['*']);
});

it('currentCorrelationId returns null when no context', function () {
    $recorder = app(EventRecorder::class);
    $recorder->reset();

    expect($recorder->currentCorrelationId())->toBeNull();
});

it('push and pop correlation context works', function () {
    $recorder = app(EventRecorder::class);
    $recorder->reset();

    $recorder->pushCorrelationContext('test-correlation-123');
    expect($recorder->currentCorrelationId())->toBe('test-correlation-123');

    $recorder->popCorrelationContext();
    expect($recorder->currentCorrelationId())->toBeNull();
});

it('nested correlation contexts stack correctly', function () {
    $recorder = app(EventRecorder::class);
    $recorder->reset();

    $recorder->pushCorrelationContext('outer');
    $recorder->pushCorrelationContext('inner');

    expect($recorder->currentCorrelationId())->toBe('inner');

    $recorder->popCorrelationContext();
    expect($recorder->currentCorrelationId())->toBe('outer');

    $recorder->popCorrelationContext();
    expect($recorder->currentCorrelationId())->toBeNull();
});

it('reset clears correlation context', function () {
    $recorder = app(EventRecorder::class);
    $recorder->pushCorrelationContext('test-id');

    expect($recorder->currentCorrelationId())->toBe('test-id');

    $recorder->reset();
    expect($recorder->currentCorrelationId())->toBeNull();
});
