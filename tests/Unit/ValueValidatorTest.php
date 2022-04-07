<?php

namespace Hyvor\FilterQ\Tests\Unit;

use Carbon\Carbon;
use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Hyvor\FilterQ\Key;
use Hyvor\FilterQ\Tests\TestCase;
use Hyvor\FilterQ\ValueValidator;

class ValueValidatorTest extends TestCase
{

    public function test_key_value() {

        $key = new Key('test');
        $key->values([200, 250]);

        $validated = ValueValidator::validate($key, 250);

        $this->assertEquals($validated, 250);

    }

    public function test_key_value_wrong() {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->values([200, 250]);
        ValueValidator::validate($key, 300);

    }

    public function test_key_value_type_string() {

        $key = new Key('test');
        $key->valueType('string');
        $value = ValueValidator::validate($key, 'some string');


        $this->assertEquals($value, 'some string');
    }

    public function test_key_value_type_string_wrong() {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('string');
        ValueValidator::validate($key, 300);

    }

    public function test_key_value_type_int()
    {

        $key = new Key('test');
        $key->valueType('int');
        $value = ValueValidator::validate($key, 200);

        $this->assertEquals($value, 200);

    }

    public function test_key_value_type_int_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('int');
        ValueValidator::validate($key, 'string');

    }

    public function test_key_value_type_float()
    {

        $key = new Key('test');
        $key->valueType('float');
        $value = ValueValidator::validate($key, 20.0);

        $this->assertEquals($value, 20.0);

    }

    public function test_key_value_type_float_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('float');
        ValueValidator::validate($key, 20);

    }

    public function test_key_value_type_numeric()
    {

        $key = new Key('test');
        $key->valueType('numeric');

        $this->assertEquals(ValueValidator::validate($key, 20), 20);
        $this->assertEquals(ValueValidator::validate($key, '20'), '20');
        $this->assertEquals(ValueValidator::validate($key, 20.2), 20.2);

    }

    public function test_key_value_type_numeric_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('numeric');
        ValueValidator::validate($key, null);

    }

    public function test_key_value_type_bool()
    {

        $key = new Key('test');
        $key->valueType('bool');

        $this->assertEquals(ValueValidator::validate($key, true), true);
        $this->assertEquals(ValueValidator::validate($key, false), false);

    }

    public function test_key_value_type_bool_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('bool');
        ValueValidator::validate($key, null);

    }

    public function test_key_value_type_null()
    {

        $key = new Key('test');
        $key->valueType('null');

        $this->assertEquals(ValueValidator::validate($key, null), null);

    }

    public function test_key_value_type_null_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('null');
        ValueValidator::validate($key, 1220);

    }

    public function test_key_value_type_date()
    {

        $key = new Key('test');
        $key->valueType('date');

        $this->assertEquals(ValueValidator::validate($key, '2020-02-10'), Carbon::parse('2020-02-10'));

    }

    public function test_key_value_type_date_relative()
    {

        $key = new Key('test');
        $key->valueType('date');

        $this->assertEquals(ValueValidator::validate($key, 'yesterday'), Carbon::parse('yesterday'));

    }

    /**
     * @group failing
     */
    public function test_key_value_type_date_unix()
    {

        $key = new Key('test');
        $key->valueType('date');

        $this->assertEquals(ValueValidator::validate($key, 1649358544), Carbon::createFromTimestamp(1649358544));

    }

    public function test_key_value_type_date_wrong()
    {

        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('date');
        ValueValidator::validate($key, 'fslerklwao');

    }

    public function test_key_value_type_union()
    {

        $key = new Key('test');
        $key->valueType('date|null');

        $this->assertEquals(ValueValidator::validate($key, null), null);

    }

    public function test_key_value_type_union_wrong()
    {
        $this->expectException(InvalidValueException::class);

        $key = new Key('test');
        $key->valueType('date|null');

        ValueValidator::validate($key, true);

    }

    public function test_key_value_type_union_array()
    {

        $key = new Key('test');
        $key->valueType(['int', 'string']);

        $this->assertEquals(ValueValidator::validate($key, 'string'), 'string');

    }


}