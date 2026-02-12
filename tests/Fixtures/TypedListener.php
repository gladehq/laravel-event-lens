<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

class TypedListener
{
    public static bool $handled = false;
    public static ?ConstructorParamEvent $receivedEvent = null;

    public function handle(ConstructorParamEvent $event): void
    {
        static::$handled = true;
        static::$receivedEvent = $event;
    }

    public static function reset(): void
    {
        static::$handled = false;
        static::$receivedEvent = null;
    }
}
