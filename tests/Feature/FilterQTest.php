<?php
namespace Hyvor\FilterQ\Tests\Feature;

use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/* class FilterQTest extends TestCase {

    public function test() {

        $query = FilterQ::input('hello=12&hyvor=talk&id=12093&slug=12kj')
            ->builder(DB::table('posts'))
            ->addOperator('~', 'LIKE')
            ->finish();

        //$query->dd();

    }

} */