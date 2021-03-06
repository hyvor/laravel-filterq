<?php

namespace Hyvor\FilterQ;

use Closure;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FilterQ
{
    /**
     * The FilterQ expression
     */
    private string $expression;

    /**
     * The builder which we add where statements to
     */
    private EloquentBuilder|QueryBuilder|Relation $builder;

    /**
     * Supported operators globally (when key-level operators are not set)
     */
    private Operators $operators;

    /**
     * Allowed keys
     *
     *  key => column_name for where
     */
    private Keys $keys;

    /**
     * To make sure a key's join is only run one time
     * @var string[]
     */
    private array $joinedKeys = [];

    public function __construct()
    {
        $this->keys = new Keys();
        $this->operators = new Operators();
    }

    public function expression(?string $expression) : self
    {
        $this->expression = trim($expression ?? '');
        return $this;
    }

    public function builder(EloquentBuilder|QueryBuilder|Relation|string $builder) : self
    {

        /**
         * Convert model to EloquentBuilder
         */
        if (is_string($builder) && is_subclass_of($builder, Model::class)) {
            $builder = $builder::query();
        }

        if (!(
            $builder instanceof EloquentBuilder ||
            $builder instanceof QueryBuilder ||
            $builder instanceof Relation
        )) {
            throw new FilterQException('Builder must be an instanceof Eloquent or Query builder');
        }

        $this->builder = $builder;
        return $this;
    }

    public function keys(Closure $closure): FilterQ
    {
        $closure($this->keys);
        return $this;
    }

    public function operators(Closure $closure): FilterQ
    {
        $closure($this->operators);
        return $this;
    }


    public function addWhere(): EloquentBuilder|QueryBuilder|Relation
    {

        if (empty($this->expression)) {
            return $this->builder;
        }

        $parsed = Parser::parse($this->expression);

        /**
         *
         * $parsed = [
         *  'or' => [
         *      ['id', '=', 1],
         *      ['slug', '=', 'hello'],
         *      [
     *              'and' => [
         *              ['title', '=', 'hey']
         *          ]
 *              ]
         *  ]
         * ]
         */

        $this->builder->where(function ($query) use ($parsed) {
            $this->addWhereToQuery($query, $parsed);
        });

        return $this->builder;
    }

    /**
     * @param mixed[] $logicChunk
     * @throws FilterQException
     */
    private function addWhereToQuery(EloquentBuilder|QueryBuilder $query, array $logicChunk) : void
    {

        $type = array_key_exists('or', $logicChunk) ? 'or' : 'and';
        $logicChunkWhere = $type === 'and' ? 'where' : 'orWhere';

        foreach ($logicChunk[$type] as $condition) {
            if (isset($condition['and']) || isset($condition['or'])) {
                /**
                 * Logical condition (AND|OR)
                 */
                $query->{$logicChunkWhere}(function ($q) use ($condition) {
                    $this->addWhereToQuery($q, $condition);
                });
            } else {

                /**
                 * Comparison condition
                 */
                $key = $condition[0];
                $operator = $condition[1];
                $value = $condition[2];

                /**
                 *
                 */
                $keyInst = $this->keys->get($key);

                /**
                 * Only supported keys can be used
                 */
                if ($keyInst === null) {
                    throw new FilterQException("Key '$key' is not supported for filtering");
                }

                /**
                 * @throw InvalidValueException
                 */
                $value = ValueValidator::validate($keyInst, $value);

                $column = $keyInst->getColumnName();
                $join = $keyInst->getJoin();

                /**
                 * Check if the operator is valid
                 */
                $sqlOperator = $this->operators->get($operator);
                if (!$sqlOperator) {
                    throw new FilterQException("Operator '$operator' not supported for filtering");
                }

                /**
                 * Check if the operator is allowed in the key
                 */
                $includedOperators = $keyInst->getIncludedOperators();
                $excludedOperators = $keyInst->getExcludedOperators();
                if (
                    ($includedOperators !== null && !in_array($operator, $includedOperators)) ||
                    ($excludedOperators !== null && in_array($operator, $excludedOperators))
                ) {
                    throw new FilterQException("Operator '$operator' is not allowed for filtering (with $key)");
                }

                /**
                 * Join a table
                 */
                if ($join) {
                    /**
                     * Make sure a join of a key is only run one time
                     * even there are multiple usages in the logic
                     */
                    if (!in_array($key, $this->joinedKeys)) {
                        $join($this->builder);
                        $this->joinedKeys[] = $key;
                    }
                }

                if (is_callable($sqlOperator)) {

                    /**
                     * Operator set to callback to do things like whereRaw
                     * Now user has to handle the where operation.
                     * If not handled correctly, it can completely break the SQL query
                     */
                    $sqlOperator(
                        $query,
                        $type, // or|and
                        $value,
                    );

                } else {

                    /**
                     * We handle the where
                     */
                    $query->{$logicChunkWhere}($column, $sqlOperator, $value);

                }
            }
        }
    }
}
