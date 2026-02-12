<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

use GladeHQ\LaravelEventLens\Contracts\Taggable;

class TaggableEvent implements Taggable
{
    public string $status;

    public function __construct(string $status = 'active')
    {
        $this->status = $status;
    }

    public function eventLensTags(): array
    {
        return ['user_status' => $this->status];
    }
}
