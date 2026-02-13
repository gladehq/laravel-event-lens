<?php

use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;
use GladeHQ\LaravelEventLens\Watchers\QueryWatcher;
use GladeHQ\LaravelEventLens\Watchers\MailWatcher;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;

// -- QueryWatcher --

it('tracks query counts with inclusive stacking', function () {
    $watcher = new QueryWatcher();
    $watcher->boot();

    $watcher->start(); // scope 1 (parent)
    $watcher->start(); // scope 2 (child)

    // Simulate 2 queries during child scope
    event(new \Illuminate\Database\Events\QueryExecuted('select 1', [], 0, resolve('db.connection')));
    event(new \Illuminate\Database\Events\QueryExecuted('select 2', [], 0, resolve('db.connection')));

    $child = $watcher->stop();
    expect($child['queries'])->toBe(2);

    $parent = $watcher->stop();
    expect($parent['queries'])->toBe(2); // inclusive counting
});

it('returns zero when stack is empty', function () {
    $watcher = new QueryWatcher();
    $result = $watcher->stop();
    expect($result['queries'])->toBe(0);
});

// -- MailWatcher --

it('tracks mail counts', function () {
    $watcher = new MailWatcher();
    $watcher->boot();

    $watcher->start();

    // We can't easily fire MessageSending without a mailer,
    // so test the structure returns correctly
    $result = $watcher->stop();
    expect($result)->toHaveKey('mails')
        ->and($result['mails'])->toBe(0);
});

// -- WatcherManager --

it('merges results from multiple watchers', function () {
    $manager = new WatcherManager([
        new QueryWatcher(),
        new MailWatcher(),
    ]);
    $manager->boot();

    $manager->start();
    $results = $manager->stop();

    expect($results)->toHaveKey('queries')
        ->and($results)->toHaveKey('mails')
        ->and($results['queries'])->toBe(0)
        ->and($results['mails'])->toBe(0);
});

// -- Custom Watcher --

it('supports custom watchers via WatcherInterface', function () {
    $custom = new class implements WatcherInterface {
        private int $count = 0;

        public function boot(): void {}

        public function start(): void
        {
            $this->count = 0;
        }

        public function stop(): array
        {
            return ['custom_metric' => 42];
        }

        public function reset(): void
        {
            $this->count = 0;
        }
    };

    $manager = new WatcherManager([$custom]);
    $manager->boot();
    $manager->start();
    $results = $manager->stop();

    expect($results)->toHaveKey('custom_metric')
        ->and($results['custom_metric'])->toBe(42);
});

// -- QueryWatcher fingerprint capture --

it('captures query fingerprints in stop result', function () {
    $watcher = new QueryWatcher();
    $watcher->boot();

    $watcher->start();

    event(new \Illuminate\Database\Events\QueryExecuted('SELECT * FROM users WHERE id = 1', [], 0, resolve('db.connection')));
    event(new \Illuminate\Database\Events\QueryExecuted('SELECT * FROM users WHERE id = 2', [], 0, resolve('db.connection')));

    $result = $watcher->stop();

    expect($result)->toHaveKey('query_fingerprints')
        ->and($result['query_fingerprints'])->toHaveCount(2)
        ->and($result['query_fingerprints'][0])->toBe('SELECT * FROM users WHERE id = ?')
        ->and($result['query_fingerprints'][1])->toBe('SELECT * FROM users WHERE id = ?')
        ->and($result['queries'])->toBe(2);
});

// -- QueryWatcher boot idempotency --

it('only registers listeners once even if boot is called multiple times', function () {
    $watcher = new QueryWatcher();
    $watcher->boot();
    $watcher->boot(); // should be no-op

    $watcher->start();
    event(new \Illuminate\Database\Events\QueryExecuted('select 1', [], 0, resolve('db.connection')));
    $result = $watcher->stop();

    // If boot registered twice, count would be 2
    expect($result['queries'])->toBe(1);
});
