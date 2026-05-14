<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use Infocyph\ArrayKit\Array\Concerns\DotNotationPublicApiTrait;

class DotNotation
{
    use DotNotationPublicApiTrait;

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $result
     */
    private static function flattenInto(array $array, string $prepend, array &$result): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                self::flattenInto($value, $prepend . $key . '.', $result);

                continue;
            }

            $result[$prepend . $key] = $value;
        }
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, string> $segments
     */
    private static function forgetBySegments(array &$array, array $segments): void
    {
        if ($segments === []) {
            return;
        }

        $segment = self::shiftSegment($segments);
        if ($segment === null) {
            return;
        }

        if ($segment === '*') {
            if ($segments !== []) {
                self::forgetEach($array, $segments);
            }

            return;
        }

        $normalized = self::unescapeSegment($segment);
        if ($segments !== [] && ArraySingle::exists($array, $normalized) && is_array($array[$normalized])) {
            self::forgetBySegments($array[$normalized], $segments);

            return;
        }

        BaseArrayHelper::forget($array, $normalized);
    }

    /**
     * Recursively apply the forget logic to each element in an array.
     *
     * @param array<array-key, mixed> $array
     * @param array<int, string> $segments
     */
    private static function forgetEach(array &$array, array $segments): void
    {
        foreach ($array as &$inner) {
            if (is_array($inner)) {
                self::forgetBySegments($inner, $segments);
            }
        }
    }

    /**
     * Retrieve a value from the array using dot notation.
     */
    private static function getValue(mixed $target, int|string $key, mixed $default): mixed
    {
        if (is_array($target) && (is_int($key) || ArraySingle::exists($target, $key))) {
            return $target[$key];
        }

        $keyPath = (string) $key;
        if (!str_contains($keyPath, '.') && !str_contains($keyPath, '\\')) {
            return self::value($default);
        }

        $missing = self::missing();
        $resolved = self::traverseGet($target, self::splitPath($keyPath), $default, $missing);

        return $resolved === $missing ? self::value($default) : $resolved;
    }

    /**
     * Sets values in the target using dot-notation with wildcard support.
     *
     * @param array<int, string> $segments
     */
    private static function handleWildcardSet(mixed &$target, array $segments, mixed $value, bool $overwrite): void
    {
        if (!is_array($target)) {
            $target = [];
        }
        if (!empty($segments)) {
            foreach ($target as &$inner) {
                self::setValueBySegments($inner, $segments, $value, $overwrite);
            }
        } elseif ($overwrite) {
            foreach ($target as &$inner) {
                $inner = $value;
            }
        }
    }

    /**
     * Get a stable sentinel that represents a missing key path.
     */
    private static function missing(): object
    {
        static $missing;

        if (!is_object($missing)) {
            $missing = new \stdClass();
        }

        return $missing;
    }

    /**
     * Retrieve a value from an array using an exact key path.
     */
    private static function segmentExact(mixed $array, string $path, mixed $default): mixed
    {
        return DotNotationPathOps::segmentExact($array, $path, $default);
    }

    /**
     * Sets a value in the target array/object using dot notation.
     */
    private static function setValue(mixed &$target, string $key, mixed $value, bool $overwrite): void
    {
        self::setValueBySegments($target, self::splitPath($key), $value, $overwrite);
    }

    /**
     * Sets a value in the target array using dot-notation segments.
     *
     * @param array<array-key, mixed> &$target
     * @param array<int, string> $segments
     */
    private static function setValueArray(array &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        $segment = self::unescapeSegment($segment);

        if (!empty($segments)) {
            if (!ArraySingle::exists($target, $segment)) {
                $target[$segment] = [];
            }
            self::setValueBySegments($target[$segment], $segments, $value, $overwrite);
        } else {
            if ($overwrite || !ArraySingle::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        }
    }

    /**
     * @param array<int, string> $segments
     */
    private static function setValueBySegments(mixed &$target, array $segments, mixed $value, bool $overwrite): void
    {
        if ($segments === []) {
            return;
        }

        $first = self::shiftSegment($segments);
        if ($first === null) {
            return;
        }

        if ($first === '*') {
            self::handleWildcardSet($target, $segments, $value, $overwrite);

            return;
        }

        if (is_array($target)) {
            self::setValueArray($target, $first, $segments, $value, $overwrite);
        } elseif (is_object($target)) {
            self::setValueObject($target, $first, $segments, $value, $overwrite);
        } else {
            self::setValueFallback($target, $first, $segments, $value, $overwrite);
        }
    }

    /**
     * Sets a value in a target that is not an array or object.
     *
     * @param array<int, string> $segments
     */
    private static function setValueFallback(mixed &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        $segment = self::unescapeSegment($segment);
        $target = [];
        if (!empty($segments)) {
            self::setValueBySegments($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }

    /**
     * Sets a value in an object using dot-notation segments.
     *
     * @param array<int, string> $segments
     */
    private static function setValueObject(object &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        $segment = self::unescapeSegment($segment);

        if (!empty($segments)) {
            if (!isset($target->{$segment})) {
                $target->{$segment} = [];
            }
            self::setValueBySegments($target->{$segment}, $segments, $value, $overwrite);
        } else {
            if ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        }
    }

    /**
     * @param array<int, string> $segments
     */
    private static function shiftSegment(array &$segments): ?string
    {
        if ($segments === []) {
            return null;
        }

        $segment = $segments[0] ?? null;
        array_shift($segments);

        return is_string($segment) ? $segment : null;
    }

    /**
     * Parse a dot path into escaped segments and cache compiled segments.
     *
     * @return array<int, string>
     */
    private static function splitPath(string $path): array
    {
        return DotNotationPathOps::splitPath($path);
    }

    /**
     * Traverses the target array/object to retrieve a value using dot notation.
     *
     * @param array<int, string> $segments
     */
    private static function traverseGet(mixed $target, array $segments, mixed $default, object $missing): mixed
    {
        return DotNotationPathOps::traverseGet(
            $target,
            $segments,
            $default,
            $missing,
            static fn(mixed $value): mixed => self::value($value),
        );
    }

    /**
     * Convert escaped segment markers into literal key text.
     */
    private static function unescapeSegment(string $segment): string
    {
        return DotNotationPathOps::unescapeSegment($segment);
    }

    /**
     * Returns the given value if it's not a callable, otherwise calls it and returns the result.
     */
    private static function value(mixed $val): mixed
    {
        return $val instanceof \Closure ? $val() : $val;
    }
}
