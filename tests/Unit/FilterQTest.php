<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Hyvor\FilterQ\Exceptions\FilterQException;
use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\TestModel;
use Hyvor\FilterQ\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class FilterQTest extends TestCase {

    public function testEmpty() {

        $filterQ = FilterQ::expression(null)
            ->builder(TestModel::class)
            ->addWhere();

        $filterQ2 = FilterQ::expression('')
            ->builder(TestModel::class)
            ->addWhere();

        $q = TestModel::query();

        $this->assertEquals($filterQ->toSql(), $q->toSql());
        $this->assertEquals($filterQ2->toSql(), $q->toSql());

    }

    public function testWithModel() {

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

    public function testWithEloquentBuilder() {

        $test = TestModel::where('name', 'hi');

        $filterQ = FilterQ::expression('id=1|slug=hello')
            ->builder($test)
            ->keys(function($keys) {
                $keys->add('id');
                $keys->add('slug');
            })
            ->addWhere();

        $q = TestModel::where('name', 'hi')
        ->where(function($query) {
            $query->where('id', 1)
                ->orWhere('slug', 'hello');
        });

        $this->assertEquals($filterQ->toSql(), $q->toSql());

    }

    public function testWithQueryBuilder() {

        $test = DB::table('posts')->where('name', 'hi');

        $filterQ = FilterQ::expression('id=1|slug=hello')
            ->builder($test)
            ->keys(function($keys) {
                $keys->add('id');
                $keys->add('slug');
            })
            ->addWhere();

        $q = DB::table('posts')->where('name', 'hi')
        ->where(function($query) {
            $query->where('id', 1)
                ->orWhere('slug', 'hello');
        });

        $this->assertEquals($filterQ->toSql(), $q->toSql());

    }

    public function test_with_relationship() {

        $testModel = TestModel::factory()->make();
        $test = $testModel->rel()->where(function ($query) {
            $query->where('id', 2);
        });

        $filterQ = FilterQ::expression('id=2')
            ->builder($testModel->rel())
            ->keys(function($keys) {
                $keys->add('id');
            })
            ->addWhere();

        $this->assertEquals($filterQ->toSql(), $test->toSql());

    }

    public function testJoin() {

        $filterQ = FilterQ::expression('author.name=test')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('author.name')
                    ->column('authors.name')
                    ->join('authors', 'authors.id', '=', 'posts.author_id', 'left');
            })
            ->addWhere();

        $q = TestModel::leftJoin('authors', 'authors.id', '=', 'posts.author_id')
            ->where(function($query) {
                $query->where('authors.name', 'test');
            });

        $this->assertEquals($filterQ->toSql(), $q->toSql());


    }

    public function testJoinWithFunc() {

        $filterQWithFunc = FilterQ::expression('author.name=test')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('author.name')
                    ->column('authors.name')
                    ->join(function($query) {
                        $query->join('authors', 'authors.id', '=', 'posts.author_id');
                    });
            })
            ->addWhere();

        $q = TestModel::join('authors', 'authors.id', '=', 'posts.author_id')
            ->where(function($query) {
                $query->where('authors.name', 'test');
            });

        $this->assertEquals($filterQWithFunc->toSql(), $q->toSql());

    }

    public function testCustomOperatorLike() {

        $withLike = FilterQ::expression("title~'Hello%'")
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('title');
            })
            ->operators(function($operators) {
                $operators->add('~', 'LIKE');
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->where('title', 'LIKE', 'Hello%');
        });

        $this->assertEquals($withLike->toSql(), $q->toSql());

    }

    public function testCustomOperatorMatchAgainst() {

        $withMatchAgainst = FilterQ::expression("title!Hello")
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('title');
            })
            ->operators(function($operators) {
                $operators->add('!', function($query, $whereType, $value) {
                    $rawWhere = $whereType === 'and' ? 'whereRaw' : 'orWhereRaw';
                    $query->{$rawWhere}('MATCH (title) AGAINST (?)', [$value]);
                });
            })
            ->addWhere();

        $q = TestModel::where(function($query) {
            $query->whereRaw('MATCH (title) AGAINST (?)', ['Hello']);
        });

        $this->assertEquals($withMatchAgainst->toSql(), $q->toSql());

    }

    public function testExceptionAccessingRemovedOperator() {

        $this->expectException(FilterQException::class);

        FilterQ::expression('id>20')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id');
            })
            ->operators(function($operators) {
                $operators->remove('>');
            })
            ->addWhere();

    }

    public function testExceptionAccessingInvalidOperator() {

        $this->expectException(FilterQException::class);

        FilterQ::expression('id%20')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id');
            })
            ->addWhere();

    }

    public function testKeyOperatorsIncluding() {

        $this->expectException(FilterQException::class);

        FilterQ::expression('id!=20')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')
                    ->operators('=,>,<');
            })
            ->addWhere();

    }

    public function testKeyOperatorsIncludingArray() {

        $this->expectException(FilterQException::class);

        FilterQ::expression('id!=20')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')
                    ->operators(['=', '>']);
            })
            ->addWhere();

    }

    public function testKeyOperatorsExcluding() {

        $this->expectException(FilterQException::class);

        FilterQ::expression('id>20')
            ->builder(TestModel::class)
            ->keys(function($keys) {
                $keys->add('id')
                    ->operators('>', true);
            })
            ->addWhere();

    }

}
