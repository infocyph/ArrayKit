<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\DotNotation;

it('flattens a multi-level array into dot notation', function () {
    $source = ['user' => ['name' => 'Alice', 'roles' => ['admin', 'editor']]];
    $flat   = DotNotation::flatten($source);
    expect($flat)->toBe([
        'user.name'      => 'Alice',
        'user.roles.0'   => 'admin',
        'user.roles.1'   => 'editor',
    ]);
});

it('expands a dot-notation array back to nested structure', function () {
    $dotArray = [
        'app.name' => 'MyApp',
        'app.env'  => 'local',
    ];
    $expanded = DotNotation::expand($dotArray);
    expect($expanded)->toBe([
        'app' => [
            'name' => 'MyApp',
            'env'  => 'local',
        ],
    ]);
});

it('gets a nested value with dot notation', function () {
    $array = ['db' => ['host' => 'localhost', 'port' => 3306]];
    expect(DotNotation::get($array, 'db.port'))
        ->toBe(3306)
        ->and(DotNotation::get($array, 'db.user', 'root'))->toBe('root');
});

it('sets a nested value with dot notation', function () {
    $array = [];
    DotNotation::set($array, 'session.timeout', 120);
    expect($array)->toBe(['session' => ['timeout' => 120]]);
});

it('forgets a nested key with dot notation', function () {
    $array = ['user' => ['name' => 'Alice', 'email' => 'alice@example.com']];
    DotNotation::forget($array, 'user.email');
    expect($array)->toBe(['user' => ['name' => 'Alice']]);
});

//
// Test flatten() and expand()
//
it('flattens a multidimensional array using dot notation', function () {
    $data = [
        'user' => [
            'name' => 'John',
            'email' => 'john@example.com',
        ],
        'order' => [
            'id' => 123,
            'total' => 99.99,
        ],
    ];

    $flattened = DotNotation::flatten($data);
    expect($flattened)->toBe([
        'user.name' => 'John',
        'user.email' => 'john@example.com',
        'order.id' => 123,
        'order.total' => 99.99,
    ]);
});

it('expands a flattened array back into a multidimensional array', function () {
    $flattened = [
        'user.name' => 'John',
        'user.email' => 'john@example.com',
        'order.id' => 123,
        'order.total' => 99.99,
    ];
    $expanded = DotNotation::expand($flattened);
    expect($expanded)->toBe([
        'user' => [
            'name' => 'John',
            'email' => 'john@example.com',
        ],
        'order' => [
            'id' => 123,
            'total' => 99.99,
        ],
    ]);
});

//
// Test has() and hasAny()
//
it('checks that has() returns true when keys exist', function () {
    $data = [
        'user' => ['name' => 'Alice'],
        'order' => ['id' => 10],
    ];
    expect(DotNotation::has($data, 'user.name'))
        ->toBeTrue()
        ->and(DotNotation::has($data, ['user.name', 'order.id']))->toBeTrue();
});

it('checks that has() returns false if a key is missing', function () {
    $data = [
        'user' => ['name' => 'Alice'],
    ];
    expect(DotNotation::has($data, 'user.email'))->toBeFalse();
});

it('checks that hasAny() returns true if at least one key exists', function () {
    $data = [
        'user' => ['name' => 'Alice'],
    ];
    expect(DotNotation::hasAny($data, ['user.email', 'user.name']))->toBeTrue();
});

//
// Test get()
//
it('returns entire array if no key is provided', function () {
    $data = ['a' => 1, 'b' => 2];
    expect(DotNotation::get($data))->toBe($data);
});

it('retrieves a nested value using dot notation', function () {
    $data = [
        'user' => ['name' => 'Bob', 'age' => 30],
    ];
    expect(DotNotation::get($data, 'user.name'))->toBe('Bob');
});

it('returns default value if key is not found', function () {
    $data = ['a' => 1];
    expect(DotNotation::get($data, 'b', 'default'))->toBe('default');
});

it('retrieves multiple keys when passed an array', function () {
    $data = [
        'user' => ['name' => 'Carol', 'email' => 'carol@example.com'],
        'order' => ['id' => 101],
    ];
    $result = DotNotation::get($data, ['user.name', 'order.id'], 'none');
    expect($result)->toBe([
        'user.name' => 'Carol',
        'order.id' => 101,
    ]);
});

//
// Test set() and fill()
//
it('sets a nested value using dot notation', function () {
    $data = [];
    DotNotation::set($data, 'user.name', 'Diana');
    expect($data)->toBe([
        'user' => ['name' => 'Diana']
    ]);
});

it('replaces the entire array if key is null in set()', function () {
    $data = ['a' => 1];
    DotNotation::set($data, null, ['b' => 2]);
    expect($data)->toBe(['b' => 2]);
});

it('sets multiple key-value pairs when given an array in set()', function () {
    $data = [];
    DotNotation::set($data, [
        'user.name' => 'Eve',
        'user.email' => 'eve@example.com'
    ]);
    expect($data)->toBe([
        'user' => [
            'name' => 'Eve',
            'email' => 'eve@example.com'
        ]
    ]);
});

it('does not overwrite existing keys when fill() is used', function () {
    $data = ['user' => ['name' => 'Frank']];
    DotNotation::fill($data, 'user.name', 'George');
    expect($data['user']['name'])->toBe('Frank');
});

it('fills missing keys when fill() is used', function () {
    $data = ['user' => []];
    DotNotation::fill($data, 'user.email', 'frank@example.com');
    expect($data['user']['email'])->toBe('frank@example.com');
});

//
// Test type-specific retrieval: string, integer, float, boolean, arrayValue
//
it('retrieves a string value with string()', function () {
    $data = ['key' => 'hello'];
    expect(DotNotation::string($data, 'key'))->toBe('hello');
});

it('throws exception in string() if value is not string', function () {
    $data = ['key' => 123];
    expect(fn () => DotNotation::string($data, 'key'))->toThrow(InvalidArgumentException::class);
});

it('retrieves an integer value with integer()', function () {
    $data = ['key' => 42];
    expect(DotNotation::integer($data, 'key'))->toBe(42);
});

it('throws exception in integer() if value is not int', function () {
    $data = ['key' => '42'];
    expect(fn () => DotNotation::integer($data, 'key'))->toThrow(InvalidArgumentException::class);
});

it('retrieves a float value with float()', function () {
    $data = ['key' => 3.14];
    expect(DotNotation::float($data, 'key'))->toBe(3.14);
});

it('throws exception in float() if value is not float', function () {
    $data = ['key' => '3.14'];
    expect(fn () => DotNotation::float($data, 'key'))->toThrow(InvalidArgumentException::class);
});

it('retrieves a boolean value with boolean()', function () {
    $data = ['key' => true];
    expect(DotNotation::boolean($data, 'key'))->toBeTrue();
});

it('throws exception in boolean() if value is not bool', function () {
    $data = ['key' => 'true'];
    expect(fn () => DotNotation::boolean($data, 'key'))->toThrow(InvalidArgumentException::class);
});

it('retrieves an array value with arrayValue()', function () {
    $data = ['key' => [1, 2, 3]];
    expect(DotNotation::arrayValue($data, 'key'))->toBe([1, 2, 3]);
});

it('throws exception in arrayValue() if value is not array', function () {
    $data = ['key' => 'not an array'];
    expect(fn () => DotNotation::arrayValue($data, 'key'))->toThrow(InvalidArgumentException::class);
});

//
// Test pluck()
//
it('plucks multiple values from an array using dot notation', function () {
    $data = [
        'user' => ['name' => 'Helen', 'email' => 'helen@example.com'],
        'order' => ['id' => 555, 'total' => 75.5],
    ];
    $result = DotNotation::pluck($data, ['user.name', 'order.id'], 'default');
    expect($result)->toBe([
        'user.name' => 'Helen',
        'order.id' => 555,
    ]);
});

//
// Test all() and tap()
//
it('returns the given array using all()', function () {
    $data = ['x' => 10, 'y' => 20];
    expect(DotNotation::all($data))->toBe($data);
});

it('taps into an array and returns it unchanged', function () {
    $data = ['x' => 10, 'y' => 20];
    $called = false;
    $result = DotNotation::tap($data, function ($arr) use (&$called, $data) {
        $called = true;
        expect($arr)->toBe($data);
    });
    expect($called)
        ->toBeTrue()
        ->and($result)->toBe($data);
});

//
// Test ArrayAccess-like helper methods (offsetExists, offsetGet, offsetSet, offsetUnset)
//
it('checks offsetExists() using DotNotation::offsetExists()', function () {
    $data = ['a' => 1];
    expect(DotNotation::offsetExists($data, 'a'))
        ->toBeTrue()
        ->and(DotNotation::offsetExists($data, 'b'))->toBeFalse();
});

it('retrieves a value using offsetGet()', function () {
    $data = ['a' => 1];
    expect(DotNotation::offsetGet($data, 'a'))->toBe(1);
});

it('sets a value using offsetSet()', function () {
    $data = [];
    DotNotation::offsetSet($data, 'a', 100);
    expect($data)->toBe(['a' => 100]);
});

it('unsets a value using offsetUnset()', function () {
    $data = ['a' => 1, 'b' => 2];
    DotNotation::offsetUnset($data, 'a');
    expect(isset($data['a']))
        ->toBeFalse()
        ->and($data)->toBe(['b' => 2]);
});
