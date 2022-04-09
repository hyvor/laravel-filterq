<?php

namespace Hyvor\FilterQ;

use Closure;

class Operators
{

    /**
     * @var array{string?: string|Closure}
     */
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

    public function add(string $operator, null|string|Closure $sqlOperator = null) : self
    {
        $this->operators[$operator] = $sqlOperator ?? $operator;
        return $this;
    }

    public function remove(string $operator) : self
    {
        if (isset($this->operators[$operator])) {
            unset($this->operators[$operator]);
        }
        return $this;
    }

    /**
     * @param string $operator
     * @return null|string|Closure
     */
    public function get(string $operator)
    {
        return $this->operators[$operator] ?? null;
    }
}
