<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('scopes to root events only', function () {
    EventLog::insert([
        ['event_id' => 'root', 'correlation_id' => 'c1', 'parent_event_id' => null, 'event_name' => 'Root', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'child', 'correlation_id' => 'c1', 'parent_event_id' => 'root', 'event_name' => 'Child', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::roots()->count())->toBe(1)
        ->and(EventLog::roots()->first()->event_name)->toBe('Root');
});

it('scopes by correlation id', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'aaa', 'event_name' => 'A', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'bbb', 'event_name' => 'B', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forCorrelation('aaa')->count())->toBe(1)
        ->and(EventLog::forCorrelation(null)->count())->toBe(2); // null returns all
});

it('scopes by event name with like match', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\UserRegistered', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forEvent('Order')->count())->toBe(1)
        ->and(EventLog::forEvent(null)->count())->toBe(2);
});

it('escapes LIKE wildcards in event name search', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App%Events', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'App\Events\OrderPlaced', 'listener_name' => 'Closure', 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Searching for literal "%" should only match the event containing "%", not act as wildcard
    expect(EventLog::forEvent('App%Events')->count())->toBe(1)
        ->and(EventLog::forEvent('App%Events')->first()->event_id)->toBe('e1');
});

it('scopes by slow threshold', function () {
    EventLog::insert([
        ['event_id' => 'fast', 'correlation_id' => 'c1', 'event_name' => 'Fast', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'slow', 'correlation_id' => 'c2', 'event_name' => 'Slow', 'listener_name' => 'Closure', 'execution_time_ms' => 500, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::slow()->count())->toBe(1)
        ->and(EventLog::slow(5)->count())->toBe(2); // both above 5ms
});

it('scopes by date range', function () {
    EventLog::insert([
        ['event_id' => 'old', 'correlation_id' => 'c1', 'event_name' => 'Old', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now()->subDays(30), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'new', 'correlation_id' => 'c2', 'event_name' => 'New', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::betweenDates(now()->subDay(), now()->addDay())->count())->toBe(1)
        ->and(EventLog::betweenDates(null, null)->count())->toBe(2);
});

it('scopes by minimum query count', function () {
    EventLog::insert([
        ['event_id' => 'low', 'correlation_id' => 'c1', 'event_name' => 'Low', 'listener_name' => 'Closure', 'side_effects' => json_encode(['queries' => 0]), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'high', 'correlation_id' => 'c2', 'event_name' => 'High', 'listener_name' => 'Closure', 'side_effects' => json_encode(['queries' => 5]), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::withMinQueries(3)->count())->toBe(1);
    expect(EventLog::withMinQueries(3)->first()->event_name)->toBe('High');
});

it('resolves parent-child relationships', function () {
    EventLog::insert([
        ['event_id' => 'parent-id', 'correlation_id' => 'c1', 'parent_event_id' => null, 'event_name' => 'Parent', 'listener_name' => 'Closure', 'execution_time_ms' => 100, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'child-id', 'correlation_id' => 'c1', 'parent_event_id' => 'parent-id', 'event_name' => 'Child', 'listener_name' => 'Closure', 'execution_time_ms' => 50, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $parent = EventLog::where('event_id', 'parent-id')->first();
    $child = EventLog::where('event_id', 'child-id')->first();

    expect($parent->children)->toHaveCount(1)
        ->and($parent->children->first()->event_id)->toBe('child-id')
        ->and($child->parent->event_id)->toBe('parent-id');
});

it('has an index on execution_time_ms', function () {
    $indexes = DB::select("PRAGMA index_list('event_lens_events')");
    $indexNames = array_map(fn ($i) => $i->name, $indexes);

    $found = false;
    foreach ($indexNames as $name) {
        $columns = DB::select("PRAGMA index_info('{$name}')");
        foreach ($columns as $col) {
            if ($col->name === 'execution_time_ms') {
                $found = true;
                break 2;
            }
        }
    }

    expect($found)->toBeTrue();
});

it('filters events by payload content', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'Order', 'listener_name' => 'Closure', 'payload' => json_encode(['order_id' => 42, 'customer' => 'Alice']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'User', 'listener_name' => 'Closure', 'payload' => json_encode(['user_id' => 99]), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forPayload('Alice')->count())->toBe(1)
        ->and(EventLog::forPayload('Alice')->first()->event_id)->toBe('e1');
});

it('returns all events when payload filter is null', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'A', 'listener_name' => 'Closure', 'payload' => json_encode(['key' => 'val']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'B', 'listener_name' => 'Closure', 'payload' => json_encode(['key' => 'other']), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forPayload(null)->count())->toBe(2)
        ->and(EventLog::forPayload('')->count())->toBe(2);
});

it('escapes LIKE wildcards in payload search', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'A', 'listener_name' => 'Closure', 'payload' => json_encode(['discount' => '50%']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'B', 'listener_name' => 'Closure', 'payload' => json_encode(['amount' => 500]), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Searching for literal "50%" should only match the event containing "50%"
    expect(EventLog::forPayload('50%')->count())->toBe(1)
        ->and(EventLog::forPayload('50%')->first()->event_id)->toBe('e1');
});

it('filters events by tag content', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'A', 'listener_name' => 'Closure', 'tags' => json_encode(['user_status' => 'active']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'B', 'listener_name' => 'Closure', 'tags' => json_encode(['priority' => 'low']), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forTag('active')->count())->toBe(1)
        ->and(EventLog::forTag('active')->first()->event_id)->toBe('e1');
});

it('returns all events when tag filter is null', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'A', 'listener_name' => 'Closure', 'tags' => json_encode(['k' => 'v']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'B', 'listener_name' => 'Closure', 'tags' => json_encode(['k' => 'other']), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forTag(null)->count())->toBe(2)
        ->and(EventLog::forTag('')->count())->toBe(2);
});

it('escapes LIKE wildcards in tag search', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'A', 'listener_name' => 'Closure', 'tags' => json_encode(['discount' => '50%']), 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'B', 'listener_name' => 'Closure', 'tags' => json_encode(['amount' => '500']), 'execution_time_ms' => 5, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::forTag('50%')->count())->toBe(1)
        ->and(EventLog::forTag('50%')->first()->event_id)->toBe('e1');
});

it('can create events using factory', function () {
    EventLog::factory()->count(3)->create();

    expect(EventLog::count())->toBe(3);
});

it('supports factory states', function () {
    $event = EventLog::factory()->slow(250.0)->withSideEffects(5, 2)->create();

    expect($event->execution_time_ms)->toBe(250.0);
    expect($event->side_effects['queries'])->toBe(5);
    expect($event->side_effects['mails'])->toBe(2);
});
