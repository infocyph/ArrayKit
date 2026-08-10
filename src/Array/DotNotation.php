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
            if (is_array($value) && $value !== []) {
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
    private static function forgetBySegments(array &$array, array $segments, int $position = 0): void
    {
        $segmentCount = count($segments);
        if ($position >= $segmentCount) {
            return;
        }

        $segment = $segments[$position];
        $next = $position + 1;

        if ($segment === '*') {
            if ($next < $segmentCount) {
                self::forgetEach($array, $segments, $next);
            }

            return;
        }

        $normalized = self::unescapeSegment($segment);
        if ($next < $segmentCount && ArraySingle::exists($array, $normalized) && is_array($array[$normalized])) {
            self::forgetBySegments($array[$normalized], $segments, $next);

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
    private static function forgetEach(array &$array, array $segments, int $position): void
    {
        foreach ($array as &$inner) {
            if (is_array($inner)) {
                self::forgetBySegments($inner, $segments, $position);
            }
        }
    }

    /**
     * Retrieve a value from the array using dot notation.
     */
    private static function getValue(mixed $target, int|string $key, mixed $default): mixed
    {
        return self::resolveValue($target, $key, $default);
    }

    private static function getValueSafe(
        mixed $target,
        int|string $key,
        mixed $default,
        int $maxDepth,
        int $maxNodes,
        bool $throwOnTooDeep,
    ): mixed {
        return self::resolveValue($target, $key, $default, $maxDepth, $maxNodes, $throwOnTooDeep);
    }

    /**
     * Sets values in the target using dot-notation with wildcard support.
     *
     * @param array<array-key, mixed> $target
     * @param array<int, string> $segments
     */
    private static function handleWildcardSet(
        array &$target,
        array $segments,
        int $position,
        mixed $value,
        bool $overwrite,
    ): void {
        if ($position < count($segments)) {
            foreach ($target as &$inner) {
                self::setValueBySegments($inner, $segments, $position, $value, $overwrite);
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

    private static function resolveValue(
        mixed $target,
        int|string $key,
        mixed $default,
        ?int $maxDepth = null,
        ?int $maxNodes = null,
        bool $throwOnTooDeep = false,
    ): mixed {
        if (is_array($target) && ArraySingle::exists($target, $key)) {
            return $target[$key];
        }

        $keyPath = (string) $key;
        if (!str_contains($keyPath, '.') && !str_contains($keyPath, '\\')) {
            return self::value($default);
        }

        $missing = self::missing();
        $resolved = ($maxDepth === null || $maxNodes === null)
            ? self::traverseGet($target, self::splitPath($keyPath), $default, $missing)
            : self::traverseGetWithLimits(
                $target,
                self::splitPath($keyPath),
                $default,
                $missing,
                $maxDepth,
                $maxNodes,
                $throwOnTooDeep,
            );

        return $resolved === $missing ? self::value($default) : $resolved;
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
     *
     * @param array<array-key, mixed> $target
     */
    private static function setValue(array &$target, string $key, mixed $value, bool $overwrite): void
    {
        $segments = self::splitPath($key);
        $segment = $segments[0];

        if ($segment === '*') {
            self::handleWildcardSet($target, $segments, 1, $value, $overwrite);

            return;
        }

        self::setValueArray($target, $segment, $segments, 1, $value, $overwrite);
    }

    /**
     * Sets a value in the target array using dot-notation segments.
     *
     * @param array<array-key, mixed> &$target
     * @param array<int, string> $segments
     */
    private static function setValueArray(
        array &$target,
        string $segment,
        array $segments,
        int $position,
        mixed $value,
        bool $overwrite,
    ): void {
        $segment = self::unescapeSegment($segment);

        if ($position < count($segments)) {
            if (!ArraySingle::exists($target, $segment)) {
                $target[$segment] = [];
            }

            self::setValueBySegments($target[$segment], $segments, $position, $value, $overwrite);
        } else {
            if ($overwrite || !ArraySingle::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        }
    }

    /**
     * @param array<int, string> $segments
     */
    private static function setValueBySegments(
        mixed &$target,
        array $segments,
        int $position,
        mixed $value,
        bool $overwrite,
    ): void {
        if ($position >= count($segments)) {
            return;
        }

        $segment = $segments[$position];
        $next = $position + 1;

        if ($segment === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            self::handleWildcardSet($target, $segments, $next, $value, $overwrite);

            return;
        }

        if (is_array($target)) {
            self::setValueArray($target, $segment, $segments, $next, $value, $overwrite);
        } elseif (is_object($target)) {
            self::setValueObject($target, $segment, $segments, $next, $value, $overwrite);
        } else {
            self::setValueFallback($target, $segment, $segments, $next, $value, $overwrite);
        }
    }

    /**
     * Sets a value in a target that is not an array or object.
     *
     * @param array<int, string> $segments
     */
    private static function setValueFallback(
        mixed &$target,
        string $segment,
        array $segments,
        int $position,
        mixed $value,
        bool $overwrite,
    ): void {
        $segment = self::unescapeSegment($segment);
        $target = [];
        if ($position < count($segments)) {
            self::setValueBySegments($target[$segment], $segments, $position, $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }

    /**
     * Sets a value in an object using dot-notation segments.
     *
     * @param array<int, string> $segments
     */
    private static function setValueObject(
        object &$target,
        string $segment,
        array $segments,
        int $position,
        mixed $value,
        bool $overwrite,
    ): void {
        $segment = self::unescapeSegment($segment);
        $propertyExists = property_exists($target, $segment);

        if ($position < count($segments)) {
            if (!$propertyExists) {
                $target->{$segment} = [];
            }

            self::setValueBySegments($target->{$segment}, $segments, $position, $value, $overwrite);
        } else {
            if ($overwrite || !$propertyExists) {
                $target->{$segment} = $value;
            }
        }
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
     * @param array<int, string> $segments
     */
    private static function traverseGetWithLimits(
        mixed $target,
        array $segments,
        mixed $default,
        object $missing,
        int $maxDepth,
        int $maxNodes,
        bool $throwOnTooDeep,
    ): mixed {
        $visitedNodes = 0;

        return DotNotationPathOps::traverseGet(
            $target,
            $segments,
            $default,
            $missing,
            static fn(mixed $value): mixed => self::value($value),
            $maxDepth,
            $maxNodes,
            $throwOnTooDeep,
            1,
            $visitedNodes,
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
