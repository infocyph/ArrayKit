<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array\Concerns;

use Infocyph\ArrayKit\Array\ArraySingle;
use InvalidArgumentException;

trait DotNotationPublicApiTrait
{
    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function all(array $array): array
    {
        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function arrayValue(array $array, string $key, mixed $default = null): array
    {
        $value = self::get($array, $key, $default);
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expected array, got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function boolean(array $array, string $key, mixed $default = null): bool
    {
        $value = self::get($array, $key, $default);
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Expected bool, got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function expand(array $array): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            self::set($results, $key, $value);
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed>|string $keys
     */
    public static function fill(array &$array, array|string $keys, mixed $value = null): void
    {
        self::set($array, $keys, $value, false);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function flatten(array $array, string $prepend = ''): array
    {
        $results = [];
        self::flattenInto($array, $prepend, $results);

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function float(array $array, string $key, mixed $default = null): float
    {
        $value = self::get($array, $key, $default);
        if (!is_float($value)) {
            throw new InvalidArgumentException('Expected float, got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $target
     * @param array<int, int|string>|string|int|null $keys
     */
    public static function forget(array &$target, array|string|int|null $keys): void
    {
        if ($keys === null || $keys === []) {
            return;
        }

        if (is_array($keys)) {
            foreach ($keys as $path) {
                self::forget($target, $path);
            }

            return;
        }

        self::forgetBySegments($target, self::splitPath((string) $keys));
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|int|string|null $keys
     */
    public static function get(array $array, array|int|string|null $keys = null, mixed $default = null): mixed
    {
        if ($keys === null) {
            return $array;
        }

        if (is_array($keys)) {
            $results = [];
            foreach ($keys as $k) {
                $resolvedKey = (string) $k;
                $results[$resolvedKey] = self::getValue($array, $resolvedKey, $default);
            }

            return $results;
        }

        return self::getValue($array, $keys, $default);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     */
    public static function has(array $array, array|string $keys): bool
    {
        if (empty($array) || empty($keys)) {
            return false;
        }

        if (is_string($keys) && ArraySingle::exists($array, $keys)) {
            return true;
        }

        $keys = (array) $keys;
        $missing = self::missing();
        foreach ($keys as $key) {
            $resolvedKey = (string) $key;
            if (ArraySingle::exists($array, $resolvedKey)) {
                continue;
            }
            if (self::segmentExact($array, $resolvedKey, $missing) === $missing) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     */
    public static function hasAny(array $array, array|string $keys): bool
    {
        if (empty($array) || empty($keys)) {
            return false;
        }

        $keys = (array) $keys;

        return array_any($keys, static fn(int|string $key): bool => self::has($array, (string) $key));
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function integer(array $array, string $key, mixed $default = null): int
    {
        $value = self::get($array, $key, $default);
        if (!is_int($value)) {
            throw new InvalidArgumentException('Expected int, got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function offsetExists(array $array, string $key): bool
    {
        return self::has($array, $key);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function offsetGet(array $array, string $key): mixed
    {
        return self::get($array, $key);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function offsetSet(array &$array, string $key, mixed $value): void
    {
        self::set($array, $key, $value);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function offsetUnset(array &$array, string $key): void
    {
        self::forget($array, $key);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     * @return array<array-key, mixed>
     */
    public static function pluck(array $array, array|string $keys, mixed $default = null): array
    {
        $keys = (array) $keys;
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = self::get($array, $key, $default);
        }

        return $results;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed>|string|null $keys
     */
    public static function set(array &$array, array|string|null $keys = null, mixed $value = null, bool $overwrite = true): bool
    {
        if ($keys === null) {
            $array = (array) $value;

            return true;
        }

        if (is_array($keys)) {
            foreach ($keys as $k => $val) {
                $working = $array;
                self::setValue($working, (string) $k, $val, $overwrite);
                if (is_array($working)) {
                    $array = $working;
                }
            }
        } else {
            $working = $array;
            self::setValue($working, $keys, $value, $overwrite);
            if (is_array($working)) {
                $array = $working;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function string(array $array, string $key, mixed $default = null): string
    {
        $value = self::get($array, $key, $default);
        if (!is_string($value)) {
            throw new InvalidArgumentException('Expected string, got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function tap(array $array, callable $callback): array
    {
        $callback($array);

        return $array;
    }
}
