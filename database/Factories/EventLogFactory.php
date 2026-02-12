<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Database\Factories;

use GladeHQ\LaravelEventLens\Models\EventLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventLog>
 */
class EventLogFactory extends Factory
{
    protected $model = EventLog::class;

    public function definition(): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'parent_event_id' => null,
            'event_name' => 'App\Events\TestEvent',
            'listener_name' => 'Closure',
            'execution_time_ms' => $this->faker->randomFloat(2, 1, 50),
            'happened_at' => now(),
        ];
    }

    public function slow(float $ms = 500.0): static
    {
        return $this->state(fn () => [
            'execution_time_ms' => $ms,
        ]);
    }

    public function root(): static
    {
        return $this->state(fn () => [
            'parent_event_id' => null,
        ]);
    }

    public function childOf(EventLog $parent): static
    {
        return $this->state(fn () => [
            'correlation_id' => $parent->correlation_id,
            'parent_event_id' => $parent->event_id,
        ]);
    }

    public function withException(string $message = 'Something went wrong'): static
    {
        return $this->state(fn () => [
            'exception' => "RuntimeException: {$message}",
        ]);
    }

    public function withSideEffects(int $queries = 0, int $mails = 0): static
    {
        return $this->state(fn () => [
            'side_effects' => ['queries' => $queries, 'mails' => $mails],
        ]);
    }
}
