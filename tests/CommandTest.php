<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $migration = include __DIR__.'/../database/migrations/create_event_lens_table.php';
    $migration->up();
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

// -- Install Command --

it('install command publishes config and assets', function () {
    artisan('vendor:publish', ['--tag' => 'event-lens-config', '--force' => true])
        ->assertSuccessful();
});

// -- Status Command --

it('status command runs successfully', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'App\Events\Test', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    artisan('event-lens:status')
        ->assertSuccessful();
});

// -- Clear Command --

it('clear command removes all data with force flag', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'Test', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['event_id' => 'e2', 'correlation_id' => 'c2', 'event_name' => 'Test', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(EventLog::count())->toBe(2);

    artisan('event-lens:clear', ['--force' => true])
        ->assertSuccessful();

    expect(EventLog::count())->toBe(0);
});

it('clear command respects cancellation', function () {
    EventLog::insert([
        ['event_id' => 'e1', 'correlation_id' => 'c1', 'event_name' => 'Test', 'listener_name' => 'Closure', 'execution_time_ms' => 10, 'happened_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    artisan('event-lens:clear')
        ->expectsConfirmation('This will delete ALL EventLens data. Continue?', 'no')
        ->assertSuccessful();

    expect(EventLog::count())->toBe(1);
});
