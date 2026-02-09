<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestEvent
{
    public $user;
    public $secret = 'hidden';

    public function __construct($user = null)
    {
        $this->user = $user;
    }
}
