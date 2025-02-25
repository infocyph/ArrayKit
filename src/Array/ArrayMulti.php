<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class ArrayMulti
{
    /**
     * Select only certain keys from a multidimensional array.
     *
     * This method is the multidimensional equivalent of ArraySingle::only.
     *
     * @param array $array the multidimensional array to select from
     * @param array|string $keys the keys to select
     * @return array a new array with the selected keys
     */
    public static function only(array $array, array|string $keys): array
    {
        $result = [];
        $pick = array_flip((array)$keys);

        foreach ($array as $item) {
            if (is_array($item)) {
                $result[] = array_intersect_key($item, $pick);
            }
        }
        return $result;
    }


    /**
     * Collapses a multidimensional array into a single-dimensional array.
     *
     * This method takes a multidimensional array and merges all its
     * sub-arrays into a single-level array. Only one level of the
     * array is collapsed, so nested arrays within sub-arrays will
     * remain unchanged.
     *
     * @param array $array The multidimensional array to collapse.
     * @return array A single-dimensional array with all sub-array elements.
     */
    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (is_array($values)) {
                $results = array_merge($results, $values);
            }
        }
        return $results;
    }


    /**
     * Determine the depth of a multidimensional array.
     *
     * The depth is the level of nesting of the array, i.e. the
     * number of levels of arrays that are nested within one
     * another. The outermost level is 1, and each nested level
     * increments the depth by 1.
     *
     * @param array $array The multidimensional array to determine the depth of.
     * @return int The depth of the array.
     */
    public static function depth(array $array): int
    {
        if (empty($array)) {
            return 0;
        }

        $depth = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));

        foreach ($iterator as $unused) {
            $depth = max($depth, $iterator->getDepth());
        }
        return $depth + 1;  // zero-based => plus one
    }


    /**
     * Recursively flatten a multidimensional array to a specified depth.
     *
     * This method takes a multidimensional array and reduces it to a single-level
     * array up to the specified depth. If no depth is specified or if it is set
     * to \INF, the array will be completely flattened.
     *
     * @param array $array The multidimensional array to flatten.
     * @param float|int $depth The maximum depth to flatten. Defaults to infinite.
     * @return array A flattened array up to the specified depth.
     */
    public static function flatten(array $array, float|int $depth = \INF): array
    {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = ($depth === 1)
                    ? array_values($item)
                    : static::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }


    /**
     * Flatten the array into a single level but preserve keys.
     *
     * This method takes a multidimensional array and reduces it to a single-level
     * array, but preserves all keys. The resulting array will have the same keys
     * as the original array, but with all nested arrays flattened to the same
     * level.
     *
     * @param array $array The multidimensional array to flatten.
     * @return array A flattened array with all nested arrays flattened to the same level.
     */
    public static function flattenByKey(array $array): array
    {
        return iterator_to_array(
            new RecursiveIteratorIterator(new RecursiveArrayIterator($array)),
            false,
        );
    }


    /**
     * Recursively sort a multidimensional array by keys/values.
     *
     * This method takes a multidimensional array and recursively sorts it by
     * keys or values. The sorting options and direction are determined by the
     * $options and $descending parameters respectively.
     *
     * @param array $array The multidimensional array to sort.
     * @param int $options The sorting options. Defaults to SORT_REGULAR.
     * @param bool $descending Whether to sort in descending order. Defaults to false.
     * @return array The sorted array.
     */
    public static function sortRecursive(array $array, int $options = \SORT_REGULAR, bool $descending = false): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value, $options, $descending);
            }
        }

        if (ArraySingle::isAssoc($array)) {
            $descending
                ? krsort($array, $options)
                : ksort($array, $options);
        } else {
            $descending
                ? rsort($array, $options)
                : sort($array, $options);
        }
        return $array;
    }


    /**
     * Return the first item in a 2D array, or single-dim array, depending on usage.
     * If a callback is provided, return the first item that matches the callback.
     * Otherwise, return the first item in the array.
     *
     * @param array $array The array to search in.
     * @param callable|null $callback The callback to apply to each element.
     * @param mixed $default The default value to return if the array is empty.
     * @return mixed The first item in the array, or the default value if empty.
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }


    /**
     * Return the last item in a 2D array or single-dim array, depending on usage.
     * If a callback is provided, return the last item that matches the callback.
     * Otherwise, return the last item in the array.
     *
     * @param array $array The array to search in.
     * @param callable|null $callback The callback to apply to each element.
     * @param mixed $default The default value to return if the array is empty.
     * @return mixed The last item in the array, or the default value if empty.
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }
        // Reverse array with preserve_keys = true:
        return static::first(array_reverse($array, true), $callback, $default);
    }


    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' between 18 and 65").
     *
     * @param array $array The 2D array to filter.
     * @param string $key The key in each sub-array to compare.
     * @param float|int $from The lower bound of the comparison.
     * @param float|int $to The upper bound of the comparison.
     * @return array The filtered array.
     */
    public static function between(array $array, string $key, float|int $from, float|int $to): array
    {
        return array_filter($array, fn ($item) => ArraySingle::exists($item, $key)
            && compare($item[$key], $from, '>=')
            && compare($item[$key], $to, '<='));
    }


    /**
     * Filter a 2D array by a custom callback function on each row.
     *
     * If no callback is provided, the method will return the entire array.
     * If the array is empty and a default value is provided, that value will be returned.
     *
     * @param array $array The 2D array to filter.
     * @param callable|null $callback The callback function to apply to each element.
     *   If null, the method will return the entire array.
     * @param mixed $default The default value to return if the array is empty.
     * @return mixed The filtered array, or the default value if the array is empty.
     */
    public static function whereCallback(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : $array;
        }
        return array_filter($array, fn ($item, $index) => $callback($item, $index), \ARRAY_FILTER_USE_BOTH);
    }


    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' > 18").
     *
     * If the third argument is omitted, the second argument is treated as the value to compare.
     * If the third argument is provided, it is used as the operator for the comparison.
     *
     * @param array $array The 2D array to filter.
     * @param string $key The key in each sub-array to compare.
     * @param mixed $operator The operator to use for the comparison. If null, the second argument is treated as the value to compare.
     * @param mixed $value The value to compare.
     * @return array The filtered array.
     */
    public static function where(array $array, string $key, mixed $operator = null, mixed $value = null): array
    {
        // If only 2 args, treat second as $value
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = null;
        }

        return array_filter($array, fn ($item) => ArraySingle::exists($item, $key) && compare($item[$key], $value, $operator));
    }

    /**
     * Break a 2D array into smaller chunks of a specified size.
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
     * Apply a callback to each row in the array, optionally preserving keys.
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
        foreach ($array as $key => $row) {
            $results[$key] = $callback($row, $key);
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
        foreach ($array as $key => $row) {
            if ($callback($row, $key) === false) {
                break;
            }
        }
        return $array;
    }


    /**
     * Reduce an array to a single value using a callback function.
     *
     * The callback function receives three arguments: the accumulator,
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
        foreach ($array as $key => $row) {
            $accumulator = $callback($accumulator, $row, $key);
        }
        return $accumulator;
    }


    /**
     * Check if the array (of rows) contains at least one row matching a condition
     *
     * @param array $array The array to search.
     * @param callable $callback The callback to apply to each element.
     * @return bool Whether at least one element passed the truth test.
     */
    public static function some(array $array, callable $callback): bool
    {
        foreach ($array as $key => $row) {
            if ($callback($row, $key)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Determine if all rows in a 2D array pass the given truth test.
     *
     * The callback function receives two arguments: the value of the current
     * row and its key. It should return true if the condition is met, or false otherwise.
     *
     * @param array $array The array of rows to evaluate.
     * @param callable $callback The callback to apply to each row.
     * @return bool Whether all rows passed the truth test.
     */
    public static function every(array $array, callable $callback): bool
    {
        foreach ($array as $key => $row) {
            if (!$callback($row, $key)) {
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
     * Return a new array with all duplicate rows removed.
     *
     * The method takes an array and an optional boolean parameter as arguments.
     * If the boolean parameter is not provided, it defaults to false, which means
     * loose comparison (==) will be used when checking for duplicate values.
     * If the boolean parameter is true, strict comparison (===) will be used.
     *
     * The method iterates over the array, keeping track of values seen so far
     * in an array. If a value is seen for the first time, it is added to the
     * results array. If a value is seen again, it is skipped.
     * If the value is an array itself, it is serialized before being compared.
     *
     * @param array $array The array to remove duplicates from.
     * @param bool $strict Whether to use strict comparison (===) or loose comparison (==). Defaults to false.
     * @return array The array with all duplicate values removed.
     */
    public static function unique(array $array, bool $strict = false): array
    {
        $seen = [];
        $results = [];
        foreach ($array as $key => $row) {
            // If the row is itself an array, we serialize it for comparison:
            $compareValue = is_array($row) ? serialize($row) : $row;
            if (!in_array($compareValue, $seen, $strict)) {
                $seen[] = $compareValue;
                $results[$key] = $row;
            }
        }
        return $results;
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
        // Could unify via BaseArrayHelper::doReject($array, $callback).
        // Or keep local logic:
        if (is_callable($callback)) {
            return array_filter($array, fn ($row, $key) => !$callback($row, $key), \ARRAY_FILTER_USE_BOTH);
        }
        return array_filter($array, fn ($row) => $row != $callback);
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
        foreach ($array as $key => $row) {
            if ($callback($row, $key)) {
                $passed[$key] = $row;
            } else {
                $failed[$key] = $row;
            }
        }
        return [$passed, $failed];
    }


    /**
     * Skip the first $count items of the array and return the remainder.
     *
     * The method takes two parameters: the array to skip and the number of items to skip.
     * It returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to skip.
     * @param int $count The number of items to skip.
     * @return array The skipped array.
     */
    public static function skip(array $array, int $count): array
    {
        return array_slice($array, $count, null, true);
    }


    /**
     * Skip rows while the callback returns true; once false, keep the remainder.
     *
     * The method takes an array and a callback as parameters.
     * It iterates over the array, applying the callback to each row.
     * As long as the callback returns true, the row is skipped.
     * The first row for which the callback returns false is kept,
     * and all subsequent rows are also kept.
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
        foreach ($array as $key => $row) {
            if ($skipping && !$callback($row, $key)) {
                $skipping = false;
            }
            if (!$skipping) {
                $result[$key] = $row;
            }
        }
        return $result;
    }


    /**
     * Skip rows until the callback returns true, then keep the remainder.
     *
     * The method takes an array and a callback as parameters.
     * It iterates over the array, applying the callback to each row.
     * As long as the callback returns false, the row is skipped.
     * The first row for which the callback returns true is kept,
     * and all subsequent rows are also kept.
     * The method returns an array with the same type of indices as the input array.
     *
     * @param array $array The array to skip.
     * @param callable $callback The callback to use for skipping.
     * @return array The skipped array.
     */
    public static function skipUntil(array $array, callable $callback): array
    {
        return static::skipWhile($array, fn ($row, $key) => !$callback($row, $key));
    }


    /**
     * Calculate the sum of an array of values, optionally using a key or callback to extract the values to sum.
     *
     * If no key or callback is provided, the method will add up all numeric values in the array.
     * If a key is provided, the method will add up all values in the array that are keyed by that column.
     * If a callback is provided, the method will pass each row in the array to the callback and add up the results.
     *
     * @param array $array The array to sum.
     * @param string|callable|null $keyOrCallback The key or callback to use to extract the values to sum.
     * @return float|int The sum of the values in the array.
     */
    public static function sum(array $array, string|callable|null $keyOrCallback = null): float|int
    {
        $total = 0;
        foreach ($array as $row) {
            if ($keyOrCallback === null) {
                if (is_numeric($row)) {
                    $total += $row;
                }
            } elseif (is_callable($keyOrCallback)) {
                $total += $keyOrCallback($row);
            } else {
                if (isset($row[$keyOrCallback]) && is_numeric($row[$keyOrCallback])) {
                    $total += $row[$keyOrCallback];
                }
            }
        }
        return $total;
    }


    /**
     * Filter rows where "column" matches one of the given values.
     *
     * @param array $array The array to filter.
     * @param string $key The key in each sub-array to compare.
     * @param array $values The values to search for.
     * @param bool $strict Whether to use strict comparison (===) or loose comparison (==).
     * @return array The filtered array.
     */
    public static function whereIn(array $array, string $key, array $values, bool $strict = false): array
    {
        return array_filter(
            $array,
            fn ($row)
            => isset($row[$key]) && in_array($row[$key], $values, $strict),
        );
    }


    /**
     * Filter rows where "column" does NOT match one of the given values.
     *
     * @param array $array The array to filter.
     * @param string $key The key in each sub-array to compare.
     * @param array $values The values to search for.
     * @param bool $strict Whether to use strict comparison (===) or loose comparison (==).
     * @return array The filtered array.
     */
    public static function whereNotIn(array $array, string $key, array $values, bool $strict = false): array
    {
        return array_filter(
            $array,
            fn ($row)
            => !isset($row[$key]) || !in_array($row[$key], $values, $strict),
        );
    }


    /**
     * Filter rows where a column is null.
     *
     * This method takes a 2D array and a key as parameters. It returns a new array
     * containing only the rows where the specified key exists and its value is null.
     *
     * @param array $array The array to filter.
     * @param string $key The key in each sub-array to check for null value.
     * @return array The filtered array with rows where the specified key is null.
     */
    public static function whereNull(array $array, string $key): array
    {
        return array_filter(
            $array,
            fn ($row)
            => !empty($row) && array_key_exists($key, $row) && $row[$key] === null,
        );
    }


    /**
     * Filter rows where a column is not null.
     *
     * This method takes a 2D array and a key as parameters. It returns a new array
     * containing only the rows where the specified key exists and its value is not null.
     *
     * @param array $array The array to filter.
     * @param string $key The key in each sub-array to check for non-null value.
     * @return array The filtered array with rows where the specified key is not null.
     */
    public static function whereNotNull(array $array, string $key): array
    {
        return array_filter($array, fn ($row) => isset($row[$key]));
    }


    /**
     * Group a 2D array by a given column or callback.
     *
     * This method takes a 2D array and a key or a callback as parameters.
     * It returns a new array containing the grouped data.
     *
     * If the grouping key is a string, it is used as a key in each sub-array to group by.
     * If the grouping key is a callable, it is called with each sub-array and its key as arguments,
     * and the return value is used as the grouping key.
     *
     * If the `$preserveKeys` parameter is true, the original key from the array is preserved
     * in the grouped array. Otherwise, the grouped array values are indexed numerically.
     *
     * @param array $array The array to group.
     * @param string|callable $groupBy The key or callback to group by.
     * @param bool $preserveKeys Whether to preserve the original key in the grouped array.
     * @return array The grouped array.
     */
    public static function groupBy(array $array, string|callable $groupBy, bool $preserveKeys = false): array
    {
        $results = [];
        foreach ($array as $key => $row) {
            $gKey = null;
            if (is_callable($groupBy)) {
                $gKey = $groupBy($row, $key);
            } elseif (isset($row[$groupBy])) {
                $gKey = $row[$groupBy];
            } else {
                $gKey = '_undefined';
            }

            if ($preserveKeys) {
                $results[$gKey][$key] = $row;
            } else {
                $results[$gKey][] = $row;
            }
        }
        return $results;
    }


    /**
     * Sort a 2D array by a specified column or using a callback function.
     *
     * This method sorts an array based on a given column name or a custom callback.
     * The sorting can be performed in ascending or descending order, and it allows
     * specifying sorting options.
     *
     * @param array $array The array to sort.
     * @param string|callable $by The column key to sort by, or a callable function that returns the value to sort by.
     * @param bool $desc Whether to sort in descending order. Defaults to false (ascending order).
     * @param int $options The sorting options. Defaults to SORT_REGULAR.
     * @return array The sorted array.
     */
    public static function sortBy(
        array $array,
        string|callable $by,
        bool $desc = false,
        int $options = \SORT_REGULAR,
    ): array {
        uasort($array, function ($a, $b) use ($by, $desc, $options) {
            $valA = is_callable($by) ? $by($a) : ($a[$by] ?? null);
            $valB = is_callable($by) ? $by($b) : ($b[$by] ?? null);

            if ($valA === $valB) {
                return 0;
            }
            $comparison = ($valA < $valB) ? -1 : 1;
            return $desc ? -$comparison : $comparison;
        });
        return $array;
    }


    /**
     * Sort a 2D array by a specified column or using a callback function, in descending order.
     *
     * This is a convenience method for calling `sortBy` with the third argument set to true.
     *
     * @param array $array The array to sort.
     * @param string|callable $by The column key to sort by, or a callable function that returns the value to sort by.
     * @param int $options The sorting options. Defaults to SORT_REGULAR.
     * @return array The sorted array.
     */
    public static function sortByDesc(array $array, string|callable $by, int $options = \SORT_REGULAR): array
    {
        return static::sortBy($array, $by, true, $options);
    }
}
