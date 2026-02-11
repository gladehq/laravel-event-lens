<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

class OrderPlacedEvent
{
    public TrackableOrder $order;

    public function __construct(TrackableOrder $order)
    {
        $this->order = $order;
    }
}
