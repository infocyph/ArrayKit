<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use ArrayAccess;
use InvalidArgumentException;

class BaseArrayHelper
{
    /**
     * Check if an array is multi-dimensional.
     *
     * This method is a shortcut for checking if an array is multi-dimensional.
     * It checks if the array is an array and if the count of the array is not
     * equal to the count of the array with the COUNT_RECURSIVE flag.
     *
     * @param mixed $array The array to check.
     * @return bool True if the array is multi-dimensional, false otherwise.
     */
    public static function isMultiDimensional(mixed $array): bool
    {
        return is_array($array)
            && count($array) !== count($array, COUNT_RECURSIVE);
    }


    /**
     * Wrap a value in an array if it's not already an array; otherwise return the array as is.
     *
     * If the value is empty, an empty array is returned.
     *
     * @param mixed $value The value to wrap.
     * @return array The wrapped value.
     */
    public static function wrap(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }


    /**
     * Unwrap a value from an array if it contains exactly one element.
     *
     * This method checks if the given value is an array. If it is not,
     * the value is returned as is. If the value is an array with exactly
     * one element, that element is returned. Otherwise, the array itself
     * is returned.
     *
     * @param mixed $value The value to potentially unwrap.
     * @return mixed The unwrapped value or the original array.
     */
    public static function unWrap(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        return (count($value) === 1) ? reset($value) : $value;
    }


    /**
     * Determine if at least one element in the array passes the given truth test.
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to use for searching.
     * @return bool Whether at least one element passed the truth test.
     */
    public static function haveAny(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === true) {
                return true;
            }
        }
        return false;
    }


    /**
     * Determine if all elements in the array pass the given truth test.
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to use for searching.
     * @return bool Whether all elements passed the truth test.
     */
    public static function isAll(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === false) {
                return false;
            }
        }
        return true;
    }


    /**
     * Search the array for a given value and return its key if found.
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to use for searching.
     *
     * @return int|string|null The key of the value if found, or null if not found.
     */
    public static function findKey(array $array, callable $callback): int|string|null
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === true) {
                return $key;
            }
        }
        return null;
    }


    /**
     * Check if the given value is an array or an instance of ArrayAccess.
     *
     * @param mixed $value The value to check.
     * @return bool True if the value is accessible, false otherwise.
     */
    public static function accessible(mixed $value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }


    /**
     * Check if all the given keys exist in the array.
     *
     * @param array $array The array to search.
     * @param int|string|array $keys The key(s) to check for existence.
     *
     * @return bool True if all the given keys exist in the array, false otherwise.
     */
    public static function has(array $array, int|string|array $keys): bool
    {
        $keys = (array) $keys;
        if (empty($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check if at least one of the given keys exists in the array.
     *
     * This function accepts a single key or an array of keys and
     * determines whether at least one of them exists within the
     * provided array. If any key is found, the function returns
     * true. Otherwise, it returns false.
     *
     * @param array $array The array to search.
     * @param int|string|array $keys The key(s) to check for existence.
     * @return bool True if at least one key exists in the array, false otherwise.
     */
    public static function hasAny(array $array, int|string|array $keys): bool
    {
        $keys = (array) $keys;
        if (empty($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Generate an array containing a sequence of numbers.
     *
     * This function creates an array of numbers starting from $start up to $end,
     * incrementing by $step. If $step is zero, an empty array is returned.
     *
     * @param int $start The starting number of the sequence.
     * @param int $end The ending number of the sequence.
     * @param int $step The increment between each number in the sequence. Defaults to 1.
     * @return array An array containing the sequence of numbers.
     */
    public static function range(int $start, int $end, int $step = 1): array
    {
        if ($step === 0) {
            // We could throw an exception, or return empty:
            return [];
        }
        return range($start, $end, $step);
    }


    /**
     * Create an array of the specified length and fill it with the results of the
     * given callback function. If the callback is not provided, the array will be
     * filled with the numbers 1 through $number.
     *
     * Example:
     *      ArrayKit::times(3, function ($i) {
     *          return "Row #{$i}";
     *      });
     *      // Output: ["Row #1", "Row #2", "Row #3"]
     *
     * @param int $number The length of the array.
     * @param callable|null $callback The callback function to use.
     * @return array The filled array.
     */
    public static function times(int $number, ?callable $callback = null): array
    {
        $results = [];
        if ($number < 1) {
            return $results;
        }

        for ($i = 1; $i <= $number; $i++) {
            $results[] = $callback ? $callback($i) : $i;
        }

        return $results;
    }


    /**
     * Check if at least one element in the array passes a given truth test.
     *
     * This function is an alias for haveAny, which is a more descriptive name.
     * It is provided for syntactic sugar, as it is very common to want to
     * check if at least one item in an array matches a given criteria.
     *
     * @param array $array The array to check.
     * @param callable $callback The callback to apply to each element.
     * @return bool True if at least one element passes the test, false otherwise.
     */
    public static function any(array $array, callable $callback): bool
    {
        return static::haveAny($array, $callback);
    }


    /**
     * Check if all elements in the array pass the given truth test.
     *
     * This function applies a callback to each element of the array.
     * If the callback returns true for all elements, the function returns true.
     * Otherwise, it returns false.
     *
     * @param array $array The array to be evaluated.
     * @param callable $callback The callback function to apply to each element.
     * @return bool True if all elements pass the truth test, false otherwise.
     */
    public static function all(array $array, callable $callback): bool
    {
        return static::isAll($array, $callback);
    }

    /* ------------------------------------------------------------------------
     |                     Additional "Sugar" Methods (Point 1)
       ---------------------------------------------------------------------- */


    /**
     * Pass the array to the given callback and return it.
     *
     * Useful for tapping into a fluent method chain for debugging.
     *
     * @param array $array The array to be tapped.
     * @param callable $callback The callback to apply to the array.
     * @return array The original array.
     */
    public static function tap(array $array, callable $callback): array
    {
        $callback($array);
        return $array;
    }


    /**
     * Remove one or multiple array items from an array.
     *
     * This function takes an array and a key or array of keys as parameters.
     * It then iterates over the given keys, and unsets the corresponding
     * items from the array.
     *
     * @param array $array The array from which to remove items.
     * @param int|string|array $keys The key or array of keys to be removed.
     */
    public static function forget(array &$array, int|string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            unset($array[$key]);
        }
    }


    /**
     * Retrieve one or multiple random items from an array.
     *
     * By default, this function returns a single item from the array.
     * If you pass a number as the second argument, it will return that
     * number of items. If you set the third argument to `true`, the
     * keys from the original array are preserved in the returned array.
     *
     * @param array $array The array from which to retrieve random items.
     * @param int|null $number The number of items to retrieve. If null, a single item is returned.
     * @param bool $preserveKeys Whether to preserve the keys from the original array.
     *
     * @return mixed The retrieved item(s) from the array.
     *
     * @throws InvalidArgumentException If the user requested more items than the array contains.
     */
    public static function random(array $array, int $number = null, bool $preserveKeys = false): mixed
    {
        $count = count($array);

        // If array is empty or user requested <=0 items, handle edge-case:
        if ($count === 0 || ($number !== null && $number <= 0)) {
            return ($number === null) ? null : [];
        }

        // If we only want one item:
        if ($number === null) {
            $randKey = array_rand($array);
            return $array[$randKey];
        }

        if ($number > $count) {
            throw new InvalidArgumentException(
                "You requested $number items, but array only has $count."
            );
        }

        // For multiple items:
        $keys = array_rand($array, $number);
        if (!is_array($keys)) {
            // array_rand returns a single value when $number=1
            $keys = [$keys];
        }

        $results = [];
        foreach ($keys as $key) {
            if ($preserveKeys) {
                $results[$key] = $array[$key];
            } else {
                $results[] = $array[$key];
            }
        }

        return $results;
    }


    /**
     * Filter an array by rejecting elements based on a callback function or value.
     *
     * This function takes an array and a callback or value as parameters.
     * If the callback is callable, it applies the callback to each element of the array.
     * Elements for which the callback returns false are kept.
     * If the callback is a value, elements equal to this value are rejected.
     * The function returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to be filtered.
     * @param mixed $callback The callback function or value for filtering.
     * @return array The array with elements rejected based on the callback or value.
     */
    public static function doReject(array $array, mixed $callback): array
    {
        if (is_callable($callback)) {
            return array_filter(
                $array,
                fn ($val, $key) => !$callback($val, $key),
                ARRAY_FILTER_USE_BOTH
            );
        }
        return array_filter($array, fn ($val) => $val != $callback);
    }
}
