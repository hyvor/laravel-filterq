<?php

namespace Hyvor\FilterQ\Tests\Unit\FilterQ;

use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\TestCase;
use Hyvor\FilterQ\Tests\TestModel;

class KeyValueTypeTest extends TestCase
{

    public function test_key_type_int_check()
    {

        $filterQ = FilterQ::expression("id=2")
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->valueType('int');
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('id', 2);
        });

        $this->assertEquals($q->toSql(), $filterQ->toSql());

    }

    public function test_key_type_int_invalid_check()
    {
        $this->expectException(InvalidValueException::class);

        FilterQ::expression("id='2'")
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->valueType('int');
            })
            ->addWhere();

    }

}