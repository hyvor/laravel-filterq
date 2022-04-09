<?php

namespace Hyvor\FilterQ;

use Hyvor\FilterQ\Exceptions\ParserException;

class Parser
{

    private string $input;

    /**
     * @var array{
     *      or?: array<array{string, string, mixed}>,
     *      and?: array<array{string, string, mixed}>,
     *      int?: array{string, string, mixed}
     * }
     */
    public array $output = [];

    /**
     * Cursor
     */
    private int $i = 0;

    private const ERROR_AND_OR_TOGETHER = 'AND and OR cannot be combined together. Use parentheses to separate them';
    private const ERROR_NO_CLOSING_PARENTHESIS = 'Closing ) not found';
    private const ERROR_NO_CLOSING_QUOTE = "Closing quote (') not found";

    public function __construct(string $input)
    {

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
         * If we just matched, ( in the beginning and ) at the end, it will not suffice
         */

        /**
         * 1. Match balanced parentheses (not global match)
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
     * This function parses anything between ()
     * It is called recursively for nested ()
     * @return mixed[]
     * @throws ParserException
     */
    private function parseParentheses() : array
    {

        $this->skipWhitespaces();

        /**
         * Skip (
         * This assumes that the cursor is currently at ( after clearing whitespaces
         */
        $this->i++;

        /**
         * Saves the output to return at the end of this function
         */
        $output = [];

        /**
         * AND | OR
         */
        $currentLogic = null;

        while (
            $this->validateEndOfInput(self::ERROR_NO_CLOSING_PARENTHESIS) &&
            $this->input[$this->i] !== ')'
        ) {
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
             * (=|!=|>=|<=|>|<|[!@#$%^&*~`?]{1,2}) - matches an operator [2]
             *      =|!=|>=|<=|>|< - default operators
             *      [!@#$%^&*~`?]{1,2} - custom operators (one or two chars)
             */
            $matched = preg_match(
                '/^([a-zA-Z0-9_.]+)(?:\s+)?(=|!=|>=|<=|>|<|[!@#$%^&*~`?]{1,2})/',
                $this->getNextPart(),
                $keyMatches
            );

            if ($matched) {
                $fullMatch = $keyMatches[0];
                $key = $keyMatches[1];
                $operator = $keyMatches[2];

                $this->i += strlen($fullMatch);

                /**
                 * Find the value which comes after the key+operator
                 */
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

            /**
             * Skip unmatched text/content except )
             */
            if ($this->input[$this->i] !== ')') {
                $this->i++;
            }
        }

        // skip )
        $this->i++;

        $currentLogic ??= 'and';
        return [
            $currentLogic => $output
        ];
    }

    private function parseValue() : mixed
    {
        $this->skipWhitespaces();

        return $this->parseNumber() ??
            $this->parseString() ??
            $this->parseKeyword('true', true) ??
            $this->parseKeyword('false', false) ??
            $this->parseKeyword('null', null);
    }

    /**
     * Parses a number
     */
    private function parseNumber(): int|float|null
    {

        if (
            preg_match(
                '/(^-?\d+(?:(\.)(\d+))?)(?:[^\d]|$)/',
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


    private function parseString(): ?string
    {

        // with quotes
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
        } elseif (
            preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]+/', $this->getNextPart(), $withoutQuotesMatches)
        ) {
            // without quotes

            $string = $withoutQuotesMatches[0];

            if (in_array($string, ['true', 'false', 'null'])) {
                return null;
            }

            $this->i += strlen($string);

            return $string;
        }

        return null;
    }

    private function parseKeyword(string $string, ?bool $value): ?bool
    {

        if (substr($this->input, $this->i, strlen($string)) === $string) {
            $this->i += strlen($string);
            return $value;
        }

        return null;
    }

    /**
     * Get the part after the cursor in the input
     */
    private function getNextPart() : string
    {
        return substr($this->input, $this->i);
    }

    /**
     * Skips whitespaces
     * Cursor placed at the next
     */
    private function skipWhitespaces() : void
    {
        /**
         * No need to check the end of string, because the input is trimmed
         */
        while (
            $this->input[$this->i] === " " ||
            $this->input[$this->i] === "\n" ||
            $this->input[$this->i] === "\t" ||
            $this->input[$this->i] === "\r"
        ) {
            $this->i++;
        }
    }

    /**
     * @throws ParserException
     */
    private function validateEndOfInput(string $error) : bool
    {

        /**
         * Check if the string is over before the next loop
         */
        if (!isset($this->input[$this->i])) {
            throw new ParserException($error);
        }

        return true;
    }

    /**
     * @return array{
     *      or?: array<array{string, string, mixed}>,
     *      and?: array<array{string, string, mixed}>,
     *      int?: array{string, string, mixed}
     * }
     */
    public static function parse(string $input) : array
    {
        return (new self($input))->output;
    }
}
