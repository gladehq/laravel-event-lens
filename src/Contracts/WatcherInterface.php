<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Contracts;

interface WatcherInterface
{
    /**
     * Register event listeners (called once at boot).
     */
    public function boot(): void;

    /**
     * Push a new counting scope onto the stack.
     */
    public function start(): void;

    /**
     * Pop the current scope and return its counts.
     *
     * @return array<string, int>
     */
    public function stop(): array;
}
