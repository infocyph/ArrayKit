<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\DotNotation;

it('flattens a multi-level array into dot notation', function () {
    $source = ['user' => ['name' => 'Alice', 'roles' => ['admin', 'editor']]];
    $flat = DotNotation::flatten($source);
    expect($flat)->toBe([
        'user.name' => 'Alice',
        'user.roles.0' => 'admin',
        'user.roles.1' => 'editor',
    ]);
});

it('expands a dot-notation array back to nested structure', function () {
    $dotArray = [
        'app.name' => 'MyApp',
        'app.env' => 'local',
    ];
    $expanded = DotNotation::expand($dotArray);
    expect($expanded)->toBe([
        'app' => [
            'name' => 'MyApp',
            'env' => 'local',
        ],
    ]);
});

it('round trips literal path-control characters through flatten and expand', function () {
    $source = [
        'service.name' => [
            'path\\part' => [
                '*' => ['{first}' => ['{last}' => 'value']],
            ],
        ],
    ];

    $flat = DotNotation::flatten($source);

    expect(DotNotation::expand($flat))->toBe($source)
        ->and(DotNotation::paths($source))->toBe(array_keys($flat));
});

it('writes only to supported object properties', function () {
    $object = new class
    {
        public array $profile = [];

        public readonly string $identifier;

        private string $secret = 'hidden';

        public function __construct()
        {
            $this->identifier = 'fixed';
        }
    };
    $target = ['object' => $object, 'dynamic' => new stdClass];

    DotNotation::set($target, 'object.profile.name', 'Ada');
    DotNotation::set($target, 'dynamic.created', true);

    expect($object->profile)->toBe(['name' => 'Ada'])
        ->and($target['dynamic']->created)->toBeTrue()
        ->and(fn () => DotNotation::set($target, 'object.secret', 'visible'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DotNotation::set($target, 'object.missing', 'value'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DotNotation::set($target, 'object.identifier', 'changed'))
        ->toThrow(InvalidArgumentException::class);
});

it('renames overlapping parent and child paths without losing the captured value', function () {
    $parentToChild = ['a' => ['b' => 1, 'c' => 2]];
    $childToParent = ['a' => ['b' => 1, 'c' => 2]];

    expect(DotNotation::rename($parentToChild, 'a', 'a.moved'))->toBeTrue()
        ->and($parentToChild)->toBe(['a' => ['moved' => ['b' => 1, 'c' => 2]]])
        ->and(DotNotation::move($childToParent, 'a.b', 'a'))->toBeTrue()
        ->and($childToParent)->toBe(['a' => 1]);
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

it('uses dotted strings as paths and escaped dots as literal keys consistently', function () {
    $array = [
        'foo.bar' => 1,
        'foo' => ['bar' => 2],
    ];

    expect(DotNotation::get($array, 'foo.bar'))->toBe(2)
        ->and(DotNotation::get($array, 'foo\\.bar'))->toBe(1)
        ->and(DotNotation::has($array, 'foo.bar'))->toBeTrue()
        ->and(DotNotation::has($array, 'foo\\.bar'))->toBeTrue();

    DotNotation::set($array, 'foo.bar', 3);
    DotNotation::set($array, 'foo\\.bar', 4);

    expect($array)->toBe([
        'foo.bar' => 4,
        'foo' => ['bar' => 3],
    ]);

    DotNotation::forget($array, 'foo.bar');
    DotNotation::forget($array, 'foo\\.bar');

    expect($array)->toBe(['foo' => []]);
});

it('does not remove or replace scalar parents when a nested path cannot be filled or forgotten', function () {
    $array = ['a' => 'scalar'];

    DotNotation::forget($array, 'a.b');
    DotNotation::fill($array, 'a.b', 1);

    expect($array)->toBe(['a' => 'scalar']);
});

it('forgets terminal wildcards at root and nested paths', function () {
    $nested = [
        'users' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
        'meta' => true,
    ];
    DotNotation::forget($nested, 'users.*');

    expect($nested)->toBe(['users' => [], 'meta' => true]);

    DotNotation::forget($nested, '*');
    expect($nested)->toBe([]);
});

it('renames the same path location that it reads', function () {
    $array = [
        'foo.bar' => 'literal',
        'foo' => ['bar' => 'nested'],
    ];

    expect(DotNotation::rename($array, 'foo.bar', 'foo.baz'))->toBeTrue()
        ->and($array)->toBe([
            'foo.bar' => 'literal',
            'foo' => ['baz' => 'nested'],
        ]);
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

it('accepts a zero-like root key', function () {
    expect(DotNotation::has(['0' => 'zero'], '0'))->toBeTrue()
        ->and(DotNotation::hasAny(['0' => 'zero'], ['missing', '0']))->toBeTrue();
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

it('returns default for a missing integer key', function () {
    $data = [0 => 'zero'];

    expect(DotNotation::get($data, 1, 'fallback'))->toBe('fallback');
});

it('returns null when key exists with null value', function () {
    $data = ['user' => ['middle_name' => null]];

    expect(DotNotation::get($data, 'user.middle_name', 'fallback'))->toBeNull();
});

it('returns null for existing object properties with null values', function () {
    $data = ['user' => (object) ['middle_name' => null]];

    expect(DotNotation::get($data, 'user.middle_name', 'fallback'))->toBeNull();
});

it('does not treat existing value equal to default as missing', function () {
    $data = ['app' => ['env' => 'local']];

    expect(DotNotation::get($data, 'app.env', 'local'))->toBe('local');
});

it('evaluates callable default once when key path is missing', function () {
    $data = ['app' => ['name' => 'ArrayKit']];
    $calls = 0;

    $value = DotNotation::get($data, 'app.env.current', function () use (&$calls) {
        $calls++;

        return 'fallback';
    });

    expect($value)->toBe('fallback')
        ->and($calls)->toBe(1);
});

it('returns string defaults as-is when key is not found', function () {
    $data = [];
    expect(DotNotation::get($data, 'missing.key', 'file'))->toBe('file');
});

it('supports escaped dot paths for literal dot keys', function () {
    $data = [
        'service.name' => 'ArrayKit',
        'service' => ['name' => 'Nested'],
    ];

    expect(DotNotation::get($data, 'service\\.name'))->toBe('ArrayKit')
        ->and(DotNotation::has($data, 'service\\.name'))->toBeTrue();
});

it('supports escaped backslashes without corrupting compiled paths', function () {
    $data = [
        'root\\name' => ['value' => 'found'],
    ];

    expect(DotNotation::get($data, 'root\\\\name.value'))->toBe('found')
        ->and(DotNotation::get($data, 'root\\\\name.value'))->toBe('found');
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
        'user' => ['name' => 'Diana'],
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
        'user.email' => 'eve@example.com',
    ]);
    expect($data)->toBe([
        'user' => [
            'name' => 'Eve',
            'email' => 'eve@example.com',
        ],
    ]);
});

it('does not overwrite existing keys when fill() is used', function () {
    $data = ['user' => ['name' => 'Frank']];
    DotNotation::fill($data, 'user.name', 'George');
    expect($data['user']['name'])->toBe('Frank');
});

it('does not overwrite existing null object properties when fill() is used', function () {
    $data = ['user' => (object) ['middle_name' => null]];

    DotNotation::fill($data, 'user.middle_name', 'George');

    expect($data['user']->middle_name)->toBeNull();
});

it('fills missing keys when fill() is used', function () {
    $data = ['user' => []];
    DotNotation::fill($data, 'user.email', 'frank@example.com');
    expect($data['user']['email'])->toBe('frank@example.com');
});

it('supports escaped dot paths for set and forget', function () {
    $data = [];
    DotNotation::set($data, 'service\\.name', 'ArrayKit');

    expect($data)->toBe(['service.name' => 'ArrayKit']);

    DotNotation::forget($data, 'service\\.name');
    expect($data)->toBe([]);
});

it('supports wildcard set and wildcard forget', function () {
    $data = [
        'users' => [
            ['name' => 'Alice', 'active' => false, 'secret' => 'a'],
            ['name' => 'Bob', 'active' => false, 'secret' => 'b'],
        ],
    ];

    DotNotation::set($data, 'users.*.active', true);
    DotNotation::forget($data, 'users.*.secret');

    expect($data)->toBe([
        'users' => [
            ['name' => 'Alice', 'active' => true],
            ['name' => 'Bob', 'active' => true],
        ],
    ]);
});

it('supports multiple wildcards and missing wildcard branches', function () {
    $data = [
        'companies' => [
            ['teams' => [['name' => 'A'], ['name' => 'B']]],
            ['teams' => [['name' => 'C'], []]],
        ],
    ];

    expect(DotNotation::get($data, 'companies.*.teams.*.name', 'missing'))
        ->toBe(['A', 'B', 'C', 'missing']);

    DotNotation::set($data, 'companies.*.teams.*.active', true);
    DotNotation::forget($data, 'companies.*.teams.*.active');

    expect(DotNotation::get($data, 'companies.*.teams.*.active', 'missing'))
        ->toBe(['missing', 'missing', 'missing', 'missing']);
});

it('supports hasWildcard, paths and matches helpers', function () {
    $data = [
        'users' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
        'meta' => ['count' => 2],
    ];

    expect(DotNotation::hasWildcard('users.*.name'))->toBeTrue()
        ->and(DotNotation::hasWildcard('users.0.name'))->toBeFalse()
        ->and(DotNotation::paths($data))->toContain('users.0.name', 'users.1.name', 'meta.count')
        ->and(DotNotation::matches($data, 'users.*.name'))->toBeTrue()
        ->and(DotNotation::matches($data, 'users.*.email'))->toBeFalse();
});

it('supports rename and move helpers', function () {
    $data = ['user' => ['name' => 'Alice', 'role' => 'admin']];

    expect(DotNotation::rename($data, 'user.role', 'user.type'))->toBeTrue()
        ->and($data)->toBe(['user' => ['name' => 'Alice', 'type' => 'admin']])
        ->and(DotNotation::move($data, 'user.type', 'profile.kind'))->toBeTrue()
        ->and($data)->toBe(['user' => ['name' => 'Alice'], 'profile' => ['kind' => 'admin']]);
});

it('treats same-path rename and move operations as no-ops', function () {
    $data = ['user' => ['role' => 'admin']];

    expect(DotNotation::rename($data, 'user.role', 'user.role'))->toBeTrue()
        ->and(DotNotation::move($data, 'user.role', 'user.role'))->toBeTrue()
        ->and($data)->toBe(['user' => ['role' => 'admin']]);
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

it('supports safe get traversal limits with graceful fallback', function () {
    $data = [
        'users' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
    ];

    expect(DotNotation::getSafe($data, 'users.*.name', 'missing', maxDepth: 1))->toBe(['missing', 'missing']);
});

it('can throw on safe get traversal limit overflow', function () {
    $data = [
        'users' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
    ];

    expect(fn () => DotNotation::getSafe($data, 'users.*.name', 'missing', maxDepth: 1, throwOnTooDeep: true))
        ->toThrow(RuntimeException::class);
});

it('applies safe traversal limits to ordinary path segments and nodes', function () {
    $data = ['one' => ['two' => ['three' => 'value']]];

    expect(DotNotation::getSafe($data, 'one.two.three', 'missing', maxDepth: 2))->toBe('missing')
        ->and(DotNotation::getSafe($data, 'one.two.three', 'missing', maxNodes: 2))->toBe('missing')
        ->and(fn () => DotNotation::getSafe(
            $data,
            'one.two.three',
            'missing',
            maxDepth: 2,
            throwOnTooDeep: true,
        ))->toThrow(RuntimeException::class);
});
