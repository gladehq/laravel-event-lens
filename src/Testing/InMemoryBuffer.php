<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Testing;

use GladeHQ\LaravelEventLens\Services\EventLensBuffer;

class InMemoryBuffer extends EventLensBuffer
{
    protected static array $records = [];

    public function push(array $data): void
    {
        static::$records[] = $data;
    }

    /**
     * No-op -- data stays in memory for assertion.
     */
    public function flush(): void
    {
        //
    }

    public static function records(): array
    {
        return static::$records;
    }

    public static function reset(): void
    {
        static::$records = [];
    }

    public function count(): int
    {
        return count(static::$records);
    }
}
