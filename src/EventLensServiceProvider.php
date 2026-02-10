<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use GladeHQ\LaravelEventLens\Watchers\WatcherManager;

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

        // Register Buffer & WatcherManager as Singletons
        $this->app->singleton(Services\EventLensBuffer::class);

        $this->app->singleton(WatcherManager::class, function ($app) {
            $watcherClasses = config('event-lens.watchers', [
                Watchers\QueryWatcher::class,
                Watchers\MailWatcher::class,
            ]);

            $watchers = array_map(fn ($class) => $app->make($class), $watcherClasses);

            return new WatcherManager($watchers);
        });

        $this->app->singleton(Collectors\EventCollector::class);
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

            $this->publishes([
                __DIR__.'/../resources/event-lens.css' => public_path('vendor/event-lens/event-lens.css'),
            ], 'event-lens-assets');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/event-lens'),
            ], 'event-lens-views');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                Commands\InstallCommand::class,
                Commands\StatusCommand::class,
                Commands\ClearCommand::class,
                Commands\PruneEventLensCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'event-lens');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerGate();

        if ($this->shouldEnable()) {
            $this->app->make(WatcherManager::class)->boot();

            $this->app->terminating(function () {
                $this->app->make(Services\EventLensBuffer::class)->flush();
            });

            $this->registerOctaneReset();
            $this->registerQueueTracing();
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

    protected function registerOctaneReset(): void
    {
        if (! class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(\Laravel\Octane\Events\RequestReceived::class, function () {
            $this->app->make(Services\EventLensBuffer::class)->flush();
            $this->app->make(Services\EventRecorder::class)->reset();
            $this->app->make(WatcherManager::class)->reset();
        });
    }

    protected function registerQueueTracing(): void
    {
        $recorder = $this->app->make(Services\EventRecorder::class);

        Queue::createPayloadUsing(function ($connectionName, $queue, array $payload) use ($recorder) {
            $correlationId = $recorder->currentCorrelationId();
            if ($correlationId) {
                return ['event_lens_correlation_id' => $correlationId];
            }
            return [];
        });

        $this->app['events']->listen(\Illuminate\Queue\Events\JobProcessing::class, function ($event) use ($recorder) {
            $correlationId = $event->job->payload()['event_lens_correlation_id'] ?? null;
            if ($correlationId) {
                $recorder->pushCorrelationContext($correlationId);
            }
        });

        $this->app['events']->listen(\Illuminate\Queue\Events\JobProcessed::class, function () use ($recorder) {
            $recorder->popCorrelationContext();
        });

        $this->app['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function () use ($recorder) {
            $recorder->popCorrelationContext();
        });
    }

    protected function shouldEnable(): bool
    {
        return config('event-lens.enabled', true);
    }
}
