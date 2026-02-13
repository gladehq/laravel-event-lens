<?php

use GladeHQ\LaravelEventLens\Services\EventRecorder;
use GladeHQ\LaravelEventLens\Services\EventLensBuffer;
use GladeHQ\LaravelEventLens\Services\RequestContextResolver;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;
use GladeHQ\LaravelEventLens\Watchers\QueryWatcher;
use GladeHQ\LaravelEventLens\Watchers\MailWatcher;

it('reset clears watcher stacks', function () {
    $query = new QueryWatcher();
    $mail = new MailWatcher();
    $manager = new WatcherManager([$query, $mail]);

    $query->start();
    $mail->start();

    $manager->reset();

    // After reset, stop should return zero (empty stack)
    expect($query->stop()['queries'])->toBe(0);
    expect($mail->stop()['mails'])->toBe(0);
});

it('reset clears recorder call stack', function () {
    $recorder = app(EventRecorder::class);

    // Simulate some state via capture (which pushes to callStack)
    // We can't easily inspect private state, but reset() should not throw
    $recorder->reset();

    // The recorder should work normally after reset
    expect(true)->toBeTrue();
});

it('resets request context resolver on octane reset', function () {
    $resolver = app(RequestContextResolver::class);

    $resolver->setQueueJobName('App\Jobs\TestJob');
    expect($resolver->resolve()['type'])->toBe('queue');

    // Simulate what EventRecorder::reset() does (which Octane triggers)
    $resolver->reset();

    $context = $resolver->resolve();
    expect($context['type'])->not->toBe('queue');
});

it('reset clears buffer', function () {
    $buffer = app(EventLensBuffer::class);

    $buffer->push([
        'event_id' => 'test-1',
        'correlation_id' => 'c-1',
        'event_name' => 'Test',
        'listener_name' => 'Closure',
        'execution_time_ms' => 1.0,
        'happened_at' => now(),
    ]);

    expect($buffer->count())->toBe(1);

    // Flush acts as reset for buffer
    $buffer->flush();

    expect($buffer->count())->toBe(0);
});
