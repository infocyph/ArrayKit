# ArrayKit

[![Security & Standards](https://github.com/infocyph/arraykit/actions/workflows/build.yml/badge.svg)](https://github.com/infocyph/arraykit/actions/workflows/build.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/955ce7fb105f4243a018e701f76ebf44)](https://app.codacy.com/gh/infocyph/ArrayKit/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/arraykit?color=green&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2Farraykit)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/arraykit)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/arraykit/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/arraykit)

**ArrayKit** is a modern PHP 8.2+ library offering powerful array manipulation utilities, dynamic configuration management, and hookable “get/set” functionality.  
It’s designed to handle everything from **simple, single-dimensional arrays** to **deeply nested** or multidimensional data structures. It also provides a flexible **config system** and **advanced hooking** for data transformation.

## Features

- **Single-Dimensional Array Helpers** (`ArraySingle`):
    - Check if an array is associative or a list.
    - Filter, paginate, detect duplicates, compute averages, etc.
- **Multi-Dimensional Array Helpers** (`ArrayMulti`):
    - Flatten or collapse arrays, measure nesting depth, sort recursively, filter by custom conditions.
- **Dot Notation Utilities** (`DotNotation`):
    - Flatten/expand arrays using “dot” keys (e.g. `user.profile.name`).
    - Get, set, or remove nested values via a simple dotted key system.
- **Config System** (`Config`, `DynamicConfig`):
    - Centralized loading, retrieving, and setting of configuration values (via dot notation).
    - Optional hooking on get/set for dynamic transformations (`DynamicConfig`).
- **Hooking / On-Get/On-Set** (`HookTrait`):
    - Attach callable hooks to **specific keys** to transform data at runtime.
    - E.g., automatically hash passwords on set, or format strings on get.
- **Collection Classes** (`BucketCollection`, `HookedCollection`):
    - Provides an OOP wrapper around arrays, implementing `ArrayAccess`, `Iterator`, `Countable`, and `JsonSerializable`.
    - The `HookedCollection` extends this with hooks for on-get/on-set transformations.

## Requirements

- **PHP 8.2** or above

## Installation

```bash
composer require infocyph/arraykit
```

## Quick Usage Examples

### 1. Single-Dimensional Array Helpers

```php
use Infocyph\ArrayKit\Array\ArraySingle;

$array = [1, 2, 3, 2];

// Check if the array is a list
$isList = ArraySingle::isList($array); // true

// Get duplicates
$duplicates = ArraySingle::duplicates($array); // [2]

// Paginate
$pageData = ArraySingle::paginate($array, page:1, perPage:2); // [1, 2]
```

### 2. Multi-Dimensional Array Helpers

```php
use Infocyph\ArrayKit\Array\ArrayMulti;

$arr = [
    [1, 2],
    [3, 4, [5, 6]],
];

// Flatten the entire array to a single level
$flattened = ArrayMulti::flatten($arr);
// [1, 2, 3, 4, 5, 6]

// Collapse one level (concatenate sub-arrays)
$collapsed = ArrayMulti::collapse($arr);
// [1, 2, 3, 4, [5, 6]]

// Measure nesting depth
$depth = ArrayMulti::depth($arr);
// 3 (since there's a nested level [5, 6])

// Sort recursively (ascending by default)
$sorted = ArrayMulti::sortRecursive($arr);
// After sorting each nested sub-array
```

### 3. Dot Notation

```php
use Infocyph\ArrayKit\Array\DotNotation;

$data = [
    'user' => [
        'profile' => [
            'name' => 'Alice'
        ]
    ]
];

// Get using dot notation
$name = DotNotation::get($data, 'user.profile.name'); // "Alice"

// Set using dot notation
DotNotation::set($data, 'user.profile.email', 'alice@example.com');

// Flatten
$flat = DotNotation::flatten($data);
// [ 'user.profile.name' => 'Alice', 'user.profile.email' => 'alice@example.com' ]
```

### 4. Dynamic Config with Hooks

```php
use Infocyph\ArrayKit\Config\DynamicConfig;

// Suppose you have a DynamicConfig instance:
$config = new DynamicConfig();

// Load from a PHP file returning an array
$config->loadFile('/path/to/config.php');

// Attach a hook that runs whenever we set "db.password"
$config->onSet('db.password', function ($value) {
    return password_hash($value, PASSWORD_BCRYPT);
});

// Setting db.password automatically hashes it:
$config->set('db.password', 'secret123');

// Getting "db.password" will run any "on get" hooks if set.
// (By default, there's none unless you attach it.)
$hashedPassword = $config->get('db.password');
```

### 5. Hooked Collection

```php
use Infocyph\ArrayKit\Collection\HookedCollection;

$hooked = new HookedCollection(['name' => 'Alice']);

// On-get hook: force uppercase
$hooked->onGet('name', fn($val) => strtoupper($val));

// On-set hook: add a prefix
$hooked->onSet('title', fn($val) => 'Title: '.$val);

// Getting 'name' triggers the on-get hook
echo $hooked['name']; 
// Outputs: "ALICE"

// Setting 'title' triggers the on-set hook
$hooked['title'] = 'Admin'; 
echo $hooked['title']; 
// Outputs: "Title: Admin"
```

---

## Support

Bug/issue/help? Just create an issue!

## License

This library is licensed under the **MIT License**—feel free to use it in personal or commercial projects. See the [LICENSE](LICENSE) file for details.