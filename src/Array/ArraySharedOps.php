<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

final class ArraySharedOps
{
    public static function asString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return '';
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function each(array $array, callable $callback): array
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function every(array $array, callable $callback): bool
    {
        return array_all($array, static fn(mixed $value, int|string $key): bool => (bool) $callback($value, $key));
    }

    public static function normalizeArrayKey(mixed $value): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        return self::asString($value);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function partition(array $array, callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $passed[$key] = $value;
            } else {
                $failed[$key] = $value;
            }
        }

        return [$passed, $failed];
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skip(array $array, int $count): array
    {
        return array_slice($array, $count, null, true);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skipUntil(array $array, callable $callback): array
    {
        return self::skipWhile($array, fn($value, $key) => !$callback($value, $key));
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public static function skipWhile(array $array, callable $callback): array
    {
        $result = [];
        $skipping = true;

        foreach ($array as $key => $value) {
            if ($skipping && !$callback($value, $key)) {
                $skipping = false;
            }
            if (!$skipping) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
