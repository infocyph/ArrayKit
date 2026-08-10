<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

/**
 * @internal
 */
final class ArrayValueSetOps
{
    private const int XXH128_MIN_FINGERPRINT_BYTES = 64;

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $needles
     */
    public static function containsAll(array $array, array $needles, bool $strict): bool
    {
        if (!$strict) {
            return array_all($needles, static fn(mixed $needle): bool => in_array($needle, $array, false));
        }

        $lookup = self::buildStrictLookup($array);
        if ($lookup === null) {
            return array_all($needles, static fn(mixed $needle): bool => in_array($needle, $array, true));
        }

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
            return array_any($needles, static fn(mixed $needle): bool => in_array($needle, $array, false));
        }

        $lookup = self::buildStrictLookup($array);
        if ($lookup === null) {
            return array_any($needles, static fn(mixed $needle): bool => in_array($needle, $array, true));
        }

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
        return self::filterByMembership($array, $values, $strict, false);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function duplicates(array $array): array
    {
        if (!self::allStrictHashable($array)) {
            return self::duplicatesByScan($array);
        }

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

    public static function fingerprint(mixed $value, bool $strict = true): string
    {
        return $strict
            ? self::fingerprintStrict($value)
            : self::fingerprintLoose($value);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function intersect(array $array, array $values, bool $strict): array
    {
        return self::filterByMembership($array, $values, $strict, true);
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

        if (!$strict || !self::allStrictHashable($left) || !self::allStrictHashable($right)) {
            return self::sameByScan($left, $right, $strict);
        }

        $leftCounts = self::countsByFingerprint($left);
        $rightCounts = self::countsByFingerprint($right);
        ksort($leftCounts);
        ksort($rightCounts);

        return $leftCounts === $rightCounts;
    }

    /**
     * Track strict membership with canonical fingerprints and a safe scan fallback.
     *
     * Long fingerprints use verified digest buckets, so a digest collision cannot
     * change equality. Values such as NaN that are not reflexive use PHP's strict
     * comparison semantics in the fallback bucket.
     *
     * @param array<string, true> $seen
     * @param array<string, string|list<string>> $digestBuckets
     * @param array<int, mixed> $fallback
     */
    public static function strictValueAlreadySeen(
        mixed $value,
        array &$seen,
        array &$digestBuckets,
        array &$fallback,
    ): bool {
        if (!self::isStrictHashable($value)) {
            if (in_array($value, $fallback, true)) {
                return true;
            }

            $fallback[] = $value;

            return false;
        }

        return self::fingerprintAlreadySeen(
            $seen,
            $digestBuckets,
            self::fingerprintStrict($value),
        );
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function unique(array $array, bool $strict): array
    {
        if (!$strict) {
            /** @var array<array-key, mixed> $unique */
            $unique = array_unique($array, \SORT_REGULAR);

            return $unique;
        }

        $seen = [];
        $digestBuckets = [];
        $fallback = [];
        $result = [];
        foreach ($array as $key => $item) {
            if (self::strictValueAlreadySeen($item, $seen, $digestBuckets, $fallback)) {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function allStrictHashable(array $array): bool
    {
        return array_all($array, fn($value) => self::isStrictHashable($value));
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<string, bool>|null
     */
    private static function buildStrictLookup(array $array): ?array
    {
        $lookup = [];
        foreach ($array as $value) {
            if (!self::isStrictHashable($value)) {
                return null;
            }

            $lookup[self::fingerprintStrict($value)] = true;
        }

        return $lookup;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<string, int>
     */
    private static function countsByFingerprint(array $array): array
    {
        $counts = [];
        foreach ($array as $value) {
            $fingerprint = self::fingerprintStrict($value);
            $counts[$fingerprint] = ($counts[$fingerprint] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private static function duplicatesByScan(array $array): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($array as $value) {
            if (!in_array($value, $seen, true)) {
                $seen[] = $value;

                continue;
            }

            if (!in_array($value, $duplicates, true)) {
                $duplicates[] = $value;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private static function filterByMembership(array $array, array $values, bool $strict, bool $keepMatches): array
    {
        $lookup = $strict ? self::buildStrictLookup($values) : null;
        $results = [];

        foreach ($array as $key => $value) {
            $matches = $lookup === null
                ? in_array($value, $values, $strict)
                : isset($lookup[self::fingerprintStrict($value)]);

            if ($matches === $keepMatches) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Use XXH128 only for long canonical keys, retaining canonical values in
     * each digest bucket so a hash collision can never change equality. A PHP
     * 8.4 CLI microbenchmark (100 runs of 1,000 nested 128-byte values) measured
     * 110.8 ms with verified digest buckets versus 138.6 ms with canonical keys.
     * The threshold avoids the measured hashing regression for short scalars.
     *
     * @param array<string, true> $seen
     * @param array<string, string|list<string>> $digestBuckets
     */
    private static function fingerprintAlreadySeen(array &$seen, array &$digestBuckets, string $fingerprint): bool
    {
        if (strlen($fingerprint) < self::XXH128_MIN_FINGERPRINT_BYTES) {
            if (isset($seen[$fingerprint])) {
                return true;
            }

            $seen[$fingerprint] = true;

            return false;
        }

        $digest = "\0" . hash('xxh128', $fingerprint, true);
        if (!isset($digestBuckets[$digest])) {
            $digestBuckets[$digest] = $fingerprint;

            return false;
        }

        $bucket = $digestBuckets[$digest];
        if (is_string($bucket)) {
            if ($bucket === $fingerprint) {
                return true;
            }

            $digestBuckets[$digest] = [$bucket, $fingerprint];

            return false;
        }

        if (in_array($fingerprint, $bucket, true)) {
            return true;
        }

        $bucket[] = $fingerprint;
        $digestBuckets[$digest] = $bucket;

        return false;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function fingerprintArrayLoose(array $value): string
    {
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = self::fingerprintLoose($key) . '=>' . self::fingerprintLoose($item);
        }

        return implode('|', $parts);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function fingerprintArrayStrict(array $value): string
    {
        $parts = [];
        foreach ($value as $key => $item) {
            $keyFingerprint = self::fingerprintStrict($key);
            $valueFingerprint = self::fingerprintStrict($item);
            $parts[] = strlen($keyFingerprint) . ':' . $keyFingerprint
                . strlen($valueFingerprint) . ':' . $valueFingerprint;
        }

        return count($value) . ':' . implode('', $parts);
    }

    private static function fingerprintFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'float:nan';
        }

        if ($value === \INF) {
            return 'float:inf';
        }

        if ($value === -\INF) {
            return 'float:-inf';
        }

        return $value == 0.0
            ? 'float:zero'
            : 'float:' . bin2hex(pack('E', $value));
    }

    private static function fingerprintLoose(mixed $value): string
    {
        return match (true) {
            is_int($value), is_float($value), is_bool($value), $value === null => 'numeric:' . (float) $value,
            is_string($value) => is_numeric($value) ? 'numeric:' . (float) $value : 'string:' . $value,
            is_array($value) => 'array:' . self::fingerprintArrayLoose($value),
            is_object($value) => 'object-value:' . self::fingerprintArrayLoose(get_object_vars($value)),
            is_resource($value) => 'resource:' . get_resource_type($value) . ':' . (int) $value,
            default => 'unknown:' . get_debug_type($value),
        };
    }

    private static function fingerprintStrict(mixed $value): string
    {
        return match (true) {
            $value === null => 'null:',
            is_bool($value) => 'bool:' . ($value ? '1' : '0'),
            is_int($value) => 'int:' . $value,
            is_float($value) => self::fingerprintFloat($value),
            is_string($value) => 'string:' . strlen($value) . ':' . $value,
            is_array($value) => 'array:' . self::fingerprintArrayStrict($value),
            is_object($value) => 'object:' . $value::class . ':' . spl_object_id($value),
            is_resource($value) => 'resource:' . get_resource_type($value) . ':' . (int) $value,
            default => 'unknown:' . get_debug_type($value),
        };
    }

    private static function isStrictHashable(mixed $value): bool
    {
        if (is_float($value)) {
            return !is_nan($value);
        }

        if (!is_array($value)) {
            return true;
        }

        return self::allStrictHashable($value);
    }

    /**
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     */
    private static function sameByScan(array $left, array $right, bool $strict): bool
    {
        $unmatched = array_values($right);

        foreach ($left as $leftValue) {
            $matchedKey = array_find_key($unmatched, fn($rightValue) => $strict ? $leftValue === $rightValue : $leftValue == $rightValue);
            if ($matchedKey === null) {
                return false;
            }

            unset($unmatched[$matchedKey]);
        }

        return true;
    }
}
