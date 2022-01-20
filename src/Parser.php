<?php
namespace Hyvor\FilterQ;

use Hyvor\FilterQ\Exceptions\ParserException;
use Hyvor\FilterQ\Exceptions\ParserNotNumberException;

class Parser {

    /**
     * Saves the jsonlogic-like output
     */
    public array $output = [];

    /**
     * Cursor
     */
    private int $i = 0;

    private CONST ERROR_AND_OR_TOGETHER = 'AND and OR cannot be combined together. Use parentheses to seperate them';
    private CONST ERROR_NO_CLOSING_PARENTHESIS = 'Closing ) not found';
    private CONST ERROR_NO_CLOSING_QUOTE = "Closing quote (') not found";

    public function __construct(string $input) {

        /**
         * Remove whitespaces
         */
        $this->input = trim($input);
        
        /**
         * Add parentheses around input if not
         * This makes it possible to run the first step just like other steps
         * 
         * We should not be fooled by something like this:
         * (free=false)|(other=true)
         * 
         * If we just matched, ( in the begining and ) at the end, it will not suffice
         */
        
        /**
         * 1. Match balanced parentheses (no global)
         *    https://stackoverflow.com/a/35271017/9059939
         * 2. If the length of the fully matched string is not the length of the input,
         *    It means there are no global brackets
         */
        if (
            !preg_match('/\((?:[^)(]+|(?R))*+\)/', $this->input, $matches) ||
            strlen($matches[0]) !== strlen($input)
        ) {
            $this->input = "($this->input)";
        }

        $this->output = $this->parseParentheses();
    }

    /**
     * This funcrtion parses anything between ()
     * It is called recursively
     */
    private function parseParentheses() {

        $this->skipWhitespaces();

        /**
         * Skip (
         * This assumes that the cursor is currently at ( after clearing whitespaces
         */
        $this->i++;

        /**
         * Saves the output to return at the end of this function
         * This is 
         */
        $output = [];
        $currentLogic = null;

        while ($this->validateEndOfInput() && $this->input[$this->i] !== ')') {

            $this->skipWhitespaces();

            if ($this->input[$this->i] === '(') {
                $parsed = $this->parseParentheses();
                $output[] = $parsed;
                continue;
            }

            /**
             * Match the next part to see if it is a key + operator declaration
             * 
             * ([a-zA-Z0-9_.]+) - matches and saves a key [1]
             * (?:\s+)? - matches (dont save) any spaces
             * (=|!=|>|<|>=|<=) - matches an operator [2]
             */
            $matched = preg_match('/^([a-zA-Z0-9_.]+)(?:\s+)?(=|!=|>=|<=|>|<)/', $this->getNextPart(), $keyMatches);

            if ($matched) {
                $fullMatch = $keyMatches[0];
                $key = $keyMatches[1];
                $operator = $keyMatches[2];

                $this->i += strlen($fullMatch);

                $value = $this->parseValue();

                $output[] = [$key, $operator, $value];

                continue;
            }

            // and
            if ($this->input[$this->i] === '&') {
                if ($currentLogic === 'or') {
                    throw new ParserException(self::ERROR_AND_OR_TOGETHER);
                }
                $currentLogic = 'and';
                $this->i++;

                continue;
            }

            // or
            if ($this->input[$this->i] === '|') {
                if ($currentLogic === 'and') {
                    throw new ParserException(self::ERROR_AND_OR_TOGETHER);
                }
                $currentLogic = 'or';
                $this->i++;

                continue;
            }

            $this->i++;
        }

        // skip )
        $this->i++;

        $currentLogic ??= 'and';
        return [
            $currentLogic => $output
        ];
    }

    private function parseValue() {
        $this->skipWhitespaces();

        /**
         * It is important to have true/false/null before parsing strings, 
         * because those values can be matched as literals in parseString
         */
        return $this->parseNumber() ??
            $this->parseString() ??
            $this->parseKeyword('true', true) ??
            $this->parseKeyword('false', false) ?? 
            $this->parseKeyword('null', null);
    }

    private function parseNumber() : int|float|null {    

        if (
            preg_match(
                '/(^\-?\d+(?:(\.)(\d+))?)(?:[^\d]|$)/', 
                $this->getNextPart(), 
                $numberMatches
            )
        ) {
            $number = $numberMatches[1];
            $isFloat = $numberMatches[2] ?? false;

            $this->i += strlen($number);

            if ($isFloat) {
                return (float) $number;
            } else {
                return (int) $number;
            }
        }

        return null;

    }


    private function parseString() : ?string {
        

        if ($this->input[$this->i] === "'") {
            $this->i++;

            $string = "";

            while (
                $this->validateEndOfInput(self::ERROR_NO_CLOSING_QUOTE) &&
                $this->input[$this->i] !== "'"
            ) {

                if ($this->input[$this->i] === "\\") {

                    $nextChar = $this->input[$this->i + 1];
                    if ($nextChar === "'" || $nextChar === '\\') {
                        $string .= $nextChar;
                        $this->i += 2;
                    }

                } else {
                    $string .= $this->input[$this->i];
                    $this->i++;
                }

            }

            $this->i++;

            return $string;

        } else if (
            preg_match('/^[a-zA-Z0-9_-]+/', $this->getNextPart(), $withoutQuotesMatches)
        ) {

            $string = $withoutQuotesMatches[0];

            if (in_array($string, ['true', 'false', 'null']))
                return null;

            $this->i += strlen($string);

            return $string;

        }

        return null;

    }

    private function parseKeyword($string, $value) : ?bool {

        if (substr($this->input, $this->i, strlen($string)) === $string) {
            $this->i += strlen($string);
            return $value;
        }

        return null;

    }




    /**
     * Get the part after the cursor in the input
     */
    private function getNextPart() {
        return substr($this->input, $this->i);
    }

    /**
     * Skips whitespaces
     * Cursor placed at the next
     */
    private function skipWhitespaces() {
        while (
            $this->input[$this->i] === " " ||
            $this->input[$this->i] === "\n" ||
            $this->input[$this->i] === "\t" ||
            $this->input[$this->i] === "\r"
        ) {
            $this->i++;
        }
    }

    private function validateEndOfInput($error = null) {
        /**
         * Check if the string is over before the next loop
         */
        $error ??= self::ERROR_NO_CLOSING_PARENTHESIS;
        if (!isset($this->input[$this->i])) {
            throw new ParserException($error);
            return false;
        }

        return true;
    }



    static function parse(string $input) {
        return (new self($input))->output;
    }

}