<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Watchers;

use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;
use GladeHQ\LaravelEventLens\Services\NplusOneDetector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

class QueryWatcher implements WatcherInterface
{
    protected array $stack = [];

    protected array $fingerprintStack = [];

    protected bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $detector = app(NplusOneDetector::class);

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use ($detector) {
            foreach ($this->stack as &$scope) {
                $scope++;
            }

            $fingerprint = $detector->normalizeQuery($event->sql);
            foreach ($this->fingerprintStack as &$fingerprints) {
                $fingerprints[] = $fingerprint;
            }
        });

        $this->booted = true;
    }

    public function start(): void
    {
        $this->stack[] = 0;
        $this->fingerprintStack[] = [];
    }

    public function stop(): array
    {
        $count = empty($this->stack) ? 0 : array_pop($this->stack);
        $fingerprints = empty($this->fingerprintStack) ? [] : array_pop($this->fingerprintStack);

        return [
            'queries' => $count,
            'query_fingerprints' => $fingerprints,
        ];
    }

    public function reset(): void
    {
        $this->stack = [];
        $this->fingerprintStack = [];
    }
}
