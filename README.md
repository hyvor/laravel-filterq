# Introduction

FilterQ allows advanced filtering in Laravel APIs. For example, you can get a single-line input like this from your users.

```
name=starter&(type=image|type=video)
```

Then, FilterQ can convert it to `WHERE` statements in Laravel Query Builder like this:

```php
->where(function($query) { // wrapper

    $query->where('name', 'starter')
        ->where(function($query) {
            $query->where('type', 'image')
                ->orWhere('type', 'video');
        });

})
```

or in SQL:

```sql
WHERE ( name = 'starter' AND ( type = 'image' OR type = 'video') )
```

It's like making it possible for your API consumers to filter data directly using SQL, but securely.

---
FilterQ was built for [Hyvor Blogs](https://blogs.hyvor.com)' Data API.

---

## Features

* Easy-to-write, single or multi-line expressions.
* Logical operators (`&` and `|`) and nesting/grouping (with `()`)
* Secure. FilterQ only gives access to the columns and operators you define.
* Supports joining tables. Users can not only filter by columns but also via joined tables (Amazing, right?)
* Supports "type hinting" for keys.
* Extensible. You can add your own operators easily (ex: SQL `LIKE`).

# FilterQ Expressions

Example: `(published_at > 1639665890 & published_at < 1639695890) | is_featured=true`

A **FitlerQ Expression** is a combination of **conditions**, connected and grouped using one or more of the following.

- `&` - AND
- `|` - OR
- `()` - to group logic

A condition has three parts:

- `key`
- `operator`
- `value`

### Key

Usually, a key is a column name of the table. But, it can also be something else where you use a custom handler to create the `where` part in Laravel.

It should match `[a-zA-Z0-9_.]+`. For example, `key`, `key_2`, `key.child` are valid.

### Operators

By default, the following operators are supported.

- `=` - equals
- `!=` - not equal
- `>` - greater than
- `<` - less than
- `>=` - greater than or equals
- `<=` - less than or equals

If you want to add more operators (ex: an operator for SQL `LIKE`), see [Custom Operators](#custom-operators).

### Values

- null: `null`
- boolean: `true` or `false`
- strings: `'hey'` or `hey`
  - Strings without quotes should match `[a-zA-Z_][a-zA-Z0-9_-]+`.
    - should start with a letter
    - can contain alphanumeric characters, dashes, or underscores
    - no spaces are allowed
    - it cannot be `true`, `false`, or `null`
- number: `250`, `-250`, or `2.5`

# Basic Usage

```
composer require hyvor/laravel-filterq
```

```php
use Hyvor\FilterQ\Facades\FilterQ;

$query = FilterQ::expression('id=100|slug=hello')
    ->builder(Post::class)
    ->keys(function($keys) {
        $keys->add('id')->column('posts.id');
        $keys->add('slug');
        $keys->add('author.name')
            ->column('authors.name')
            ->join('authors', 'authors.id', '=', 'posts.author_id', 'left');
    })
    ->addWhere();

$posts = $query
    ->limit(25)
    ->orderBy('id', 'DESC')
    ->get();
```

`FilterQ` is a [Laravel Facade](https://laravel.com/docs/8.x/facades), so you can start with any method you like. The last method must be `addWhere()`.

Let's learn step by step.

### 1. Setting the FilterQ Expression and Builder

In most cases, you will get a single input in your API endpoints as the FilterQ expression. Therefore, here's an example with a controller.

```php

class MyController {

    public function handle(Request $request) {

        $filter = $request->input('filter');

        FilterQ::expression($expression)
            ->builder(Post::class)
            // other methods...

    }

}
```

* `FilterQ::expression()` sets the FilterQ expression
* `builder()` sets the Laravel Query. It accepts a Laravel Query Builder, Eloquent Query Builder, Relation, or a Model.

### 2. Set Keys

Setting keys is important. Laravel uses prepared statements to prevent SQL injection. But, prepared statements only secure "data", not column names. Therefore, to prevent SQL injection, you have to define all keys (or columns) you allow the user to define within the FitlerQ expression.

Here, we define two keys: `id` and `slug`. So, users can use these two keys in their FilterQ expressions. Using any other will throw an error.

```php
FilterQ::expression(...)
    ->builder(Post::class)
    ->keys(function ($keys) {
        $keys->add('id')->column('posts.id');
        $keys->add('slug');
    });
```

* `keys()` takes a closure that will be called with a `$keys` (`Hyvor\FilterQ\Keys`) object.
  * You must call the `$keys->add($key)` to registers a key. It returns a `Hyvor\FilterQ\Key` object, which you can chain to add more options.
  * `Key::column()` sets the column name. If this is not called, FilterQ uses the key name as the column name.
  * `Key::join()` [sets a join](#joins).
  * `Key::operators()` [sets supported operators](#key-operators)
  * `Key::valueType()` [defines supported value types](#key-value-types)
  * `Key::values()` [defines supported values](#key-values)


### 3. Finally, call `addWhere()`

After all the above operations, call the `addWhere()` method. This will add `where` statements to the builder you provided and will return the query builder itself. You can then do other operations like `limit`, `orderBy`, and even add more `where` statements. Finally, call `get()` to get results.

```php
$posts = FilterQ::expression(...)
    ->builder(...)
    ->keys(...)
    ->addWhere()
    ->limit(...)
    ->orderBy(...)
    ->get();
```

## Joins

Sometimes, you want to join a foreign table when a key is present. Let's see this example.

* You have two tables: `posts` and `authors`.
* You have a `/posts` endpoint with FilterQ expressions support.
* You now want to allow users to filter posts by `author.name`

```php
FilterQ::expression('author.name=hyvor')
    ->builder(Post::class)
    ->keys(function($keys) {
        $keys->add('author.name')
            ->column('authors.name')
            ->join('authors', 'authors.id', '=', 'posts.author_id');
    })
    ->addWhere();
```

In this example, two things are done on the `Post::class` query builder.
1. A `->join()` is added to the query builder because the `author.name` key is present.
2. A `->where()` is added for `authors.name` column.

> It is important to note that even if the same key is present multiple times, only one join will only be added.

Here's what the above query will look like in SQL:

```sql
select * from "posts" 
inner join "authors" on "authors"."id" = "posts"."author_id" 
where ("authors"."name" = 'hyvor')
```

`->join()` function takes the same arguments as the [Laravel's Join](https://laravel.com/docs/8.x/queries#joins).

### Left and Right Joins

The above example makes an `INNER JOIN`. If you want to add left or right joins, use the fourth parameter.

```php
->join('authors', 'authors.id', '=', 'posts.author_id', 'left')
```

### JOIN with a callback

If you want to add [Advanced Joins](https://laravel.com/docs/8.x/queries#advanced-join-clauses) or [Subquery Joins](https://laravel.com/docs/8.x/queries#subquery-joins), use a callback.

```php

// join with WHERE
$keys->add(...)
    ->join(function($query) {
        $query->join('authors', function($join) {
            $join->on('authors.id', '=', 'posts.author_id')
                ->where('authors.status', '!=', 'active');
        });
    });

// subquery JOINS
$keys->add(...)
    ->join(function($query) {
        $query->joinSub(...);
    });
```

## Key Operators

It is possible (and recommended) to define what operators are allowed by a key.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(function($keys) {
        // only these operators will be allowed (comma-separated string)
        $keys->add('id')->operators('=,>,<');

        // or use an array
        $keys->add('slug')->operators(['=', '!=']);

        // exclude operators (use true as the second param)
        $keys->add('age')->operators('>', true);
    })
    ->addWhere();
```

## Key Value Types

It is possible (and highly recommended) to define what value types are supported by a key.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(function($keys) {

        $keys->add('id')->valueType('integer');
        $keys->add('name')->valueType('string');
        $keys->add('description')->valueType('string|null'); // |-seperated types
        $keys->add('title')->valueType(['string', 'null']); // or an array
        $keys->add('created_at')->valueType('date');

    });
```

The `valueType` method supports the following types: 

Scalar:

* `int`
* `float`
* `string`
* `null`
* `bool`

Special:

* `numeric` - int, float, or numeric string (uses PHP's [is_numeric](https://www.php.net/manual/en/function.is-numeric.php))
* `date` - A valid date/time string or an integer UNIX timestamp. (PHP's [strtotime](https://www.php.net/manual/en/function.strtotime.php) function is used to parse, therefore relative dates like "-7 days" are supported).

You may specify multiple types using the `|` character or by sending an array.

```php
$keys->add('created_at')->valueType('date|null');
// or
$keys->add('created_at')->valueType(['date', 'null']);
```

## Key Values

It is possible to set what values are supported by a key. This is mostly useful for enum columns.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(function($keys) {
    
        // allows either published or draft
        $keys->add('status')->values(['published', 'draft']); 

        // only 200 is allowed
        $keys->add('id')->values(200);

    });
```

## Custom Operators

What if you wanted to support SQL `LIKE` as an operator? You can register a custom operator.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(...)
    ->operators(function ($operators) {
        $operators->add('~', 'LIKE');
    });
```

* `$operators->add($filterQOperator, $sqlOperator)`
* `$filterQOperator` should match this regex: ``[!@#$%^&*~`?]{1,2}``. In simple terms, you can use these special characters (`!` `@` `#` `$` `%` `^` `&` `*` `~` `?`) one or two times as an operator.
* `$sqlOperator` is its corresponding SQL operator.

Let's see an example.

```php
FilterQ::expression("title~'Hello%'")
    ->builder(Post::class)
    ->keys(function($keys) {
        $keys->add('title');
    })
    ->operators(function($operators) {
        $operators->add('~', 'LIKE');
    })
    ->addWhere();
```

This will create the following SQL query

```sql
select * from "posts" where ("title" LIKE 'Hello%')
```

## Removing an operator

If you don't want one of the default operators, you can remove it using `$operators->remove($operator)`.

```php
->operators(function($operators) {
    $operators->remove('>');
});
```

## Advanced Operators

Not all operators work similar to `LIKE`. For example, MYSQL's `MATCH AGAINST`. Here's how to add an advanced operator like that.

> **Important**: Use the `where`/`orWhere`, `whereRaw`/`orWhere` correctly as shown in the example below. Otherwise, logic may not work as expected.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(...)
    ->operators(function($operators) {
        $operators->add('!', function($query, $whereType, $value) {

            /**
             * $query - use it to add WHERE statements
             * $whereType - and|or (current logical scope)
             * $value - value in the FilterQ expression
             */

            // THIS IS IMPORTANT!!!
            $rawWhere = $whereType === 'and' ? 'whereRaw' : 'orWhereRaw';

            // $query->whereRaw()
            $query->{$rawWhere}('MATCH (title) AGAINST (?)', [$value]);
        
        });
    })
    ->addWhere();
```

## Exceptions Handling

It is advised to use FilterQ with proper exception handling.

FilterQ can throw 3 errors:

* `Hyvor\FilterQ\Exceptions\FilterQException`
* `Hyvor\FilterQ\Exceptions\ParserException` - when there's an error parsing the FilterQ Expression.
* `Hyvor\FilterQ\Exceptions\InvalidValueException` - when an invalid value is used as per defined by [key values](#key-values) or [key value types](#key-value-types)

The last two errors extend the `FilterQException` error, so catching the first one is enough. All errors have a "safe" message (English only), which can be displayed back to users for API debugging purposes.

```php
try {

    $postsBuilder = FilterQ::expressions(...)
        ->builder(...)
        ->keys(...)
        ->addWhere();

} catch (FilterQException $e) {
    dd($e->getMessage());
}

$posts = $postsBuilder->get();
```

