<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

class NplusOneDetector
{
    protected int $threshold = 5;

    /**
     * Detect N+1 event patterns within a correlation.
     *
     * @param  array<string, int>  $stormCounters  Keyed by "{correlationId}:{eventName}"
     * @return array{type: string, event_class: string, count: int}|null
     */
    public function checkEventPattern(string $correlationId, array $stormCounters): ?array
    {
        $prefix = "{$correlationId}:";

        foreach ($stormCounters as $key => $count) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            if ($count >= $this->threshold) {
                $eventClass = substr($key, strlen($prefix));

                return [
                    'type' => 'event',
                    'event_class' => $eventClass,
                    'count' => $count,
                ];
            }
        }

        return null;
    }

    /**
     * Detect N+1 query patterns from SQL fingerprints.
     *
     * @param  string[]  $queryFingerprints
     * @return array{type: string, pattern: string, count: int}|null
     */
    public function checkQueryPattern(array $queryFingerprints): ?array
    {
        if (empty($queryFingerprints)) {
            return null;
        }

        $grouped = array_count_values($queryFingerprints);

        foreach ($grouped as $fingerprint => $count) {
            if ($count >= $this->threshold) {
                return [
                    'type' => 'query',
                    'pattern' => $fingerprint,
                    'count' => $count,
                ];
            }
        }

        return null;
    }

    /**
     * Normalize a SQL query into a fingerprint by replacing literals with placeholders.
     */
    public function normalizeQuery(string $sql): string
    {
        // Replace quoted strings (single and double) with ?
        $sql = preg_replace("/('[^']*'|\"[^\"]*\")/", '?', $sql);

        // Replace numeric literals (standalone numbers) with ?
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);

        // Collapse whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
    }

    public function reset(): void
    {
        // Stateless - nothing to clear
    }
}
