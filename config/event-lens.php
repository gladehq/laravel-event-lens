<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | EventLens Status
    |--------------------------------------------------------------------------
    |
    | You may disable EventLens entirely here. tightly
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
    'sampling_rate' => env('EVENT_LENS_SAMPLING_RATE', 0.1),

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
     * Database connection to use for storing events. 
     * null = default application connection.
     */
    'database_connection' => null,
    
    /*
     * Pruning configuration
     */
    'prune_after_days' => 7,
];
