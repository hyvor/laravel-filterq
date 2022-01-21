<?php
namespace Hyvor\FilterQ;

use Illuminate\Database\Query\Builder;

class FilterQ {

    /**
     * Allowed keys
     */
    private $keys = [];

    /**
     * The input string
     * contains the logic
     */
    private string $input;

    /**
     * The builder which we add where statements to
     */
    private Builder $builder;

    /**
     * [
     *  '=' => '='
     * ]
     * key = the operator in the logical expression
     * value = the SQL operator that it converts to
     */
    private array $operators = [
        '=' => '=',
        '!=' => '!=',
        '>' => '>',
        '<' => '<',
        '>=' => '>=',
        '<=' => '<=',
    ];

    public function input(string $input) {
        $this->input = $input;
        return $this;
    }

    public function builder(Builder $builder) {
        $this->builder = $builder;
        return $this;
    }

    public function addOperator(string $operator, string $sqlOperator) {
        $this->operators[$operator] = $sqlOperator;

        return $this;
    }

    public function removeOperators(string $operator) {
        if (isset($this->operators[$operator])) {
            unset($this->operators[$operator]);
        }
        return $this;
    }

    public function setOperators(array $operators) {
        $this->operators = $operators;
        return $this;
    }


    public function finish() {
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

        $type = $logicChunk['type'];
        $logicChunkWhere = $type === 'and' ? 'where' : 'orWhere';

        foreach ($logicChunk['conditions'] as $condition) {

            if (isset($condition['type'])) {
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

                $query->{$logicChunkWhere}($key, $operator, $value);
    
            }

        }        

    }


}