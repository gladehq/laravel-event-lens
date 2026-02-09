<?php

namespace GladeHQ\LaravelEventLens\Collectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EventCollector
{
    public function collectPayload($event, $payload): array
    {
        $data = is_object($event) ? $event : $payload;
        return $this->serialize($data);
    }

    public function collectModelChanges($event): array
    {
        $changes = [];
        if (is_object($event)) {
            foreach ((new \ReflectionClass($event))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($event);
                
                if ($value instanceof Model && $value->isDirty()) {
                   $changes[$property->getName()] = $value->getDirty();
                }
            }
        }
        return $changes;
    }

    protected function serialize($data): array
    {
        return $this->redact($this->normalize($data));
    }
    
    protected function normalize($data, $depth = 0)
    {
        if ($depth > 5) return '[DEPTH EXCEEDED]';

        if ($data instanceof Model) {
            // SAFE MODE: Only attributes, no relationships
            return $data->attributesToArray();
        }

        if ($data instanceof Collection) {
            return $data->map(fn($item) => $this->normalize($item, $depth + 1))->toArray();
        }
        
        if (is_object($data)) {
             if (method_exists($data, 'toArray')) {
                 return $this->normalize($data->toArray(), $depth + 1);
             }
             return $this->normalize((array) $data, $depth + 1);
        }
        
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalize($v, $depth + 1);
            }
            return $data;
        }
        
        if (is_string($data)) {
            // Binary Check
            if (! mb_check_encoding($data, 'UTF-8')) {
                return '[BINARY DATA ' . strlen($data) . ' bytes]';
            }
            // Truncation
            if (strlen($data) > 1024) {
                 return substr($data, 0, 1024) . '... [TRUNCATED]';
            }
        }

        return $data;
    }

    protected function redact(array $data, int $depth = 0): array
    {
        if ($depth > 5) return $data;

        $redactedKeys = config('event-lens.redacted_keys', ['password', 'token']);

        foreach ($data as $key => $value) {
            if (in_array($key, $redactedKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value, $depth + 1);
            }
        }

        return $data;
    }
}
