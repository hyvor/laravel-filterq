<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Carbon\Carbon;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Hyvor\FilterQ\Key;
use Hyvor\FilterQ\Tests\TestCase;

class KeyTest extends TestCase {

    public function testKeyName()
    {
        $this->expectException(FilterQException::class);
        new Key('(E*@NCQLK');
    }

}
