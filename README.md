# Laravel Event Lens

Deep observability for Laravel Events and Listeners with execution tracing, waterfall visualization, side-effect tracking, health monitoring, and performance analysis.

## Features

- **Event tracing** - Capture every `dispatch()`, `until()` and listener execution with parent-child hierarchy
- **Waterfall visualization** - Interactive dashboard showing event execution trees with timing bars
- **Side-effect tracking** - Count database queries and mails sent per listener via pluggable watchers
- **Payload inspection** - Safe serialization with binary detection, depth limits, string truncation and key redaction
- **Model change tracking** - Automatic dirty-state capture for Eloquent models in event payloads
- **Polymorphic model linking** - Associate events with models via `HasEventLens` trait
- **Exception capture** - Record exceptions thrown during listener execution with error filtering and breakdown
- **Event tagging** - Opt-in structured metadata via `Taggable` interface with dashboard filtering
- **Cross-queue tracing** - Correlation ID propagation through queued jobs
- **Storm detection** - Flag event chains that fire excessively within a single correlation
- **Request context binding** - Capture HTTP method/path/user or CLI command alongside event traces
- **Dead listener audit** - Identify registered listeners that have never executed
- **Listener health scores** - Per-listener reliability scores based on error rate, slowness, and volume
- **SLA enforcement** - Define time budgets per event or listener with compliance tracking
- **Schema drift detection** - Detect payload structure changes over time
- **N+1 detection** - Flag listeners with suspiciously high query counts
- **Blast radius mapping** - Visualize which listeners are triggered by each event and their downstream impact
- **Performance regression detection** - Compare recent execution times against historical baselines to surface slowdowns
- **Event replay** - Re-dispatch previously recorded events from the dashboard for debugging
- **OpenTelemetry export** - Export event traces as OTLP spans to any compatible backend (Jaeger, Zipkin, Datadog, etc.)
- **Octane safe** - Automatic state reset between requests
- **Sampling** - Configurable rate to minimize production overhead
- **Statistics dashboard** - Tabbed interface (Overview, Performance, Errors) with execution time histogram, event mix composition bar, error breakdown, query load ranking, listener-level drill-down, and daily timeline with error overlay

## Requirements

- PHP 8.2+
- Laravel 10 or 11

## Installation

```bash
composer require gladehq/laravel-event-lens
php artisan event-lens:install
```

The install command publishes the config file and runs migrations.

### Manual installation

```bash
php artisan vendor:publish --tag=event-lens-config
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
| `storm_threshold` | `50` | Same-event fires per correlation before flagging as storm |
| `stale_threshold_days` | `30` | Days without execution before a listener is considered stale |
| `sla_budgets` | `[]` | Execution time budgets per event/listener class (ms) |
| `allow_replay` | `false` | Enable event replay from the dashboard |
| `regression_threshold` | `2.0` | Multiplier for flagging performance regressions (e.g. 2.0 = 2x slower) |
| `otlp_endpoint` | `null` | OTLP endpoint URL for trace export |
| `otlp_service_name` | `null` | Service name included in OTLP resource attributes |

## Dashboard

Visit `/event-lens` (or your configured path) to access:

- **Stream** - Live event feed with filtering by event name, listener name, payload content, tag content, date range, slow-only and errors-only toggles. Each row shows listener name, inline badges for errors, query counts, mail counts and tags, and a copy-to-clipboard button for correlation IDs.
- **Statistics** - Tabbed dashboard organized into three views. Always-visible summary cards (total events, avg execution time, slow count, error rate, total queries, total mails) with quick date presets (Today, 7d, 30d). **Overview tab**: daily volume bar chart with error rate dots overlay, execution time distribution histogram (color-coded latency buckets), and event mix composition bar. **Performance tab**: top events by frequency with inline proportional bars and expandable per-listener breakdown, slowest individual events, and heaviest events by query load with inline bars. **Errors tab**: error breakdown grouped by exception type with count badge. All event names link to the stream page for drill-down. Tabs are bookmarkable via URL hash.
- **Health** - Multi-tab health dashboard with: **Audit** (dead listeners, orphan events, stale listeners), **Listener Health** (per-listener scores with error rate, p95, avg time, and volume breakdown), **SLA Compliance** (budget vs actual with compliance percentage per event/listener), **Blast Radius** (event-to-listener dependency map with error and slow indicators), and **Regressions** (listeners whose recent execution time has spiked compared to baseline, with severity badges).
- **Waterfall** - Per-correlation execution tree with timing bars, query/mail counts, error and slow counts in the header, error propagation badges on ancestor nodes, "Jump to error" navigation, compact/detailed view toggle, and OTLP trace export button (when configured).
- **Detail** - Listener-focused header with event context, previous/next sibling navigation within the correlation, payload and correlation ID copy-to-clipboard, expandable exception with collapsible stack trace, model changes rendered as a before/after diff table, tags, side effects, and event replay button (when enabled).

### Authorization

By default, the dashboard is accessible only in the `local` environment. Customize via config:

```php
'authorization' => function ($user) {
    return $user && $user->isAdmin();
},
```

## Health Monitoring

The `/event-lens/health` page provides five diagnostic tabs:

### Audit
Scans your registered listeners against recorded execution data. Surfaces dead listeners (registered but never fired), orphan events (dispatched but no listeners registered), and stale listeners (no executions within `stale_threshold_days`).

### Listener Health
Assigns a 0-100 reliability score per listener based on error rate, slow execution percentage, and volume. Scores are color-coded: green (80+), yellow (50-79), red (below 50).

### SLA Compliance
Compares actual execution times against budgets defined in `sla_budgets`. Shows compliance percentage, average execution time, and p95 for each configured event or listener. Supports exact class names and wildcard patterns.

```php
'sla_budgets' => [
    'App\Listeners\SendEmail' => 500,    // 500ms budget
    'App\Events\OrderPlaced' => 200,     // 200ms budget
    'App\Events\*' => 1000,             // Wildcard fallback
],
```

### Blast Radius
Maps which listeners are triggered by each event, showing error counts and slow execution counts per listener. Helps identify high-risk events whose failures cascade through many listeners.

### Regressions
Compares each listener's average execution time over the last 24 hours against its 7-day baseline. Flags listeners whose recent performance exceeds the baseline by `regression_threshold` (default: 2x). Severity levels: **warning** (above threshold) and **critical** (5x+ slower). Requires a minimum of 3 executions in each period to avoid false positives.

## Event Replay

Re-dispatch a previously recorded event from the detail page. Useful for debugging listeners against the same event data without manually reconstructing payloads.

```php
// config/event-lens.php
'allow_replay' => env('EVENT_LENS_ALLOW_REPLAY', false),
```

When enabled, root events (`Event::dispatch` rows) show a "Replay" button on the detail page. The original payload is deserialized and the event is re-dispatched through Laravel's event system. Only root events can be replayed — individual listener rows cannot.

**Safety**: Disabled by default. Enable only in development or staging environments. Replayed events pass through the full listener pipeline, including side effects (emails, DB writes, API calls).

## OpenTelemetry Export

Export event traces as OTLP-compatible spans to any OpenTelemetry backend (Jaeger, Zipkin, Datadog, Honeycomb, etc.). No additional PHP packages required — uses Laravel's built-in HTTP client.

```php
// config/event-lens.php
'otlp_endpoint' => env('EVENT_LENS_OTLP_ENDPOINT'),        // e.g. https://otel.example.com
'otlp_service_name' => env('EVENT_LENS_OTLP_SERVICE_NAME'), // e.g. my-app
```

When configured, the waterfall page shows an "Export Trace" button that sends the full correlation trace to your OTLP endpoint as `/v1/traces`. Each event log becomes a span with:

- `traceId` derived from the correlation ID
- `spanId` derived from the event ID
- `parentSpanId` linking child spans to their parent
- Attributes: `event.name`, `event.listener`, `db.query_count`, `mail.count`, `error`, `exception.message`, `event_lens.storm`, `event_lens.sla_breach`

## Octane Support

EventLens automatically resets all singleton state (buffer, recorder, watchers) between Octane requests when `Laravel\Octane` is detected. No configuration needed.

## Queue Tracing

Correlation IDs propagate automatically into queued job payloads. Events dispatched inside a job inherit the parent correlation ID, linking the full trace across sync and async boundaries. No configuration needed.

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

## Event Tagging

Tag events with structured metadata by implementing the `Taggable` interface:

```php
use GladeHQ\LaravelEventLens\Contracts\Taggable;

class OrderPlaced implements Taggable
{
    public function __construct(
        public Order $order,
        public string $channel,
    ) {}

    public function eventLensTags(): array
    {
        return [
            'channel' => $this->channel,
            'priority' => $this->order->total > 1000 ? 'high' : 'normal',
        ];
    }
}
```

Tags are stored in a dedicated JSON column and displayed as purple badges on the dashboard. Use the "Tag Contains" filter on the stream page to search events by tag content.

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
| `event-lens:install` | Publish config and run migrations |
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
| Event tagging | No | Yes (opt-in Taggable interface) |
| Error breakdown by exception type | No | Yes |
| Query load ranking per event | No | Yes |
| Per-listener performance breakdown | No | Yes (expandable in statistics) |
| Execution time distribution | No | Yes (color-coded histogram) |
| Event mix composition | No | Yes (stacked bar visualization) |
| Error propagation in trace tree | No | Yes (ancestor warning badges) |
| Sibling navigation in detail view | No | Yes (prev/next within correlation) |
| Model changes diff view | No | Yes (before/after table) |
| Compact trace view | No | Yes (toggle detailed/compact) |
| Storm detection | No | Yes (configurable threshold) |
| Request context binding | No | Yes (HTTP, CLI, Queue) |
| Dead listener audit | No | Yes (health dashboard) |
| Listener health scores | No | Yes (0-100 reliability score) |
| SLA enforcement | No | Yes (configurable time budgets) |
| Blast radius mapping | No | Yes (event-to-listener dependency map) |
| Performance regression detection | No | Yes (baseline vs recent comparison) |
| Event replay | No | Yes (re-dispatch from dashboard) |
| OpenTelemetry export | No | Yes (OTLP spans, no extra packages) |
| Footprint | Heavy | Lightweight |

## License

MIT
