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
