<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\Pipeline;

use function Infocyph\ArrayKit\array_get as arraykit_array_get;
use function Infocyph\ArrayKit\array_set as arraykit_array_set;
use function Infocyph\ArrayKit\chain as arraykit_chain;
use function Infocyph\ArrayKit\collect as arraykit_collect;
use function Infocyph\ArrayKit\compare as arraykit_compare;

// Optional global helpers:
// this file is intentionally not autoloaded by default to avoid global symbol pressure.

if (!function_exists('compare')) {
    /**
     * Compare two values using a specified operator.
     *
     * @param mixed $retrieved The value to compare
     * @param mixed $value The reference value
     * @param string|null $operator Supported operators:
     *                              '!=', '<>', 'ne', '<', 'lt', '>', 'gt',
     *                              '<=', 'lte', '>=', 'gte', '===', '!=='
     *                              or null/default for '=='.
     * @return bool True if comparison holds, false otherwise
     */
    function compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    {
        return arraykit_compare($retrieved, $value, $operator);
    }
}

if (!function_exists('array_get')) {
    /**
     * Retrieve one or multiple items from the array using dot notation.
     *
     * The following cases are handled:
     *  - If no key is provided, the entire array is returned.
     *  - If an array of keys is provided, all values are returned in an array.
     *  - If a single key is provided, the value is returned directly.
     *
     * @param array<array-key, mixed> $array The array to retrieve items from.
     * @param int|string|array<int, string|int>|null $key The key(s) to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value(s).
     */
    function array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed
    {
        return arraykit_array_get($array, $key, $default);
    }
}

if (!function_exists('array_set')) {
    /**
     * Set one or multiple items in the array using dot notation.
     *
     * If no key is provided, the entire array is replaced with $value.
     * If an array of key-value pairs is provided, each value is set.
     * If a single key is provided, the value is set directly.
     *
     * @param array<array-key, mixed> $array The array to set items in.
     * @param string|array<array-key, mixed>|null $key
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite existing values. If false, existing values are preserved.
     * @return bool True on success
     */
    function array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool
    {
        return arraykit_array_set($array, $key, $value, $overwrite);
    }
}
if (!function_exists('collect')) {
    /**
     * Wrap the given value in an {@see Collection}.
     *
     * @param mixed $data Anything “array-able”: array, Traversable, scalar, etc.
     */
    function collect(mixed $data = []): Collection
    {
        return arraykit_collect($data);
    }
}
if (!function_exists('chain')) {
    /**
     * Start a chainable pipeline on any “array-able” value.
     *
     * @param mixed $data Array, Traversable, scalar, etc.
     */
    function chain(mixed $data): Pipeline
    {
        return arraykit_chain($data);
    }
}
