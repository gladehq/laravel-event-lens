<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Watchers;

use GladeHQ\LaravelEventLens\Contracts\WatcherInterface;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

class MailWatcher implements WatcherInterface
{
    protected array $stack = [];

    protected bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Event::listen(MessageSending::class, function () {
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

        return ['mails' => $count];
    }
}
