<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Testing;

use Closure;
use GladeHQ\LaravelEventLens\Services\EventRecorder;
use PHPUnit\Framework\Assert;
use ReflectionProperty;

trait AssertsEventLens
{
    protected function setUpEventLens(): void
    {
        $buffer = new InMemoryBuffer();

        // Swap the buffer on the already-resolved EventRecorder via reflection.
        // This is a testing utility -- reaching into internals is expected.
        $recorder = app(EventRecorder::class);
        $property = new ReflectionProperty(EventRecorder::class, 'buffer');
        $property->setValue($recorder, $buffer);

        InMemoryBuffer::reset();
    }

    protected function tearDownEventLens(): void
    {
        InMemoryBuffer::reset();
    }

    /**
     * Assert that a given event class was dispatched and recorded by EventLens.
     */
    protected function assertEventDispatched(string $eventClass, ?Closure $callback = null): void
    {
        $matching = $this->findRecordsForEvent($eventClass);

        Assert::assertNotEmpty(
            $matching,
            "Expected event [{$eventClass}] to be dispatched, but it was not recorded."
        );

        if ($callback !== null) {
            foreach ($matching as $record) {
                $callback($record);
            }
        }
    }

    /**
     * Assert that a given event class was NOT dispatched.
     */
    protected function assertEventNotDispatched(string $eventClass): void
    {
        $matching = $this->findRecordsForEvent($eventClass);

        Assert::assertEmpty(
            $matching,
            "Unexpected event [{$eventClass}] was dispatched."
        );
    }

    /**
     * Assert that an event triggered the expected chain of listeners in order.
     */
    protected function assertEventLensChain(string $eventClass, array $expectedListeners): void
    {
        $root = collect(InMemoryBuffer::records())
            ->first(fn (array $r) => $r['event_name'] === $eventClass && $r['parent_event_id'] === null);

        Assert::assertNotNull(
            $root,
            "No root event found for [{$eventClass}]."
        );

        $children = collect(InMemoryBuffer::records())
            ->filter(fn (array $r) => $r['correlation_id'] === $root['correlation_id'] && $r['parent_event_id'] !== null)
            ->values();

        $actualListeners = $children->pluck('listener_name')->toArray();

        Assert::assertEquals(
            $expectedListeners,
            $actualListeners,
            "Event chain for [{$eventClass}] did not match expected listeners."
        );
    }

    /**
     * Assert that a listener class was executed at least once.
     */
    protected function assertListenerExecuted(string $listenerClass): void
    {
        $matching = collect(InMemoryBuffer::records())
            ->filter(fn (array $r) => $r['listener_name'] === $listenerClass);

        Assert::assertNotEmpty(
            $matching->toArray(),
            "Expected listener [{$listenerClass}] to be executed, but it was not recorded."
        );
    }

    /**
     * Assert that all executions of a listener completed within a time threshold.
     */
    protected function assertListenerUnder(string $listenerClass, float $maxMs): void
    {
        $matching = collect(InMemoryBuffer::records())
            ->filter(fn (array $r) => $r['listener_name'] === $listenerClass);

        Assert::assertNotEmpty(
            $matching->toArray(),
            "No records found for listener [{$listenerClass}]."
        );

        foreach ($matching as $record) {
            Assert::assertLessThanOrEqual(
                $maxMs,
                $record['execution_time_ms'],
                "Listener [{$listenerClass}] took {$record['execution_time_ms']}ms, exceeding {$maxMs}ms threshold."
            );
        }
    }

    /**
     * Assert that no recorded events contain exceptions.
     */
    protected function assertNoExceptions(): void
    {
        $withExceptions = collect(InMemoryBuffer::records())
            ->filter(fn (array $r) => ! empty($r['exception']));

        Assert::assertEmpty(
            $withExceptions->toArray(),
            'Expected no exceptions, but found: ' . $withExceptions->pluck('exception')->implode(', ')
        );
    }

    /**
     * Find all in-memory records matching an event class name.
     */
    protected function findRecordsForEvent(string $eventClass): array
    {
        return collect(InMemoryBuffer::records())
            ->filter(fn (array $r) => $r['event_name'] === $eventClass)
            ->values()
            ->toArray();
    }
}
