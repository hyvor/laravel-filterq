<?php
namespace Hyvor\FilterQ;

use Closure;
use Hyvor\FilterQ\Exceptions\FilterQException;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FilterQ {

    /**
     * The input string
     * contains the logic
     */
    private string $input;

    /**
     * The builder which we add where statements to
     */
    private EloquentBuilder|QueryBuilder $builder;

    /**
     * Supported operators globally (when key-level operators are not set)
     */
    private array $operators = ['=', '!=', '<', '>', '<=', '>=', '~'];

    /**
     * Allowed keys
     *  
     *  key => column_name for where
     */
    private Keys $keys;

    /**
     * To make sure a key's join is only run one time
     */
    private array $joinedKeys = [];

    public function __construct() {
        $this->keys = new Keys();
    }

    public function input(string $input) {
        $this->input = $input;
        return $this;
    }

    public function builder(EloquentBuilder|QueryBuilder|string $builder) {

        /**
         * Convert model to EloquentBuilder
         */
        if (is_string($builder) && is_subclass_of($builder, Model::class)) {
            $builder = $builder::query();
        }

        if (!($builder instanceof EloquentBuilder || $builder instanceof QueryBuilder)) {
            throw new FilterQException('Builder must be an instanceof Eloquent or Query builder');
        }

        $this->builder = $builder;
        return $this;
    }
    
    public function keys(Closure $closure) : FilterQ {
        $closure($this->keys);
        return $this;
    }

    public function operators(string|array $operators, bool $not = false) : FilterQ {
        
        return $this;
    }


    public function addWhere() : EloquentBuilder|QueryBuilder {

        $parsed = Parser::parse($this->input, $this->operators); 

       // dd($parsed);
        /**
         * Logic chunk = [
         *  'type' => and|or
         *  'conditions' => [
         *      ['key', '=', 'value']
         *  ]
         * ]
         */

        $this->builder->where(function($query) use ($parsed) {
            $this->addWhereToQuery($query, $parsed);
        });

        return $this->builder;
    }

    private function addWhereToQuery($query, $logicChunk) {

        $type = isset($logicChunk['or']) ? 'or' : 'and';
        $logicChunkWhere = $type === 'and' ? 'where' : 'orWhere';

        foreach ($logicChunk[$type] as $condition) {

            if (isset($condition['and']) || isset($condition['or'])) {
                /**
                 * Logical condition (AND|OR)
                 */
                $query->{$logicChunkWhere}(function($q) use ($condition) {
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

                $column = $keyInst->getColumnName();
                $join = $keyInst->getJoin();

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

                $query->{$logicChunkWhere}($column, $operator, $value);
    
            }

        }        

    }


}