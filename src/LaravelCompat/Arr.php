<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\LaravelCompat;

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\DotNotation;

final class Arr
{
    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     * @return array<array-key, mixed>
     */
    public static function except(array $array, array|string $keys): array
    {
        return ArraySingle::except($array, $keys);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|int|string|null $key
     */
    public static function get(array $array, array|int|string|null $key = null, mixed $default = null): mixed
    {
        if (is_array($key)) {
            /** @var array<int, int|string> $key */
            $key = array_values($key);
        }

        return DotNotation::get($array, $key, $default);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     */
    public static function has(array $array, array|string $keys): bool
    {
        return DotNotation::has($array, $keys);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     */
    public static function hasAny(array $array, array|string $keys): bool
    {
        return DotNotation::hasAny($array, $keys);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|string $keys
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array|string $keys): array
    {
        return ArraySingle::only($array, $keys);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed>|string|null $key
     */
    public static function set(array &$array, array|string|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        return DotNotation::set($array, $key, $value, $overwrite);
    }
}
