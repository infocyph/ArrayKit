<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

final class ArraySingleOps
{
    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $needles
     */
    public static function containsAll(array $array, array $needles, bool $strict): bool
    {
        return ArrayValueSetOps::containsAll($array, $needles, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $needles
     */
    public static function containsAny(array $array, array $needles, bool $strict): bool
    {
        return ArrayValueSetOps::containsAny($array, $needles, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function diff(array $array, array $values, bool $strict): array
    {
        return ArrayValueSetOps::diff($array, $values, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function duplicates(array $array): array
    {
        return ArrayValueSetOps::duplicates($array);
    }

    /**
     * Build a comparable fingerprint for a value.
     */
    public static function fingerprint(mixed $value, bool $strict = true): string
    {
        return ArrayValueSetOps::fingerprint($value, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function intersect(array $array, array $values, bool $strict): array
    {
        return ArrayValueSetOps::intersect($array, $values, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function max(array $array): float|int|null
    {
        return self::selectNumeric($array, pickMax: true);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function maxBy(array $array, callable $callback): mixed
    {
        return self::pickBy($array, $callback, pickMax: true);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function min(array $array): float|int|null
    {
        return self::selectNumeric($array, pickMax: false);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function minBy(array $array, callable $callback): mixed
    {
        return self::pickBy($array, $callback, pickMax: false);
    }

    /**
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     */
    public static function same(array $left, array $right, bool $strict): bool
    {
        return ArrayValueSetOps::same($left, $right, $strict);
    }

    /**
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     * @return array<int, mixed>
     */
    public static function symmetricDiff(array $left, array $right, bool $strict): array
    {
        return array_values([
            ...self::diff($left, $right, $strict),
            ...self::diff($right, $left, $strict),
        ]);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function unique(array $array, bool $strict): array
    {
        return ArrayValueSetOps::unique($array, $strict);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function where(array $array, ?callable $callback): array
    {
        $results = [];

        if ($callback === null) {
            foreach ($array as $key => $value) {
                if ((bool) $value) {
                    $results[$key] = $value;
                }
            }

            return $results;
        }

        foreach ($array as $key => $value) {
            if ((bool) self::invokeValueCallback($callback, $value, $key)) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    private static function invokeValueCallback(callable $callback, mixed $value, int|string $key): mixed
    {
        return $callback($value, $key);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function pickBy(array $array, callable $callback, bool $pickMax): mixed
    {
        $best = null;
        $bestScore = null;
        $found = false;

        foreach ($array as $key => $value) {
            $score = $callback($value, $key);
            if (!is_numeric($score)) {
                continue;
            }

            $numeric = (float) $score;
            if (!$found || ($pickMax ? ($numeric > $bestScore) : ($numeric < $bestScore))) {
                $best = $value;
                $bestScore = $numeric;
                $found = true;
            }
        }

        return $found ? $best : null;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function selectNumeric(array $array, bool $pickMax): float|int|null
    {
        $selected = null;

        foreach ($array as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $numeric = (float) $value;
            if ($selected === null || ($pickMax ? ($numeric > $selected) : ($numeric < $selected))) {
                $selected = $numeric;
            }
        }

        if ($selected === null) {
            return null;
        }

        return fmod($selected, 1.0) === 0.0 ? (int) $selected : $selected;
    }
}
