<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array\Concerns;

use Infocyph\ArrayKit\Array\ArraySharedOps;
use Infocyph\ArrayKit\Array\ArraySingle;

trait ArrayMultiQuerySortTrait
{
    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' between 18 and 65").
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function between(array $array, string $key, float|int $from, float|int $to): array
    {
        return array_filter(
            $array,
            static fn(mixed $item): bool => is_array($item)
                && ArraySingle::exists($item, $key)
                && compare($item[$key], $from, '>=')
                && compare($item[$key], $to, '<='),
        );
    }

    /**
     * Count rows grouped by key/callback buckets.
     *
     * @param array<array-key, mixed> $array
     * @return array<int|string, int>
     */
    public static function countBy(array $array, string|callable $groupBy): array
    {
        $counts = [];

        foreach ($array as $key => $row) {
            $bucket = is_callable($groupBy)
                ? $groupBy($row, $key)
                : ((is_array($row) && array_key_exists($groupBy, $row)) ? $row[$groupBy] : '_undefined');

            $normalized = self::normalizeArrayKey($bucket);
            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Return the first row where the key comparison matches.
     *
     * @param array<array-key, mixed> $array
     */
    public static function firstWhere(
        array $array,
        string $key,
        mixed $operator = null,
        mixed $value = null,
        mixed $default = null,
    ): mixed {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = null;
        }
        $operator = is_string($operator) ? $operator : null;

        foreach ($array as $row) {
            if (
                is_array($row)
                && ArraySingle::exists($row, $key)
                && compare($row[$key], $value, $operator)
            ) {
                return $row;
            }
        }

        return $default;
    }

    /**
     * Group a 2D array by a given column or callback.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function groupBy(array $array, string|callable $groupBy, bool $preserveKeys = false): array
    {
        $results = [];
        foreach ($array as $key => $row) {
            $gKey = null;
            if (is_callable($groupBy)) {
                $gKey = $groupBy($row, $key);
            } elseif (is_array($row) && isset($row[$groupBy])) {
                $gKey = $row[$groupBy];
            } else {
                $gKey = '_undefined';
            }
            $groupKey = self::normalizeArrayKey($gKey);

            if ($preserveKeys) {
                $results[$groupKey][$key] = $row;
            } else {
                $results[$groupKey][] = $row;
            }
        }

        return $results;
    }

    /**
     * Alias of keyBy().
     *
     * @param array<array-key, mixed> $array
     * @return array<int|string, mixed>
     */
    public static function indexBy(array $array, string|callable $indexBy): array
    {
        return static::keyBy($array, $indexBy);
    }

    /**
     * Key rows by a derived key (last write wins on duplicate keys).
     *
     * @param array<array-key, mixed> $array
     * @return array<int|string, mixed>
     */
    public static function keyBy(array $array, string|callable $keyBy): array
    {
        $results = [];

        foreach ($array as $index => $row) {
            $resolved = is_callable($keyBy)
                ? $keyBy($row, $index)
                : ((is_array($row) && array_key_exists($keyBy, $row)) ? $row[$keyBy] : '_undefined');

            $results[self::normalizeArrayKey($resolved)] = $row;
        }

        return $results;
    }

    /**
     * Return the last item in a 2D array or single-dim array, depending on usage.
     *
     * @param array<array-key, mixed> $array
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Transform rows and return a key/value map from callback results.
     *
     * Callback must return a one-item array like [newKey => newValue].
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $results = [];

        foreach ($array as $key => $row) {
            $mapped = $callback($row, $key);
            if (!is_array($mapped)) {
                continue;
            }

            foreach ($mapped as $mappedKey => $mappedValue) {
                $results[self::normalizeArrayKey($mappedKey)] = $mappedValue;
            }
        }

        return $results;
    }

    /**
     * Return the maximum numeric row value by key/callback.
     *
     * @param array<array-key, mixed> $array
     */
    public static function max(array $array, string|callable $keyOrCallback): float|int|null
    {
        $max = self::selectExtremeValue($array, $keyOrCallback, pickMax: true);

        if ($max === null) {
            return null;
        }

        return fmod($max, 1.0) === 0.0 ? (int) $max : $max;
    }

    /**
     * Return the row with the largest numeric score by key/callback.
     *
     * @param array<array-key, mixed> $array
     */
    public static function maxBy(array $array, string|callable $keyOrCallback): mixed
    {
        return self::selectExtremeRow($array, $keyOrCallback, pickMax: true);
    }

    /**
     * Return the minimum numeric row value by key/callback.
     *
     * @param array<array-key, mixed> $array
     */
    public static function min(array $array, string|callable $keyOrCallback): float|int|null
    {
        $min = self::selectExtremeValue($array, $keyOrCallback, pickMax: false);

        if ($min === null) {
            return null;
        }

        return fmod($min, 1.0) === 0.0 ? (int) $min : $min;
    }

    /**
     * Return the row with the smallest numeric score by key/callback.
     *
     * @param array<array-key, mixed> $array
     */
    public static function minBy(array $array, string|callable $keyOrCallback): mixed
    {
        return self::selectExtremeRow($array, $keyOrCallback, pickMax: false);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function pluck(array $array, string $column, ?string $indexBy = null): array
    {
        $results = [];
        foreach ($array as $row) {
            if (!is_array($row) || !array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            if ($indexBy !== null && array_key_exists($indexBy, $row)) {
                $results[self::normalizeArrayKey($row[$indexBy])] = $value;
            } else {
                $results[] = $value;
            }
        }

        return $results;
    }

    /**
     * Return an array with all values that do not pass the given callback.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function reject(array $array, mixed $callback = true): array
    {
        if (is_callable($callback)) {
            return array_filter($array, fn($row, $key) => !$callback($row, $key), \ARRAY_FILTER_USE_BOTH);
        }

        return array_filter($array, fn($row) => $row != $callback);
    }

    /**
     * Check if the array (of rows) contains at least one row matching a condition.
     *
     * @param array<array-key, mixed> $array
     */
    public static function some(array $array, callable $callback): bool
    {
        return array_any($array, static fn(mixed $row, int|string $key): bool => (bool) $callback($row, $key));
    }

    /**
     * Sort a 2D array by a specified column or using a callback function.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function sortBy(
        array $array,
        string|callable $by,
        bool $desc = false,
        int $options = \SORT_REGULAR,
    ): array {
        uasort($array, function ($a, $b) use ($by, $desc, $options) {
            $valA = is_callable($by) ? $by($a) : (is_array($a) ? ($a[$by] ?? null) : null);
            $valB = is_callable($by) ? $by($b) : (is_array($b) ? ($b[$by] ?? null) : null);

            $comparison = self::compareSortValues($valA, $valB, $options);

            return $desc ? -$comparison : $comparison;
        });

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function sortByDesc(array $array, string|callable $by, int $options = \SORT_REGULAR): array
    {
        return static::sortBy($array, $by, true, $options);
    }

    /**
     * Recursively sort a multidimensional array by keys/values.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
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
            usort(
                $array,
                static fn(mixed $left, mixed $right): int => $descending
                    ? -self::compareSortValues($left, $right, $options)
                    : self::compareSortValues($left, $right, $options),
            );
        }

        return $array;
    }

    /**
     * Calculate the sum of an array of values.
     *
     * @param array<array-key, mixed> $array
     */
    public static function sum(array $array, string|callable|null $keyOrCallback = null): float|int
    {
        $total = 0;
        foreach ($array as $row) {
            $total += self::extractSummableValue($row, $keyOrCallback);
        }

        return fmod($total, 1.0) === 0.0 ? (int) $total : $total;
    }

    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' > 18").
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function where(array $array, string $key, mixed $operator = null, mixed $value = null): array
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = null;
        }
        $operator = is_string($operator) ? $operator : null;

        return array_filter(
            $array,
            static fn(mixed $item): bool => is_array($item)
                && ArraySingle::exists($item, $key)
                && compare($item[$key], $value, $operator),
        );
    }

    /**
     * Filter a 2D array by a custom callback function on each row.
     *
     * @param array<array-key, mixed> $array
     */
    public static function whereCallback(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : $array;
        }

        return array_filter($array, static fn(mixed $item, int|string $index): bool => (bool) $callback($item, $index), \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter rows where "column" matches one of the given values.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function whereIn(array $array, string $key, array $values, bool $strict = false): array
    {
        return array_filter(
            $array,
            static fn(mixed $row): bool => is_array($row)
                && array_key_exists($key, $row)
                && in_array($row[$key], $values, $strict),
        );
    }

    /**
     * Filter rows where "column" does NOT match one of the given values.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function whereNotIn(array $array, string $key, array $values, bool $strict = false): array
    {
        return array_filter(
            $array,
            static fn(mixed $row): bool => !is_array($row)
                || !array_key_exists($key, $row)
                || !in_array($row[$key], $values, $strict),
        );
    }

    /**
     * Filter rows where a column is not null.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereNotNull(array $array, string $key): array
    {
        return array_filter($array, static fn(mixed $row): bool => is_array($row) && isset($row[$key]));
    }

    /**
     * Filter rows where a column is null.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereNull(array $array, string $key): array
    {
        return array_filter(
            $array,
            static fn(mixed $row): bool => is_array($row)
                && !empty($row)
                && array_key_exists($key, $row)
                && $row[$key] === null,
        );
    }

    private static function asNumeric(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private static function asString(mixed $value): string
    {
        return ArraySharedOps::asString($value);
    }

    /**
     * Compare two values according to PHP sort options.
     */
    private static function compareSortValues(mixed $left, mixed $right, int $options): int
    {
        if ($left === $right) {
            return 0;
        }

        $caseInsensitive = (bool) ($options & \SORT_FLAG_CASE);
        $baseOption = $options & ~\SORT_FLAG_CASE;

        return match ($baseOption) {
            \SORT_NUMERIC => self::asNumeric($left) <=> self::asNumeric($right),
            \SORT_STRING => $caseInsensitive
                ? strcasecmp(self::asString($left), self::asString($right))
                : strcmp(self::asString($left), self::asString($right)),
            \SORT_NATURAL => $caseInsensitive
                ? strnatcasecmp(self::asString($left), self::asString($right))
                : strnatcmp(self::asString($left), self::asString($right)),
            \SORT_LOCALE_STRING => strcoll(self::asString($left), self::asString($right)),
            default => $left <=> $right,
        };
    }

    private static function extractComparableValue(mixed $row, string|callable $keyOrCallback): ?float
    {
        if (is_callable($keyOrCallback)) {
            $result = $keyOrCallback($row);

            return is_numeric($result) ? (float) $result : null;
        }

        if (is_array($row) && array_key_exists($keyOrCallback, $row) && is_numeric($row[$keyOrCallback])) {
            return (float) $row[$keyOrCallback];
        }

        return null;
    }

    private static function extractSummableValue(mixed $row, string|callable|null $keyOrCallback): float
    {
        if ($keyOrCallback === null) {
            return is_numeric($row) ? (float) $row : 0.0;
        }

        if (is_callable($keyOrCallback)) {
            $result = $keyOrCallback($row);

            return is_numeric($result) ? (float) $result : 0.0;
        }

        if (is_array($row) && isset($row[$keyOrCallback]) && is_numeric($row[$keyOrCallback])) {
            return (float) $row[$keyOrCallback];
        }

        return 0.0;
    }

    private static function normalizeArrayKey(mixed $value): int|string
    {
        return ArraySharedOps::normalizeArrayKey($value);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function selectExtremeRow(array $array, string|callable $keyOrCallback, bool $pickMax): mixed
    {
        $bestRow = null;
        $bestScore = null;
        $found = false;

        foreach ($array as $row) {
            $score = self::extractComparableValue($row, $keyOrCallback);
            if ($score === null) {
                continue;
            }

            if (!$found || ($pickMax ? ($score > $bestScore) : ($score < $bestScore))) {
                $bestRow = $row;
                $bestScore = $score;
                $found = true;
            }
        }

        return $found ? $bestRow : null;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function selectExtremeValue(array $array, string|callable $keyOrCallback, bool $pickMax): ?float
    {
        $selected = null;

        foreach ($array as $row) {
            $value = self::extractComparableValue($row, $keyOrCallback);
            if ($value === null) {
                continue;
            }

            if ($selected === null || ($pickMax ? ($value > $selected) : ($value < $selected))) {
                $selected = $value;
            }
        }

        return $selected;
    }
}
