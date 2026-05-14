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
        if (!$strict) {
            return array_all($needles, fn($needle) => in_array($needle, $array, false));
        }

        $lookup = self::buildStrictLookup($array);

        return array_all(
            $needles,
            static fn(mixed $needle): bool => isset($lookup[self::fingerprintStrict($needle)]),
        );
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $needles
     */
    public static function containsAny(array $array, array $needles, bool $strict): bool
    {
        if (!$strict) {
            return array_any($needles, fn($needle) => in_array($needle, $array, false));
        }

        $lookup = self::buildStrictLookup($array);

        return array_any(
            $needles,
            static fn(mixed $needle): bool => isset($lookup[self::fingerprintStrict($needle)]),
        );
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function diff(array $array, array $values, bool $strict): array
    {
        if (!$strict) {
            return array_filter($array, static fn(mixed $value): bool => !in_array($value, $values, false));
        }

        $lookup = self::buildStrictLookup($values);

        return array_filter(
            $array,
            static fn(mixed $value): bool => !isset($lookup[self::fingerprintStrict($value)]),
        );
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function duplicates(array $array): array
    {
        $strictLookup = [];
        $strictCounts = [];
        $duplicates = [];

        foreach ($array as $value) {
            $fingerprint = self::fingerprintStrict($value);
            if (!isset($strictLookup[$fingerprint])) {
                $strictLookup[$fingerprint] = $value;
                $strictCounts[$fingerprint] = 1;

                continue;
            }

            $strictCounts[$fingerprint]++;
            if ($strictCounts[$fingerprint] === 2) {
                $duplicates[] = $strictLookup[$fingerprint];
            }
        }

        return $duplicates;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function intersect(array $array, array $values, bool $strict): array
    {
        if (!$strict) {
            return array_filter($array, static fn(mixed $value): bool => in_array($value, $values, false));
        }

        $lookup = self::buildStrictLookup($values);

        return array_filter(
            $array,
            static fn(mixed $value): bool => isset($lookup[self::fingerprintStrict($value)]),
        );
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
        if (count($left) !== count($right)) {
            return false;
        }

        $leftCounts = self::countsByFingerprint($left, $strict);
        $rightCounts = self::countsByFingerprint($right, $strict);
        ksort($leftCounts);
        ksort($rightCounts);

        return $leftCounts === $rightCounts;
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
        if (!$strict) {
            /** @var array<int, mixed> $unique */
            $unique = array_values(array_unique($array, \SORT_REGULAR));

            return $unique;
        }

        $seen = [];
        $result = [];
        foreach ($array as $item) {
            $fingerprint = self::fingerprintStrict($item);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<string, bool>
     */
    private static function buildStrictLookup(array $array): array
    {
        $lookup = [];
        foreach ($array as $value) {
            $lookup[self::fingerprintStrict($value)] = true;
        }

        return $lookup;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<string, int>
     */
    private static function countsByFingerprint(array $array, bool $strict): array
    {
        $counts = [];
        foreach ($array as $value) {
            $fingerprint = $strict ? self::fingerprintStrict($value) : self::fingerprintLoose($value);
            $counts[$fingerprint] = ($counts[$fingerprint] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function fingerprintArray(array $value): string
    {
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = self::fingerprintStrict($key) . '=>' . self::fingerprintStrict($item);
        }

        return implode('|', $parts);
    }

    /**
     * Build a loose-comparison-style fingerprint for set equality checks.
     */
    private static function fingerprintLoose(mixed $value): string
    {
        return match (true) {
            is_int($value), is_float($value), is_bool($value), $value === null => 'numeric:' . (float) $value,
            is_string($value) => is_numeric($value) ? 'numeric:' . (float) $value : 'string:' . $value,
            is_array($value) => 'array:' . self::fingerprintArray($value),
            is_object($value) => 'object-value:' . self::fingerprintArray(get_object_vars($value)),
            is_resource($value) => 'resource:' . get_resource_type($value) . ':' . (int) $value,
            default => 'unknown:' . get_debug_type($value),
        };
    }

    /**
     * Build a strict fingerprint that preserves type distinctions.
     */
    private static function fingerprintStrict(mixed $value): string
    {
        return match (true) {
            $value === null => 'null:',
            is_bool($value) => 'bool:' . ($value ? '1' : '0'),
            is_int($value) => 'int:' . $value,
            is_float($value) => 'float:' . json_encode($value, JSON_PRESERVE_ZERO_FRACTION),
            is_string($value) => 'string:' . $value,
            is_array($value) => 'array:' . self::fingerprintArray($value),
            is_object($value) => 'object:' . $value::class . ':' . spl_object_id($value),
            is_resource($value) => 'resource:' . get_resource_type($value) . ':' . (int) $value,
            default => 'unknown:' . get_debug_type($value),
        };
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
