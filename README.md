Here’s a **clean, complete, updated** `README.md` draft for your **ArrayKit** that accurately reflects your full current
structure, highlights *all modules* and *traits*, and demonstrates its scope in a clear, developer-friendly way:

---

# ArrayKit

[![Security & Standards](https://github.com/infocyph/arraykit/actions/workflows/build.yml/badge.svg)](https://github.com/infocyph/arraykit/actions/workflows/build.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/955ce7fb105f4243a018e701f76ebf44)](https://app.codacy.com/gh/infocyph/ArrayKit/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/arraykit?color=green\&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2Farraykit)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/arraykit)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/arraykit/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/arraykit)

**ArrayKit** is a modern **PHP 8.2+** library for elegant, high-performance **array manipulation**, **dot notation
utilities**, **dynamic configuration**, **hookable collections**, and more.
From shallow single arrays to deeply nested data structures — **ArrayKit** provides a fluent, reliable toolkit for
real-world PHP projects.

---

## 📦 Features at a Glance

✅ **Single-Dimensional Helpers**
✅ **Multi-Dimensional Helpers**
✅ **Dot Notation Get/Set/Flatten**
✅ **Dynamic Config with Hooks**
✅ **Collection & Hooked Collection**
✅ **Traits for DTO & Hooking**
✅ **Pipeline for Collection Ops**
✅ **Global Helpers (`functions.php`)**

---

## 📚 Modules

### ➤ Array Helpers

| Helper              | Description                                                                                        |
|---------------------|----------------------------------------------------------------------------------------------------|
| **ArraySingle**     | Helpers for single-dimensional arrays (detect list/assoc, filter, paginate, duplicates, averages). |
| **ArrayMulti**      | Helpers for multi-dimensional arrays (flatten, collapse, depth, recursive sort, filter).           |
| **DotNotation**     | Get/set/remove values using dot keys; flatten & expand nested arrays with dot keys.                |
| **BaseArrayHelper** | Internal shared base for consistent API across helpers.                                            |

---

### ➤ Config System

| Class               | Description                                                                                                         |
|---------------------|---------------------------------------------------------------------------------------------------------------------|
| **Config**          | Immutable dot-access configuration loader.                                                                          |
| **DynamicConfig**   | Extends `Config` with **on-get/on-set hooks** to transform values dynamically (e.g., encrypt/decrypt, auto-format). |
| **BaseConfigTrait** | Shared config logic.                                                                                                |

---

### ➤ Collections

| Class                   | Description                                                                                |
|-------------------------|--------------------------------------------------------------------------------------------|
| **Collection**          | OOP array wrapper implementing `ArrayAccess`, `Iterator`, `Countable`, `JsonSerializable`. |
| **HookedCollection**    | Extends `Collection` with **on-get/on-set hooks** for real-time transformation of values.  |
| **Pipeline**            | Functional-style pipeline for chaining operations on collections.                          |
| **BaseCollectionTrait** | Shared collection behavior.                                                                |

---

### ➤ Traits

| Trait         | Description                                                                                    |
|---------------|------------------------------------------------------------------------------------------------|
| **HookTrait** | Generic hook system for on-get/on-set callbacks. Used by `DynamicConfig` & `HookedCollection`. |
| **DTOTrait**  | Utility trait for DTO-like behavior: populate, extract, cast arrays/objects easily.            |

---

### ➤ Global Helpers

| File              | Description                                                |
|-------------------|------------------------------------------------------------|
| **functions.php** | Global shortcut functions for frequent array/config tasks. |

---

## ✅ Requirements

* **PHP 8.2** or higher

---

## ⚡ Installation

```bash
composer require infocyph/arraykit
```

---

## 🚀 Quick Examples

### 🔹 Single-Dimensional Helpers

```php
use Infocyph\ArrayKit\Array\ArraySingle;

$list = [1, 2, 3, 2];

// Is it a list?
$isList = ArraySingle::isList($list); // true

// Duplicates
$dupes = ArraySingle::duplicates($list); // [2]

// Pagination
$page = ArraySingle::paginate($list, page:1, perPage:2); // [1, 2]
```

---

### 🔹 Multi-Dimensional Helpers

```php
use Infocyph\ArrayKit\Array\ArrayMulti;

$data = [ [1, 2], [3, [4, 5]] ];

// Flatten to one level
$flat = ArrayMulti::flatten($data); // [1, 2, 3, 4, 5]

// Collapse one level
$collapsed = ArrayMulti::collapse($data); // [1, 2, 3, [4, 5]]

// Nesting depth
$depth = ArrayMulti::depth($data); // 3

// Recursive sort
$sorted = ArrayMulti::sortRecursive($data);
```

---

### 🔹 Dot Notation

```php
use Infocyph\ArrayKit\Array\DotNotation;

$user = [
    'profile' => ['name' => 'Alice']
];

// Get value
$name = DotNotation::get($user, 'profile.name'); // Alice

// Set value
DotNotation::set($user, 'profile.email', 'alice@example.com');

// Flatten
$flat = DotNotation::flatten($user);
// [ 'profile.name' => 'Alice', 'profile.email' => 'alice@example.com' ]
```

---

### 🔹 Dynamic Config with Hooks

```php
use Infocyph\ArrayKit\Config\DynamicConfig;

$config = new DynamicConfig();

// Load from file
$config->loadFile(__DIR__.'/config.php');

// Hook: auto-hash password when set
$config->onSet('auth.password', fn($v) => password_hash($v, PASSWORD_BCRYPT));

// Hook: decrypt when getting 'secure.key'
$config->onGet('secure.key', fn($v) => decrypt($v));

// Use it
$config->set('auth.password', 'secret123');
$hashed = $config->get('auth.password');
```

---

### 🔹 Hooked Collection

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

---

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
```

---

## 🤝 Support

Have a bug or feature idea? Please [open an issue](https://github.com/infocyph/arraykit/issues).

---

## 📄 License

Licensed under the **MIT License** — use it freely for personal or commercial projects. See [LICENSE](LICENSE) for
details.

---

Let me know if you’d like me to wrap this into your repo as a `README.md` file or adjust tone/style further!
