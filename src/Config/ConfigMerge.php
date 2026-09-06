<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use InvalidArgumentException;

final class ConfigMerge
{
    private function __construct() {}

    public static function isMap(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value);
    }

    /**
     * Recursively overlay configuration maps while replacing list values atomically.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $overlay
     * @return array<array-key, mixed>
     */
    public static function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (
                array_key_exists($key, $base)
                && self::isMap($base[$key])
                && self::isMap($value)
            ) {
                /** @var array<array-key, mixed> $baseValue */
                $baseValue = $base[$key];
                /** @var array<array-key, mixed> $overlayValue */
                $overlayValue = $value;
                $base[$key] = self::merge($baseValue, $overlayValue);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param iterable<array<array-key, mixed>> $layers
     * @return array<array-key, mixed>
     */
    public static function mergeMany(iterable $layers): array
    {
        $merged = [];

        foreach ($layers as $layer) {
            if (!is_array($layer)) {
                throw new InvalidArgumentException('Configuration merge layers must be arrays.');
            }

            $merged = self::merge($merged, $layer);
        }

        return $merged;
    }
}
