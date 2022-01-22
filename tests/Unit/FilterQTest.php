<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\TestModel;
use Hyvor\FilterQ\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class FilterQTest extends TestCase {

    public function testBasic() {

        $filterQ = FilterQ::expression('id=1|slug=hello')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id');
                $keys->add('slug');
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('id', 1)
                ->orWhere('slug', 'hello');
        });

        $this->assertEquals($filterQ->toSql(), $q->toSql());

    }

    public function testJoin() {

        $filterQ = FilterQ::expression('author.name=test')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('author.name')
                    ->column('authors.name')
                    ->join(function());
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('id', 1)
                ->orWhere('slug', 'hello');
        });

        $this->assertEquals($filterQ->toSql(), $q->toSql());

    }

}
