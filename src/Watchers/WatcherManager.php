<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Watchers;

use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;

class WatcherManager
{
    /** @var WatcherInterface[] */
    protected array $watchers = [];

    /**
     * @param WatcherInterface[] $watchers
     */
    public function __construct(array $watchers = [])
    {
        $this->watchers = $watchers;
    }

    public function boot(): void
    {
        foreach ($this->watchers as $watcher) {
            $watcher->boot();
        }
    }

    public function start(): void
    {
        foreach ($this->watchers as $watcher) {
            $watcher->start();
        }
    }

    /**
     * @return array<string, int>
     */
    public function stop(): array
    {
        $results = [];

        foreach ($this->watchers as $watcher) {
            $results = array_merge($results, $watcher->stop());
        }

        return $results;
    }
}
