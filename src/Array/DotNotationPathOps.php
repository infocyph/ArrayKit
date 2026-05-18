<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

final class DotNotationPathOps
{
    /**
     * Access a segment in a target array or object.
     */
    public static function accessSegment(mixed $target, int|string $segment, object $missing): mixed
    {
        if (is_array($target) && ArraySingle::exists($target, $segment)) {
            return $target[$segment];
        }

        if (!is_object($target)) {
            return $missing;
        }

        $property = (string) $segment;
        if (!property_exists($target, $property) && !isset($target->{$property})) {
            return $missing;
        }

        try {
            return $target->{$property};
        } catch (\Error) {
            return $missing;
        }
    }

    /**
     * Normalize a dot-notation segment by replacing escaped values and resolving
     * special values such as '{first}' and '{last}'.
     */
    public static function normalizeSegment(string $segment, mixed $target): int|string
    {
        return match ($segment) {
            '\\*' => '*',
            '\\{first}' => '{first}',
            '{first}' => self::resolveFirst($target) ?? '{first}',
            '\\{last}' => '{last}',
            '{last}' => self::resolveLast($target) ?? '{last}',
            default => self::unescapeSegment($segment),
        };
    }

    /**
     * Retrieve a value from an array using an exact key path.
     */
    public static function segmentExact(mixed $array, string $path, mixed $default): mixed
    {
        $parts = self::splitPath($path);
        foreach ($parts as $part) {
            $resolved = self::unescapeSegment($part);
            if (is_array($array) && ArraySingle::exists($array, $resolved)) {
                $array = $array[$resolved];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Parse a dot path into escaped segments and cache compiled segments.
     *
     * @return array<int, string>
     */
    public static function splitPath(string $path): array
    {
        /** @var array<string, array<int, string>> $cache */
        static $cache = [];
        $maxEntries = 1024;

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        $segments = [];
        $current = '';
        $escaped = false;

        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];

            if ($escaped) {
                $current .= '\\' . $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === '.') {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($escaped) {
            $current .= '\\\\';
        }

        $segments[] = $current;

        if (count($cache) >= $maxEntries) {
            $cache = [];
        }

        $cache[$path] = $segments;

        return $segments;
    }

    /**
     * Traverse a target using dot-notation segments.
     *
     * @param array<int, string> $segments
     * @param callable(mixed): mixed $defaultResolver
     */
    public static function traverseGet(
        mixed $target,
        array $segments,
        mixed $default,
        object $missing,
        callable $defaultResolver,
        int $maxDepth = 0,
        int $maxNodes = 0,
        bool $throwOnTooDeep = false,
        int $currentDepth = 1,
        int &$visitedNodes = 0,
    ): mixed {
        if ($maxDepth > 0 && $currentDepth > $maxDepth) {
            return self::handleTraversalLimit($missing, $throwOnTooDeep, 'Dot path traversal exceeded max depth.');
        }

        $visitedNodes++;
        if ($maxNodes > 0 && $visitedNodes > $maxNodes) {
            return self::handleTraversalLimit($missing, $throwOnTooDeep, 'Dot path traversal exceeded max node count.');
        }

        foreach ($segments as $index => $segment) {
            unset($segments[$index]);

            if ($segment === '*') {
                return self::traverseWildcard(
                    $target,
                    $segments,
                    $default,
                    $missing,
                    $defaultResolver,
                    $maxDepth,
                    $maxNodes,
                    $throwOnTooDeep,
                    $currentDepth,
                    $visitedNodes,
                );
            }

            $normalized = self::normalizeSegment($segment, $target);
            $target = self::accessSegment($target, $normalized, $missing);
            if ($target === $missing) {
                return $missing;
            }
        }

        return $target;
    }

    /**
     * Convert escaped segment markers into literal key text.
     */
    public static function unescapeSegment(string $segment): string
    {
        $unescaped = str_replace(
            ['\\.', '\\\\'],
            ['.', '\\'],
            $segment,
        );

        return str_replace(
            ['\\*', '\\{first}', '\\{last}'],
            ['*', '{first}', '{last}'],
            $unescaped,
        );
    }

    private static function handleTraversalLimit(object $missing, bool $throwOnTooDeep, string $message): mixed
    {
        if ($throwOnTooDeep) {
            throw new \RuntimeException($message);
        }

        return $missing;
    }

    /**
     * Resolve the {first} segment for an array-like target.
     */
    private static function resolveFirst(mixed $target): string|int|null
    {
        if (is_object($target) && method_exists($target, 'all')) {
            $arr = $target->all();
            if (!is_array($arr)) {
                return null;
            }

            return array_key_first($arr);
        }

        if (is_array($target)) {
            return array_key_first($target);
        }

        return '{first}';
    }

    /**
     * Resolves the {last} segment for an array-like target.
     */
    private static function resolveLast(mixed $target): string|int|null
    {
        if (is_object($target) && method_exists($target, 'all')) {
            $arr = $target->all();
            if (!is_array($arr)) {
                return null;
            }

            return array_key_last($arr);
        }

        if (is_array($target)) {
            return array_key_last($target);
        }

        return '{last}';
    }

    /**
     * Traverse a target array/object using dot-notation with wildcard support.
     *
     * @param array<int, string> $segments
     * @param callable(mixed): mixed $defaultResolver
     */
    private static function traverseWildcard(
        mixed $target,
        array $segments,
        mixed $default,
        object $missing,
        callable $defaultResolver,
        int $maxDepth,
        int $maxNodes,
        bool $throwOnTooDeep,
        int $currentDepth,
        int &$visitedNodes,
    ): mixed {
        $target = is_object($target) && method_exists($target, 'all') ? $target->all() : $target;

        if (!is_array($target)) {
            return $missing;
        }

        $result = [];
        foreach ($target as $item) {
            $resolved = self::traverseGet(
                $item,
                $segments,
                $default,
                $missing,
                $defaultResolver,
                $maxDepth,
                $maxNodes,
                $throwOnTooDeep,
                $currentDepth + 1,
                $visitedNodes,
            );
            $result[] = $resolved === $missing ? $defaultResolver($default) : $resolved;
        }
        if (in_array('*', $segments, true)) {
            $result = ArrayMulti::collapse($result);
        }

        return $result;
    }
}
