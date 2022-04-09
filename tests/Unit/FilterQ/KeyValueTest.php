<?php

namespace Hyvor\FilterQ\Tests\Unit\FilterQ;

use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\TestCase;
use Hyvor\FilterQ\Tests\TestModel;

/**
 * Tests key values and value types
 */
class KeyValueTest extends TestCase
{

    public function test_key_value()
    {
        $filterQ = FilterQ::expression('id=200')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->values(200);
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('id', 200);
        });

        $this->assertEquals($q->toSql(), $filterQ->toSql());
    }

    public function test_key_value_invalid()
    {
        $this->expectException(InvalidValueException::class);

        FilterQ::expression('id=200')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->values(300);
            })
            ->addWhere();
    }

    public function test_key_values()
    {

        $filterQ = FilterQ::expression('id=200|id=300')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->values([200,300]);
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('id', 200)
                ->orWhere('id', 300);
        });

        $this->assertEquals($q->toSql(), $filterQ->toSql());

    }

    public function test_key_values_invalid()
    {
        $this->expectException(InvalidValueException::class);

        FilterQ::expression('id=200|id=300')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->values([200,400]);
            })
            ->addWhere();
    }

    public function test_key_invalid_values_one_among_many()
    {
        $this->expectException(InvalidValueException::class);

        FilterQ::expression('id=200|slug=photo')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')->values([200,400]);
                $keys->add('slug')->values(['audio', 'type']);
            })
            ->addWhere();
    }

}