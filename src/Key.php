<?php

namespace Hyvor\FilterQ;

use Closure;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Illuminate\Database\Query\Expression;

class Key
{
    private string $name; // key name
    private string $column; // where column name
    private ?Closure $join = null;

    private ?array $includedOperators = null;
    private ?array $excludedOperators = null;

    public function __construct($name)
    {

        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $name)) {
            throw new FilterQException("Invalid key name: $name");
        }

        $this->name = $name;
        return $this;
    }

    /**
     * string or DB::raw()
     */
    public function column(string|Expression $column)
    {
        $this->column = $column;
        return $this;
    }

    public function operators(string|array $operators, bool $exclude = false)
    {
        if (is_string($operators)) {
            $operators = explode(',', $operators);
        }

        if ($exclude) {
            $this->excludedOperators = $operators;
        } else {
            $this->includedOperators = $operators;
        }
        return $this;
    }

    public function join(...$joinParams)
    {

        $join = $joinParams[0];
        
        if (!is_callable($join)) {
            $join = function ($query) use ($joinParams) {
                $query->join(...$joinParams);
            };
        }

        $this->join = $join;
        return $this;
    }

    public function getColumnName()
    {
        return $this->column ?? $this->name;
    }
    public function getJoin()
    {
        return $this->join;
    }
    public function getIncludedOperators()
    {
        return $this->includedOperators;
    }
    public function getExcludedOperators()
    {
        return $this->excludedOperators;
    }
}
