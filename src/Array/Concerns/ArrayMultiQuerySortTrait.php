<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array\Concerns;

use Infocyph\ArrayKit\Array\ArraySharedOps;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\ArraySingleOps;
use Infocyph\ArrayKit\Array\ArrayValueSetOps;
use InvalidArgumentException;

use function Infocyph\ArrayKit\compare;

/** @internal */
trait ArrayMultiQuerySortTrait
{
    private const int ROW_MEMBERSHIP_LOOKUP_MIN_VALUES = 256;

    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' between 18 and 65").
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function between(array $array, string $key, float|int $from, float|int $to): array
    {
        $results = [];
        foreach ($array as $index => $item) {
            if (
                is_array($item)
                && ArraySingle::exists($item, $key)
                && compare($item[$key], $from, '>=')
                && compare($item[$key], $to, '<=')
            ) {
                $results[$index] = $item;
            }
        }

        return $results;
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
        $useCallback = !is_string($groupBy);

        foreach ($array as $key => $row) {
            if (!$useCallback && (!is_array($row) || !array_key_exists($groupBy, $row))) {
                continue;
            }

            $bucket = $useCallback ? $groupBy($row, $key) : $row[$groupBy];
            $arrayKey = self::requireArrayKey($bucket, 'countBy');
            $counts[$arrayKey] = ($counts[$arrayKey] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Return duplicate rows by derived key (first duplicate occurrence onward).
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function duplicatesBy(array $array, string|callable $keyOrCallback, bool $strict = false): array
    {
        return self::collectByDerivedKey($array, $keyOrCallback, $strict, keepDuplicates: true);
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
        if (func_num_args() === 3) {
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
     * Return the first row where a key value is in the given value set.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     */
    public static function firstWhereIn(array $array, string $key, array $values, bool $strict = false, mixed $default = null): mixed
    {
        $lookup = self::buildInLookup($values, $strict);
        if ($lookup === null) {
            return self::firstWhereInByScan($array, $key, $values, $strict, $default);
        }

        foreach ($array as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }

            if (self::rowLookupContains($lookup, $values, $row[$key], $strict)) {
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
        $useCallback = !is_string($groupBy);

        foreach ($array as $key => $row) {
            if (!$useCallback && (!is_array($row) || !array_key_exists($groupBy, $row))) {
                continue;
            }

            $resolved = $useCallback ? $groupBy($row, $key) : $row[$groupBy];
            $groupKey = self::requireArrayKey($resolved, 'groupBy');

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
        $useCallback = !is_string($keyBy);

        foreach ($array as $index => $row) {
            if (!$useCallback && (!is_array($row) || !array_key_exists($keyBy, $row))) {
                continue;
            }

            $resolved = $useCallback ? $keyBy($row, $index) : $row[$keyBy];
            $results[self::requireArrayKey($resolved, 'keyBy')] = $row;
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

        $resolved = $default;
        foreach ($array as $key => $row) {
            if ($callback($row, $key)) {
                $resolved = $row;
            }
        }

        return $resolved;
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
                $results[$mappedKey] = $mappedValue;
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
        return self::formatNumericResult(
            self::selectExtremeValue($array, $keyOrCallback, pickMax: true),
        );
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
        return self::formatNumericResult(
            self::selectExtremeValue($array, $keyOrCallback, pickMax: false),
        );
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
            if ($indexBy === null) {
                $results[] = $value;

                continue;
            }

            if (array_key_exists($indexBy, $row)) {
                $results[self::requireArrayKey($row[$indexBy], 'pluck')] = $value;
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
        $results = [];
        if (!is_string($callback) && is_callable($callback)) {
            foreach ($array as $key => $row) {
                if (!$callback($row, $key)) {
                    $results[$key] = $row;
                }
            }

            return $results;
        }

        foreach ($array as $key => $row) {
            if ($row != $callback) {
                $results[$key] = $row;
            }
        }

        return $results;
    }

    /**
     * Check if the array (of rows) contains at least one row matching a condition.
     *
     * @param array<array-key, mixed> $array
     */
    public static function some(array $array, callable $callback): bool
    {
        return array_any($array, $callback);
    }

    /**
     * Sort a 2D array by a specified column or using a callback function.
     *
     * Callback receives ($row, $key).
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
        if (!is_string($by)) {
            $scores = [];
            foreach ($array as $key => $row) {
                $scores[$key] = self::invokeRowCallback($by, $row, $key);
            }

            uksort(
                $array,
                static function (int|string $leftKey, int|string $rightKey) use ($scores, $desc, $options): int {
                    $comparison = self::compareSortValues(
                        $scores[$leftKey] ?? null,
                        $scores[$rightKey] ?? null,
                        $options,
                    );

                    return self::applySortDirection($comparison, $desc);
                },
            );

            return $array;
        }

        uasort($array, function ($a, $b) use ($by, $desc, $options) {
            $valA = is_array($a) ? ($a[$by] ?? null) : null;
            $valB = is_array($b) ? ($b[$by] ?? null) : null;

            return self::applySortDirection(
                self::compareSortValues($valA, $valB, $options),
                $desc,
            );
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
     * Sort by multiple sort rules.
     *
     * Each criterion can be:
     * - ['column', 'asc'|'desc', SORT_*]
     * - [callable, 'asc'|'desc', SORT_*]
     *
     * @param array<array-key, mixed> $array
     * @param array<int, array<int, mixed>> $criteria
     * @return array<array-key, mixed>
     */
    public static function sortByMany(array $array, array $criteria): array
    {
        if ($criteria === []) {
            return $array;
        }

        $prepared = self::prepareSortByManyCriteria($array, $criteria);
        uksort(
            $array,
            static fn(int|string $leftKey, int|string $rightKey): int => self::compareByManyCriteria(
                $leftKey,
                $rightKey,
                $prepared,
            ),
        );

        return $array;
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
        unset($value);

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
     * Safe recursive sort with depth/node guards.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function sortRecursiveGuarded(
        array $array,
        int $options = \SORT_REGULAR,
        bool $descending = false,
        int $maxDepth = 256,
        int $maxNodes = 100000,
        bool $throwOnTooDeep = false,
    ): array {
        $visitedNodes = 0;

        return self::sortRecursiveWithGuards(
            $array,
            $options,
            $descending,
            1,
            $visitedNodes,
            $maxDepth,
            $maxNodes,
            $throwOnTooDeep,
        );
    }

    /**
     * Calculate the sum of an array of values.
     *
     * Callback receives ($row, $key). Non-numeric values are ignored.
     *
     * @param array<array-key, mixed> $array
     */
    public static function sum(array $array, string|callable|null $keyOrCallback = null): float|int
    {
        $total = 0;
        foreach ($array as $key => $row) {
            $total += self::extractSummableValue($row, $keyOrCallback, $key);
        }

        return $total;
    }

    /**
     * Return unique rows by derived key.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function uniqueBy(array $array, string|callable $keyOrCallback, bool $strict = false): array
    {
        return self::collectByDerivedKey($array, $keyOrCallback, $strict, keepDuplicates: false);
    }

    /**
     * Filter a 2D array by a single key's comparison (like "where 'age' > 18").
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function where(array $array, string $key, mixed $operator = null, mixed $value = null): array
    {
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = null;
        }
        $operator = is_string($operator) ? $operator : null;

        $results = [];
        foreach ($array as $index => $item) {
            if (
                is_array($item)
                && ArraySingle::exists($item, $key)
                && compare($item[$key], $value, $operator)
            ) {
                $results[$index] = $item;
            }
        }

        return $results;
    }

    /**
     * Filter rows where a key value falls inside an inclusive range.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereBetween(array $array, string $key, float|int $from, float|int $to): array
    {
        return self::between($array, $key, $from, $to);
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

        $results = [];
        foreach ($array as $index => $item) {
            if ((bool) $callback($item, $index)) {
                $results[$index] = $item;
            }
        }

        return $results;
    }

    /**
     * Filter rows where a key value contains a given substring.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereContains(array $array, string $key, string $needle, bool $caseSensitive = true): array
    {
        $match = $caseSensitive ? $needle : strtolower($needle);

        return self::filterByTextMatch(
            $array,
            $key,
            static fn(string $text): bool => str_contains($caseSensitive ? $text : strtolower($text), $match),
        );
    }

    /**
     * Filter rows where a key value ends with a given suffix.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereEndsWith(array $array, string $key, string $suffix, bool $caseSensitive = true): array
    {
        $needle = $caseSensitive ? $suffix : strtolower($suffix);

        return self::filterByTextMatch(
            $array,
            $key,
            static fn(string $text): bool => str_ends_with($caseSensitive ? $text : strtolower($text), $needle),
        );
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
        $results = [];
        $lookup = self::buildInLookup($values, $strict);

        if ($lookup === null) {
            return self::whereInByScan($array, $key, $values, $strict, true);
        }

        foreach ($array as $index => $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }

            if (self::rowLookupContains($lookup, $values, $row[$key], $strict)) {
                $results[$index] = $row;
            }
        }

        return $results;
    }

    /**
     * Filter rows where a key value matches an SQL-like pattern ('%' and '_').
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereLike(array $array, string $key, string $pattern, bool $caseSensitive = false): array
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/' . ($caseSensitive ? '' : 'i');
        $results = [];

        foreach ($array as $index => $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $text = (string) $value;
            if (preg_match($regex, $text) === 1) {
                $results[$index] = $row;
            }
        }

        return $results;
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
        $results = [];
        $lookup = self::buildInLookup($values, $strict);

        if ($lookup === null) {
            return self::whereInByScan($array, $key, $values, $strict, false);
        }

        foreach ($array as $index => $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                $results[$index] = $row;

                continue;
            }

            if (!self::rowLookupContains($lookup, $values, $row[$key], $strict)) {
                $results[$index] = $row;
            }
        }

        return $results;
    }

    /**
     * Filter rows where a column is not null.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereNotNull(array $array, string $key): array
    {
        return self::filterByNullState($array, $key, expectNull: false);
    }

    /**
     * Filter rows where a column is null.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereNull(array $array, string $key): array
    {
        return self::filterByNullState($array, $key, expectNull: true);
    }

    /**
     * Filter rows where a key value starts with a given prefix.
     *
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function whereStartsWith(array $array, string $key, string $prefix, bool $caseSensitive = true): array
    {
        $needle = $caseSensitive ? $prefix : strtolower($prefix);

        return self::filterByTextMatch(
            $array,
            $key,
            static fn(string $text): bool => str_starts_with($caseSensitive ? $text : strtolower($text), $needle),
        );
    }

    private static function applySortDirection(int $comparison, bool $desc): int
    {
        return $desc ? -$comparison : $comparison;
    }

    private static function asNumeric(mixed $value): float|int
    {
        return ArraySingleOps::numericValue($value) ?? 0;
    }

    private static function asString(mixed $value): string
    {
        return ArraySharedOps::asString($value);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, bool>|null
     */
    private static function buildInLookup(array $values, bool $strict): ?array
    {
        if (count($values) < self::ROW_MEMBERSHIP_LOOKUP_MIN_VALUES) {
            return null;
        }

        if ($strict) {
            $lookup = [];
            foreach ($values as $value) {
                if (self::containsNonReflexiveStrictValue($value)) {
                    return null;
                }

                $lookup[ArraySingleOps::fingerprint($value, true)] = true;
            }

            return $lookup;
        }

        $lookup = ['type:non-numeric-string' => true];
        foreach ($values as $value) {
            if (!is_string($value) || is_numeric($value)) {
                return null;
            }

            $lookup['value:' . strlen($value) . ':' . $value] = true;
        }

        return $lookup;
    }

    private static function canTraverse(
        int $currentDepth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
        bool $throwOnTooDeep,
    ): bool {
        if ($maxDepth > 0 && $currentDepth > $maxDepth) {
            if ($throwOnTooDeep) {
                throw new \RuntimeException('Recursive sort exceeded max depth.');
            }

            return false;
        }

        $visitedNodes++;
        if ($maxNodes > 0 && $visitedNodes > $maxNodes) {
            if ($throwOnTooDeep) {
                throw new \RuntimeException('Recursive sort exceeded max node count.');
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function collectByDerivedKey(
        array $array,
        string|callable $keyOrCallback,
        bool $strict,
        bool $keepDuplicates,
    ): array {
        if (!$strict) {
            return self::collectByDerivedKeyLoose($array, $keyOrCallback, $keepDuplicates);
        }

        return self::collectByDerivedKeyStrict($array, $keyOrCallback, $keepDuplicates);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function collectByDerivedKeyLoose(
        array $array,
        string|callable $keyOrCallback,
        bool $keepDuplicates,
    ): array {
        $results = [];
        $seen = [];
        foreach ($array as $index => $row) {
            if (is_string($keyOrCallback)) {
                if (!is_array($row) || !array_key_exists($keyOrCallback, $row)) {
                    continue;
                }

                $derived = $row[$keyOrCallback];
            } else {
                $derived = self::invokeRowCallback($keyOrCallback, $row, $index);
            }
            $alreadySeen = in_array($derived, $seen, false);
            if ($alreadySeen === $keepDuplicates) {
                $results[$index] = $row;
            }
            if (!$alreadySeen) {
                $seen[] = $derived;
            }
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function collectByDerivedKeyStrict(
        array $array,
        string|callable $keyOrCallback,
        bool $keepDuplicates,
    ): array {
        $results = [];
        $seen = [];
        $digestBuckets = [];
        $fallback = [];

        foreach ($array as $index => $row) {
            if (is_string($keyOrCallback)) {
                if (!is_array($row) || !array_key_exists($keyOrCallback, $row)) {
                    continue;
                }

                $derived = $row[$keyOrCallback];
            } else {
                $derived = self::invokeRowCallback($keyOrCallback, $row, $index);
            }
            $alreadySeen = ArrayValueSetOps::strictValueAlreadySeen(
                $derived,
                $seen,
                $digestBuckets,
                $fallback,
            );

            if ($alreadySeen === $keepDuplicates) {
                $results[$index] = $row;
            }
        }

        return $results;
    }

    /**
     * @param array<int, array{values:array<array-key, mixed>, desc:bool, options:int}> $criteria
     */
    private static function compareByManyCriteria(int|string $leftKey, int|string $rightKey, array $criteria): int
    {
        foreach ($criteria as $criterion) {
            $leftValue = $criterion['values'][$leftKey];
            $rightValue = $criterion['values'][$rightKey];
            $comparison = self::compareSortValues($leftValue, $rightValue, $criterion['options']);
            if ($comparison !== 0) {
                return self::applySortDirection($comparison, $criterion['desc']);
            }
        }

        return 0;
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

    private static function containsNonReflexiveStrictValue(mixed $value): bool
    {
        if (is_float($value)) {
            return is_nan($value);
        }

        if (!is_array($value)) {
            return false;
        }

        return array_any($value, self::containsNonReflexiveStrictValue(...));
    }

    private static function extractComparableValue(mixed $row, string|callable $keyOrCallback, int|string $key): float|int|null
    {
        if (!is_string($keyOrCallback)) {
            $result = self::invokeRowCallback($keyOrCallback, $row, $key);

            return ArraySingleOps::numericValue($result);
        }

        if (is_array($row) && array_key_exists($keyOrCallback, $row)) {
            return ArraySingleOps::numericValue($row[$keyOrCallback]);
        }

        return null;
    }

    private static function extractRowTextValue(mixed $row, string $key): ?string
    {
        if (!is_array($row) || !array_key_exists($key, $row)) {
            return null;
        }

        $value = $row[$key];
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        return (string) $value;
    }

    private static function extractSummableValue(mixed $row, string|callable|null $keyOrCallback, int|string $key): float|int
    {
        if ($keyOrCallback === null) {
            return ArraySingleOps::numericValue($row) ?? 0;
        }

        if (!is_string($keyOrCallback)) {
            $result = self::invokeRowCallback($keyOrCallback, $row, $key);

            return ArraySingleOps::numericValue($result) ?? 0;
        }

        if (is_array($row) && isset($row[$keyOrCallback])) {
            return ArraySingleOps::numericValue($row[$keyOrCallback]) ?? 0;
        }

        return 0;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function filterByNullState(array $array, string $key, bool $expectNull): array
    {
        $results = [];
        foreach ($array as $index => $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }

            $isNull = $row[$key] === null;
            if (($expectNull && $isNull) || (!$expectNull && !$isNull)) {
                $results[$index] = $row;
            }
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param callable(string): bool $matcher
     * @return array<array-key, mixed>
     */
    private static function filterByTextMatch(array $array, string $key, callable $matcher): array
    {
        $results = [];
        foreach ($array as $index => $row) {
            $text = self::extractRowTextValue($row, $key);
            if ($text === null || !$matcher($text)) {
                continue;
            }

            $results[$index] = $row;
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     */
    private static function firstWhereInByScan(
        array $array,
        string $key,
        array $values,
        bool $strict,
        mixed $default,
    ): mixed {
        foreach ($array as $row) {
            if (is_array($row) && array_key_exists($key, $row) && in_array($row[$key], $values, $strict)) {
                return $row;
            }
        }

        return $default;
    }

    private static function formatNumericResult(float|int|null $value): float|int|null
    {
        return $value;
    }

    private static function invokeRowCallback(callable $callback, mixed $row, int|string $key): mixed
    {
        return $callback($row, $key);
    }

    /**
     * @param array<int, array<int, mixed>> $criteria
     * @return array<int, array{by:string|callable, desc:bool, options:int}>
     */
    private static function normalizeSortByManyCriteria(array $criteria): array
    {
        $normalized = [];
        foreach ($criteria as $criterion) {
            $by = $criterion[0] ?? null;
            if (!is_string($by) && !is_callable($by)) {
                continue;
            }

            $rawDirection = $criterion[1] ?? 'asc';
            $direction = is_string($rawDirection) ? strtolower($rawDirection) : 'asc';
            $desc = $direction === 'desc';
            $options = isset($criterion[2]) && is_int($criterion[2]) ? $criterion[2] : \SORT_REGULAR;

            $normalized[] = [
                'by' => $by,
                'desc' => $desc,
                'options' => $options,
            ];
        }

        return $normalized;
    }

    /**
     * Resolve each criterion once per row before sorting.
     *
     * @param array<array-key, mixed> $array
     * @param array<int, array<int, mixed>> $criteria
     * @return array<int, array{values:array<array-key, mixed>, desc:bool, options:int}>
     */
    private static function prepareSortByManyCriteria(array $array, array $criteria): array
    {
        $prepared = [];

        foreach (self::normalizeSortByManyCriteria($criteria) as $criterion) {
            $values = [];
            foreach ($array as $key => $row) {
                $values[$key] = self::resolveSortByManyValue($row, $criterion['by'], $key);
            }

            $prepared[] = [
                'values' => $values,
                'desc' => $criterion['desc'],
                'options' => $criterion['options'],
            ];
        }

        return $prepared;
    }

    private static function requireArrayKey(mixed $value, string $operation): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            $operation . ' derived key must be an integer or string; ' . get_debug_type($value) . ' given.',
        );
    }

    private static function resolveDerivedValue(mixed $row, string|callable $keyOrCallback, int|string $index): mixed
    {
        if (!is_string($keyOrCallback)) {
            return self::invokeRowCallback($keyOrCallback, $row, $index);
        }

        return is_array($row) && array_key_exists($keyOrCallback, $row) ? $row[$keyOrCallback] : null;
    }

    private static function resolveSortByManyValue(mixed $row, string|callable $by, int|string $key): mixed
    {
        if (!is_string($by)) {
            return self::invokeRowCallback($by, $row, $key);
        }

        return is_array($row) ? ($row[$by] ?? null) : null;
    }

    /**
     * @param array<string, bool> $lookup
     * @param array<array-key, mixed> $values
     */
    private static function rowLookupContains(array $lookup, array $values, mixed $candidate, bool $strict): bool
    {
        if ($strict) {
            return isset($lookup[ArraySingleOps::fingerprint($candidate, true)]);
        }

        if (is_string($candidate) && !is_numeric($candidate)) {
            return isset($lookup['value:' . strlen($candidate) . ':' . $candidate]);
        }

        return in_array($candidate, $values, false);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function selectExtremeRow(array $array, string|callable $keyOrCallback, bool $pickMax): mixed
    {
        $bestRow = null;
        $bestScore = null;
        $found = false;

        foreach ($array as $key => $row) {
            $score = self::extractComparableValue($row, $keyOrCallback, $key);
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
    private static function selectExtremeValue(array $array, string|callable $keyOrCallback, bool $pickMax): float|int|null
    {
        $selected = null;

        foreach ($array as $key => $row) {
            $value = self::extractComparableValue($row, $keyOrCallback, $key);
            if ($value === null) {
                continue;
            }

            if ($selected === null || ($pickMax ? ($value > $selected) : ($value < $selected))) {
                $selected = $value;
            }
        }

        return $selected;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function sortRecursiveWithGuards(
        array $array,
        int $options,
        bool $descending,
        int $currentDepth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
        bool $throwOnTooDeep,
    ): array {
        if (!self::canTraverse($currentDepth, $visitedNodes, $maxDepth, $maxNodes, $throwOnTooDeep)) {
            return $array;
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::sortRecursiveWithGuards(
                    $value,
                    $options,
                    $descending,
                    $currentDepth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $throwOnTooDeep,
                );
            }
        }
        unset($value);

        if (ArraySingle::isAssoc($array)) {
            $descending
                ? krsort($array, $options)
                : ksort($array, $options);
        } else {
            usort(
                $array,
                static fn(mixed $left, mixed $right): int => self::applySortDirection(
                    self::compareSortValues($left, $right, $options),
                    $descending,
                ),
            );
        }

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private static function whereInByScan(
        array $array,
        string $key,
        array $values,
        bool $strict,
        bool $keepMatches,
    ): array {
        $results = [];
        foreach ($array as $index => $row) {
            $matches = is_array($row)
                && array_key_exists($key, $row)
                && in_array($row[$key], $values, $strict);
            if ($matches === $keepMatches) {
                $results[$index] = $row;
            }
        }

        return $results;
    }
}
