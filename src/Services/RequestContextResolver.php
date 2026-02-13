<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

class RequestContextResolver
{
    protected ?string $queueJobName = null;

    public function resolve(): ?array
    {
        if ($this->queueJobName !== null) {
            return [
                'type' => 'queue',
                'job' => $this->queueJobName,
            ];
        }

        if (! app()->runningInConsole()) {
            $request = request();

            return [
                'type' => 'http',
                'method' => $request->method(),
                'path' => $request->path(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ];
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? ($argv[0] ?? null);

        if ($command === null) {
            return null;
        }

        return [
            'type' => 'cli',
            'command' => $command,
        ];
    }

    public function setQueueJobName(?string $name): void
    {
        $this->queueJobName = $name;
    }

    public function reset(): void
    {
        $this->queueJobName = null;
    }
}
