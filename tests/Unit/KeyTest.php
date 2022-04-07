<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Carbon\Carbon;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Hyvor\FilterQ\Key;
use Hyvor\FilterQ\Tests\TestCase;
use Hyvor\FilterQ\Tests\TestModel;
use Illuminate\Support\Facades\DB;

class KeyTest extends TestCase {

    public function test_key_name()
    {
        $this->expectException(FilterQException::class);
        new Key('(E*@NCQLK');
    }

    public function test_key_column()
    {
        $key = new Key('test');
        $key->column('age');

        $this->assertEquals('age', $key->getColumnName());
    }

    public function test_key_column_expression()
    {
        $key = new Key('test');
        $expression = DB::raw('COUNT(id) as count');
        $key->column($expression);

        $this->assertEquals($expression, $key->getColumnName());
    }

    public function test_key_operators()
    {
        $key = new Key('test');
        $key->operators('>,<');

        $this->assertEquals(['>', '<'], $key->getIncludedOperators());
    }

    public function test_key_operators_array()
    {
        $key = new Key('test');
        $key->operators(['>','<']);

        $this->assertEquals(['>', '<'], $key->getIncludedOperators());
    }

    public function test_key_values()
    {
        $key = new Key('test');
        $key->values(200);

        $this->assertEquals([200], $key->getSupportedValues());
    }

    public function test_key_values_array()
    {
        $key = new Key('test');
        $key->values([1,2]);

        $this->assertEquals([1,2], $key->getSupportedValues());
    }

    public function test_key_value_type()
    {
        $key = new Key('test');
        $key->valueType('string');

        $this->assertEquals(['string'], $key->getSupportedValueTypes());
    }

    public function test_key_value_type_multi()
    {
        $key = new Key('test');
        $key->valueType('string|null');

        $this->assertEquals(['string', 'null'], $key->getSupportedValueTypes());
    }

    public function test_key_value_type_wrong()
    {
        $this->expectException(FilterQException::class);

        $key = new Key('test');
        $key->valueType('stringify');
    }

    public function test_join()
    {

        $joinParams = ['tags', 'tags.id', '=', 'post.tag_id'];

        $key = new Key('test');
        $key->join(...$joinParams);

        $query = TestModel::join(...$joinParams)->toSql();

        $joinFunc = $key->getJoin();
        $newQuery = TestModel::query();
        $joinFunc($newQuery);

        $this->assertEquals(
            $query,
            $newQuery->toSql()
        );
    }

    public function test_join_callback()
    {
        $key = new Key('test');

        $joinCallback = function($join) {
            $join->on('tags.id', '=', 'post.tag_id')
                ->where('id', 2);
        };

        $callback = function ($query) use ($joinCallback) {
            $query->join('tags', $joinCallback);
        };
        $key->join($callback);

        $query = TestModel::join('tags', $joinCallback)->toSql();

        $joinFunc = $key->getJoin();
        $newQuery = TestModel::query();
        $joinFunc($newQuery);

        $this->assertEquals(
            $query,
            $newQuery->toSql()
        );

    }

}