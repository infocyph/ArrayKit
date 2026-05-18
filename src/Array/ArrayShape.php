<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use InvalidArgumentException;

final class ArrayShape
{
    /**
     * Validate a row against a simple shape definition.
     *
     * @param array<array-key, mixed> $row
     * @param array<string, string> $shape
     * @return array<array-key, mixed>
     */
    public static function require(array $row, array $shape): array
    {
        foreach ($shape as $key => $type) {
            $optional = str_ends_with($key, '?');
            $resolvedKey = $optional ? substr($key, 0, -1) : $key;
            if ($resolvedKey === '') {
                throw new InvalidArgumentException('Shape keys must not be empty.');
            }

            if (!array_key_exists($resolvedKey, $row)) {
                if ($optional) {
                    continue;
                }

                throw new InvalidArgumentException("Missing required key [{$resolvedKey}].");
            }

            $value = $row[$resolvedKey];
            if (!self::matchesType($value, $type)) {
                $actual = get_debug_type($value);

                throw new InvalidArgumentException("Key [{$resolvedKey}] expected type [{$type}], got [{$actual}].");
            }
        }

        return $row;
    }

    private static function matchesType(mixed $value, string $type): bool
    {
        $normalized = strtolower(trim($type));
        if ($normalized === 'mixed') {
            return true;
        }

        if (str_starts_with($normalized, 'list<') && str_ends_with($normalized, '>')) {
            if (!is_array($value) || !array_is_list($value)) {
                return false;
            }

            $inner = substr($normalized, 5, -1);

            return array_all($value, fn($item) => self::matchesType($item, $inner));
        }

        return match ($normalized) {
            'int', 'integer' => is_int($value),
            'string' => is_string($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'list' => is_array($value) && array_is_list($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            'null' => $value === null,
            default => false,
        };
    }
}
