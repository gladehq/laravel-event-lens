<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

class ReplayableEvent
{
    public string $orderId;
    public string $status;

    public function __construct(string $orderId, string $status = 'pending')
    {
        $this->orderId = $orderId;
        $this->status = $status;
    }
}
