# Laravel Event Lens

Deep observability for Laravel Events and Listeners with execution tracing, waterfall visualization and side-effect tracking.

## Features

- **Event tracing** - Capture every `dispatch()`, `until()` and listener execution with parent-child hierarchy
- **Waterfall visualization** - Interactive dashboard showing event execution trees with timing bars
- **Side-effect tracking** - Count database queries and mails sent per listener via pluggable watchers
- **Payload inspection** - Safe serialization with binary detection, depth limits, string truncation and key redaction
- **Model change tracking** - Automatic dirty-state capture for Eloquent models in event payloads
- **Polymorphic model linking** - Associate events with models via `HasEventLens` trait
- **Exception capture** - Record exceptions thrown during listener execution
- **Cross-queue tracing** - Correlation ID propagation through queued jobs
- **Octane safe** - Automatic state reset between requests
- **Sampling** - Configurable rate to minimize production overhead
- **Statistics dashboard** - Aggregate views with cached queries

## Requirements

- PHP 8.2+
- Laravel 10 or 11

## Installation

```bash
composer require gladehq/laravel-event-lens
php artisan event-lens:install
```

The install command publishes the config file, CSS assets and runs migrations.

### Manual installation

```bash
php artisan vendor:publish --tag=event-lens-config
php artisan vendor:publish --tag=event-lens-assets
php artisan migrate
```

## Configuration

All options live in `config/event-lens.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Master switch |
| `sampling_rate` | `1.0` | 0.0-1.0 (0% to 100%) |
| `namespaces` | `['App\Events\*']` | Event class patterns to monitor (wildcards supported) |
| `capture_backtrace` | `false` | Record dispatch file/line (dev only, expensive) |
| `redacted_keys` | `['password', ...]` | Keys redacted from payloads (case-insensitive) |
| `authorization` | `null` | Closure for dashboard access (default: local env only) |
| `middleware` | `['web']` | Dashboard route middleware |
| `path` | `'event-lens'` | Dashboard URL prefix |
| `watchers` | `[QueryWatcher, MailWatcher]` | Active watcher classes |
| `database_connection` | `null` | Separate DB connection for storage |
| `prune_after_days` | `7` | Data retention period |
| `slow_threshold` | `100.0` | Milliseconds to flag as slow |
| `buffer_size` | `1000` | Max in-memory events before auto-flush |

## Dashboard

Visit `/event-lens` (or your configured path) to access:

- **Stream** - Live event feed with search, date filtering and slow-only toggle
- **Statistics** - Aggregate metrics, slowest events and daily volume
- **Waterfall** - Per-correlation execution tree with timing visualization
- **Detail** - Individual event inspection with payload, side effects and exception data

### Authorization

By default, the dashboard is accessible only in the `local` environment. Customize via config:

```php
'authorization' => function ($user) {
    return $user && $user->isAdmin();
},
```

## Octane Support

EventLens automatically resets all singleton state (buffer, recorder, watchers) between Octane requests when `Laravel\Octane` is detected. No configuration needed.

## Queue Tracing

Correlation IDs propagate automatically into queued job payloads. Events dispatched inside a job inherit the parent correlation ID, linking the full trace across sync and async boundaries.

For per-job opt-in middleware:

```php
use GladeHQ\LaravelEventLens\Queue\EventLensJobMiddleware;

class MyJob
{
    public function middleware(): array
    {
        return [new EventLensJobMiddleware()];
    }
}
```

## HasEventLens Trait

Associate models with their event logs:

```php
use GladeHQ\LaravelEventLens\Concerns\HasEventLens;

class Order extends Model
{
    use HasEventLens;
}

// Query events for a model
$order->eventLogs()->latest()->get();
```

Events automatically detect models in their public properties and store `model_type`/`model_id` for polymorphic lookups.

## Custom Watchers

Implement `WatcherInterface` to track additional side effects:

```php
use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;

class RedisWatcher implements WatcherInterface
{
    protected array $stack = [];

    public function boot(): void
    {
        // Register listeners for Redis commands
    }

    public function start(): void { $this->stack[] = 0; }
    public function stop(): array { return ['redis' => array_pop($this->stack) ?? 0]; }
    public function reset(): void { $this->stack = []; }
}
```

Register in config:

```php
'watchers' => [
    \GladeHQ\LaravelEventLens\Watchers\QueryWatcher::class,
    \GladeHQ\LaravelEventLens\Watchers\MailWatcher::class,
    \App\Watchers\RedisWatcher::class,
],
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `event-lens:install` | Publish config, assets and run migrations |
| `event-lens:status` | Show current status and statistics |
| `event-lens:clear --force` | Truncate all event data |
| `event-lens:prune --days=7` | Delete events older than N days |
| `event-lens:prune --dry-run` | Preview prune without deleting |

## Publishing Views

```bash
php artisan vendor:publish --tag=event-lens-views
```

Views will be published to `resources/views/vendor/event-lens/`.

## How It Differs from Telescope

| Feature | Telescope | Event Lens |
|---------|-----------|------------|
| Focus | All request lifecycle | Events and listeners only |
| Parent-child tracing | No | Yes (correlation + hierarchy) |
| Waterfall visualization | No | Yes |
| Side-effect tracking per listener | No | Yes (queries, mails) |
| Model change tracking | No | Yes (dirty state capture) |
| Polymorphic model linking | No | Yes |
| Cross-queue correlation | No | Yes |
| Custom watchers | No | Yes (pluggable interface) |
| Footprint | Heavy | Lightweight |

## License

MIT
