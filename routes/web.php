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
    Route::get('/statistics', [EventLensController::class, 'statistics'])->name('event-lens.statistics');
    Route::get('/health', [EventLensController::class, 'health'])->name('event-lens.health');
    Route::get('/api/latest', [EventLensController::class, 'latest'])->name('event-lens.api.latest');
    Route::get('/event/{eventId}', [EventLensController::class, 'detail'])->name('event-lens.detail');
    Route::post('/replay/{eventId}', [EventLensController::class, 'replay'])->name('event-lens.replay');
    Route::post('/export/{correlationId}', [EventLensController::class, 'export'])->name('event-lens.export');
    Route::get('/flow-map', [EventLensController::class, 'flowMap'])->name('event-lens.flow-map');
    Route::get('/comparison', [EventLensController::class, 'comparison'])->name('event-lens.comparison');
    Route::get('/assets/{file}', [EventLensController::class, 'asset'])->name('event-lens.asset')->where('file', '.+');
    Route::get('/{correlationId}', [EventLensController::class, 'show'])->name('event-lens.show');
});
