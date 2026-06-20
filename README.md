# ArrayKit

[![Security & Standards](https://github.com/infocyph/arraykit/actions/workflows/security-standards.yml/badge.svg)](https://github.com/infocyph/ArrayKit/actions/workflows/security-standards.yml)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/ArrayKit?color=green\&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2FArrayKit)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/ArrayKit)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/ArrayKit/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/ArrayKit)
[![Documentation](https://img.shields.io/badge/Documentation-ArrayKit-blue?logo=readthedocs&logoColor=white)](https://docs.infocyph.com/projects/arraykit/)

**ArrayKit** is a modern **PHP 8.4+** library for elegant, high-performance **array manipulation**, **dot notation
utilities**, **dynamic configuration**, **hookable collections**, and more.
From shallow single arrays to deeply nested data structures — **ArrayKit** provides a fluent, reliable toolkit for
real-world PHP projects.

## Features at a Glance

- **Single-Dimensional Helpers**
- **Multi-Dimensional Helpers**
- **Dot Notation Get/Set/Flatten**
- **Dynamic Config with Hooks**
- **Collection & Hooked Collection**
- **Unified Facade (`ArrayKit`)**
- **Traits for DTO & Hooking**
- **Pipeline for Collection Ops**
- **LazyCollection for Generator-Based Flows**
- **ArrayShape Validation Helper**
- **Compiled Config + Lazy Namespace Cache**
- **Namespaced Helpers + Optional Globals**

## Modules

### Array Helpers

| Helper              | Description                                                                                        |
|---------------------|----------------------------------------------------------------------------------------------------|
| **ArraySingle**     | Helpers for single-dimensional arrays (set ops, mapWithKeys, countBy, min/max, paginate, duplicates, averages). |
| **ArrayMulti**      | Helpers for multi-dimensional arrays (flatten, collapse, depth, keyBy/indexBy, firstWhere, recursive sort/filter). |
| **DotNotation**     | Get/set/remove values using dot keys; wildcard support; escaped literal-dot paths; flatten & expand. |
| **ArrayShape**      | Lightweight array-shape assertions for row validation (`require`).                                     |
| **BaseArrayHelper** | Internal shared base for consistent API across helpers.                                            |
| **ArraySharedOps**  | Internal shared operations used by `ArraySingle` and `ArrayMulti` (`each/every/partition/skip*`). |

### Config System

| Class               | Description                                                                                                         |
|---------------------|---------------------------------------------------------------------------------------------------------------------|
| **Config**          | Dot-access configuration loader with explicit hook-aware variants (`getWithHooks`, `setWithHooks`, `fillWithHooks`) plus compiled cache export/load and read memoization. |
| **LazyFileConfig**  | First-segment lazy loader (`db.host` loads `db.php` on demand) with namespace cache files for structural reads and a flat leaf-index cache for exact scalar lookups.      |
| **BaseConfigTrait** | Shared config logic.                                                                                                |


### Collections

| Class                   | Description                                                                                |
|-------------------------|--------------------------------------------------------------------------------------------|
| **Collection**          | OOP array wrapper implementing `ArrayAccess`, `IteratorAggregate`, `Countable`, `JsonSerializable`. |
| **HookedCollection**    | Extends `Collection` with **on-get/on-set hooks** for real-time transformation of values.  |
| **Pipeline**            | Functional-style pipeline for chaining operations on collections.                          |
| **LazyCollection**      | Generator-backed lazy operations (`mapLazy`, `filterLazy`, `chunkLazy`, `take`, `takeUntil`). |
| **BaseCollectionTrait** | Shared collection behavior.                                                                |


### Traits

| Trait         | Description                                                                                    |
|---------------|------------------------------------------------------------------------------------------------|
| **HookTrait** | Generic hook system for on-get/on-set callbacks. Used by `Config`, `LazyFileConfig`, and `HookedCollection`. |
| **DTOTrait**  | Utility trait for DTO-like behavior: populate, extract, cast arrays/objects easily.            |


### Helper Functions

| Helper Surface | Description |
|----------------|-------------|
| **`Infocyph\ArrayKit\*`** | Namespaced helper functions (`compare`, `array_get`, `array_set`, `collect`, `chain`) autoloaded by default. |
| **`functions.php`** | Optional global helper variants (manual include when needed). |

### ➤ Facade

| Class             | Description                                                                                   |
|-------------------|-----------------------------------------------------------------------------------------------|
| **ArrayKit**      | Single entry point for arrays, dot tools, config, and collections (`single()`, `multi()`, etc.). |

## Requirements

* **PHP 8.4** or higher


## Installation

```bash
composer require infocyph/arraykit
```

```php
<?php
// Namespaced helpers are autoloaded by default.
use function Infocyph\ArrayKit\array_get;
use function Infocyph\ArrayKit\array_set;
use function Infocyph\ArrayKit\collect;
use function Infocyph\ArrayKit\chain;

// Optional: enable global helpers in projects that explicitly want them.
require_once __DIR__ . '/vendor/infocyph/arraykit/src/functions.php';
```

## Quick Examples

### One Facade Entry Point

```php
use Infocyph\ArrayKit\ArrayKit;

$isList = ArrayKit::single()->isList([1, 2, 3]);            // true
$flat = ArrayKit::multi()->flatten([[1], [2, [3]]]);        // [1, 2, 3]
$name = ArrayKit::dot()->get(['user' => ['n' => 'A']], 'user.n'); // A

$config = ArrayKit::config(['app' => ['env' => 'local']]);
$env = $config->get('app.env');                            // local
```

### Single-Dimensional Helpers

```php
use Infocyph\ArrayKit\Array\ArraySingle;

$list = [1, 2, 3, 2];

// Is it a list?
$isList = ArraySingle::isList($list); // true

// Duplicates
$dupes = ArraySingle::duplicates($list); // [2]

// Contains checks
$hasAll = ArraySingle::containsAll($list, [1, 2]); // true
$hasAny = ArraySingle::containsAny($list, [99, 2]); // true

// Pagination
$page = ArraySingle::paginate($list, page:1, perPage:2); // [1, 2]
```

### Multi-Dimensional Helpers

```php
use Infocyph\ArrayKit\Array\ArrayMulti;

$data = [ [1, 2], [3, [4, 5]] ];

// Flatten to one level
$flat = ArrayMulti::flatten($data); // [1, 2, 3, 4, 5]
$flatZero = ArrayMulti::flatten($data, 0); // [[1, 2], [3, [4, 5]]]
$flatOne = ArrayMulti::flatten($data, 1); // [1, 2, 3, [4, 5]]

// Multi-column and query helpers
$sortedMany = ArrayMulti::sortByMany($rows, [
    ['status', 'asc'],
    ['created_at', 'desc'],
]);
$active = ArrayMulti::whereStartsWith($rows, 'status', 'act', false);
$match = ArrayMulti::whereLike($rows, 'email', '%@example.com');
$first = ArrayMulti::firstWhereIn($rows, 'role', ['admin', 'editor']);
$uniqueUsers = ArrayMulti::uniqueBy($rows, 'email');
$dupeUsers = ArrayMulti::duplicatesBy($rows, fn ($row) => strtolower((string) ($row['email'] ?? '')));

// Collapse one level
$collapsed = ArrayMulti::collapse($data); // [1, 2, 3, [4, 5]]

// Nesting depth
$depth = ArrayMulti::depth($data); // 3

// Recursive sort
$sorted = ArrayMulti::sortRecursive($data);
```

### Dot Notation

```php
use Infocyph\ArrayKit\Array\DotNotation;

$user = [
    'profile' => ['name' => 'Alice']
];

// Get value
$name = DotNotation::get($user, 'profile.name'); // Alice
$literal = DotNotation::get(['profile.name' => 'flat'], 'profile\\.name'); // flat

// Set value
DotNotation::set($user, 'profile.email', 'alice@example.com');

// Flatten
$flat = DotNotation::flatten($user);

// wildcard set
DotNotation::set($user, 'users.*.active', true);
// [ 'profile.name' => 'Alice', 'profile.email' => 'alice@example.com' ]
```

### Config Hooks (Explicit)

```php
use Infocyph\ArrayKit\Config\Config;

$config = new Config();

// Load from file
$config->loadFile(__DIR__.'/config.php');

// Hook: auto-hash password when set
$config->onSet('auth.password', fn($v) => password_hash($v, PASSWORD_BCRYPT));

// Hook: decrypt when getting 'secure.key'
$config->onGet('secure.key', fn($v) => decrypt($v));

// Use it
$config->setWithHooks('auth.password', 'secret123');
$hashed = $config->getWithHooks('auth.password');

// Typed getters + state helpers
$port = $config->getInt('db.port', 3306);
$config->snapshot('before-runtime');
$config->merge(['app' => ['env' => 'production']]);
$changed = $config->changed('before-runtime');
$config->restore('before-runtime');

// Compiled cache export / load
$config->exportCache(__DIR__ . '/bootstrap/cache/config.php');
$cached = new Config();
$cached->loadCache(__DIR__ . '/bootstrap/cache/config.php');
```

### Hooked Collection

```php
use Infocyph\ArrayKit\Collection\HookedCollection;

$collection = new HookedCollection(['name' => 'alice']);

// Hook on-get: uppercase
$collection->onGet('name', fn($v) => strtoupper($v));

// Hook on-set: prefix
$collection->onSet('role', fn($v) => "Role: $v");

echo $collection['name']; // ALICE

$collection['role'] = 'admin';
echo $collection['role']; // Role: admin
```

### 🔹 DTO Trait Example

```php
use Infocyph\ArrayKit\traits\DTOTrait;

class UserDTO {
    use DTOTrait;

    public string $name;
    public string $email;
}

$user = new UserDTO();
$user->fromArray(['name' => 'Alice', 'email' => 'alice@example.com']);
$array = $user->toArray();

// Advanced hydration / export
$user->hydrate(['name' => 'Alice'], mapping: ['name' => 'full_name']);
$deep = $user->toArrayDeep();
```

### Lazy + Shape + Cache

```php
use Infocyph\ArrayKit\Array\ArrayShape;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Config\LazyFileConfig;

$lazy = ArrayKit::lazyCollection(range(1, 10))
    ->filterLazy(fn ($v) => $v % 2 === 0)
    ->take(3)
    ->all(); // [2, 4, 6]

$row = ArrayShape::require(
    ['id' => 1, 'email' => 'a@example.com', 'roles' => ['admin']],
    ['id' => 'int', 'email' => 'string', 'roles' => 'list<string>'],
);

$config = new LazyFileConfig(__DIR__ . '/config', namespaceCacheDirectory: __DIR__ . '/bootstrap/cache/config');
$config->warmNamespaceCache(['db', 'cache']);

// Exact scalar leaf reads can hit bootstrap/cache/config/__flat.php first.
$host = $config->get('db.host');
```

## Behavior Notes

- `ArrayMulti::flatten($array, 0)` keeps top-level values unchanged; `1` flattens one level; `INF` fully flattens.
- `ArraySingle::avg()`, `sum()`, `isPositive()`, and `isNegative()` only consider numeric values (non-numeric values are ignored).
- `ArraySingle::paginate()` requires `page >= 1` and `perPage >= 1` (throws `InvalidArgumentException` otherwise).
- Callback-based row helpers (`ArrayMulti::sortBy()`, `sum()`, `maxBy()`, `minBy()`) support `($row, $key)`.
- `DotNotation` treats existing `null` keys/properties as present (does not fall back to defaults).
- `DotNotation::hasWildcard()`, `paths()`, `matches()`, `rename()`, and `move()` are available for wildcard/path operations.
- For untrusted/deep payloads, use bounded traversal variants: `DotNotation::getSafe()`, `ArrayMulti::depthGuarded()`, `flattenGuarded()`, and `sortRecursiveGuarded()`.
- `LazyFileConfig` namespace cache writes one cache file per namespace plus a shared `__flat.php` file containing only final scalar/null leaf values for exact-key fast paths.

## Security

Protected by [PHPForge](https://github.com/infocyph/PHPForge) — an automated quality and security gate for PHP projects.

---

<div align="center">
  <sub><strong>Made with ❤️ for the PHP community</strong></sub><br />
  <sub><a href="LICENSE">MIT Licensed</a></sub><br />
  <a href="https://docs.infocyph.com/projects/ArrayKit">Documentation</a> •
  <a href="SECURITY.md">Security</a> •
  <a href="CODE_OF_CONDUCT.md">Code of Conduct</a> •
  <a href="CONTRIBUTING.md">Contributing</a> •
  <a href="https://github.com/infocyph/ArrayKit/issues">Report Bug</a> •
  <a href="https://github.com/infocyph/ArrayKit/issues">Request Feature</a>
</div>
