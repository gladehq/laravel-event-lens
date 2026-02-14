<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Commands;

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Support\TraceTreeBuilder;
use Illuminate\Console\Command;

class TraceCommand extends Command
{
    protected $signature = 'event-lens:trace {correlationId} {--json : Output as JSON}';
    protected $description = 'Display an event trace tree for a given correlation ID';

    public function handle(): int
    {
        $correlationId = $this->argument('correlationId');

        $events = EventLog::forCorrelation($correlationId)
            ->orderBy('happened_at')
            ->get();

        if ($events->isEmpty()) {
            $this->error("No events found for correlation ID: {$correlationId}");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($events->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $tree = TraceTreeBuilder::build($events);
        $this->renderTree($tree);

        return self::SUCCESS;
    }

    protected function renderTree(array $nodes, int $depth = 0): void
    {
        foreach ($nodes as $node) {
            $indent = str_repeat('  ', $depth);
            $prefix = $depth > 0 ? "{$indent}├─ " : '';

            $time = number_format($node->execution_time_ms, 2);
            $color = $this->timeColor($node->execution_time_ms);

            $name = $node->listener_name === 'Event::dispatch'
                ? $node->event_name
                : $node->listener_name;

            $extra = [];
            if ($node->exception) {
                $extra[] = '<fg=red>ERROR</>';
            }
            $sideEffects = $node->side_effects ?? [];
            if (($sideEffects['queries'] ?? 0) > 0) {
                $extra[] = ($sideEffects['queries']) . 'q';
            }
            if (($sideEffects['mails'] ?? 0) > 0) {
                $extra[] = ($sideEffects['mails']) . 'm';
            }
            if (($sideEffects['http_calls'] ?? 0) > 0) {
                $extra[] = ($sideEffects['http_calls']) . 'h';
            }

            $extraStr = $extra ? ' [' . implode(', ', $extra) . ']' : '';

            $this->line("{$prefix}<fg={$color}>{$name}</> <fg=gray>{$time}ms</>{$extraStr}");

            if ($node->children && $node->children->count()) {
                $this->renderTree($node->children->all(), $depth + 1);
            }
        }
    }

    protected function timeColor(float $ms): string
    {
        if ($ms > 500) return 'red';
        if ($ms > 100) return 'yellow';
        return 'green';
    }
}
