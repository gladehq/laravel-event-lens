<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Contracts;

interface Taggable
{
    /**
     * Return key-value pairs to tag this event with.
     *
     * @return array<string, scalar|null>
     */
    public function eventLensTags(): array;
}
