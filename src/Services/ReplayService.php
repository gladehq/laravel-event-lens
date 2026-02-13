<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Services;

use GladeHQ\LaravelEventLens\Models\EventLog;

class ReplayService
{
    /**
     * Replay a previously recorded event by re-dispatching it.
     *
     * @return array{success: bool, error?: string, correlation_id?: string}
     */
    public function replay(EventLog $event): array
    {
        if (! config('event-lens.allow_replay', false)) {
            return ['success' => false, 'error' => 'Event replay is disabled. Set allow_replay to true in config/event-lens.php.'];
        }

        if ($event->listener_name !== 'Event::dispatch') {
            return ['success' => false, 'error' => 'Only root events (Event::dispatch) can be replayed.'];
        }

        $eventClass = $event->event_name;

        if (! class_exists($eventClass)) {
            return ['success' => false, 'error' => "Event class {$eventClass} no longer exists."];
        }

        try {
            $instance = $this->reconstruct($eventClass, $event->payload);

            event($instance);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reconstruct an event object from stored payload data.
     */
    protected function reconstruct(string $eventClass, ?array $payload): object
    {
        $payload = $payload ?? [];

        // Strip internal metadata keys
        $payload = collect($payload)
            ->except(['__context', '__request_context'])
            ->all();

        $reflection = new \ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            $instance = $reflection->newInstance();

            foreach ($payload as $key => $value) {
                if ($reflection->hasProperty($key) && $reflection->getProperty($key)->isPublic()) {
                    $instance->{$key} = $value;
                }
            }

            return $instance;
        }

        // Try matching constructor parameters by name from payload
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $payload)) {
                $args[] = $payload[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}
