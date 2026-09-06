<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

final class Environment
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function all(bool $includeHttpServerValues = false): array
    {
        $server = array_filter(
            $_SERVER,
            fn($key) => is_string($key)
                && ($includeHttpServerValues || !str_starts_with($key, 'HTTP_')),
            ARRAY_FILTER_USE_KEY,
        );

        $env = array_filter($_ENV, is_string(...), ARRAY_FILTER_USE_KEY);
        $process = getenv();

        return $env + $server + $process;
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::all();
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER) && !str_starts_with($key, 'HTTP_')) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function has(string $key): bool
    {
        if (array_key_exists($key, $_ENV)) {
            return true;
        }

        if (array_key_exists($key, $_SERVER) && !str_starts_with($key, 'HTTP_')) {
            return true;
        }

        return getenv($key) !== false;
    }

    public static function ref(string $key, mixed $default = null): EnvReference
    {
        return new EnvReference($key, $default);
    }
}
