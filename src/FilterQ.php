<?php
namespace Hyvor\FilterQ;

class FilterQ {

    /**
     * string '=' or
     * array  ['~', 'LIKE'] [filterQ operator, SQL operator]
     */
    private $operators = [
        '=', '!=', '>', '<', '>=', '<='
    ];

    /**
     * Allowed keys
     */
    private $keys = [];

    public function __construct(string $input) {
        $this->input = Parser::parse($input);

        
    }

    public function addOperator(string $operator, ?string $sqlOperator = null) {
        $this->operators[] = $sqlOperator === null ? $operator : [$operator, $sqlOperator];
    }

    public function removeOperators(string|array $operators) {
        if (is_string($operators)) {
            $operators = [$operators];
        }
        $this->operators = array_diff($this->operators, $operators);
    }

    public function setKeys($keys) {

    }


    static function parse(string $input) {
        return new self($input);
    }
}