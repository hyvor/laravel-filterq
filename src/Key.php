<?php
namespace Hyvor\FilterQ;

use Closure;
use Illuminate\Database\Query\Expression;

class Key {

    private string $name; // key name
    private string $column; // where column name
    private array $operators; // operators
    private ?Closure $join = null;

    public function __construct($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * string or DB::raw()
     */
    public function column(string|Expression $column) {
        $this->column = $column;
        return $this;
    }

    public function operators($operators) {
        $this->operators = $operators;
        return $this;
    }
    
    public function join(array|Closure $join) {
        if (is_array($join)) {
            $join = function($query) use ($join) {
                $query->join($join[0], $join[1], $join[2], $join[3]);
            };
        }
        $this->join = $join;
        return $this;
    }

    public function getColumnName() {
        return $this->column ?? $this->name;
    }
    public function getJoin() {
        return $this->join;
    }

}