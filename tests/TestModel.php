<?php
namespace Hyvor\FilterQ\Tests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model {

    use HasFactory;

    protected $table = 'posts';
    protected $guarded = [];

    public function rel() {
        return $this->hasMany(TestModel2::class);
    }

    protected static function newFactory()
    {
        return TestModelFactory::new();
    }

}
