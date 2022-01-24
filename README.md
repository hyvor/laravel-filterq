# Introduction

FilterQ allows advanced filtering in Laravel APIs. FilterQ was created for [Hyvor Blogs Data API]() to allow users to filter data the way **THEY WANT** using nested logic, just like they have access to `WHERE` in the SQL query.

You can get a single-line `filter` expression like this in your APIs.
```
name=starter&(type=image|type=video)
```

And, FilterQ securely converts it to `WHERE` in Laravel Query Builder.
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

FilterQ supports `AND`, `OR`, and grouping logics, which makes it extremely powerful. It's like making it possible for your API consumers to filter data directly using SQL, but securely.

## Features

* Easy-to-write, single-line expressions.
* Logical operators (`&` and `|`) and nesting/grouping (with `()`)
* Secure. FilterQ only gives access to the columns and operators you define.
* Supports joining tables. Users can not only filter by columns but also via joined tables (Amazing, right?)
* Extensible. You can add your own operators easily.

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

### 1. Setting the FilterQ Expression and Builder

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

* `expression()` sets the FilterQ expression
* `builder()` sets the Laravel Query. It accepts be a Laravel Query Builder, Eloquent Query Builder, or a Model.


### 2. Set Keys

Setting keys is important. Laravel uses prepared statements to prevent SQL injection. But, prepared statements only secure "data", not column names. Therefore, to prevent SQL injection, you have to define all keys (or columns) you allow the user to define within the FitlerQ expression.

```php
FilterQ::expression(...)
    ->builder(...)
    ->keys(function ($keys) {
        $keys->add('id')->column('posts.id');
        $keys->add('slug');
    });
```

* `keys()` takes a closure that will be called with `$keys` (`Hyvor\FilterQ\Keys`) object that can be used to set the supported keys.
  * You must call the `$keys->add($key)` to registers a key. It returns a `Hyvor\FilterQ\Key` object, which you can chain to add more options.
  * `Key::column()` sets the column name. If this is not called, FilterQ assumes that the key name is the column name.
  * `Key::join()` sets a join (see below for more details). 


### 3. Finally, call `addWhere()`

After all above operations, call the `addWhere()` method. This will add `where` statements to the builder you provided and will return the builder itself.


* `addWhere()` should be called finally. It returns the query builder, which now has the `where()` statements based on the provided FilterQ expression. You can then do other operations (`limit`, `orderBy`, and even more `where` statements) and call `get()` to get results.


## Joining Tables

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

In this example, a join will be added to the query builder if the `author.name` key is present (Note that even if the key is present multiple times, join will only be added one time). Then, it will add a `where` statement for column `authors.name`.

Here's how the above query will look like in SQL:

```sql
select * from "posts" 
inner join "authors" on "authors"."id" = "posts"."author_id" 
where ("authors"."name" = ?)
```

`->join()` function is same as the [Laravel's Join](https://laravel.com/docs/8.x/queries#joins).

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

## Registering Custom Operators

What if you wanted to support SQL `LIKE`? You can register a custom operator (here you are extending the FilterQ expressions language).

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

This will create this SQL query

```sql
select * from "posts" where ("title" LIKE 'Hello%')
```

## Removing an operator

If you don't want one of default operators, you can remove it using `$operators->remove($operator)`.

```php
->operators(function($operators) {
    $operators->remove('>');
});
```

If someone tries use a non-defined or remove operator, an error will be thrown. See Exception handling below for more details on errors.

## Limiting Operators of Each Key

It is possible and recommended to define what operators are allowed for each key.

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


## Advanced Operators

All operators does not work similar to `LIKE`. For example, MYSQL's `MATCH AGAINST`. Here's how to add an advanced operator like that.

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

FilterQ throws `Hyvor\FilterQ\Exceptions\FilterQException` on an error with a message explaining the issue, which is safe to show to the user.

So, it is better to use FilterQ keeping exception handling in mind.

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

