<?php

use Hyvor\FilterQ\Exceptions\ParserException;
use Hyvor\FilterQ\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

    public function testParsingKeywords() {

        $this->assertEquals(
            Parser::parse('key=true'),
            [
                'and' => [
                    ['key', '=', true]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key!=false'),
            [
                'and' => [
                    ['key', '!=', false]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key=null'),
            [
                'and' => [
                    ['key', '=', null]
                ]
            ]
        );

    }

    public function testParsingNumbers() {

        $this->assertEquals(
            Parser::parse('key=200'),
            [
                'and' => [
                    ['key', '=', 200]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key>2000000000'),
            [
                'and' => [
                    ['key', '>', 2000000000]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key<-100'),
            [
                'and' => [
                    ['key', '<', -100]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key<=2.5'),
            [
                'and' => [
                    ['key', '<=', 2.5]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key>=-2.5'),
            [
                'and' => [
                    ['key', '>=', -2.5]
                ]
            ]
        );

    }

    public function testParingStrings() {

        $this->assertEquals(
            Parser::parse("key='Hello World'"),
            [
                'and' => [
                    ['key', '=', 'Hello World']
                ]
            ]
        );

        // with escaping
        $this->assertEquals(
            Parser::parse("key='Hello \'World\''"),
            [
                'and' => [
                    ['key', '=', "Hello 'World'"]
                ]
            ]
        );

    }

    public function testParsingStringsWithoutQuotes() {

        $this->assertEquals(
            Parser::parse("key=hello"),
            [
                'and' => [
                    ['key', '=', 'hello']
                ]
            ]
        );
 
        $this->assertEquals(
            Parser::parse("key=hello_world"),
            [
                'and' => [
                    ['key', '=', 'hello_world']
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse("key=hello-world"),
            [
                'and' => [
                    ['key', '=', 'hello-world']
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse("key=_0139210a-fejlwq"),
            [
                'and' => [
                    ['key', '=', '_0139210a-fejlwq']
                ]
            ]
        );

    }

    public function testExceptionNoClosingParentheses() {
        /**
         * Error thrown when parenthesis does not match
         */
        $this->expectException(ParserException::class);

        Parser::parse('some=2&(dawl=21');
    }

    public function testExceptionNoClosingQuote() {
        /**
         * Error thrown when parenthesis does not match
         */
        $this->expectException(ParserException::class);

        Parser::parse("key='hello");
    }

    public function testExceptionAndOrTogether() {
        /**
         * Error thrown when parenthesis does not match
         */
        $this->expectException(ParserException::class);

        Parser::parse('key=1|key2=2&key3=3');
    }

    public function testNested() {

        $this->assertEquals(
            Parser::parse('key1=1&(key2=2|key3=3)'),
            [
                'and' => [
                    ['key1', '=', '1'],
                    [
                        'or' => [
                            ['key2', '=', '2'],
                            ['key3', '=', '3'],
                        ]
                    ]
                ]
            ]
        );

        $this->assertEquals(
            Parser::parse('key1=1&(key2=2|(key3=3&key4=4&key5=5))'),
            [
                'and' => [
                    ['key1', '=', '1'],
                    [
                        'or' => [
                            ['key2', '=', '2'],
                            [
                                'and' => [
                                    ['key3', '=', '3'],
                                    ['key4', '=', '4'],
                                    ['key5', '=', '5'],
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );

    }

}