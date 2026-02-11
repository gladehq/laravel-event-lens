<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

use GladeHQ\LaravelEventLens\Concerns\HasEventLens;
use Illuminate\Database\Eloquent\Model;

class TrackableOrder extends Model
{
    use HasEventLens;

    protected $table = 'trackable_orders';
    protected $guarded = [];
}
