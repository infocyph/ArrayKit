<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

use Stringable;
use UnexpectedValueException;

/**
 * @internal
 */
final class EnvValueResolver
{
    public const string LITERAL_DOLLAR = "\0";

    private function __construct() {}

    public static function protectEscapedDollars(string $value): string
    {
        return preg_replace_callback('/\\\\+\$/', static function (array $matches): string {
            $backslashes = substr($matches[0], 0, -1);
            if (strlen($backslashes) % 2 === 1) {
                return substr($backslashes, 0, -1) . self::LITERAL_DOLLAR;
            }

            return $matches[0];
        }, $value) ?? $value;
    }

    /**
     * @param array<string, string|null> $items
     * @return array<string, string|null>
     */
    public static function resolve(array $items): array
    {
        $resolved = [];
        foreach (array_keys($items) as $name) {
            $resolved[$name] = self::resolveName($name, $items, $resolved, []);
        }

        return array_intersect_key($resolved, $items);
    }

    /**
     * @param array<string, string|null> $items
     * @return array<string, string|null>
     */
    public static function restoreLiteralDollars(array $items): array
    {
        foreach ($items as $name => $value) {
            if ($value !== null) {
                $items[$name] = str_replace(self::LITERAL_DOLLAR, '$', $value);
            }
        }

        return $items;
    }

    private static function assertClosedVariableExpansions(string $value): void
    {
        $length = strlen($value);
        for ($index = 0; $index < $length - 1; $index++) {
            if ($value[$index] !== '$' || $value[$index + 1] !== '{') {
                continue;
            }

            if (self::isEscapedAt($value, $index) || str_contains(substr($value, $index + 2), '}')) {
                continue;
            }

            throw new UnexpectedValueException('Unclosed braces on environment variable expansion.');
        }
    }

    private static function externalValueToString(mixed $value): ?string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    private static function isEscapedAt(string $value, int $index): bool
    {
        $backslashes = 0;
        for ($cursor = $index - 1; $cursor >= 0 && $value[$cursor] === '\\'; $cursor--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }

    private static function lookupExternalVariable(string $name): ?string
    {
        if (array_key_exists($name, $_ENV)) {
            return self::externalValueToString($_ENV[$name]);
        }

        if (array_key_exists($name, $_SERVER) && !str_starts_with($name, 'HTTP_')) {
            return self::externalValueToString($_SERVER[$name]);
        }

        $value = getenv($name);

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, string|null> $items
     * @param array<string, string|null> $resolved
     * @param array<string, true> $resolving
     */
    private static function lookupVariable(string $name, array $items, array &$resolved, array $resolving): ?string
    {
        if (array_key_exists($name, $items)) {
            if (isset($resolving[$name])) {
                throw new UnexpectedValueException("Circular environment variable reference detected for [{$name}].");
            }

            return self::resolveName($name, $items, $resolved, $resolving);
        }

        if (array_key_exists($name, $resolved)) {
            return $resolved[$name];
        }

        return self::lookupExternalVariable($name);
    }

    /**
     * @param array<int|string, string> $matches
     * @param array<string, string|null> $items
     * @param array<string, string|null> $resolved
     * @param array<string, true> $resolving
     */
    private static function resolveMatch(array $matches, array $items, array &$resolved, array $resolving): string
    {
        $slashes = self::stringMatch($matches, 'slashes');
        if (strlen($slashes) % 2 === 1) {
            return substr(self::stringMatch($matches, 0), 1);
        }

        $name = self::stringMatch($matches, 'brace') ?: self::stringMatch($matches, 'plain');
        $defaultOperator = self::stringMatch($matches, 'operator');
        $value = self::lookupVariable($name, $items, $resolved, $resolving);

        if (($value === null || $value === '') && $defaultOperator !== '') {
            $value = self::resolveVariables(self::stringMatch($matches, 'default'), $items, $resolved, $resolving);
            if ($defaultOperator === ':=') {
                $resolved[$name] = $value;
            }
        }

        return $slashes . ($value ?? '');
    }

    /**
     * @param array<string, string|null> $items
     * @param array<string, string|null> $resolved
     * @param array<string, true> $resolving
     */
    private static function resolveName(string $name, array $items, array &$resolved, array $resolving): ?string
    {
        if (array_key_exists($name, $resolved)) {
            return $resolved[$name];
        }

        if (isset($resolving[$name])) {
            throw new UnexpectedValueException("Circular environment variable reference detected for [{$name}].");
        }

        $value = $items[$name] ?? null;
        if ($value === null) {
            return $resolved[$name] = null;
        }

        $resolving[$name] = true;

        return $resolved[$name] = self::resolveVariables($value, $items, $resolved, $resolving);
    }

    /**
     * @param array<string, string|null> $items
     * @param array<string, string|null> $resolved
     * @param array<string, true> $resolving
     */
    private static function resolveVariables(string $value, array $items, array &$resolved, array $resolving): string
    {
        self::assertClosedVariableExpansions($value);

        $value = preg_replace_callback(
            '/(?<!\\\\)(?P<slashes>\\\\*)\$(?:\{(?P<brace>[A-Za-z_][A-Za-z0-9_]*)(?:(?P<operator>:-|:=)(?P<default>[^}]*))?\}|(?P<plain>[A-Za-z_][A-Za-z0-9_]*))/',
            static function (array $matches) use ($items, &$resolved, $resolving): string {
                return self::resolveMatch($matches, $items, $resolved, $resolving);
            },
            $value,
        );

        return str_replace(self::LITERAL_DOLLAR, '$', $value ?? '');
    }

    /**
     * @param array<array-key, mixed> $matches
     * @param array-key $key
     */
    private static function stringMatch(array $matches, int|string $key): string
    {
        return isset($matches[$key]) && is_string($matches[$key]) ? $matches[$key] : '';
    }
}
