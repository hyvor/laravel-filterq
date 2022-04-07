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
     * string or DB::raw()
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
    public function values($values)
    {

        if (!is_array($values)) {
            $values = [$values];
        }
        $this->supportedValues = $values;

    }

    /**
     * Set supported value types
     * @throws FilterQException
     */
    public function valueType(array|string $type) : void
    {

        $types = is_string($type) ? explode('|', $type) : $type;

        $validTypes = [
            // scalar
            'int',
            'float',
            'string',
            'null',
            'bool',

            // other
            'numeric',
            'date',
        ];

        foreach ($types as $type) {

            if (!in_array($type, $validTypes)) {
                throw new FilterQException("Key type $type is not supported");
            }

        }

        $this->supportedValueTypes = $types;

    }

    public function join(...$joinParams) : self
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
    public function getIncludedOperators() : ?array
    {
        return $this->includedOperators;
    }
    public function getExcludedOperators() : ?array
    {
        return $this->excludedOperators;
    }

    /**
     * @template T
     * @param T $value
     * @return T|Carbon
     * @throws InvalidValueException
     */
    public function validateAndSanitizeValue(mixed $value)
    {

        if (is_array($this->supportedValues)) {

            if (!in_array($value, $this->supportedValues)) {
                $values = implode(', ', $this->supportedValues);
                throw new InvalidValueException(
                    "The key $this->name only supports the following values for filtering: $values. '$value' given"
                );
            }

        }

        if (is_array($this->supportedValueTypes)) {

            $isValid = false;

            $valueType = gettype($value);

            foreach ($this->supportedValueTypes as $supportedValueType) {

                if (
                    // scalar
                    ($supportedValueType === 'int' && $valueType === 'integer') ||
                    ($supportedValueType === 'float' && $valueType === 'double') ||
                    ($supportedValueType === 'string' && $valueType === 'string') ||
                    ($supportedValueType === 'bool' && $valueType === 'boolean') ||
                    ($supportedValueType === 'null' && $valueType === 'NULL') ||

                    // functions
                    ($supportedValueType === 'numeric' && is_numeric($value))
                ) {

                    $isValid = true;
                    break;

                } else if ($supportedValueType === 'date') {

                    // Check if the date is valid
                    $date = strtotime($value);

                    if ($date !== false) {
                        $isValid = true;
                        // update value to carbon date
                        $value = Carbon::createFromTimestamp($date);
                        break;
                    }

                }

            }

            if (!$isValid) {
                $validTypesString = implode('|', $this->supportedValueTypes);
                throw new InvalidValueException("Value for $this->name should be one of: $validTypesString");
            }

        }

        return $value;

    }


}
