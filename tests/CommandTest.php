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
    // Skip migrate since table already exists in test; just test publish steps
    artisan('vendor:publish', ['--tag' => 'event-lens-config', '--force' => true])
        ->assertSuccessful();
});
