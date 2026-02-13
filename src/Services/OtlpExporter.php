<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OtlpExporter
{
    /**
     * Export a full event trace as OTLP spans.
     *
     * @return array{success: bool, error?: string, span_count?: int}
     */
    public function export(string $correlationId): array
    {
        $endpoint = config('event-lens.otlp_endpoint');

        if (empty($endpoint)) {
            return ['success' => false, 'error' => 'OTLP endpoint not configured. Set otlp_endpoint in config/event-lens.php.'];
        }

        $events = EventLog::forCorrelation($correlationId)
            ->orderBy('happened_at')
            ->get();

        if ($events->isEmpty()) {
            return ['success' => false, 'error' => 'No events found for this trace.'];
        }

        $spans = $this->buildSpans($events, $correlationId);
        $payload = $this->buildOtlpPayload($spans);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(rtrim($endpoint, '/') . '/v1/traces', $payload);

            if ($response->successful()) {
                return ['success' => true, 'span_count' => count($spans)];
            }

            return ['success' => false, 'error' => "OTLP endpoint returned HTTP {$response->status()}: {$response->body()}"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Failed to reach OTLP endpoint: {$e->getMessage()}"];
        }
    }

    /**
     * Build OTLP-compatible span objects from EventLog records.
     */
    public function buildSpans($events, string $correlationId): array
    {
        $traceId = $this->toHex($correlationId, 32);

        return $events->map(function ($event) use ($traceId) {
            $spanId = $this->toHex($event->event_id, 16);
            $parentSpanId = $event->parent_event_id
                ? $this->toHex($event->parent_event_id, 16)
                : '';

            $startTimeUnixNano = (int) ($event->happened_at->getPreciseTimestamp(6) * 1000);
            $endTimeUnixNano = $startTimeUnixNano + (int) ($event->execution_time_ms * 1_000_000);

            $attributes = [
                $this->attr('event.name', $event->event_name),
                $this->attr('event.listener', $event->listener_name),
                $this->attr('event.execution_time_ms', (string) round($event->execution_time_ms, 2)),
            ];

            if ($event->side_effects) {
                if (isset($event->side_effects['queries'])) {
                    $attributes[] = $this->attr('db.query_count', (string) $event->side_effects['queries']);
                }
                if (isset($event->side_effects['mails'])) {
                    $attributes[] = $this->attr('mail.count', (string) $event->side_effects['mails']);
                }
            }

            if ($event->exception) {
                $attributes[] = $this->attr('error', 'true');
                $attributes[] = $this->attr('exception.message', Str::limit($event->exception, 200));
            }

            if ($event->is_storm) {
                $attributes[] = $this->attr('event_lens.storm', 'true');
            }

            if ($event->is_sla_breach) {
                $attributes[] = $this->attr('event_lens.sla_breach', 'true');
            }

            $statusCode = $event->exception ? 2 : 1; // STATUS_CODE_ERROR : STATUS_CODE_OK

            return [
                'traceId' => $traceId,
                'spanId' => $spanId,
                'parentSpanId' => $parentSpanId,
                'name' => $event->listener_name,
                'kind' => 1, // SPAN_KIND_INTERNAL
                'startTimeUnixNano' => (string) $startTimeUnixNano,
                'endTimeUnixNano' => (string) $endTimeUnixNano,
                'attributes' => $attributes,
                'status' => [
                    'code' => $statusCode,
                ],
            ];
        })->all();
    }

    protected function buildOtlpPayload(array $spans): array
    {
        $serviceName = config('event-lens.otlp_service_name') ?: config('app.name', 'laravel');

        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            $this->attr('service.name', $serviceName),
                        ],
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'laravel-event-lens',
                                'version' => '1.0.0',
                            ],
                            'spans' => $spans,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert a UUID or string to a hex ID of the specified length.
     */
    protected function toHex(string $input, int $length): string
    {
        $hex = str_replace('-', '', $input);

        if (ctype_xdigit($hex) && strlen($hex) >= $length) {
            return substr($hex, 0, $length);
        }

        return substr(md5($input), 0, $length);
    }

    protected function attr(string $key, string $value): array
    {
        return [
            'key' => $key,
            'value' => ['stringValue' => $value],
        ];
    }
}
