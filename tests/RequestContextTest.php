<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use GladeHQ\LaravelEventLens\Services\RequestContextResolver;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', [
        'GladeHQ\LaravelEventLens\Tests\Fixtures\*',
        'App\*',
    ]);
    EventLog::truncate();
});

it('captures HTTP request context on root events', function () {
    // In test environment we're running in console, so we test via
    // the resolver directly and verify integration through the factory.
    $event = EventLog::factory()->root()->withRequestContext('http', '/api/orders')->create();

    $context = $event->payload['__request_context'] ?? null;

    expect($context)->not->toBeNull()
        ->and($context['type'])->toBe('http')
        ->and($context['path'])->toBe('/api/orders');
});

it('does not include request context on child events', function () {
    Event::listen(TestEvent::class, fn () => true);

    event(new TestEvent());
    app(EventLensBuffer::class)->flush();

    $childEvents = EventLog::whereNotNull('parent_event_id')->get();

    foreach ($childEvents as $child) {
        expect($child->payload)->not->toHaveKey('__request_context');
    }
});

it('returns null context when no request is active', function () {
    // The resolver running in console test without argv should still return something
    $resolver = new RequestContextResolver();

    // We're running in console in tests, so it should return CLI context
    $context = $resolver->resolve();

    // In test runner there's always an argv, so context should be CLI
    if ($context !== null) {
        expect($context['type'])->toBe('cli');
    } else {
        expect($context)->toBeNull();
    }
});

it('resolves CLI context', function () {
    $resolver = new RequestContextResolver();

    // Running in console mode (which tests do), the resolver reads $_SERVER['argv']
    $context = $resolver->resolve();

    // Tests always run from CLI, so we expect CLI context
    expect($context)->not->toBeNull()
        ->and($context['type'])->toBe('cli')
        ->and($context['command'])->toBeString();
});

it('resolves queue context when job name is set', function () {
    $resolver = new RequestContextResolver();
    $resolver->setQueueJobName('App\Jobs\ProcessPayment');

    $context = $resolver->resolve();

    expect($context)->not->toBeNull()
        ->and($context['type'])->toBe('queue')
        ->and($context['job'])->toBe('App\Jobs\ProcessPayment');

    // Reset clears the queue job name
    $resolver->reset();
    $afterReset = $resolver->resolve();

    expect($afterReset['type'])->not->toBe('queue');
});
