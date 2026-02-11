<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', [
        'GladeHQ\LaravelEventLens\Tests\Fixtures\*',
        'event.*',
        'App\*',
    ]);
    EventLog::truncate();
});

it('until() produces EventLog records', function () {
    Event::listen('event.halting', function () {
        return 'halted-result';
    });

    $result = Event::until('event.halting');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect($result)->toBe('halted-result');

    $logs = EventLog::all();
    expect($logs->count())->toBeGreaterThanOrEqual(1);
    expect($logs->where('event_name', 'event.halting')->count())->toBeGreaterThanOrEqual(1);
});

it('subscriber listeners are wrapped and recorded', function () {
    $subscriber = new class {
        public function subscribe($events)
        {
            $events->listen('event.subscribed', [$this, 'handleEvent']);
        }

        public function handleEvent()
        {
            return 'subscriber-handled';
        }
    };

    Event::subscribe($subscriber);
    Event::dispatch('event.subscribed');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    $logs = EventLog::all();
    expect($logs->where('event_name', 'event.subscribed')->count())->toBeGreaterThanOrEqual(1);
});

it('skips wrapping listeners for non-monitored events', function () {
    $executed = false;

    Event::listen('Illuminate\Some\Event', function () use (&$executed) {
        $executed = true;
    });

    Event::dispatch('Illuminate\Some\Event');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect($executed)->toBeTrue();
    expect(EventLog::count())->toBe(0);
});

it('wraps listeners for monitored events', function () {
    Event::listen('App\Events\Test', fn () => true);
    Event::dispatch('App\Events\Test');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect(EventLog::where('event_name', 'App\Events\Test')->count())->toBeGreaterThanOrEqual(1);
});

it('always wraps wildcard listeners', function () {
    Config::set('event-lens.namespaces', ['event.*']);

    $captured = false;
    Event::listen('*', function () use (&$captured) {
        $captured = true;
    });

    Event::dispatch('event.test');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect($captured)->toBeTrue();
    expect(EventLog::where('event_name', 'event.test')->count())->toBeGreaterThanOrEqual(1);
});

it('skips dispatch capture for non-monitored events', function () {
    Event::dispatch('Illuminate\Some\Event');

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect(EventLog::count())->toBe(0);
});

it('passes through push without capture', function () {
    // push delegates directly to original — no recording
    Event::push('event.pushed', ['data' => 'test']);

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    expect(EventLog::where('event_name', 'event.pushed')->count())->toBe(0);
});

it('passes through forget without capture', function () {
    Event::listen('event.forgettable', fn () => true);
    Event::forget('event.forgettable');

    expect(Event::hasListeners('event.forgettable'))->toBeFalse();
});

it('passes through forgetPushed without capture', function () {
    Event::push('event.pushed', ['data' => 'test']);
    Event::forgetPushed();

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    // forgetPushed delegates directly — no recording
    expect(EventLog::where('event_name', 'event.pushed')->count())->toBe(0);
});
