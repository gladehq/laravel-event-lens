<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

class ConstructorParamEvent
{
    public function __construct(
        public string $name,
        public int $priority = 1,
    ) {}
}
