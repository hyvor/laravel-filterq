<?php
namespace Hyvor\FilterQ\Tests\Unit;

use Hyvor\FilterQ\Exceptions\ParserException;
use Hyvor\FilterQ\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

    public function testParsingKeywords() {

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', true]
                ]
            ],
            Parser::parse('key=true')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '!=', false]
                ]
            ],
            Parser::parse('key!=false')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', null]
                ]
            ],
            Parser::parse('key=null')
        );

    }

    public function testParsingNumbers() {

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 200]
                ]
            ],
            Parser::parse('key=200')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '>', 2000000000]
                ]
            ],
            Parser::parse('key>2000000000')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '<', -100]
                ]
            ],
            Parser::parse('key<-100')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '<=', 2.5]
                ]
            ],
            Parser::parse('key<=2.5')
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '>=', -2.5]
                ]
            ],
            Parser::parse('key>=-2.5')
        );

    }

    public function testParingStrings() {

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 'Hello World']
                ]
            ],
            Parser::parse("key='Hello World'")
        );

        // with escaping
        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', "Hello 'World'"]
                ]
            ],
            Parser::parse("key='Hello \'World\''")
        );

    }

    public function testParsingStringsWithoutQuotes() {

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 'hello']
                ]
            ],
            Parser::parse("key=hello")
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 'hello_world']
                ]
            ],
            Parser::parse("key=hello_world")
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 'hello-world']
                ]
            ],
            Parser::parse("key=hello-world")
        );

        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', '_0139210a-fejlwq']
                ]
            ],
            Parser::parse("key=_0139210a-fejlwq")
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

    public function testParsingNested() {

        $this->assertEquals(
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
            ],
            Parser::parse('key1=1&(key2=2|key3=3)')
        );

        $this->assertEquals(
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
            ],
            Parser::parse('key1=1&(key2=2|(key3=3&key4=4&key5=5))')
        );

    }

    public function testParsingMultiline() {

        $this->assertEquals(
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
            ],
            Parser::parse("
                key1 = 1 &
                (
                    key2 = 2 |
                    key3 = 3
                )
            ")
        );

    }

    public function testParsingInvalid() {

        // skips the outside of quotes
        $this->assertEquals(
            [
                'and' => [
                    ['key', '=', 'hello'],
                ]
            ],
            Parser::parse("key='hello'world")
        );

    }

    public function test_parsing_nested()
    {

        $this->assertEquals(
            [
                'or' => [
                    [
                        'and' => [
                            ['key1', '=', 1],
                            ['key', '=', 2]
                        ]
                    ],
                    [
                        'and' => [
                            ['key', '=', 2]
                        ]
                    ]
                ]
            ],
            Parser::parse('((key1=1&key=2)|(key=2))')
        );

    }

}