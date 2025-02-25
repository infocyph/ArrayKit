<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

class ArraySingle
{
    /**
     * Check if a given key exists in a single-dimensional array.
     *
     * This method determines whether the specified key is present
     * in the array, either by checking if it is set or if it exists
     * as a key in the array.
     *
     * @param array $array The array to search in.
     * @param int|string $key The key to check for existence.
     * @return bool True if the key exists in the array, false otherwise.
     */
    public static function exists(array $array, int|string $key): bool
    {
        return isset($array[$key]) || array_key_exists($key, $array);
    }


    /**
     * Select only certain keys from a single-dimensional array.
     *
     * This method is the single-dimensional equivalent of ArrayMulti::only.
     *
     * @param array $array The array to select from.
     * @param array|string $keys The keys to select.
     * @return array A new array with the selected keys.
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }


    /**
     * Split an array into separate arrays of keys and values.
     *
     * Useful for destructuring an array into separate key and value arrays.
     *
     * @param array $array The array to split.
     * @return array A new array containing two child arrays: 'keys' and 'values'.
     * @example
     *      $data = ['a' => 1, 'b' => 2, 'c' => 3];
     *      $keysAndValues = ArraySingle::separate($data);
     *      // $keysAndValues === ['keys' => ['a', 'b', 'c'], 'values' => [1, 2, 3]];
     */
    public static function separate(array $array): array
    {
        return [
            'keys'   => array_keys($array),
            'values' => array_values($array),
        ];
    }


    /**
     * Determine if an array is a strict list (i.e., has no string keys).
     *
     * A strict list is an array where all keys are integers and are in sequence
     * from 0 to n-1, where n is the length of the array.
     *
     * @param array $array The array to test.
     * @return bool True if the array is a strict list, false otherwise.
     */
    public static function isList(array $array): bool
    {
        return static::exists($array, 0)
            && array_keys($array) === range(0, count($array) - 1);
    }


    /**
     * Determine if an array is an associative array (i.e., has string keys).
     *
     * An associative array is an array where at least one key is a string.
     *
     * @param array $array The array to test.
     * @return bool True if the array is an associative array, false otherwise.
     */
    public static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }


    /**
     * Prepend a value to the beginning of an array.
     *
     * If the second parameter is null, the value is prepended as the first element
     * in the array. If the second parameter is a key, the value is prepended with
     * that key.
     *
     * @param array $array The array to prepend to.
     * @param mixed $value The value to prepend.
     * @param mixed $key The key to prepend with. If null, the value is prepended as the first element.
     * @return array The modified array.
     */
    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if ($key === null) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        return $array;
    }


    /**
     * Determine if all values in the array are positive numbers.
     *
     * @param array $array The array to check.
     * @return bool True if all values are positive, false otherwise.
     */
    public static function isPositive(array $array): bool
    {
        return !empty($array) && min($array) > 0;
    }


    /**
     * Determine if all values in the array are negative numbers.
     *
     * @param array $array The array to check.
     * @return bool True if all values are negative, false otherwise.
     */
    public static function isNegative(array $array): bool
    {
        return !empty($array) && max($array) < 0;
    }


    /**
     * Randomly shuffles the elements in the given array.
     *
     * If no seed is given, the internal PHP random number generator is used.
     * If a seed is given, the Mersenne Twister random number generator is
     * seeded with the given value, used to shuffle the array, and then reset
     * to the current internal PHP random number generator seed.
     *
     * @param array $array The array to shuffle.
     * @param int|null $seed Optional seed for the Mersenne Twister.
     * @return array The shuffled array.
     */
    public static function shuffle(array $array, ?int $seed = null): array
    {
        if ($seed === null) {
            \shuffle($array);
        } else {
            \mt_srand($seed);
            \shuffle($array);
            \mt_srand();
        }
        return $array;
    }


    /**
     * Check if all values in the array are integers.
     *
     * @param array $array The array to check.
     * @return bool True if all values are integers, false otherwise.
     */
    public static function isInt(array $array): bool
    {
        return $array === static::where($array, 'is_int');
    }


    /**
     * Get only the non-empty values from the array.
     *
     * A value is considered non-empty if it is not an empty string.
     *
     * @param array $array The array to check.
     * @return array The non-empty values.
     */
    public static function nonEmpty(array $array): array
    {
        return array_values(static::where($array, 'strlen'));
    }


    /**
     * Calculate the average of an array of numbers.
     *
     * @param array $array The array of numbers to average.
     * @return float|int The average of the numbers in the array. If the array is empty, 0 is returned.
     */
    public static function avg(array $array): float|int
    {
        if (empty($array)) {
            return 0;
        }
        return array_sum($array) / count($array);
    }


    /**
     * Determine if all values in the array are unique.
     *
     * @param array $array The array to check.
     * @return bool True if all values are unique, false otherwise.
     */
    public static function isUnique(array $array): bool
    {
        return count($array) === count(array_flip($array));
    }


    /**
     * Get only the positive numeric values from the array.
     *
     * @param array $array The array to check.
     * @return array The positive numeric values.
     */
    public static function positive(array $array): array
    {
        return static::where($array, static fn ($value) => is_numeric($value) && $value > 0);
    }


    /**
     * Get only the negative numeric values from the array.
     *
     * @param array $array The array to check.
     * @return array The negative numeric values.
     */
    public static function negative(array $array): array
    {
        return static::where($array, static fn ($value) => is_numeric($value) && $value < 0);
    }


    /**
     * Get every n-th element from the array
     *
     * @param array $array The array to slice.
     * @param int $step The "step" value (i.e. the interval between selected elements).
     * @param int $offset The offset from which to begin selecting elements.
     *
     * @return array The sliced array.
     */
    public static function nth(array $array, int $step, int $offset = 0): array
    {
        $results  = [];
        $position = 0;

        foreach ($array as $item) {
            if ($position % $step === $offset) {
                $results[] = $item;
            }
            $position++;
        }

        return $results;
    }


    /**
     * Retrieve duplicate values from an array.
     *
     * This method returns an array of values that occur more than once in the input array.
     *
     * @param array $array The array to search for duplicates.
     * @return array An array of duplicate values.
     */
    public static function duplicates(array $array): array
    {
        $duplicates = [];
        foreach (array_count_values($array) as $value => $count) {
            if ($count > 1) {
                $duplicates[] = $value;
            }
        }
        return $duplicates;
    }


    /**
     * "Paginate" the array by slicing it into a smaller segment.
     *
     * @param array $array The array to paginate.
     * @param int $page The page number to retrieve (1-indexed).
     * @param int $perPage The number of items per page.
     *
     * @return array The paginated slice of the array.
     */
    public static function paginate(array $array, int $page, int $perPage): array
    {
        return array_slice(
            $array,
            max(0, ($page - 1) * $perPage),
            $perPage,
            true
        );
    }


    /**
     * Combine two arrays into one array with corresponding key-value pairs.
     *
     * The function takes two arrays, one of keys and one of values, and combines them
     * into a single array. If the two arrays are not of equal length, the function
     * will truncate the longer array to match the length of the shorter array.
     *
     * @param array $keys The array of keys.
     * @param array $values The array of values.
     *
     * @return array The combined array.
     */
    public static function combine(array $keys, array $values): array
    {
        $keyCount   = count($keys);
        $valueCount = count($values);

        if ($keyCount !== $valueCount) {
            $size   = ($keyCount > $valueCount) ? $valueCount : $keyCount;
            $keys   = array_slice($keys, 0, $size);
            $values = array_slice($values, 0, $size);
        }

        return array_combine($keys, $values) ?: [];
    }


    /**
     * Filter the array using a callback function.
     *
     * If the callback is omitted, the function will return all elements in the
     * array that are truthy.
     *
     * @param array $array The array to search.
     * @param callable|null $callback The callback function to use for filtering.
     *   This function should take two arguments, the value and the key of each
     *   element in the array. The function should return true for elements that
     *   should be kept, and false for elements that should be discarded.
     *
     * @return array The filtered array.
     */
    public static function where(array $array, ?callable $callback = null): array
    {
        $flag = ($callback !== null) ? \ARRAY_FILTER_USE_BOTH : 0;
        return array_filter($array, $callback ?? fn ($val) => (bool) $val, $flag);
    }


    /**
     * Search the array for a given value and return its key if found.
     *
     * If the value is a callable, it will be called for each element in the array,
     * and if the callback returns true, the key will be returned. If the value is
     * not a callable, the function will search for the value in the array using
     * strict comparison. If the value is found, its key will be returned. If the
     * value is not found, null will be returned.
     *
     * @param array $array The array to search.
     * @param mixed $needle The value to search for, or a callable to use for
     *   searching.
     *
     * @return int|string|null The key of the value if found, or null if not found.
     */
    public static function search(array $array, mixed $needle): int|string|null
    {
        if (is_callable($needle)) {
            foreach ($array as $key => $value) {
                if ($needle($value, $key) === true) {
                    return $key;
                }
            }
            return null;
        }
        $foundKey = array_search($needle, $array, true);
        return $foundKey === false ? null : $foundKey;
    }


    /**
     * Break an array into smaller chunks of a specified size.
     *
     * This function splits the input array into multiple smaller arrays, each
     * containing up to the specified number of elements. If the specified size
     * is less than or equal to zero, the entire array is returned as a single chunk.
     *
     * @param array $array The array to be chunked.
     * @param int $size The size of each chunk.
     * @param bool $preserveKeys Whether to preserve the keys in the chunks.
     *
     * @return array An array of arrays, each representing a chunk of the original array.
     */
    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    {
        if ($size <= 0) {
            return [$array];
        }
        return array_chunk($array, $size, $preserveKeys);
    }


    /**
     * Apply a callback to each item in the array, optionally preserving keys.
     *
     * The callback function receives two arguments: the value of the current
     * element and its key. The callback should return the value to be used
     * in the resulting array.
     *
     * @param array $array The array to be mapped over.
     * @param callable $callback The callback function to apply to each element.
     *
     * @return array The array with each element transformed by the callback.
     */
    public static function map(array $array, callable $callback): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $results[$key] = $callback($value, $key);
        }
        return $results;
    }


    /**
     * Execute a callback on each item in the array, returning the original array.
     *
     * The callback function receives two arguments: the value of the current
     * element and its key. The callback should return a value that can be
     * evaluated to boolean. If the callback returns false, the iteration is
     * broken. Otherwise, the iteration continues.
     *
     * @param array $array The array to be iterated over.
     * @param callable $callback The callback function to apply to each element.
     *
     * @return array The original array.
     */
    public static function each(array $array, callable $callback): array
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }
        return $array;
    }


    /**
     * Reduce an array to a single value using a callback function.
     *
     * The callback function should accept three arguments: the accumulator,
     * the current array value, and the current array key. It should return
     * the updated accumulator value.
     *
     * @param array $array The array to reduce.
     * @param callable $callback The callback function to apply to each element.
     * @param mixed $initial The initial value of the accumulator.
     * @return mixed The reduced value.
     */
    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;
        foreach ($array as $key => $value) {
            $accumulator = $callback($accumulator, $value, $key);
        }
        return $accumulator;
    }


    /**
     * Determine if at least one element in the array passes the given truth test.
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to apply to each element.
     * @return bool Whether at least one element passed the truth test.
     */
    public static function some(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Determine if all elements in the array pass the given truth test.
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to apply to each element.
     * @return bool Whether all elements passed the truth test.
     */
    public static function every(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Determine if the array contains a given value or if a callback function
     * returns true for at least one element.
     *
     * If the second argument is a callable, it is used as a callback function
     * that receives the value and key of each element in the array. If the
     * callback returns true, the function returns true.
     *
     * If the second argument is not a callable, it is used as the value to
     * search for in the array. The optional third argument determines whether
     * to use strict comparison (===) or loose comparison (==).
     *
     * @param array $array The array to search.
     * @param mixed $valueOrCallback The value to search for, or a callable to apply to each element.
     * @param bool $strict Whether to use strict comparison (===) or loose comparison (==).
     * @return bool Whether the array contains the given value or whether the callback returned true for at least one element.
     */
    public static function contains(array $array, mixed $valueOrCallback, bool $strict = false): bool
    {
        if (is_callable($valueOrCallback)) {
            return static::some($array, $valueOrCallback);
        }
        return in_array($valueOrCallback, $array, $strict);
    }


    /**
     * Return the sum of all the elements in the array.
     *
     * If a callback is provided, it will be executed for each element in the
     * array and the return value will be added to the total.
     *
     * @param array $array The array to sum.
     * @param callable|null $callback The callback to execute for each element.
     * @return float|int The sum of all the elements in the array.
     */
    public static function sum(array $array, ?callable $callback = null): float|int
    {
        if ($callback === null) {
            return array_sum($array);
        }

        $total = 0;
        foreach ($array as $value) {
            $total += $callback($value);
        }
        return $total;
    }


    /**
     * Return an array with all duplicate values removed.
     *
     * The second parameter, $strict, determines whether to use strict comparison (===) or loose comparison (==) when
     * checking for duplicate values. If not provided, it defaults to false, which means loose comparison will be used.
     *
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to remove duplicates from.
     * @param bool $strict Whether to use strict comparison (===) or loose comparison (==). Defaults to false.
     * @return array The array with all duplicate values removed.
     */
    public static function unique(array $array, bool $strict = false): array
    {
        if (!$strict) {
            return array_values(array_unique($array));
        }
        // Manual strict approach:
        $checked = [];
        $result = [];
        foreach ($array as $item) {
            if (!in_array($item, $checked, true)) {
                $checked[] = $item;
                $result[] = $item;
            }
        }
        return $result;
    }


    /**
     * Return an array with all values that do not pass the given callback.
     *
     * The method takes an array and an optional callback as parameters.
     * If the callback is not provided, it defaults to `true`, which means the method will return an array with all
     * values that are not equal to `true`.
     * If the callback is a callable, the method will use it to filter the array. If the callback returns `false` for
     * a value, that value will be rejected.
     * If the callback is not a callable, the method will use it as the value to compare against. If the value is equal
     * to the callback, it will be rejected.
     *
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to filter.
     * @param mixed $callback The callback to use for filtering, or the value to compare against. Defaults to `true`.
     * @return array The filtered array.
     */
    public static function reject(array $array, mixed $callback = true): array
    {
        return BaseArrayHelper::doReject($array, $callback);
    }


    /**
     * Return a slice of the array, starting from the given offset and with the given length.
     *
     * The method takes three parameters: the array to slice, the offset from which to start the slice,
     * and the length of the slice. If the length is not provided, the method will return all elements
     * starting from the given offset.
     *
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to slice.
     * @param int $offset The offset from which to start the slice.
     * @param int|null $length The length of the slice. If not provided, the method will return all elements
     *   starting from the given offset.
     * @return array The sliced array.
     */
    public static function slice(array $array, int $offset, ?int $length = null): array
    {
        return array_slice($array, $offset, $length, true);
    }


    /**
     * Skip the first $count items of the array and return the remainder.
     *
     * This method is an alias for `slice($array, $count)`.
     *
     * @param array $array The array to skip.
     * @param int $count The number of items to skip.
     * @return array The skipped array.
     */
    public static function skip(array $array, int $count): array
    {
        return static::slice($array, $count);
    }


    /**
     * Skip items while the callback returns true; once false, keep the remainder.
     *
     * The method takes an array and a callback as parameters.
     * It iterates over the array, applying the callback to each item.
     * As long as the callback returns true, the item is skipped.
     * The first item for which the callback returns false is kept,
     * and all subsequent items are also kept.
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to skip.
     * @param callable $callback The callback to use for skipping.
     * @return array The skipped array.
     */
    public static function skipWhile(array $array, callable $callback): array
    {
        $result = [];
        $skipping = true;

        foreach ($array as $key => $value) {
            if ($skipping && !$callback($value, $key)) {
                $skipping = false;
            }
            if (!$skipping) {
                $result[$key] = $value;
            }
        }
        return $result;
    }


    /**
     * Skip items until the callback returns true, then keep the remainder.
     *
     * The method takes an array and a callback as parameters.
     * It iterates over the array, applying the callback to each item.
     * As long as the callback returns false, the item is skipped.
     * The first item for which the callback returns true is kept,
     * and all subsequent items are also kept.
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to skip.
     * @param callable $callback The callback to use for skipping.
     * @return array The skipped array.
     */
    public static function skipUntil(array $array, callable $callback): array
    {
        return static::skipWhile($array, fn ($value, $key) => !$callback($value, $key));
    }


    /**
     * Partition the array into two arrays [passed, failed] based on a callback.
     *
     * The method takes an array and a callback as parameters.
     * It iterates over the array, applying the callback to each item.
     * If the callback returns true, the item is added to the "passed" array.
     * If the callback returns false, the item is added to the "failed" array.
     * The method returns an array with two elements, the first being the "passed" array,
     * and the second being the "failed" array.
     *
     * @param array $array The array to partition.
     * @param callable $callback The callback to use for partitioning.
     * @return array An array with two elements, the first being the "passed" array, and the second being the "failed" array.
     */
    public static function partition(array $array, callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $passed[$key] = $value;
            } else {
                $failed[$key] = $value;
            }
        }
        return [$passed, $failed];
    }
}
