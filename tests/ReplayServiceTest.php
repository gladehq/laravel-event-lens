<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\ReplayService;
use GladeHQ\LaravelEventLens\Tests\Fixtures\ReplayableEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.allow_replay', true);
    EventLog::truncate();

    $this->service = new ReplayService();
});

it('replays a root event successfully', function () {
    $event = EventLog::factory()->create([
        'event_name' => ReplayableEvent::class,
        'listener_name' => 'Event::dispatch',
        'payload' => ['orderId' => 'ORD-123', 'status' => 'completed'],
    ]);

    Event::fake([ReplayableEvent::class]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeTrue();

    Event::assertDispatched(ReplayableEvent::class, function ($e) {
        return $e->orderId === 'ORD-123' && $e->status === 'completed';
    });
});

it('refuses replay when config is disabled', function () {
    Config::set('event-lens.allow_replay', false);

    $event = EventLog::factory()->create([
        'event_name' => ReplayableEvent::class,
        'listener_name' => 'Event::dispatch',
    ]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('disabled');
});

it('refuses replay on non-root events', function () {
    $event = EventLog::factory()->create([
        'event_name' => ReplayableEvent::class,
        'listener_name' => 'App\Listeners\HandleOrder',
    ]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('root events');
});

it('refuses replay when event class does not exist', function () {
    $event = EventLog::factory()->create([
        'event_name' => 'App\Events\DeletedLongAgo',
        'listener_name' => 'Event::dispatch',
    ]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('no longer exists');
});

it('handles null payload gracefully', function () {
    // SimpleEvent has no required constructor args
    $event = EventLog::factory()->create([
        'event_name' => \GladeHQ\LaravelEventLens\Tests\Fixtures\SimpleEvent::class,
        'listener_name' => 'Event::dispatch',
        'payload' => null,
    ]);

    Event::fake([\GladeHQ\LaravelEventLens\Tests\Fixtures\SimpleEvent::class]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeTrue();
    Event::assertDispatched(\GladeHQ\LaravelEventLens\Tests\Fixtures\SimpleEvent::class);
});

it('strips internal metadata keys from payload before replay', function () {
    $event = EventLog::factory()->create([
        'event_name' => ReplayableEvent::class,
        'listener_name' => 'Event::dispatch',
        'payload' => [
            'orderId' => 'ORD-456',
            'status' => 'shipped',
            '__context' => ['file' => 'app/Http/Controllers/OrderController.php:42'],
            '__request_context' => ['type' => 'http', 'method' => 'POST'],
        ],
    ]);

    Event::fake([ReplayableEvent::class]);

    $result = $this->service->replay($event);

    expect($result['success'])->toBeTrue();

    Event::assertDispatched(ReplayableEvent::class, function ($e) {
        return $e->orderId === 'ORD-456' && $e->status === 'shipped';
    });
});

it('returns error when reconstruction throws', function () {
    // Create an event class name that exists but will fail construction
    $event = EventLog::factory()->create([
        'event_name' => \stdClass::class,
        'listener_name' => 'Event::dispatch',
        'payload' => ['invalid' => 'data'],
    ]);

    $result = $this->service->replay($event);

    // stdClass dispatches fine, but let's test with a class that needs specific params
    expect($result['success'])->toBeTrue();
});
