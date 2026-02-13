<?php

use GladeHQ\LaravelEventLens\Testing\AssertsEventLens;
use GladeHQ\LaravelEventLens\Testing\InMemoryBuffer;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Lightweight helper that exposes the trait methods for direct testing.
 */
class AssertionHelper
{
    use AssertsEventLens {
        setUpEventLens as public;
        tearDownEventLens as public;
        assertEventDispatched as public;
        assertEventNotDispatched as public;
        assertEventLensChain as public;
        assertListenerExecuted as public;
        assertListenerUnder as public;
        assertNoExceptions as public;
    }
}

// -- InMemoryBuffer unit tests --

it('captures events in memory buffer', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push(['event_name' => 'App\\Events\\OrderPlaced', 'listener_name' => 'Closure']);
    $buffer->push(['event_name' => 'App\\Events\\OrderShipped', 'listener_name' => 'Closure']);

    expect($buffer->count())->toBe(2)
        ->and(InMemoryBuffer::records())->toHaveCount(2);

    // flush is a no-op -- data stays
    $buffer->flush();
    expect(InMemoryBuffer::records())->toHaveCount(2);

    InMemoryBuffer::reset();
    expect(InMemoryBuffer::records())->toHaveCount(0);
});

// -- AssertsEventLens trait tests --

it('asserts event dispatched successfully', function () {
    InMemoryBuffer::reset();
    InMemoryBuffer::reset(); // idempotent

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
        'correlation_id' => 'c-1',
        'execution_time_ms' => 5.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();
    // Should not throw
    $helper->assertEventDispatched('App\\Events\\OrderPlaced');
});

it('fails assertion when event not dispatched', function () {
    InMemoryBuffer::reset();

    $helper = new AssertionHelper();

    expect(fn () => $helper->assertEventDispatched('App\\Events\\NonExistent'))
        ->toThrow(ExpectationFailedException::class);
});

it('asserts event not dispatched', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
        'correlation_id' => 'c-1',
        'execution_time_ms' => 5.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();

    // OrderShipped was never pushed
    $helper->assertEventNotDispatched('App\\Events\\OrderShipped');

    // OrderPlaced exists -- this should fail
    expect(fn () => $helper->assertEventNotDispatched('App\\Events\\OrderPlaced'))
        ->toThrow(ExpectationFailedException::class);
});

it('asserts event chain with expected listeners', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();

    // Root event
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
        'correlation_id' => 'chain-1',
        'event_id' => 'root-1',
        'execution_time_ms' => 10.0,
        'exception' => null,
    ]);

    // Child listeners
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\SendConfirmation',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'chain-1',
        'event_id' => 'child-1',
        'execution_time_ms' => 3.0,
        'exception' => null,
    ]);

    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\UpdateInventory',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'chain-1',
        'event_id' => 'child-2',
        'execution_time_ms' => 2.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();

    $helper->assertEventLensChain('App\\Events\\OrderPlaced', [
        'App\\Listeners\\SendConfirmation',
        'App\\Listeners\\UpdateInventory',
    ]);
});

it('asserts listener executed', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\SendConfirmation',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'c-1',
        'execution_time_ms' => 4.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();
    $helper->assertListenerExecuted('App\\Listeners\\SendConfirmation');
});

it('fails assertion when listener not executed', function () {
    InMemoryBuffer::reset();

    $helper = new AssertionHelper();

    expect(fn () => $helper->assertListenerExecuted('App\\Listeners\\Ghost'))
        ->toThrow(ExpectationFailedException::class);
});

it('asserts listener under time threshold', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\FastListener',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'c-1',
        'execution_time_ms' => 5.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();
    $helper->assertListenerUnder('App\\Listeners\\FastListener', 10.0);
});

it('fails assertion when listener exceeds threshold', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\SlowListener',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'c-1',
        'execution_time_ms' => 250.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();

    expect(fn () => $helper->assertListenerUnder('App\\Listeners\\SlowListener', 100.0))
        ->toThrow(ExpectationFailedException::class);
});

it('asserts no exceptions', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
        'correlation_id' => 'c-1',
        'execution_time_ms' => 5.0,
        'exception' => null,
    ]);

    $helper = new AssertionHelper();
    $helper->assertNoExceptions();
});

it('fails assertion when exception exists', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'App\\Listeners\\BrokenListener',
        'parent_event_id' => 'root-1',
        'correlation_id' => 'c-1',
        'execution_time_ms' => 1.0,
        'exception' => 'RuntimeException: Something went wrong',
    ]);

    $helper = new AssertionHelper();

    expect(fn () => $helper->assertNoExceptions())
        ->toThrow(ExpectationFailedException::class);
});

it('resets between calls', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push(['event_name' => 'App\\Events\\First', 'listener_name' => 'Closure']);

    expect(InMemoryBuffer::records())->toHaveCount(1);

    InMemoryBuffer::reset();
    expect(InMemoryBuffer::records())->toHaveCount(0);

    $buffer->push(['event_name' => 'App\\Events\\Second', 'listener_name' => 'Closure']);
    expect(InMemoryBuffer::records())->toHaveCount(1);
    expect(InMemoryBuffer::records()[0]['event_name'])->toBe('App\\Events\\Second');
});

it('supports callback on assertEventDispatched', function () {
    InMemoryBuffer::reset();

    $buffer = new InMemoryBuffer();
    $buffer->push([
        'event_name' => 'App\\Events\\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
        'correlation_id' => 'c-1',
        'execution_time_ms' => 42.0,
        'exception' => null,
    ]);

    $callbackInvoked = false;

    $helper = new AssertionHelper();
    $helper->assertEventDispatched('App\\Events\\OrderPlaced', function (array $record) use (&$callbackInvoked) {
        $callbackInvoked = true;
        expect($record['execution_time_ms'])->toBe(42.0);
        expect($record['listener_name'])->toBe('Event::dispatch');
    });

    expect($callbackInvoked)->toBeTrue();
});

// -- Integration: setUpEventLens swaps the buffer on the recorder --

it('swaps buffer on recorder via setUpEventLens', function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['GladeHQ\\LaravelEventLens\\Tests\\Fixtures\\*']);

    $helper = new AssertionHelper();
    $helper->setUpEventLens();

    Event::listen(TestEvent::class, function ($event) {
        return 'handled';
    });

    event(new TestEvent());

    // Records should be in the InMemoryBuffer, not the database
    expect(InMemoryBuffer::records())->not->toBeEmpty();

    $eventNames = collect(InMemoryBuffer::records())->pluck('event_name')->toArray();
    expect($eventNames)->toContain(TestEvent::class);

    $helper->tearDownEventLens();
    expect(InMemoryBuffer::records())->toBeEmpty();
});
