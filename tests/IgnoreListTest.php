<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['GladeHQ\\LaravelEventLens\\Tests\\Fixtures\\*']);
    EventLog::truncate();
});

it('ignores events matching ignore list pattern', function () {
    Config::set('event-lens.ignore', ['GladeHQ\\LaravelEventLens\\Tests\\Fixtures\\TestEvent']);

    event(new TestEvent());
    app(EventLensBuffer::class)->flush();

    expect(EventLog::count())->toBe(0);
});

it('records events not matching ignore list', function () {
    Config::set('event-lens.ignore', ['App\\Events\\SomethingElse']);

    event(new TestEvent());
    app(EventLensBuffer::class)->flush();

    expect(EventLog::count())->toBeGreaterThan(0);
});

it('supports wildcard patterns in ignore list', function () {
    Config::set('event-lens.ignore', ['GladeHQ\\LaravelEventLens\\Tests\\Fixtures\\*']);

    event(new TestEvent());
    app(EventLensBuffer::class)->flush();

    expect(EventLog::count())->toBe(0);
});
