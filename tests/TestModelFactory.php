<?php

namespace Hyvor\FilterQ\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestModelFactory extends Factory
{

    protected $model = TestModel::class;

    public function definition()
    {
        return [
            'id' => 1
        ];
    }

}