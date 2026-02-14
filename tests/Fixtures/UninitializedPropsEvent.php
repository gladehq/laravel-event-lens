<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

use Illuminate\Foundation\Events\Dispatchable;

class UninitializedPropsEvent
{
    use Dispatchable;

    public string $required;
    public int $count;
    public string $initialized = 'hello';
}
