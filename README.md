## Introduction

FilterQ allows advanced filtering in Laravel APIs. FilterQ was created for [Hyvor Blogs Data API]() to allow users to filter data the way **THEY WANT** using nested logic, just like they have access to `WHERE` in the SQL query.

You can get a single `filter` input like this in your APIs.
```
name=starter&(type=image|type=video)
```

And, FilterQ securely converts it to `WHERE` in Laravel Query Builder.
```php
->where('name', 'starter')
->where(function($query) {
    $query->where('type', 'image')
        ->orWhere('type', 'video');
});
```

FilterQ supports `AND`, `OR`, and grouping logics, which makes it extremely powerful. It's like making it possible for your API consumers to filter data directly using SQL, but securely.

### Features

* Easy-to-write, single-line expressions. Peace of mind for your API users.
* Logical expressions and nesting (`&` - AND, `|` - OR, `()`)
* Secure. FilterQ only gives access to the columns and operators you define.
* Supports joining tables. Users can not only filter by columns but also via joined tables (Amazing, right?)
* Extensible. You can add your own operators easily.

> The library contains two classes: one for parsing single line expressions to a PHP array and the other one to building the Laravel query. If you are not using Laravel, you can use the Parser and adapt its output to your framework.
## FilterQ Expressions

Example: `(published_at > 1639665890 & published_at < 1639695890) | is_featured=true`

A **FitlerQ Expression** is a combination of **conditions**, connected and grouped by the following.

- `&` - AND
- `|` - OR
- `()` - to group logic

A condition has three parts:

- `key`
- `operator`
- `value`

### - Key

Usually, the key is a column name of a table. But, it can also be something else where you use a custom handler to create the `where` part in Laravel.

It should match `[a-zA-Z0-9_.]+`. For example, `key`, `key_2`, `key.child` are valid.

### - Operators

By default, the following operators are supported.

- `=` - equals
- `!=` - not equal
- `>` - greater than
- `<` - less than
- `>=` - greater than or equals
- `<=` - less than or equals

If you want to add more operators (ex: an operator for SQL `LIKE`), see [Adding New Operators](#adding-operators).

### - Values

- null: `null`
- boolean: `true` or `false`
- strings: `'hey'` or `hey`
  - If you are using strings without quotes, it should match `[a-zA-Z_][a-zA-Z0-9_-]+`. 
    - should start with a letter
    - can contain alphanumeric characters, dashes, or underscores
    - no spaces are allowed
    - it cannot be `true`, `false`, or `null` (meaning will become different)
- number: `250`, `-250`, or `2.5`

## Basic Usage

```php
use Hyvor\FilterQ\Facades\FilterQ;

$query = FilterQ::expression('id=100|slug=hello') // FilterQ expression (usually from $request->input('filter'))
    ->builder(Post::class) // model
    ->keys(function($keys) { // set keys
        $keys->add('id')->column('posts.id');
        $keys->add('slug');
        $keys->add('author.name')
            ->column('authors.name')
            ->join('left', 'authors', 'authors.id', '=', 'posts.author_id');
    })
    ->addWhere();

$posts = $query
    ->limit(25)
    ->orderBy('id', 'DESC')
    ->get();
```

* `expression()` sets the FilterQ expression
* `builder()` sets the Laravel Query
* `keys()` takes a closure that sets the supported keys for filtering. 
  * `$keys->add($key)` registers a key and returns a `Hyvor\FilterQ\Key` object, which you can chain to add more options
  * `Key::column()` sets the column name. If this is not called, FilterQ assumes that the key name is the column name. If you use joins, it is useful to define the column name to avoid column name ambiguity.
  * `Key::join($joinType, $joinTable, $)` sets a join. 
* `addWhere()` should be called finally. It returns the query builder, which now has the `where()` statements based on the provided FilterQ expression. You can then do other operations (`limit`, `orderBy`, and even more `where` statements) and call `get()` to get results.

## Parsing

Input comes as a string (For example, `"id=213|slug=hello`). We want to convert this string to a format that we can easily work with (such as a PHP array). The `Paser::parse()` function takes a string and converts it to an array.

```php
<?php
use Hyvor\FilterQ\Parser;

$conditions = FilterQ::parse('id=213|slug=hello');
```

Here's the structure of `$conditions`:

```php
[
    "or" => [     
        ["id", "=", 213]
        ["slug", "=", "hello"]
    ]
]
```

## Converting to SQL (Laravel Query Builder)

Converting user input to a PHP array doesn't do much. Let's convert it to SQL via Laravel Query Builder. `Query::addConditions()` adds required `where`, `whereOr`, etc. to a query builder (or model) you provide.

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