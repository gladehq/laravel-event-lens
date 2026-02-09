<?php

use Illuminate\Support\Facades\Route;
use GladeHQ\LaravelEventLens\Http\Controllers\EventLensController;

Route::group([
    'prefix' => 'event-lens',
    'middleware' => ['web'], // Add auth middleware if needed
], function () {
    Route::get('/', [EventLensController::class, 'index'])->name('event-lens.index');
    Route::get('/api/latest', [EventLensController::class, 'latest'])->name('event-lens.api.latest');
    Route::get('/{correlationId}', [EventLensController::class, 'show'])->name('event-lens.show');
});
