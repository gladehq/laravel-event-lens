<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens;

use Illuminate\Support\Facades\Gate;
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
        $this->app->singleton(Services\EventRecorder::class);

        if ($this->shouldEnable()) {
            $this->app->extend('events', function ($dispatcher, $app) {
                return new EventLensProxy($dispatcher);
            });
        }
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

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'event-lens');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerGate();

        if ($this->shouldEnable()) {
            $this->app->make(Watchers\SideEffectWatcher::class)->boot();

            $this->app->terminating(function () {
                $this->app->make(Services\EventLensBuffer::class)->flush();
            });
        }
    }

    protected function registerGate(): void
    {
        Gate::define('viewEventLens', function ($user = null) {
            $callback = config('event-lens.authorization');

            if (is_callable($callback)) {
                return $callback($user);
            }

            return $this->app->environment('local');
        });
    }

    protected function shouldEnable(): bool
    {
        return config('event-lens.enabled', true);
    }
}
