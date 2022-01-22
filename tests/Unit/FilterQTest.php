<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Hyvor\FilterQ\Facades\FilterQ;
use Hyvor\FilterQ\Tests\PostModel;
use Hyvor\FilterQ\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class FilterQTest extends TestCase {

    public function test() {

        $query = FilterQ::expression('(author.name=12|author.name~20)&id=12093&slug=12kj')
            ->builder(PostModel::class)
            ->keys(function($keys) {

                $keys->add('author.name')
                    ->column('authors.name')
                    ->operators('~', true)
                    ->join(['authors', 'authors.id', '=', 'posts.author_id']);

                $keys->add('id')->column(DB::raw('posts.id'));
                $keys->add('slug');

            })
            ->operators(function ($operators) {

                $operators->add('~', 'LIKE');
            
            })
            ->addWhere();

        $query->dd();

    }

}