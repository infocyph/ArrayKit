<?php

declare(strict_types=1);


if (!function_exists('compare')) {
    /**
     * Compare two values using a specified operator.
     *
     * @param mixed       $retrieved The value to compare
     * @param mixed       $value     The reference value
     * @param string|null $operator  Supported operators:
     *                               '!=', '<>', 'ne', '<', 'lt', '>', 'gt',
     *                               '<=', 'lte', '>=', 'gte', '===', '!=='
     *                               or null/default for '=='.
     * @return bool True if comparison holds, false otherwise
     */
    function compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    {
        return match ($operator) {
            '!=', '<>', 'ne' => $retrieved != $value,
            '<', 'lt'        => $retrieved < $value,
            '>', 'gt'        => $retrieved > $value,
            '<=', 'lte'      => $retrieved <= $value,
            '>=', 'gte'      => $retrieved >= $value,
            '==='            => $retrieved === $value,
            '!=='            => $retrieved !== $value,
            default          => $retrieved == $value,
        };
    }
}

if (!function_exists('isCallable')) {
    /**
     * Determine if the given value is callable (but not a string).
     *
     * @param mixed $value
     * @return bool
     */
    function isCallable(mixed $value): bool
    {
        return !is_string($value) && is_callable($value);
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
     * @param array $array The array to retrieve items from.
     * @param int|string|array|null $key The key(s) to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value(s).
     */
    function array_get(array $array, int|string|array $key = null, mixed $default = null): mixed
    {
        return Infocyph\ArrayKit\Array\DotNotation::get($array, $key, $default);
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
     * @param array $array The array to set items in.
     * @param string|array|null $key
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite existing values. If false, existing values are preserved.
     * @return bool True on success
     */
    function array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool
    {
        return Infocyph\ArrayKit\Array\DotNotation::set($array, $key, $value, $overwrite);
    }
}
