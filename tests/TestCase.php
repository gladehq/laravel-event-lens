<?php

namespace GladeHQ\LaravelEventLens\Tests;

use GladeHQ\LaravelEventLens\EventLensServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            EventLensServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:Hupx3yAySikrM2Rower0B1HKM67yUKikav9t5R1er54=');

        // Allow dashboard access in tests
        config()->set('event-lens.authorization', fn () => true);
    }
}
