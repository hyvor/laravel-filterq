<?php

namespace Hyvor\FilterQ;

use Carbon\Carbon;
use Closure;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Hyvor\FilterQ\Exceptions\InvalidValueException;
use Illuminate\Database\Query\Expression;

class Key
{
    private string $name; // key name
    private string $column; // where column name

    private ?Closure $join = null;

    /**
     * @var null|string[]
     */
    private ?array $includedOperators = null;

    /**
     * @var null|string[]
     */
    private ?array $excludedOperators = null;

    /**
     * @var null|mixed[]
     */
    private ?array $supportedValues = null;

    /**
     * @var null|string[]
     */
    private ?array $supportedValueTypes = null;

    public function __construct(string $name)
    {

        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $name)) {
            throw new FilterQException("Invalid key name: $name");
        }

        $this->name = $name;

    }

    /**
     * @param string|Expression $column
     */
    public function column(string|Expression $column) : self
    {
        $this->column = $column;
        return $this;
    }

    /**
     * Set included or excluded operators
     *
     * @param string|string[] $operators
     * @param bool $exclude
     */
    public function operators(string|array $operators, bool $exclude = false) : self
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

    /**
     * Set supported values
     */
    public function values(mixed $values) : self
    {

        if (!is_array($values)) {
            $values = [$values];
        }
        $this->supportedValues = $values;

        return $this;

    }

    /**
     * Set supported value types
     * @param string|string[] $type
     * @throws FilterQException
     */
    public function valueType(array|string $type) : self
    {

        $types = is_string($type) ? explode('|', $type) : $type;

        foreach ($types as $type) {

            if (!in_array($type, ValueValidator::SUPPORTED_VALUES)) {
                throw new FilterQException("Key type $type is not supported");
            }

        }

        $this->supportedValueTypes = $types;

        return $this;

    }

    public function join(mixed ...$joinParams) : self
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

    public function getColumnName() : string
    {
        return $this->column ?? $this->name;
    }

    public function getJoin() : ?callable
    {
        return $this->join;
    }

    /**
     * @return null|string[]
     */
    public function getIncludedOperators() : ?array
    {
        return $this->includedOperators;
    }

    /**
     * @return null|string[]
     */
    public function getExcludedOperators() : ?array
    {
        return $this->excludedOperators;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return mixed[]|null
     */
    public function getSupportedValues(): ?array
    {
        return $this->supportedValues;
    }

    /**
     * @return string[]|null
     */
    public function getSupportedValueTypes(): ?array
    {
        return $this->supportedValueTypes;
    }

}
