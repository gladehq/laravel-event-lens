<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

class EventLensServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/event-lens.php', 'event-lens'
        );

        // Register Buffer & Watcher as Singletons
        $this->app->singleton(Watchers\SideEffectWatcher::class);
        $this->app->singleton(Services\EventLensBuffer::class);
        // Register Recorder potentially as singleton too, good practice?
        // Actually Proxy holds it so it acts like one, but safer to bind.
        $this->app->singleton(Services\EventRecorder::class);
        
        $this->app->extend('events', function ($dispatcher, $app) {
            return new EventLensProxy($dispatcher);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/event-lens.php' => config_path('event-lens.php'),
            ], 'event-lens-config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            
            $this->commands([
                Commands\PruneEventLensCommand::class,
            ]);
        }
        
        // Register routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        
        // Register views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'event-lens');
        
        // Boot Watchers
        $this->app->make(Watchers\SideEffectWatcher::class)->boot();
        
        $this->app->terminating(function () {
             /** @var Services\EventLensBuffer $buffer */
             $buffer = $this->app->make(Services\EventLensBuffer::class);
             $buffer->flush();
        });
    }

    protected function shouldEnable(): bool
    {
        return config('event-lens.enabled', true);
    }
}
