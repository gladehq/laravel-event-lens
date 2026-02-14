<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | EventLens Status
    |--------------------------------------------------------------------------
    |
    | You may disable EventLens entirely here.
    |
    */
    'enabled' => env('EVENT_LENS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | To avoid performance impact in production, you can sample a percentage
    | of events. 1.0 = 100%, 0.1 = 10%.
    |
    */
    'sampling_rate' => env('EVENT_LENS_SAMPLING_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Monitored Namespaces
    |--------------------------------------------------------------------------
    |
    | Define which event namespaces to monitor. Wildcards (*) are supported.
    |
    */
    'namespaces' => [
        'App\\Events\\*',
        // 'Illuminate\\Auth\\Events\\Login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Events
    |--------------------------------------------------------------------------
    |
    | Events matching these patterns will never be recorded, even if they
    | match the namespaces above. Wildcards (*) are supported.
    |
    */
    'ignore' => [
        // 'App\\Events\\Internal*',
        // 'App\\Events\\Heartbeat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Capture Backtrace
    |--------------------------------------------------------------------------
    |
    | Capture the file and line number where the event was dispatched.
    | Recommended ONLY for local development. High performance cost.
    |
    */
    'capture_backtrace' => env('EVENT_LENS_BACKTRACE', false),

    /*
    |--------------------------------------------------------------------------
    | Redacted Keys
    |--------------------------------------------------------------------------
    |
    | Keys in the event payload that should be redacted before storage.
    |
    */
    'redacted_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Dashboard Authorization
    |--------------------------------------------------------------------------
    |
    | This callback determines who can access the EventLens dashboard.
    | Return true to allow access. Default: allow only in local environment.
    |
    | Example:
    |   'authorization' => function ($user) {
    |       return $user && $user->isAdmin();
    |   },
    |
    */
    'authorization' => null,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all EventLens dashboard routes.
    | The Authorize middleware is always appended automatically.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    |
    | The URL prefix for the EventLens dashboard.
    |
    */
    'path' => 'event-lens',

    /*
    |--------------------------------------------------------------------------
    | Watchers
    |--------------------------------------------------------------------------
    |
    | Define which watchers are active. Each watcher must implement
    | GladeHQ\LaravelEventLens\Contracts\WatcherInterface.
    |
    | You may add custom watchers (e.g. Redis, HTTP, Cache) to this array.
    |
    */
    'watchers' => [
        \GladeHQ\LaravelEventLens\Watchers\QueryWatcher::class,
        \GladeHQ\LaravelEventLens\Watchers\MailWatcher::class,
        \GladeHQ\LaravelEventLens\Watchers\HttpWatcher::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Database connection to use for storing events.
    | null = default application connection.
    |
    */
    'database_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Number of days to retain event data before pruning.
    |
    */
    'prune_after_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Slow Threshold
    |--------------------------------------------------------------------------
    |
    | Events taking longer than this (in ms) are considered "slow".
    |
    */
    'slow_threshold' => 100.0,

    /*
    |--------------------------------------------------------------------------
    | Buffer Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of events to hold in memory before auto-flushing.
    | Prevents unbounded memory growth in high-throughput scenarios.
    |
    */
    'buffer_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Storm Detection Threshold
    |--------------------------------------------------------------------------
    |
    | Number of times the same event class can fire within a single
    | correlation before it is flagged as a "storm".
    |
    */
    'storm_threshold' => 50,

    /*
    |--------------------------------------------------------------------------
    | Stale Threshold (Days)
    |--------------------------------------------------------------------------
    |
    | Events older than this many days are considered stale.
    |
    */
    'stale_threshold_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | SLA Time Budgets (ms)
    |--------------------------------------------------------------------------
    |
    | Define maximum execution time budgets per event or listener class.
    | Exact listener names are checked first, then exact event names,
    | then wildcard patterns. Wildcards use Str::is() syntax.
    |
    */
    'sla_budgets' => [
        // 'App\Events\OrderPlaced' => 200,
        // 'App\Listeners\SendEmail' => 500,
        // 'App\Events\*' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Replay
    |--------------------------------------------------------------------------
    |
    | Allow re-dispatching previously recorded events from the dashboard.
    | Disabled by default for safety — enable only in development or staging.
    |
    */
    'allow_replay' => env('EVENT_LENS_ALLOW_REPLAY', false),

    /*
    |--------------------------------------------------------------------------
    | Regression Detection Threshold
    |--------------------------------------------------------------------------
    |
    | Multiplier used to detect performance regressions. A listener's recent
    | average (last 24h) must exceed its baseline (previous 7d) by this
    | factor to be flagged. E.g. 2.0 = recent is 2x slower than baseline.
    |
    */
    'regression_threshold' => 2.0,

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Export
    |--------------------------------------------------------------------------
    |
    | Endpoint for exporting event traces as OTLP spans.
    | Set to null to disable. Uses Laravel's HTTP client — no extra packages.
    |
    */
    'otlp_endpoint' => env('EVENT_LENS_OTLP_ENDPOINT'),
    'otlp_service_name' => env('EVENT_LENS_OTLP_SERVICE_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Configure alert notifications for anomalies (storms, SLA breaches,
    | regressions, error spikes). Alerts respect a per-type cooldown to
    | avoid notification fatigue.
    |
    */
    'alerts' => [
        'enabled' => env('EVENT_LENS_ALERTS_ENABLED', false),
        'channels' => [], // 'slack', 'mail', 'log'
        'slack_webhook' => env('EVENT_LENS_SLACK_WEBHOOK'),
        'mail_to' => env('EVENT_LENS_ALERT_MAIL_TO'),
        'log_channel' => null,
        'cooldown_minutes' => (int) env('EVENT_LENS_ALERT_COOLDOWN', 15),
        'on' => ['storm', 'sla_breach', 'regression', 'error_spike'],
    ],
];
