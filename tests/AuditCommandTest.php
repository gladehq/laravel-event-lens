<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    EventLog::truncate();
});

it('runs successfully', function () {
    artisan('event-lens:audit')
        ->assertSuccessful();
});

it('outputs audit tables', function () {
    EventLog::factory()->root()->create([
        'event_name' => 'App\Events\Lonely',
        'listener_name' => 'Event::dispatch',
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_name' => 'App\Events\OldEvent',
        'listener_name' => 'App\Listeners\Stale',
        'happened_at' => now()->subDays(60),
    ]);

    artisan('event-lens:audit')
        ->assertSuccessful()
        ->expectsOutputToContain('Dead Listeners')
        ->expectsOutputToContain('Orphan Events')
        ->expectsOutputToContain('Stale Listeners');
});

it('handles empty event log gracefully', function () {
    artisan('event-lens:audit')
        ->assertSuccessful()
        ->expectsOutputToContain('None found.');
});
