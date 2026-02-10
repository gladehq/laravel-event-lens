<?php

use Illuminate\Support\Facades\Route;
use GladeHQ\LaravelEventLens\Http\Controllers\EventLensController;
use GladeHQ\LaravelEventLens\Http\Middleware\Authorize;

Route::group([
    'prefix' => config('event-lens.path', 'event-lens'),
    'middleware' => array_merge(
        config('event-lens.middleware', ['web']),
        [Authorize::class]
    ),
], function () {
    Route::get('/', [EventLensController::class, 'index'])->name('event-lens.index');
    Route::get('/api/latest', [EventLensController::class, 'latest'])->name('event-lens.api.latest');
    Route::get('/{correlationId}', [EventLensController::class, 'show'])->name('event-lens.show');
});
