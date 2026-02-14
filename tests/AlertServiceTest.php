<?php

use GladeHQ\LaravelEventLens\Services\AlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Config::set('event-lens.alerts.enabled', true);
    Config::set('event-lens.alerts.on', ['storm', 'sla_breach', 'regression', 'error_spike']);
    Config::set('event-lens.alerts.cooldown_minutes', 15);
    Config::set('event-lens.alerts.channels', ['log']);
    Cache::flush();
});

it('fires alert on first occurrence', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $data) {
            return str_contains($message, '[EventLens] storm: App\Events\Order');
        });

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);
});

it('skips alert when on cooldown', function () {
    Log::shouldReceive('warning')->once();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['count' => 1]);
    $service->fireIfNeeded('storm', 'App\Events\Order', ['count' => 2]);
});

it('fires again after cooldown expires', function () {
    Log::shouldReceive('warning')->twice();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['count' => 1]);

    $this->travel(16)->minutes();

    $service->fireIfNeeded('storm', 'App\Events\Order', ['count' => 2]);
});

it('skips when alerts are disabled', function () {
    Config::set('event-lens.alerts.enabled', false);

    Log::shouldReceive('warning')->never();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['count' => 1]);
});

it('skips when alert type is not in on list', function () {
    Config::set('event-lens.alerts.on', ['storm']);

    Log::shouldReceive('warning')->never();

    $service = new AlertService();
    $service->fireIfNeeded('sla_breach', 'App\Events\Order::Listener', ['budget_ms' => 200]);
});

it('sends slack webhook POST', function () {
    Config::set('event-lens.alerts.channels', ['slack']);
    Config::set('event-lens.alerts.slack_webhook', 'https://hooks.slack.com/test');

    Http::fake([
        'hooks.slack.com/*' => Http::response([], 200),
    ]);

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.slack.com/test'
            && str_contains($request->data()['text'], '[EventLens] storm');
    });
});

it('sends mail via Mail facade', function () {
    Config::set('event-lens.alerts.channels', ['mail']);
    Config::set('event-lens.alerts.mail_to', 'admin@example.com');

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(function ($body, $callback) {
            return str_contains($body, '[EventLens] storm');
        });

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);
});

it('writes to log channel', function () {
    Config::set('event-lens.alerts.channels', ['log']);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $data) {
            return str_contains($message, '[EventLens] storm')
                && isset($data['event']);
        });

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);
});

it('handles channel failure gracefully', function () {
    Config::set('event-lens.alerts.channels', ['slack']);
    Config::set('event-lens.alerts.slack_webhook', 'https://hooks.slack.com/test');

    Http::fake(function () {
        throw new \RuntimeException('Connection refused');
    });

    Log::shouldReceive('debug')->once();

    $service = new AlertService();

    // Should not throw
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);
});

it('uses independent cooldown keys per type and subject', function () {
    Log::shouldReceive('warning')->twice();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\A', ['event' => 'A']);
    $service->fireIfNeeded('storm', 'App\Events\B', ['event' => 'B']);
});

it('dispatches to multiple channels simultaneously', function () {
    Config::set('event-lens.alerts.channels', ['log', 'slack']);
    Config::set('event-lens.alerts.slack_webhook', 'https://hooks.slack.com/test');

    Http::fake([
        'hooks.slack.com/*' => Http::response([], 200),
    ]);

    Log::shouldReceive('warning')->once();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\Events\Order', ['event' => 'App\Events\Order']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.slack.com/test';
    });
});

it('uses atomic cache add for cooldown', function () {
    Cache::shouldReceive('add')
        ->once()
        ->with('event-lens:alert-cooldown:storm:App\\Events\\Order', true, Mockery::type(\DateTimeInterface::class))
        ->andReturn(true);

    // Let Log::warning pass through
    Log::shouldReceive('warning')->once();

    $service = new AlertService();
    $service->fireIfNeeded('storm', 'App\\Events\\Order', ['event' => 'App\\Events\\Order']);
});
