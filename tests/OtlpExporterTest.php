<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Services\OtlpExporter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.otlp_endpoint', 'https://otel.example.com');
    Config::set('event-lens.otlp_service_name', 'test-app');
    EventLog::truncate();

    $this->exporter = new OtlpExporter();
});

it('builds spans from event log records', function () {
    $correlationId = 'corr-123';

    EventLog::factory()->create([
        'event_id' => 'root-id',
        'correlation_id' => $correlationId,
        'parent_event_id' => null,
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'Event::dispatch',
        'execution_time_ms' => 50.5,
        'happened_at' => now(),
    ]);

    EventLog::factory()->create([
        'event_id' => 'child-id',
        'correlation_id' => $correlationId,
        'parent_event_id' => 'root-id',
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\SendEmail',
        'execution_time_ms' => 30.2,
        'happened_at' => now(),
    ]);

    $events = EventLog::forCorrelation($correlationId)->orderBy('happened_at')->get();
    $spans = $this->exporter->buildSpans($events, $correlationId);

    expect($spans)->toHaveCount(2);

    // Root span
    $root = $spans[0];
    expect($root['name'])->toBe('Event::dispatch')
        ->and($root['traceId'])->toHaveLength(32)
        ->and($root['spanId'])->toHaveLength(16)
        ->and($root['parentSpanId'])->toBe('')
        ->and($root['kind'])->toBe(1);

    // Child span
    $child = $spans[1];
    expect($child['name'])->toBe('App\Listeners\SendEmail')
        ->and($child['parentSpanId'])->not->toBe('')
        ->and($child['traceId'])->toBe($root['traceId']);
});

it('includes event attributes in spans', function () {
    $correlationId = 'corr-attr';

    EventLog::factory()->create([
        'event_id' => 'attr-id',
        'correlation_id' => $correlationId,
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\ProcessOrder',
        'execution_time_ms' => 100,
        'side_effects' => ['queries' => 5, 'mails' => 1],
        'happened_at' => now(),
    ]);

    $events = EventLog::forCorrelation($correlationId)->get();
    $spans = $this->exporter->buildSpans($events, $correlationId);
    $span = $spans[0];

    $attrKeys = collect($span['attributes'])->pluck('key')->all();

    expect($attrKeys)->toContain('event.name')
        ->and($attrKeys)->toContain('event.listener')
        ->and($attrKeys)->toContain('db.query_count')
        ->and($attrKeys)->toContain('mail.count');
});

it('marks error status on spans with exceptions', function () {
    $correlationId = 'corr-err';

    EventLog::factory()->create([
        'event_id' => 'err-id',
        'correlation_id' => $correlationId,
        'event_name' => 'App\Events\OrderPlaced',
        'listener_name' => 'App\Listeners\FailingListener',
        'execution_time_ms' => 10,
        'exception' => 'RuntimeException: Something went wrong',
        'happened_at' => now(),
    ]);

    $events = EventLog::forCorrelation($correlationId)->get();
    $spans = $this->exporter->buildSpans($events, $correlationId);
    $span = $spans[0];

    expect($span['status']['code'])->toBe(2); // STATUS_CODE_ERROR

    $attrKeys = collect($span['attributes'])->pluck('key')->all();
    expect($attrKeys)->toContain('error')
        ->and($attrKeys)->toContain('exception.message');
});

it('exports trace to OTLP endpoint successfully', function () {
    Http::fake([
        'otel.example.com/*' => Http::response([], 200),
    ]);

    $correlationId = 'corr-export';

    EventLog::factory()->create([
        'correlation_id' => $correlationId,
        'event_name' => 'App\Events\Test',
        'listener_name' => 'Event::dispatch',
        'happened_at' => now(),
    ]);

    $result = $this->exporter->export($correlationId);

    expect($result['success'])->toBeTrue()
        ->and($result['span_count'])->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();
        return isset($body['resourceSpans'][0]['scopeSpans'][0]['spans'])
            && $request->url() === 'https://otel.example.com/v1/traces';
    });
});

it('returns error when OTLP endpoint is not configured', function () {
    Config::set('event-lens.otlp_endpoint', null);

    $exporter = new OtlpExporter();
    $result = $exporter->export('some-correlation');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('not configured');
});

it('returns error when no events found for correlation', function () {
    $result = $this->exporter->export('nonexistent-correlation');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('No events found');
});

it('returns error when OTLP endpoint returns error status', function () {
    Http::fake([
        'otel.example.com/*' => Http::response('Unauthorized', 401),
    ]);

    $correlationId = 'corr-fail';
    EventLog::factory()->create([
        'correlation_id' => $correlationId,
        'happened_at' => now(),
    ]);

    $result = $this->exporter->export($correlationId);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('401');
});

it('returns error when OTLP endpoint is unreachable', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $correlationId = 'corr-timeout';
    EventLog::factory()->create([
        'correlation_id' => $correlationId,
        'happened_at' => now(),
    ]);

    $result = $this->exporter->export($correlationId);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Failed to reach');
});

it('includes service name in OTLP payload', function () {
    Http::fake([
        'otel.example.com/*' => Http::response([], 200),
    ]);

    $correlationId = 'corr-svc';
    EventLog::factory()->create([
        'correlation_id' => $correlationId,
        'happened_at' => now(),
    ]);

    $this->exporter->export($correlationId);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $attrs = $body['resourceSpans'][0]['resource']['attributes'] ?? [];
        $serviceAttr = collect($attrs)->firstWhere('key', 'service.name');
        return $serviceAttr && $serviceAttr['value']['stringValue'] === 'test-app';
    });
});

it('includes storm and SLA breach attributes when flagged', function () {
    $correlationId = 'corr-flags';

    EventLog::factory()->create([
        'event_id' => 'flagged-id',
        'correlation_id' => $correlationId,
        'event_name' => 'App\Events\Storm',
        'listener_name' => 'App\Listeners\Handler',
        'is_storm' => true,
        'is_sla_breach' => true,
        'happened_at' => now(),
    ]);

    $events = EventLog::forCorrelation($correlationId)->get();
    $spans = $this->exporter->buildSpans($events, $correlationId);
    $attrKeys = collect($spans[0]['attributes'])->pluck('key')->all();

    expect($attrKeys)->toContain('event_lens.storm')
        ->and($attrKeys)->toContain('event_lens.sla_breach');
});
