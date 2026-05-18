<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit;

use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\Pipeline;

if (!function_exists(__NAMESPACE__ . '\\compare')) {
    function compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    {
        return match ($operator) {
            '!=', '<>', 'ne' => $retrieved != $value,
            '<', 'lt' => $retrieved < $value,
            '>', 'gt' => $retrieved > $value,
            '<=', 'lte' => $retrieved <= $value,
            '>=', 'gte' => $retrieved >= $value,
            '===' => $retrieved === $value,
            '!==' => $retrieved !== $value,
            default => $retrieved == $value,
        };
    }
}

if (!function_exists(__NAMESPACE__ . '\\array_get')) {
    /**
     * @param array<array-key, mixed> $array
     * @param int|string|array<int, int|string>|null $key
     */
    function array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed
    {
        return DotNotation::get($array, $key, $default);
    }
}

if (!function_exists(__NAMESPACE__ . '\\array_set')) {
    /**
     * @param array<array-key, mixed> $array
     * @param string|array<int|string, mixed>|null $key
     */
    function array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool
    {
        return DotNotation::set($array, $key, $value, $overwrite);
    }
}

if (!function_exists(__NAMESPACE__ . '\\collect')) {
    function collect(mixed $data = []): Collection
    {
        return Collection::make($data);
    }
}

if (!function_exists(__NAMESPACE__ . '\\chain')) {
    function chain(mixed $data): Pipeline
    {
        return Collection::make($data)->process();
    }
}
