<?php

namespace Hyvor\FilterQ;

use Closure;

class Operators
{
    private array $operators = [];

    public function __construct()
    {
        // add defaults
        $this->add('=');
        $this->add('!=');
        $this->add('<');
        $this->add('>');
        $this->add('<=');
        $this->add('>=');
    }

    public function add(string $operator, null|string|Closure $sqlOperator = null)
    {
        $this->operators[$operator] = $sqlOperator ?? $operator;
    }

    public function remove(string $operator)
    {
        if (isset($this->operators[$operator])) {
            unset($this->operators[$operator]);
        }
    }

    public function get(string $operator)
    {
        return $this->operators[$operator] ?? null;
    }
}
