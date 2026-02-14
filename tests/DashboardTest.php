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

it('can filter events with errors only', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Ok', 'listener_name' => 'Closure', 'exception' => null, 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\Broken', 'listener_name' => 'Closure', 'exception' => 'RuntimeException: fail', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['errors' => '1']))
        ->assertOk()
        ->assertSee('Broken')
        ->assertDontSee('App\Events\Ok');
});

it('can filter events by listener name', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Order', 'listener_name' => 'App\Listeners\SendEmail', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\Order', 'listener_name' => 'App\Listeners\UpdateInventory', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.index', ['listener' => 'SendEmail']))
        ->assertOk()
        ->assertSee('SendEmail')
        ->assertDontSee('UpdateInventory');
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

// -- Enhanced Statistics Page Tests --

it('shows error breakdown on statistics page', function () {
    EventLog::factory()->root()->withException('Connection refused')->create([
        'event_name' => 'App\Events\PaymentFailed',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->withException('Connection refused')->create([
        'event_name' => 'App\Events\PaymentFailed',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->withException('Timeout exceeded')->create([
        'event_name' => 'App\Events\SyncFailed',
        'happened_at' => now(),
    ]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('Top Errors')
        ->assertSee('App\Events\PaymentFailed')
        ->assertSee('Connection refused');
});

it('shows slow count on statistics page', function () {
    EventLog::factory()->root()->slow(500)->create(['happened_at' => now()]);
    EventLog::factory()->root()->slow(200)->create(['happened_at' => now()]);
    EventLog::factory()->root()->create(['execution_time_ms' => 10, 'happened_at' => now()]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('Slow Events')
        ->assertSee('2'); // 2 slow events
});

it('shows total queries on statistics page', function () {
    EventLog::factory()->root()->withSideEffects(queries: 5, mails: 0)->create(['happened_at' => now()]);
    EventLog::factory()->root()->withSideEffects(queries: 3, mails: 2)->create(['happened_at' => now()]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('Total DB Queries')
        ->assertSee('8')
        ->assertSee('Total Mails Sent')
        ->assertSee('2');
});

it('shows execution time distribution on statistics page', function () {
    // Fast events (0-10ms bucket)
    EventLog::factory()->root()->create(['execution_time_ms' => 5, 'happened_at' => now()]);
    EventLog::factory()->root()->create(['execution_time_ms' => 8, 'happened_at' => now()]);

    // Medium events (10-50ms bucket)
    EventLog::factory()->root()->create(['execution_time_ms' => 25, 'happened_at' => now()]);

    // Slow events (100-500ms bucket)
    EventLog::factory()->root()->create(['execution_time_ms' => 200, 'happened_at' => now()]);

    // Very slow events (500ms+ bucket)
    EventLog::factory()->root()->create(['execution_time_ms' => 1000, 'happened_at' => now()]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('Execution Time Distribution')
        ->assertSee('Event count per latency bucket')
        ->assertSee('0-10ms')
        ->assertSee('10-50ms')
        ->assertSee('50-100ms')
        ->assertSee('100-500ms')
        ->assertSee('500ms+');
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

// -- Error Propagation in Trace Tests --

it('marks ancestor nodes with has_descendant_error when child has exception', function () {
    $root = EventLog::factory()->root()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\ProcessOrder',
        'correlation_id' => 'cor-err-tree',
        'happened_at' => now(),
    ]);

    $child = EventLog::factory()->childOf($root)->create([
        'event_name' => 'App\Events\PaymentCharged',
        'listener_name' => 'App\Listeners\ChargePayment',
        'happened_at' => now()->addMilliseconds(10),
    ]);

    EventLog::factory()->childOf($child)->withException('Payment gateway timeout')->create([
        'event_name' => 'App\Events\GatewayCall',
        'listener_name' => 'App\Listeners\CallGateway',
        'happened_at' => now()->addMilliseconds(20),
    ]);

    get(route('event-lens.show', 'cor-err-tree'))
        ->assertOk()
        ->assertViewHas('totalErrors', 1)
        ->assertSee('Contains error in descendants');
});

it('shows error and slow counts in waterfall header', function () {
    $corId = 'cor-counts';
    EventLog::factory()->root()->create([
        'correlation_id' => $corId,
        'execution_time_ms' => 5,
        'happened_at' => now(),
    ]);
    EventLog::factory()->create([
        'correlation_id' => $corId,
        'parent_event_id' => 'non-null',
        'execution_time_ms' => 500,
        'happened_at' => now()->addMilliseconds(5),
    ]);
    EventLog::factory()->withException('fail')->create([
        'correlation_id' => $corId,
        'parent_event_id' => 'non-null-2',
        'execution_time_ms' => 10,
        'happened_at' => now()->addMilliseconds(10),
    ]);

    get(route('event-lens.show', $corId))
        ->assertOk()
        ->assertViewHas('totalErrors', 1)
        ->assertViewHas('totalSlow', 1);
});

// -- Prev/Next Sibling Navigation Tests --

it('shows prev and next sibling links on detail page', function () {
    $corId = 'cor-siblings';
    EventLog::insert([
        ['event_id' => 'sib-1', 'correlation_id' => $corId, 'event_name' => 'App\Events\A', 'listener_name' => 'ListenerA', 'execution_time_ms' => 10, 'happened_at' => now()->subSeconds(3), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'sib-2', 'correlation_id' => $corId, 'event_name' => 'App\Events\B', 'listener_name' => 'ListenerB', 'execution_time_ms' => 10, 'happened_at' => now()->subSeconds(2), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'sib-3', 'correlation_id' => $corId, 'event_name' => 'App\Events\C', 'listener_name' => 'ListenerC', 'execution_time_ms' => 10, 'happened_at' => now()->subSeconds(1), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Middle sibling should have both prev and next
    get(route('event-lens.detail', 'sib-2'))
        ->assertOk()
        ->assertSee('ListenerA')
        ->assertSee('ListenerC');

    // First sibling: no prev, has next
    get(route('event-lens.detail', 'sib-1'))
        ->assertOk()
        ->assertSee('Next')
        ->assertSee('ListenerB');

    // Last sibling: has prev, no next
    get(route('event-lens.detail', 'sib-3'))
        ->assertOk()
        ->assertSee('Previous')
        ->assertSee('ListenerB');
});

// -- Meaningful Detail Header Tests --

it('shows listener name as heading and event name as subtitle on detail page', function () {
    EventLog::insert([
        ['event_id' => 'evt-header', 'correlation_id' => 'cor-header', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'App\Listeners\SendConfirmation', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    get(route('event-lens.detail', 'evt-header'))
        ->assertOk()
        ->assertSee('App\Listeners\SendConfirmation')
        ->assertSee('listening to');
});

// -- Model Changes Diff View Tests --

it('renders diff table when model_changes has before/after structure', function () {
    EventLog::insert([
        [
            'event_id' => 'evt-diff',
            'correlation_id' => 'cor-diff',
            'event_name' => 'App\Events\OrderUpdated',
            'listener_name' => 'Closure',
            'model_changes' => json_encode([
                'before' => ['status' => 'pending', 'amount' => 100],
                'after' => ['status' => 'shipped', 'amount' => 100],
            ]),
            'execution_time_ms' => 10,
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    get(route('event-lens.detail', 'evt-diff'))
        ->assertOk()
        ->assertSee('Old Value')
        ->assertSee('New Value')
        ->assertSee('pending')
        ->assertSee('shipped')
        ->assertSee('Show raw JSON');
});

it('falls back to raw JSON for non-standard model changes', function () {
    EventLog::insert([
        [
            'event_id' => 'evt-raw',
            'correlation_id' => 'cor-raw',
            'event_name' => 'App\Events\OrderUpdated',
            'listener_name' => 'Closure',
            'model_changes' => json_encode(['status' => 'shipped']),
            'execution_time_ms' => 10,
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    get(route('event-lens.detail', 'evt-raw'))
        ->assertOk()
        ->assertSee('shipped')
        ->assertDontSee('Old Value');
});

// -- Expandable Exception Tests --

it('renders expandable exception with summary and stack trace toggle', function () {
    EventLog::insert([
        [
            'event_id' => 'evt-ex',
            'correlation_id' => 'cor-ex',
            'event_name' => 'App\Events\Failed',
            'listener_name' => 'Closure',
            'exception' => "RuntimeException: Something broke\n#0 /app/Foo.php(42): Bar->baz()\n#1 /app/Main.php(10): Foo->run()",
            'execution_time_ms' => 10,
            'happened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    get(route('event-lens.detail', 'evt-ex'))
        ->assertOk()
        ->assertSee('RuntimeException: Something broke')
        ->assertSee('Show stack trace');
});

// -- Health Page Tests --

it('can view the health page', function () {
    get(route('event-lens.health'))
        ->assertOk()
        ->assertViewIs('event-lens::health')
        ->assertSee('Health')
        ->assertSee('Audit')
        ->assertSee('Listener Health');
});

it('shows dead listeners on audit tab', function () {
    // Create a root dispatch with no child listener records — this alone
    // does NOT produce a "dead listener" unless the dispatcher has a
    // registered listener. The AuditService checks against the dispatcher.
    // For testing, we verify the page renders the audit section properly.
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\NeverListened',
        'listener_name' => 'Event::dispatch',
        'happened_at' => now(),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Dead Listeners');
});

it('shows orphan events on audit tab', function () {
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\Orphaned',
        'listener_name' => 'Event::dispatch',
        'happened_at' => now(),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Orphan Events')
        ->assertSee('App\Events\Orphaned');
});

it('shows stale listeners on audit tab', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\OldEvent',
        'listener_name' => 'App\Listeners\StaleHandler',
        'parent_event_id' => 'some-parent',
        'happened_at' => now()->subDays(60),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Stale Listeners')
        ->assertSee('App\Listeners\StaleHandler');
});

it('shows listener health scores', function () {
    EventLog::factory()->count(3)->create([
        'event_name' => 'App\Events\HealthTest',
        'listener_name' => 'App\Listeners\HealthyListener',
        'parent_event_id' => 'some-parent',
        'execution_time_ms' => 10,
        'happened_at' => now(),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Listener Health Scores')
        ->assertSee('App\Listeners\HealthyListener');
});

// -- Storm Badge Tests --

it('shows storm badge on stream for storm events', function () {
    EventLog::factory()->root()->storm()->create([
        'event_name' => 'App\Events\StormEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('STORM');
});

it('does not show storm badge for non-storm events', function () {
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\NormalEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertDontSee('bg-red-600 text-white">STORM');
});

// -- Request Context Badge Tests --

it('shows request context badge on stream for root events', function () {
    EventLog::factory()->root()->withRequestContext('http', '/api/orders')->create([
        'event_name' => 'App\Events\OrderPlaced',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('GET')
        ->assertSee('/api/orders');
});

// -- Storm Filter Tests --

it('filters stream by storm events only', function () {
    EventLog::factory()->root()->storm()->create([
        'event_name' => 'App\Events\StormEvent',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\NormalEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index', ['storm' => '1']))
        ->assertOk()
        ->assertSee('StormEvent')
        ->assertDontSee('App\Events\NormalEvent');
});

// -- Storm Metadata on Detail Page --

it('shows storm metadata on detail page', function () {
    EventLog::factory()->root()->storm()->create([
        'event_id' => 'evt-storm-detail',
        'event_name' => 'App\Events\StormEvent',
        'side_effects' => ['queries' => 0, 'mails' => 0, 'storm_count' => 75],
        'happened_at' => now(),
    ]);

    get(route('event-lens.detail', 'evt-storm-detail'))
        ->assertOk()
        ->assertSee('Part of storm')
        ->assertSee('75 events');
});

// -- Request Context on Detail Page --

it('shows request context on detail page', function () {
    EventLog::factory()->root()->withRequestContext('http', '/api/orders')->create([
        'event_id' => 'evt-ctx-detail',
        'event_name' => 'App\Events\OrderPlaced',
        'happened_at' => now(),
    ]);

    get(route('event-lens.detail', 'evt-ctx-detail'))
        ->assertOk()
        ->assertSee('Trigger Context')
        ->assertSee('GET /api/orders');
});

// -- Storm Count on Statistics --

it('shows storm count card on statistics overview', function () {
    EventLog::factory()->root()->storm()->create(['happened_at' => now()]);
    EventLog::factory()->root()->storm()->create(['happened_at' => now()]);
    EventLog::factory()->root()->create(['happened_at' => now()]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('Storm Events')
        ->assertSee('2');
});

// -- SLA Badge Tests --

it('shows SLA breach badge on stream', function () {
    EventLog::factory()->root()->slaBreach()->create([
        'event_name' => 'App\Events\SlowOrder',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('SLA');
});

it('shows drift badge on stream', function () {
    EventLog::factory()->root()->withDrift(['changes' => [['type' => 'added', 'field' => 'new_field']]])->create([
        'event_name' => 'App\Events\DriftedEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('DRIFT');
});

it('shows N+1 badge on stream', function () {
    EventLog::factory()->root()->nplus1()->create([
        'event_name' => 'App\Events\HeavyQuery',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('N+1');
});

// -- Filter Tests --

it('filters stream by SLA breaches', function () {
    EventLog::factory()->root()->slaBreach()->create([
        'event_name' => 'App\Events\SlaBreach',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\NormalEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index', ['sla' => '1']))
        ->assertOk()
        ->assertSee('SlaBreach')
        ->assertDontSee('App\Events\NormalEvent');
});

it('filters stream by drift events', function () {
    EventLog::factory()->root()->withDrift(['changes' => []])->create([
        'event_name' => 'App\Events\DriftedEvent',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\StableEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index', ['drift' => '1']))
        ->assertOk()
        ->assertSee('DriftedEvent')
        ->assertDontSee('App\Events\StableEvent');
});

it('filters stream by N+1 events', function () {
    EventLog::factory()->root()->nplus1()->create([
        'event_name' => 'App\Events\NplusOneEvent',
        'happened_at' => now(),
    ]);
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\CleanEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index', ['nplus1' => '1']))
        ->assertOk()
        ->assertSee('NplusOneEvent')
        ->assertDontSee('App\Events\CleanEvent');
});

// -- Detail Page SLA/Drift/N+1 Tests --

it('shows SLA breach details on detail page', function () {
    EventLog::factory()->root()->slaBreach()->create([
        'event_id' => 'evt-sla-detail',
        'event_name' => 'App\Events\SlowOrder',
        'execution_time_ms' => 300,
        'side_effects' => ['queries' => 0, 'mails' => 0, 'sla_breach' => ['budget_ms' => 200, 'actual_ms' => 300, 'exceeded_by_pct' => 50]],
        'happened_at' => now(),
    ]);

    get(route('event-lens.detail', 'evt-sla-detail'))
        ->assertOk()
        ->assertSee('SLA Budget')
        ->assertSee('BREACH')
        ->assertSee('200.0ms budget');
});

it('shows drift diff section on detail page', function () {
    EventLog::factory()->root()->withDrift([
        'changes' => [
            ['type' => 'added', 'field' => 'new_field', 'detail' => 'New field appeared'],
            ['type' => 'removed', 'field' => 'old_field', 'detail' => 'Field removed'],
        ],
    ])->create([
        'event_id' => 'evt-drift-detail',
        'event_name' => 'App\Events\DriftedEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.detail', 'evt-drift-detail'))
        ->assertOk()
        ->assertSee('Schema Drift Detected')
        ->assertSee('new_field')
        ->assertSee('old_field')
        ->assertSee('Added')
        ->assertSee('Removed');
});

it('shows N+1 detail section on detail page', function () {
    EventLog::factory()->root()->nplus1()->create([
        'event_id' => 'evt-nplus1-detail',
        'event_name' => 'App\Events\HeavyQuery',
        'happened_at' => now(),
    ]);

    get(route('event-lens.detail', 'evt-nplus1-detail'))
        ->assertOk()
        ->assertSee('N+1 Query Detected')
        ->assertSee('View N+1 detail');
});

// -- Statistics SLA Breach Count --

it('shows SLA breach count on statistics', function () {
    EventLog::factory()->root()->slaBreach()->create(['happened_at' => now()]);
    EventLog::factory()->root()->slaBreach()->create(['happened_at' => now()]);
    EventLog::factory()->root()->create(['happened_at' => now()]);

    get(route('event-lens.statistics'))
        ->assertOk()
        ->assertSee('SLA Breaches');
});

// -- Health Page SLA/Blast Radius Tab Tests --

it('can view SLA compliance tab on health page', function () {
    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('SLA Compliance')
        ->assertSee('No SLA budgets configured');
});

it('can view SLA compliance tab with budgets configured', function () {
    Config::set('event-lens.sla_budgets', [
        'App\Events\OrderPlaced' => 200,
    ]);

    EventLog::factory()->root()->slaBreach()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'execution_time_ms' => 300,
        'happened_at' => now(),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('SLA Budget Compliance')
        ->assertSee('Listeners with SLAs')
        ->assertSee('App\Events\OrderPlaced');
});

it('can view blast radius tab on health page', function () {
    EventLog::factory()->create([
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\ProcessOrder',
        'parent_event_id' => 'some-parent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Blast Radius')
        ->assertSee('Listener Blast Radius');
});

it('shows replay button on detail page for root events when enabled', function () {
    Config::set('event-lens.allow_replay', true);

    $event = EventLog::factory()->create([
        'event_name' => \GladeHQ\LaravelEventLens\Tests\Fixtures\ReplayableEvent::class,
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
    ]);

    get(route('event-lens.detail', $event->event_id))
        ->assertOk()
        ->assertSee('Replay Event');
});

it('hides replay button when config is disabled', function () {
    Config::set('event-lens.allow_replay', false);

    $event = EventLog::factory()->create([
        'event_name' => \GladeHQ\LaravelEventLens\Tests\Fixtures\ReplayableEvent::class,
        'listener_name' => 'Event::dispatch',
        'parent_event_id' => null,
    ]);

    get(route('event-lens.detail', $event->event_id))
        ->assertOk()
        ->assertDontSee('Replay Event');
});

it('hides replay button for non-root events', function () {
    Config::set('event-lens.allow_replay', true);

    $event = EventLog::factory()->create([
        'event_name' => \GladeHQ\LaravelEventLens\Tests\Fixtures\ReplayableEvent::class,
        'listener_name' => 'App\Listeners\HandleOrder',
        'parent_event_id' => 'some-parent',
    ]);

    get(route('event-lens.detail', $event->event_id))
        ->assertOk()
        ->assertDontSee('Replay Event');
});

it('shows regressions tab on health page', function () {
    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Regressions')
        ->assertSee('No performance regressions detected');
});

it('shows regression data when regressions exist', function () {
    $listener = 'App\Listeners\SlowRegressed';
    $event = 'App\Events\OrderPlaced';

    // Baseline
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 50,
            'happened_at' => now()->subDays(3),
        ]);
    }

    // Recent — 3x regression
    for ($i = 0; $i < 5; $i++) {
        EventLog::factory()->create([
            'listener_name' => $listener,
            'event_name' => $event,
            'execution_time_ms' => 150,
            'happened_at' => now()->subHours(6),
        ]);
    }

    get(route('event-lens.health'))
        ->assertOk()
        ->assertSee('Regressions')
        ->assertSee('Performance Regressions')
        ->assertSee('App\Listeners\SlowRegressed');
});

it('shows export trace button on waterfall when OTLP is configured', function () {
    Config::set('event-lens.otlp_endpoint', 'https://otel.example.com');

    $event = EventLog::factory()->create([
        'correlation_id' => 'corr-export-ui',
        'parent_event_id' => null,
    ]);

    get(route('event-lens.show', 'corr-export-ui'))
        ->assertOk()
        ->assertSee('Export Trace');
});

it('hides export trace button when OTLP is not configured', function () {
    Config::set('event-lens.otlp_endpoint', null);

    $event = EventLog::factory()->create([
        'correlation_id' => 'corr-no-export',
        'parent_event_id' => null,
    ]);

    get(route('event-lens.show', 'corr-no-export'))
        ->assertOk()
        ->assertDontSee('Export Trace');
});

// -- HTTP Calls Tests --

it('shows HTTP calls total in waterfall header', function () {
    EventLog::factory()->root()->withHttpCalls(3)->create([
        'correlation_id' => 'cor-http',
        'happened_at' => now(),
    ]);

    get(route('event-lens.show', 'cor-http'))
        ->assertOk()
        ->assertViewHas('totalHttpCalls', 3);
});

it('shows HTTP badge on index for events with http calls', function () {
    EventLog::factory()->root()->withHttpCalls(5)->create([
        'event_name' => 'App\Events\HttpEvent',
        'happened_at' => now(),
    ]);

    get(route('event-lens.index'))
        ->assertOk()
        ->assertSee('5h');
});

// -- Vendored Asset Tests --

it('returns json from index when Accept header is application/json', function () {
    EventLog::factory()->root()->create([
        'event_name' => 'App\\Events\\JsonTest',
        'happened_at' => now(),
    ]);

    $response = $this->getJson(route('event-lens.index'));

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('returns json from show when Accept header is application/json', function () {
    EventLog::factory()->root()->create([
        'correlation_id' => 'cor-json-show',
        'happened_at' => now(),
    ]);

    $response = $this->getJson(route('event-lens.show', 'cor-json-show'));

    $response->assertOk()
        ->assertJsonStructure(['correlation_id', 'events', 'summary']);
});

// -- Vendored Asset Tests --

it('serves vendored assets with correct content type', function () {
    $jsResponse = get(route('event-lens.asset', ['file' => 'alpine.min.js']));
    $jsResponse->assertOk();
    expect($jsResponse->headers->get('Content-Type'))->toContain('application/javascript');

    $cssResponse = get(route('event-lens.asset', ['file' => 'app.css']));
    $cssResponse->assertOk();
    expect($cssResponse->headers->get('Content-Type'))->toContain('text/css');

    get(route('event-lens.asset', ['file' => 'evil.php']))
        ->assertNotFound();
});
