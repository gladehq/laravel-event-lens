<?php

use GladeHQ\LaravelEventLens\Services\SlaChecker;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('event-lens.sla_budgets', [
        'App\Events\OrderPlaced' => 200,
        'App\Listeners\SendEmail' => 500,
        'App\Events\*' => 1000,
    ]);

    $this->checker = new SlaChecker();
});

it('resolves exact event name budget', function () {
    expect($this->checker->resolveBudget('App\Events\OrderPlaced', 'App\Listeners\Unknown'))
        ->toBe(200.0);
});

it('resolves exact listener name budget', function () {
    expect($this->checker->resolveBudget('App\Events\Unknown', 'App\Listeners\SendEmail'))
        ->toBe(500.0);
});

it('resolves wildcard budget', function () {
    expect($this->checker->resolveBudget('App\Events\UserRegistered', 'App\Listeners\Unknown'))
        ->toBe(1000.0);
});

it('prefers exact listener match over event match', function () {
    Config::set('event-lens.sla_budgets', [
        'App\Events\OrderPlaced' => 200,
        'App\Listeners\HandleOrder' => 300,
    ]);

    $checker = new SlaChecker();

    expect($checker->resolveBudget('App\Events\OrderPlaced', 'App\Listeners\HandleOrder'))
        ->toBe(300.0);
});

it('prefers exact match over wildcard', function () {
    expect($this->checker->resolveBudget('App\Events\OrderPlaced', 'App\Listeners\Unknown'))
        ->toBe(200.0);
});

it('returns null when no budget matches', function () {
    expect($this->checker->resolveBudget('Vendor\Package\SomeEvent', 'Vendor\Package\SomeListener'))
        ->toBeNull();
});

it('detects breach when duration exceeds budget', function () {
    $result = $this->checker->check('App\Events\OrderPlaced', 'App\Listeners\Unknown', 400.0);

    expect($result)->not->toBeNull()
        ->and($result['budget_ms'])->toBe(200.0)
        ->and($result['actual_ms'])->toBe(400.0)
        ->and($result['exceeded_by_pct'])->toBe(100.0);
});

it('returns null when within budget', function () {
    expect($this->checker->check('App\Events\OrderPlaced', 'App\Listeners\Unknown', 150.0))
        ->toBeNull();
});

it('handles empty sla_budgets config', function () {
    Config::set('event-lens.sla_budgets', []);

    $checker = new SlaChecker();

    expect($checker->resolveBudget('App\Events\OrderPlaced', 'App\Listeners\SendEmail'))
        ->toBeNull()
        ->and($checker->check('App\Events\OrderPlaced', 'App\Listeners\SendEmail', 9999.0))
        ->toBeNull();
});
