<?php

namespace GladeHQ\LaravelEventLens\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $guarded = [];
    protected $attributes = ['id' => 1, 'name' => 'Test'];

    public function toArray()
    {
        throw new \Exception("Should use attributesToArray only!");
    }
}
