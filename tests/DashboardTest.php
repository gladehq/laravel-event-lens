<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Tests\Fixtures\TestEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\get;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', [
        'GladeHQ\LaravelEventLens\Tests\Fixtures\*',
        'event.*',
    ]);
    EventLog::truncate();
});

// -- Authorization Tests --

it('denies dashboard access when authorization returns false', function () {
    Config::set('event-lens.authorization', fn () => false);

    // Re-register the gate with updated config
    Gate::define('viewEventLens', function ($user = null) {
        $callback = config('event-lens.authorization');
        return is_callable($callback) ? $callback($user) : false;
    });

    get(route('event-lens.index'))->assertForbidden();
});

it('allows dashboard access when authorization returns true', function () {
    Config::set('event-lens.authorization', fn () => true);

    get(route('event-lens.index'))->assertOk();
});

// -- Index / Stream Tests --

it('can filter events by name', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\UserRegistered', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['event' => 'OrderPlaced']))
        ->assertOk()
        ->assertSee('OrderPlaced')
        ->assertDontSee('UserRegistered');
});

it('can filter slow events only', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Fast', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\Slow', 'listener_name' => 'Closure', 'execution_time_ms' => 500, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['slow' => '1']))
        ->assertOk()
        ->assertSee('Slow')
        ->assertDontSee('App\Events\Fast');
});

// -- Statistics Page Tests --

it('can view the statistics page', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'execution_time_ms' => 50, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertViewIs('event-lens::statistics')
        ->assertViewHas('stats');
});

// -- Detail Page Tests --

it('can view the event detail page', function () {
    EventLog::insert([
        ['event_id' => 'evt-123', 'correlation_id' => 'cor-123', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'payload' => json_encode(['key' => 'value']), 'side_effects' => json_encode(['queries' => 3]), 'execution_time_ms' => 42.5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.detail', 'evt-123'))
        ->assertOk()
        ->assertViewIs('event-lens::detail')
        ->assertSee('evt-123')
        ->assertSee('42.5');
});

it('returns 404 for non-existent event detail', function () {
    get(route('event-lens.detail', 'non-existent'))
        ->assertNotFound();
});

// -- Waterfall Tests --

it('returns 404 for non-existent correlation', function () {
    get(route('event-lens.show', 'non-existent'))
        ->assertNotFound();
});

it('shows totalMails in the waterfall view', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'cor-mail', 'event_name' => 'App\Events\Mail', 'listener_name' => 'Closure', 'side_effects' => json_encode(['queries' => 1, 'mails' => 2]), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.show', 'cor-mail'))
        ->assertOk()
        ->assertViewHas('totalMails', 2);
});

// -- Polling API Tests --

it('returns events after a given id', function () {
    EventLog::insert([
        ['event_id' => 'e-old', 'correlation_id' => 'c-old', 'event_name' => 'App\Events\Old', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $oldId = EventLog::first()->id;

    EventLog::insert([
        ['event_id' => 'e-new', 'correlation_id' => 'c-new', 'event_name' => 'App\Events\New', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.api.latest', ['after_id' => $oldId]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
