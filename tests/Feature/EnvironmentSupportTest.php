<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Support\Environment;

it('includes process-only environment values in all()', function () {
    $key = 'ARRAYKIT_PROCESS_ONLY_' . strtoupper(bin2hex(random_bytes(4)));
    $previous = getenv($key);
    $envExists = array_key_exists($key, $_ENV);
    $envValue = $_ENV[$key] ?? null;
    $serverExists = array_key_exists($key, $_SERVER);
    $serverValue = $_SERVER[$key] ?? null;

    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key . '=process-value');

    try {
        expect(Environment::all()[$key] ?? null)->toBe('process-value')
            ->and(Environment::get($key))->toBe('process-value')
            ->and(Environment::has($key))->toBeTrue();
    } finally {
        if ($envExists) {
            $_ENV[$key] = $envValue;
        } else {
            unset($_ENV[$key]);
        }

        if ($serverExists) {
            $_SERVER[$key] = $serverValue;
        } else {
            unset($_SERVER[$key]);
        }

        putenv($previous === false ? $key : $key . '=' . $previous);
    }
});

it('keeps environment source precedence consistent across get and all', function () {
    $key = 'ARRAYKIT_ENV_PRECEDENCE_' . strtoupper(bin2hex(random_bytes(4)));
    $previous = getenv($key);
    $envExists = array_key_exists($key, $_ENV);
    $envValue = $_ENV[$key] ?? null;
    $serverExists = array_key_exists($key, $_SERVER);
    $serverValue = $_SERVER[$key] ?? null;

    putenv($key . '=process');
    $_SERVER[$key] = 'server';
    $_ENV[$key] = 'env';

    try {
        expect(Environment::get($key))->toBe('env')
            ->and(Environment::all()[$key] ?? null)->toBe('env');

        unset($_ENV[$key]);
        expect(Environment::get($key))->toBe('server')
            ->and(Environment::all()[$key] ?? null)->toBe('server');

        unset($_SERVER[$key]);
        expect(Environment::get($key))->toBe('process')
            ->and(Environment::all()[$key] ?? null)->toBe('process');
    } finally {
        if ($envExists) {
            $_ENV[$key] = $envValue;
        } else {
            unset($_ENV[$key]);
        }

        if ($serverExists) {
            $_SERVER[$key] = $serverValue;
        } else {
            unset($_SERVER[$key]);
        }

        putenv($previous === false ? $key : $key . '=' . $previous);
    }
});
