<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

class TestListener
{
    public static bool $handled = false;

    public function handle(...$payload): string
    {
        static::$handled = true;

        return 'listener-handled';
    }
}
