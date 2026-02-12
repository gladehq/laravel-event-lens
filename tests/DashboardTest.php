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

// -- Validation Tests --

it('rejects invalid date format on index', function () {
    get(route('event-lens.index', ['start_date' => 'not-a-date']))
        ->assertInvalid(['start_date']);
});

it('rejects oversized event filter string', function () {
    get(route('event-lens.index', ['event' => str_repeat('x', 256)]))
        ->assertInvalid(['event']);
});

it('rejects invalid date format on statistics', function () {
    get(route('event-lens.statistics', ['start_date' => 'not-a-date']))
        ->assertInvalid(['start_date']);
});

it('rejects invalid after_id on latest endpoint', function () {
    get(route('event-lens.api.latest', ['after_id' => 'abc']))
        ->assertInvalid(['after_id']);
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

it('can filter events by payload content', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'payload' => json_encode(['order_id' => 42, 'customer' => 'Alice']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\UserRegistered', 'listener_name' => 'Closure', 'payload' => json_encode(['user_id' => 99]), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['payload' => 'Alice']))
        ->assertOk()
        ->assertSee('OrderPlaced')
        ->assertDontSee('UserRegistered');
});

it('can filter events by tag content', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'tags' => json_encode(['user_status' => 'active']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\UserRegistered', 'listener_name' => 'Closure', 'tags' => json_encode(['priority' => 'low']), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['tag' => 'active']))
        ->assertOk()
        ->assertSee('OrderPlaced')
        ->assertDontSee('UserRegistered');
});

it('shows payload summary on index page', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'payload' => json_encode(['order_id' => 42, 'status' => 'pending']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('order_id: 42')
        ->assertSee('status: pending');
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

it('displays structured payload on detail page with array __context', function () {
    EventLog::insert([
        ['event_id' => 'evt-ctx', 'correlation_id' => 'cor-ctx', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'payload' => json_encode(['order_id' => 1, '__context' => ['file' => 'OrderController.php', 'line' => 42]]), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.detail', 'evt-ctx'))
        ->assertOk()
        ->assertSee('order_id')
        ->assertSee('Triggered from:')
        ->assertDontSee('__context');
});

it('displays structured payload on detail page', function () {
    EventLog::insert([
        ['event_id' => 'evt-structured', 'correlation_id' => 'cor-1', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'payload' => json_encode(['status' => 'active', 'amount' => 99.5, 'items' => [1, 2, 3], 'meta' => ['foo' => 'bar']]), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.detail', 'evt-structured'))
        ->assertOk()
        ->assertSee('active')
        ->assertSee('99.5')
        ->assertSee('Expand (3 items)')
        ->assertSee('Expand (1 keys)');
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

// -- Tags Display Tests --

it('shows tags badge on index page', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Tagged', 'listener_name' => 'Closure', 'tags' => json_encode(['env' => 'production']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('Tags');
});

it('shows tags on detail page', function () {
    EventLog::insert([
        ['event_id' => 'evt-tags', 'correlation_id' => 'cor-tags', 'event_name' => 'App\Events\Tagged', 'listener_name' => 'Closure', 'tags' => json_encode(['env' => 'production', 'priority' => 'high']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.detail', 'evt-tags'))
        ->assertOk()
        ->assertSee('env')
        ->assertSee('production')
        ->assertSee('priority')
        ->assertSee('high');
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
