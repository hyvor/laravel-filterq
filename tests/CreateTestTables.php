<?php
namespace Hyvor\FilterQ\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestTables extends Migration {

    public function up() {
        Schema::create('posts', function(Blueprint $table) {

            $table->id();
            $table->timestamps();

            // string
            $table->string('title');
            // bool
            $table->boolean('is_featured')->default(0);
            // null
            $table->string('slug')->nullable();

            // integer|join
            $table->integer('author_id');

        });

        Schema::create('authors', function(Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
        });
    }

    public function down() {

        Schema::dropIfExists('posts');
        Schema::dropIfExists('authors');

    }

}