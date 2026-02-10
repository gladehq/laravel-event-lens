<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Watchers;

use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

class QueryWatcher implements WatcherInterface
{
    protected array $stack = [];

    protected bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Event::listen(QueryExecuted::class, function () {
            foreach ($this->stack as &$scope) {
                $scope++;
            }
        });

        $this->booted = true;
    }

    public function start(): void
    {
        $this->stack[] = 0;
    }

    public function stop(): array
    {
        $count = empty($this->stack) ? 0 : array_pop($this->stack);

        return ['queries' => $count];
    }
}
