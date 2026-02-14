<?php

use GladeHQ\LaravelEventLens\Watchers\HttpWatcher;
use GladeHQ\LaravelEventLens\Watchers\QueryWatcher;
use GladeHQ\LaravelEventLens\Watchers\MailWatcher;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request as HttpClientRequest;
use GuzzleHttp\Psr7\Request as Psr7Request;

function fireRequestSending(): void
{
    $psr7 = new Psr7Request('GET', 'https://example.com');
    $request = new HttpClientRequest($psr7);

    event(new RequestSending($request));
}

// -- HttpWatcher --

it('tracks http call counts with inclusive stacking', function () {
    $watcher = new HttpWatcher();
    $watcher->boot();

    $watcher->start(); // scope 1 (parent)
    $watcher->start(); // scope 2 (child)

    fireRequestSending();
    fireRequestSending();

    $child = $watcher->stop();
    expect($child['http_calls'])->toBe(2);

    $parent = $watcher->stop();
    expect($parent['http_calls'])->toBe(2); // inclusive counting
});

it('returns zero when stack is empty', function () {
    $watcher = new HttpWatcher();
    $result = $watcher->stop();
    expect($result['http_calls'])->toBe(0);
});

it('returns http_calls key from stop', function () {
    $watcher = new HttpWatcher();
    $watcher->boot();
    $watcher->start();

    $result = $watcher->stop();

    expect($result)->toHaveKey('http_calls')
        ->and($result['http_calls'])->toBe(0);
});

it('only registers listeners once even if boot called multiple times', function () {
    $watcher = new HttpWatcher();
    $watcher->boot();
    $watcher->boot(); // should be no-op

    $watcher->start();
    fireRequestSending();
    $result = $watcher->stop();

    // If boot registered twice, count would be 2
    expect($result['http_calls'])->toBe(1);
});

it('resets all state on reset', function () {
    $watcher = new HttpWatcher();
    $watcher->boot();

    $watcher->start();
    fireRequestSending();

    $watcher->reset();

    // Stack is empty after reset, so stop returns 0
    $result = $watcher->stop();
    expect($result['http_calls'])->toBe(0);
});

it('integrates with WatcherManager', function () {
    $manager = new WatcherManager([
        new QueryWatcher(),
        new MailWatcher(),
        new HttpWatcher(),
    ]);
    $manager->boot();

    $manager->start();
    $results = $manager->stop();

    expect($results)->toHaveKey('queries')
        ->and($results)->toHaveKey('mails')
        ->and($results)->toHaveKey('http_calls')
        ->and($results['queries'])->toBe(0)
        ->and($results['mails'])->toBe(0)
        ->and($results['http_calls'])->toBe(0);
});

it('counts zero when no http events fire', function () {
    $watcher = new HttpWatcher();
    $watcher->boot();

    $watcher->start();
    // No events fired
    $result = $watcher->stop();

    expect($result['http_calls'])->toBe(0);
});
