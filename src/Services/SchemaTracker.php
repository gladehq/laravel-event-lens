<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\SchemaBaseline;
use Illuminate\Support\Facades\Log;

class SchemaTracker
{
    private array $baselineCache = [];

    /**
     * Generate a fingerprint for a payload's key/type structure.
     */
    public function fingerprint(array $payload): string
    {
        $schema = $this->buildSchema($payload);
        ksort($schema);

        $paths = array_map(
            fn (string $key, string $type) => "{$key}:{$type}",
            array_keys($schema),
            array_values($schema),
        );

        return md5(serialize($paths));
    }

    /**
     * Detect schema drift for an event class.
     *
     * Returns null on first encounter or when schema matches baseline.
     * Returns drift details when the schema has changed.
     */
    public function detectDrift(string $eventClass, array $payload): ?array
    {
        try {
            $currentSchema = $this->buildSchema($payload);
            ksort($currentSchema);
            $currentFingerprint = $this->fingerprint($payload);

            if (! array_key_exists($eventClass, $this->baselineCache)) {
                $this->baselineCache[$eventClass] = SchemaBaseline::where('event_class', $eventClass)->first();
            }
            $baseline = $this->baselineCache[$eventClass];

            // First encounter: store baseline, no drift
            if ($baseline === null) {
                $this->storeBaseline($eventClass, $currentFingerprint, $currentSchema);

                return null;
            }

            // Schema unchanged
            if ($baseline->fingerprint === $currentFingerprint) {
                return null;
            }

            // Schema drifted: compute diff
            $oldSchema = $baseline->schema;
            $changes = $this->computeChanges($oldSchema, $currentSchema);

            // Update baseline to the new schema
            $this->storeBaseline($eventClass, $currentFingerprint, $currentSchema);

            return [
                'before' => $oldSchema,
                'after' => $currentSchema,
                'changes' => $changes,
            ];
        } catch (\Throwable $e) {
            Log::warning('EventLens: Schema drift detection failed', [
                'error' => $e->getMessage(),
                'event' => $eventClass,
            ]);

            return null;
        }
    }

    /**
     * Upsert a schema baseline for the given event class.
     */
    public function storeBaseline(string $eventClass, string $fingerprint, array $schema): void
    {
        $model = SchemaBaseline::updateOrCreate(
            ['event_class' => $eventClass],
            ['fingerprint' => $fingerprint, 'schema' => $schema],
        );

        // Refresh in-memory cache with the freshly persisted model
        $this->baselineCache[$eventClass] = $model;
    }

    public function reset(): void
    {
        $this->baselineCache = [];
    }

    /**
     * Recursively build a flat key-path-to-type map from a payload.
     *
     * Skips EventLens-injected keys (__context, __request_context).
     */
    public function buildSchema(array $payload, string $prefix = ''): array
    {
        $schema = [];

        foreach ($payload as $key => $value) {
            if ($prefix === '' && in_array($key, ['__context', '__request_context'], true)) {
                continue;
            }

            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && ! array_is_list($value) && ! empty($value)) {
                $schema = array_merge($schema, $this->buildSchema($value, $path));
            } else {
                $schema[$path] = gettype($value);
            }
        }

        return $schema;
    }

    /**
     * Compute human-readable changes between two schemas.
     */
    protected function computeChanges(array $before, array $after): array
    {
        $changes = [];

        // Added keys
        foreach (array_diff_key($after, $before) as $key => $type) {
            $changes[] = "Added key: {$key}";
        }

        // Removed keys
        foreach (array_diff_key($before, $after) as $key => $type) {
            $changes[] = "Removed key: {$key}";
        }

        // Type changes
        foreach (array_intersect_key($before, $after) as $key => $oldType) {
            $newType = $after[$key];
            if ($oldType !== $newType) {
                $changes[] = "Type changed: {$key} ({$oldType} → {$newType})";
            }
        }

        return $changes;
    }
}
