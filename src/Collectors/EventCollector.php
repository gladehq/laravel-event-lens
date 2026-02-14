<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Collectors;

use GladeHQ\LaravelEventLens\Contracts\Taggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EventCollector
{
    protected ?array $redactedKeys = null;

    public function collectPayload($event, $payload): array
    {
        $data = is_object($event) ? $event : $payload;
        return $this->serialize($data);
    }

    /**
     * Single reflection pass to collect model changes AND model identity.
     *
     * @return array{model_changes: array, model_type: string|null, model_id: int|string|null}
     */
    public function collectModelInfo($event): array
    {
        $changes = [];
        $modelType = null;
        $modelId = null;

        if (is_object($event)) {
            foreach ((new \ReflectionClass($event))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (! $property->isInitialized($event)) {
                    continue;
                }
                $value = $property->getValue($event);

                if ($value instanceof Model) {
                    // Capture first model for polymorphic tracking
                    if ($modelType === null) {
                        $modelType = get_class($value);
                        $modelId = $value->getKey();
                    }

                    if ($value->isDirty()) {
                        $changes[$property->getName()] = $value->getDirty();
                    }
                }
            }
        }

        return [
            'model_changes' => $changes,
            'model_type' => $modelType,
            'model_id' => $modelId,
        ];
    }

    /**
     * Extract structured context from a thrown exception.
     */
    public function extractExceptionContext(?\Throwable $exception): ?array
    {
        if ($exception === null) {
            return null;
        }

        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_slice(
                array_map(function ($frame) {
                    return [
                        'file' => $frame['file'] ?? null,
                        'line' => $frame['line'] ?? null,
                        'class' => $frame['class'] ?? null,
                        'function' => $frame['function'] ?? null,
                    ];
                }, $exception->getTrace()),
                0,
                5
            ),
        ];
    }

    public function collectTags($event): ?array
    {
        if ($event instanceof Taggable) {
            return $event->eventLensTags();
        }

        return null;
    }

    protected function serialize($data): array
    {
        $normalized = $this->normalize($data);

        if (! is_array($normalized)) {
            $normalized = ['__value' => $normalized];
        }

        return $this->redact($normalized);
    }

    protected function normalize($data, $depth = 0)
    {
        if ($depth > 5) return '[DEPTH EXCEEDED]';

        if ($data instanceof Model) {
            return $data->attributesToArray();
        }

        if ($data instanceof Collection) {
            return $data->map(fn($item) => $this->normalize($item, $depth + 1))->toArray();
        }

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return $this->normalize($data->toArray(), $depth + 1);
            }
            return $this->normalize($this->safeObjectToArray($data), $depth + 1);
        }

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalize($v, $depth + 1);
            }
            return $data;
        }

        if (is_string($data)) {
            if (! mb_check_encoding($data, 'UTF-8')) {
                return '[BINARY DATA ' . strlen($data) . ' bytes]';
            }
            if (strlen($data) > 1024) {
                return substr($data, 0, 1024) . '... [TRUNCATED]';
            }
        }

        return $data;
    }

    protected function safeObjectToArray(object $data): array
    {
        $result = [];
        $ref = new \ReflectionClass($data);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isInitialized($data)) {
                $result[$property->getName()] = '[UNINITIALIZED]';
                continue;
            }
            $result[$property->getName()] = $property->getValue($data);
        }

        return $result;
    }

    protected function redact(array $data, int $depth = 0): array
    {
        if ($depth > 5) {
            return $data;
        }

        $redactedKeys = $this->redactedKeys ??= array_map(
            'strtolower',
            config('event-lens.redacted_keys', ['password', 'token'])
        );

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $redactedKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value, $depth + 1);
            }
        }

        return $data;
    }
}
