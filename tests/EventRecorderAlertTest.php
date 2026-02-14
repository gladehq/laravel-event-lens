<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\AlertService;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['GladeHQ\\LaravelEventLens\\Tests\\Fixtures\\*']);
    EventLog::truncate();
});

it('fires storm alert during persist', function () {
    Config::set('event-lens.storm_threshold', 1);
    Config::set('event-lens.alerts.enabled', true);
    Config::set('event-lens.alerts.on', ['storm']);
    Config::set('event-lens.alerts.channels', ['log']);
    Config::set('event-lens.alerts.cooldown_minutes', 15);

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->atLeast()->once()
        ->withArgs(function ($type) {
            return $type === 'storm';
        });

    // Build a fresh recorder with the mocked AlertService, using a shared
    // correlation so both captures share the same storm counter key.
    $recorder = new EventRecorder(
        app(\GladeHQ\LaravelEventLens\Watchers\WatcherManager::class),
        app(EventLensBuffer::class),
        app(\GladeHQ\LaravelEventLens\Collectors\EventCollector::class),
        app(\GladeHQ\LaravelEventLens\Services\RequestContextResolver::class),
        app(\GladeHQ\LaravelEventLens\Services\SlaChecker::class),
        app(\GladeHQ\LaravelEventLens\Services\SchemaTracker::class),
        app(\GladeHQ\LaravelEventLens\Services\NplusOneDetector::class),
        $mock,
    );

    $recorder->pushCorrelationContext('shared-corr');

    // First capture (count=1, not storm yet with threshold=1)
    // Actually threshold check: stormCounters > threshold, so count must exceed 1
    // count=1 is NOT storm, count=2 IS storm
    $recorder->capture(TestEvent::class, 'Closure', [new TestEvent()], fn () => true);
    $recorder->capture(TestEvent::class, 'Closure', [new TestEvent()], fn () => true);

    $recorder->popCorrelationContext();
    app(EventLensBuffer::class)->flush();
});

it('fires SLA breach alert during persist', function () {
    Config::set('event-lens.sla_budgets', [
        TestEvent::class => 1, // 1ms budget
    ]);
    Config::set('event-lens.alerts.enabled', true);
    Config::set('event-lens.alerts.on', ['sla_breach']);
    Config::set('event-lens.alerts.channels', ['log']);
    Config::set('event-lens.alerts.cooldown_minutes', 15);

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->atLeast()->once()
        ->withArgs(function ($type) {
            return $type === 'sla_breach';
        });

    $recorder = new EventRecorder(
        app(\GladeHQ\LaravelEventLens\Watchers\WatcherManager::class),
        app(EventLensBuffer::class),
        app(\GladeHQ\LaravelEventLens\Collectors\EventCollector::class),
        app(\GladeHQ\LaravelEventLens\Services\RequestContextResolver::class),
        new \GladeHQ\LaravelEventLens\Services\SlaChecker(),
        app(\GladeHQ\LaravelEventLens\Services\SchemaTracker::class),
        app(\GladeHQ\LaravelEventLens\Services\NplusOneDetector::class),
        $mock,
    );

    $recorder->capture(TestEvent::class, 'App\Listeners\SlowOne', [new TestEvent()], function () {
        usleep(5000); // 5ms — exceeds 1ms budget
    });

    app(EventLensBuffer::class)->flush();
});

it('does not break recording when alert service throws', function () {
    Config::set('event-lens.storm_threshold', 1);
    Config::set('event-lens.sla_budgets', [
        TestEvent::class => 1,
    ]);
    Config::set('event-lens.alerts.enabled', true);
    Config::set('event-lens.alerts.on', ['storm', 'sla_breach']);
    Config::set('event-lens.alerts.channels', ['log']);

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->andThrow(new \RuntimeException('Alert service exploded'));

    $recorder = new EventRecorder(
        app(\GladeHQ\LaravelEventLens\Watchers\WatcherManager::class),
        app(EventLensBuffer::class),
        app(\GladeHQ\LaravelEventLens\Collectors\EventCollector::class),
        app(\GladeHQ\LaravelEventLens\Services\RequestContextResolver::class),
        new \GladeHQ\LaravelEventLens\Services\SlaChecker(),
        app(\GladeHQ\LaravelEventLens\Services\SchemaTracker::class),
        app(\GladeHQ\LaravelEventLens\Services\NplusOneDetector::class),
        $mock,
    );

    $recorder->pushCorrelationContext('shared-corr');

    // Should not throw despite alert service failure
    $recorder->capture(TestEvent::class, 'Closure', [new TestEvent()], fn () => true);
    $recorder->capture(TestEvent::class, 'Closure', [new TestEvent()], fn () => true);

    $recorder->popCorrelationContext();
    app(EventLensBuffer::class)->flush();

    // Events were still recorded
    expect(EventLog::count())->toBeGreaterThanOrEqual(1);
});

it('does not fire alert when alerts are disabled', function () {
    Config::set('event-lens.storm_threshold', 1);
    Config::set('event-lens.alerts.enabled', false);
    Config::set('event-lens.alerts.channels', ['log']);

    // Use a real AlertService — it will check the 'enabled' config and bail early
    $alertService = new AlertService();

    // Spy on Log to verify no warning (alert) is sent
    \Illuminate\Support\Facades\Log::spy();

    $recorder = new EventRecorder(
        app(\GladeHQ\LaravelEventLens\Watchers\WatcherManager::class),
        app(EventLensBuffer::class),
        app(\GladeHQ\LaravelEventLens\Collectors\EventCollector::class),
        app(\GladeHQ\LaravelEventLens\Services\RequestContextResolver::class),
        app(\GladeHQ\LaravelEventLens\Services\SlaChecker::class),
        app(\GladeHQ\LaravelEventLens\Services\SchemaTracker::class),
        app(\GladeHQ\LaravelEventLens\Services\NplusOneDetector::class),
        $alertService,
    );

    $recorder->pushCorrelationContext('shared-corr');

    for ($i = 0; $i < 3; $i++) {
        $recorder->capture(TestEvent::class, 'Closure', [new TestEvent()], fn () => true);
    }

    $recorder->popCorrelationContext();
    app(EventLensBuffer::class)->flush();

    // No alert-level log should have been sent (only persist warnings allowed)
    \Illuminate\Support\Facades\Log::shouldNotHaveReceived('warning', function ($msg) {
        return str_contains($msg, '[EventLens] storm');
    });
});
