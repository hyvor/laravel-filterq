## Introduction

FilterQ is a library that converts a single-line logical expression to SQL WHERE (Using Laravel Query Builder).

Your API gets an input like this.
```
id=213|slug='hello'
```

And, FilterQ converts it to a SQL.
```sql
WHERE id = 213 OR slug = 'hello'
```

This allows your API consumers to write complex filtering logic, even with nesting. They can directly use the column names of your table or you can even define custom handlers to do operations like joins!

```
name=starter&(type=image|type=video)
```

```sql
WHERE name = 'starter' AND ( type = 'image' OR type = 'video' )
```

> The library contains two classes: one for parsing single line expressions to a PHP array and the other one to building the Laravel query. If you are not using Laravel, you can use the Parser and adapt its output to your framework.


## Input

Example: `(published_at > 1639665890 & published_at < 1639695890) | is_featured=true`

An **input** is a combination of **conditions**.

A condition consists of three parts:

- `key`
- `operator`
- `value`

### - Operators

- `=` - equals
- `!=` - not equal
- `>` - greater than
- `<` - less than
- `>=` - greater than or equals
- `<=` - less than or equals
- `~` - SQL LIKE (for searching strings), supports wildcard `%`

### - Values

- null: `null`
- boolean: `true` or `false`
- strings: `'hey'` or `hey`
- number: `250`

### - Logical Operators

Use logical operators to combine multiple conditions.

- `&` - AND
- `|` - OR


## Parsing

Input comes as a string (For example, `"id=213|slug=hello`). We want to convert this string to a format that we can easily work with (such as arrays). The `Paser::parse()` function takes a string and converts it to an array.

```php
<?php
use Hyvor\FilterQ\Parser;

$conditions = FilterQ::parse('id=213|slug=hello');
```

Here's the structure of `$conditions`:

```php
[
    "or" => [
        [
            "=": ["id", 213]
        ],
        [
            "=": ["slug", "hello"]
        ]
    ]
]
```

This format was inspired by [JSON Logic](https://jsonlogic.com/). Simply, all logic is written as `{"operator" : ["values" ... ]}`.

## Converting to SQL (Laravel Query Builder)

Converting user input to logic doesn't do much. Let's convert it to SQL via Laravel Query Builder. `Query::addConditions()` adds required `where`, `whereOr`, etc. to the query builder (or model) you provide.

```php
<?php
use Hyvor\FilterQ\Query;

Query::addConditions($query, $conditions);
```

In the real world, you will do something like this:

```php

$keys = ['id', 'slug'];
$condition = FilterQ::parse('id=213|slug=hello');

// initiate a query
$postQuery = DB::table('posts');

// add where statements based on $conditions
$postQuery = Query::addConditions($postQuery, $conditions, $keys);

// finally get the results
$postQuery->limit(25)->get();
```

The above example converts into Laravel like this.

```php
DB::table('posts')
    // this is the magic happens inside addConditions
    ->where(function($query) {
        $query->where('id', '=', 213)
            ->orWhere('slug', '=', 'hello');
    })
    ->limit(25)
    ->get();
```

Or, to SQL like this:

```sql
SELECT * FROM posts
WHERE (id = 213 OR slug = "hello")
LIMIT 25
```

The SQL condition that `addConditions` add is wrapped inside paranthesis, even if it is a single condition. So, you can add more `WHERE` conditions outside of it.

### Keys (or column names)

It is super important to "whitelist" keys because column names are vulnerable to SQL injection because they are not parametrized. You should never trust user input. And, [Laravel Docs](https://laravel.com/docs/8.x/queries) puts  it together like this:

> ...should never allow user input to dictate the column names referenced by your queries, including "order by" columns.

Therefore, `addConditions` will only add `where` queries for `$keys` you have defined. In the previous code, if the user uses a key other than `id` or `slug` (ex: `name='bad'`), it will throw an exception.

### Limiting operators for keys

The `$keys` array can not only be column names but also keyed arrays that define their behaviour.  You can limit operators available for a key by adding `operators` array.

```php
$keys = [
    'id' => [
        'operators' => ['=']
    ]
];
```

If you want to exclude operators,

```php
$keys = [
    'id' => [
        'exclude_operators' => ['~']
    ]
]
```

### Custom Key Handlers

Some APIs may need to use JOINS **only if a key is available**. Use custom key handlers for that. Here's what how [Hyvor blogs Data API /posts endpoint]() achieves selecting posts by author ID.

```php
$keys = [
    'id', 'slug',
    // a special key with a custom handler
    'author.id' => [
        'handler' => function($value, $query) {
            // $value = value of that key (ex: for author.id=213, value is 213)
            // use $query to add custom 
            $query->join('post_author', 'post_author.post_id', '=', 'posts.id')
                ->where('post_author.author_id', $value);
        }
    ]
];

$postQuery = DB::table('posts');
$postQuery = Query::addConditions($postQuery, $conditions, $keys);
$postQuery->limit(25)->get();
```

In this code, if the user sets a key named `author.id`, a custom handler is called, which handles a complex join operation. This makes a lot of things possible.


## Parsing + SQL

To make it simple, you can call `Query::parseAndAddConditions()` to combine `Parser::parse()` and `Query::addConditions()`

```php
// input = the input you send to parse()
// query, condition, keys = params of addConditions()
Query::parseAndAddConditions($input, $query, $condition, $keys);
```