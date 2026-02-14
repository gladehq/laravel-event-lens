<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Support;

use Illuminate\Support\Collection;

class TraceTreeBuilder
{
    /**
     * Build a nested tree from a flat collection of events, marking descendant errors.
     */
    public static function build(Collection $events): array
    {
        $tree = static::buildTree($events);

        return static::markDescendantErrors($tree);
    }

    protected static function buildTree(Collection $events, ?string $parentId = null): array
    {
        $branch = [];

        foreach ($events as $event) {
            if ($event->parent_event_id === $parentId) {
                $children = static::buildTree($events, $event->event_id);
                $event->setRelation('children', collect($children));
                $branch[] = $event;
            }
        }

        return $branch;
    }

    protected static function markDescendantErrors(array $nodes): array
    {
        foreach ($nodes as $node) {
            if ($node->children && $node->children->count()) {
                static::markDescendantErrors($node->children->all());
            }

            $node->has_descendant_error = static::hasDescendantError($node);
        }

        return $nodes;
    }

    protected static function hasDescendantError($node): bool
    {
        if (! $node->children || $node->children->isEmpty()) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child->exception !== null) {
                return true;
            }

            if (static::hasDescendantError($child)) {
                return true;
            }
        }

        return false;
    }
}
