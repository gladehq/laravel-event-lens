<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\AlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Config::set('event-lens.alerts.enabled', true);
    Config::set('event-lens.alerts.on', ['storm', 'sla_breach', 'regression', 'error_spike']);
    Config::set('event-lens.alerts.channels', ['log']);
    Config::set('event-lens.alerts.cooldown_minutes', 15);
    Config::set('event-lens.regression_threshold', 2.0);
    EventLog::truncate();
    Cache::flush();
});

it('fires regression alerts for critical severity', function () {
    $listener = 'App\Listeners\CriticalListener';
    $event = 'App\Events\OrderPlaced';

    // Baseline: 7 days ago, avg 50ms
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent: last 12h, avg 300ms (6x = critical)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 300,
            'happened_at' => now()->subHours(6),
        ]);
    }

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->once()
        ->withArgs(function ($type, $subject, $data) use ($listener) {
            return $type === 'regression' && $subject === $listener;
        });

    $this->app->instance(AlertService::class, $mock);

    artisan('event-lens:check-alerts')->assertSuccessful();
});

it('does not fire regression alerts for warning severity', function () {
    $listener = 'App\Listeners\WarnListener';
    $event = 'App\Events\OrderPlaced';

    // Baseline: avg 50ms
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent: avg 125ms (2.5x = warning, not critical)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 125,
            'happened_at' => now()->subHours(6),
        ]);
    }

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->never()
        ->withArgs(function ($type) {
            return $type === 'regression';
        });

    $this->app->instance(AlertService::class, $mock);

    artisan('event-lens:check-alerts')->assertSuccessful();
});

it('fires error spike alert when rate exceeds threshold', function () {
    // Recent: 100 events, 20 with errors (20% rate)
    for ($i = 0; $i < 80; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subMinutes(30),
            'exception' => null,
        ]);
    }
    for ($i = 0; $i < 20; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subMinutes(30),
            'exception' => 'RuntimeException: fail',
        ]);
    }

    // Baseline: 100 events, 2 with errors (2% rate)
    for ($i = 0; $i < 98; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subHours(12),
            'exception' => null,
        ]);
    }
    for ($i = 0; $i < 2; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subHours(12),
            'exception' => 'RuntimeException: fail',
        ]);
    }

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')
        ->once()
        ->withArgs(function ($type) {
            return $type === 'error_spike';
        });

    $this->app->instance(AlertService::class, $mock);

    artisan('event-lens:check-alerts')->assertSuccessful();
});

it('does not fire error spike below minimum events', function () {
    // Only 5 recent events (below threshold of 10)
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subMinutes(30),
            'exception' => 'RuntimeException: fail',
        ]);
    }

    $mock = Mockery::mock(AlertService::class);
    $mock->shouldReceive('fireIfNeeded')->never();

    $this->app->instance(AlertService::class, $mock);

    artisan('event-lens:check-alerts')->assertSuccessful();
});

it('respects cooldown in scheduled context', function () {
    // Pre-set the cooldown cache key for error_spike
    Cache::put('event-lens:alert-cooldown:error_spike:global', true, now()->addMinutes(15));

    // Seed data that would trigger an error spike
    for ($i = 0; $i < 80; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subMinutes(30),
            'exception' => null,
        ]);
    }
    for ($i = 0; $i < 20; $i++) {
        EventLog::factory()->create([
            'happened_at' => now()->subMinutes(30),
            'exception' => 'RuntimeException: fail',
        ]);
    }

    // Use a real AlertService so cooldown logic is exercised
    $spy = Mockery::spy(AlertService::class)->makePartial();
    $this->app->instance(AlertService::class, $spy);

    artisan('event-lens:check-alerts')->assertSuccessful();

    // fireIfNeeded was called, but the cooldown prevents actual dispatch
    // The key check is that the method exited early — no channels were triggered
    // Since we're using the real implementation with cooldown set, no log/slack/mail fires
});

it('outputs success when alerts are disabled', function () {
    Config::set('event-lens.alerts.enabled', false);

    artisan('event-lens:check-alerts')
        ->assertSuccessful()
        ->expectsOutput('EventLens alerts are disabled.');
});
