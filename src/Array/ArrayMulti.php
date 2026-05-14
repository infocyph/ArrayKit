<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use Infocyph\ArrayKit\Array\Concerns\ArrayMultiQuerySortTrait;

class ArrayMulti
{
    use ArrayMultiQuerySortTrait;

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    {
        if ($size <= 0) {
            return [$array];
        }

        return array_chunk($array, $size, $preserveKeys);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (is_array($values)) {
                array_push($results, ...$values);
            }
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function contains(array $array, mixed $valueOrCallback, bool $strict = false): bool
    {
        if (is_callable($valueOrCallback)) {
            return static::some($array, $valueOrCallback);
        }

        return in_array($valueOrCallback, $array, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function depth(array $array): int
    {
        if (empty($array)) {
            return 0;
        }

        return self::measureDepth($array);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function each(array $array, callable $callback): array
    {
        return ArraySharedOps::each($array, $callback);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function every(array $array, callable $callback): bool
    {
        return ArraySharedOps::every($array, $callback);
    }

    /**
     * @param array<array-key, mixed> $array
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
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
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
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function flattenByKey(array $array): array
    {
        $results = [];
        self::flattenByKeyInto($array, $results);

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
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
     * Recursively merge arrays without converting scalar collisions into arrays.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $overrides
     * @return array<array-key, mixed>
     */
    public static function mergeRecursiveDistinct(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
            ) {
                $base[$key] = self::mergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array|string $keys): array
    {
        $result = [];
        /** @var array<int, int|string> $pickKeys */
        $pickKeys = (array) $keys;
        $pick = array_flip($pickKeys);

        foreach ($array as $item) {
            if (is_array($item)) {
                $result[] = array_intersect_key($item, $pick);
            }
        }

        return $result;
    }

    /**
     * Overlay one array on top of another (distinct recursive merge).
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $overlay
     * @return array<array-key, mixed>
     */
    public static function overlay(array $base, array $overlay): array
    {
        return self::mergeRecursiveDistinct($base, $overlay);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function partition(array $array, callable $callback): array
    {
        return ArraySharedOps::partition($array, $callback);
    }

    /**
     * @param array<array-key, mixed> $array
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
     * Rename top-level keys using a map or callback.
     *
     * @param array<array-key, mixed> $array
     * @param array<int|string, int|string>|callable $mapper
     * @return array<array-key, mixed>
     */
    public static function rekey(array $array, array|callable $mapper): array
    {
        return ArraySingle::rekey($array, $mapper);
    }

    /**
     * Recursively replace values (wrapper for array_replace_recursive).
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $replacements
     * @return array<array-key, mixed>
     */
    public static function replaceRecursive(array $base, array $replacements): array
    {
        return array_replace_recursive($base, $replacements);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skip(array $array, int $count): array
    {
        return ArraySharedOps::skip($array, $count);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skipUntil(array $array, callable $callback): array
    {
        return ArraySharedOps::skipUntil($array, $callback);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skipWhile(array $array, callable $callback): array
    {
        return ArraySharedOps::skipWhile($array, $callback);
    }

    /**
     * @param array<array-key, mixed> $matrix
     * @return array<array-key, mixed>
     */
    public static function transpose(array $matrix): array
    {
        if (empty($matrix)) {
            return [];
        }
        $firstRow = current($matrix);
        if (!is_array($firstRow)) {
            return [];
        }

        $keys = array_keys($firstRow);
        $results = array_fill_keys($keys, []);

        foreach ($matrix as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $col => $value) {
                $results[$col][] = $value;
            }
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function unique(array $array, bool $strict = false): array
    {
        $seen = [];
        $results = [];
        foreach ($array as $key => $row) {
            $compareValue = is_array($row) ? serialize($row) : $row;
            if (!in_array($compareValue, $seen, $strict)) {
                $seen[] = $compareValue;
                $results[$key] = $row;
            }
        }

        return $results;
    }

    /**
     * Reindex the top-level array numerically from zero.
     *
     * @param array<array-key, mixed> $array
     * @return array<int, mixed>
     */
    public static function values(array $array): array
    {
        return array_values($array);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, mixed> $results
     */
    private static function flattenByKeyInto(array $array, array &$results): void
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                self::flattenByKeyInto($value, $results);

                continue;
            }

            $results[] = $value;
        }
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function measureDepth(array $array): int
    {
        $maxDepth = 1;

        foreach ($array as $value) {
            if (is_array($value) && $value !== []) {
                $maxDepth = max($maxDepth, self::measureDepth($value) + 1);
            }
        }

        return $maxDepth;
    }
}
