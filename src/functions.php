<?php

declare(strict_types=1);


if (!function_exists('compare')) {
    /**
     * Compare two values using a specified operator.
     *
     * @param mixed       $retrieved The value to compare
     * @param mixed       $value     The reference value
     * @param string|null $operator  Supported operators:
     *                               '!=', '<>', 'ne', '<', 'lt', '>', 'gt',
     *                               '<=', 'lte', '>=', 'gte', '===', '!=='
     *                               or null/default for '=='.
     * @return bool True if comparison holds, false otherwise
     */
    function compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    {
        return match ($operator) {
            '!=', '<>', 'ne' => $retrieved != $value,
            '<', 'lt'        => $retrieved < $value,
            '>', 'gt'        => $retrieved > $value,
            '<=', 'lte'      => $retrieved <= $value,
            '>=', 'gte'      => $retrieved >= $value,
            '==='            => $retrieved === $value,
            '!=='            => $retrieved !== $value,
            default          => $retrieved == $value,
        };
    }
}

if (!function_exists('isCallable')) {
    /**
     * Determine if the given value is callable (but not a string).
     *
     * @param mixed $value
     * @return bool
     */
    function isCallable(mixed $value): bool
    {
        return !is_string($value) && is_callable($value);
    }
}
