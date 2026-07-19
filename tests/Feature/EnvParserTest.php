<?php

declare(strict_types=1);

use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\EnvParser;

use function Infocyph\ArrayKit\dotenv;
use function Infocyph\ArrayKit\env;

it('parses common dotenv entries', function () {
    $items = EnvParser::parse(<<<'ENV'
APP_NAME=ArrayKit
APP_ENV=local # inline comment
APP_KEY=base64:abc#not-comment
EMPTY=
OPTIONAL
export CACHE_DRIVER=file
# ignored
ENV);

    expect($items)->toBe([
        'APP_NAME' => 'ArrayKit',
        'APP_ENV' => 'local',
        'APP_KEY' => 'base64:abc#not-comment',
        'EMPTY' => '',
        'OPTIONAL' => null,
        'CACHE_DRIVER' => 'file',
    ]);
});

it('parses quoted dotenv values', function () {
    $items = EnvParser::parse(<<<'ENV'
SINGLE='value # kept'
DOUBLE="line\nnext\tTabbed"
TRAILING="value" # allowed
MULTILINE="first
second"
ENV);

    expect($items)->toBe([
        'SINGLE' => 'value # kept',
        'DOUBLE' => "line\nnext\tTabbed",
        'TRAILING' => 'value',
        'MULTILINE' => "first\nsecond",
    ]);
});

it('expands dotenv variable references', function () {
    $_ENV['ARRAYKIT_EXTERNAL'] = 'external';

    try {
        $items = EnvParser::parse(<<<'ENV'
APP_NAME=ArrayKit
APP_URL="https://${APP_NAME}.test"
FROM_ENV=$ARRAYKIT_EXTERNAL
DEFAULT=${MISSING:-fallback}
ASSIGNED=${LATER:=created}
USES_ASSIGNED=$LATER
LITERAL_SINGLE='$APP_NAME'
LITERAL_DOUBLE="\$APP_NAME"
ENV);

        expect($items)->toBe([
            'APP_NAME' => 'ArrayKit',
            'APP_URL' => 'https://ArrayKit.test',
            'FROM_ENV' => 'external',
            'DEFAULT' => 'fallback',
            'ASSIGNED' => 'created',
            'USES_ASSIGNED' => 'created',
            'LITERAL_SINGLE' => '$APP_NAME',
            'LITERAL_DOUBLE' => '$APP_NAME',
        ]);
    } finally {
        unset($_ENV['ARRAYKIT_EXTERNAL']);
    }
});

it('can parse raw dotenv values without variable expansion', function () {
    $items = EnvParser::parseRaw(<<<'ENV'
APP_NAME=ArrayKit
APP_URL="https://${APP_NAME}.test"
LITERAL="\$APP_NAME"
ENV);

    expect($items)->toBe([
        'APP_NAME' => 'ArrayKit',
        'APP_URL' => 'https://${APP_NAME}.test',
        'LITERAL' => '$APP_NAME',
    ]);
});

it('supports hash-prefixed values', function () {
    expect(EnvParser::parse(<<<'ENV'
HEX_COLOR=#FF5733
ENV))->toBe([
        'HEX_COLOR' => '#FF5733',
    ]);
});

it('rejects malformed dotenv entries', function () {
    expect(fn () => EnvParser::parse('1INVALID=value'))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse('BROKEN="value'))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse('BROKEN="value" extra'))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse('SPACED=needs quotes'))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse("BAD=\0value"))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse("\xEF\xBB\xBFBAD=value"))->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parse('BROKEN=${APP_NAME'))->toThrow(UnexpectedValueException::class);
});

it('rejects unsafe bytes from iterable dotenv input', function () {
    expect(fn () => EnvParser::parseLines(["\xEF\xBB\xBFAPP_NAME=ArrayKit"]))
        ->toThrow(UnexpectedValueException::class)
        ->and(fn () => EnvParser::parseLines(["APP_NAME=Array\0Kit"]))
        ->toThrow(UnexpectedValueException::class);
});

it('rejects circular dotenv variable references', function () {
    $_ENV['A'] = 'external';

    try {
        expect(fn () => EnvParser::parse("A=\$B\nB=\$A\n"))->toThrow(UnexpectedValueException::class)
            ->and(fn () => EnvParser::parse('A=$A'))->toThrow(UnexpectedValueException::class);
    } finally {
        unset($_ENV['A']);
    }
});

it('loads and merges dotenv files into config', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arraykit-env-'.uniqid('', true).'.env';
    file_put_contents($path, "APP_NAME=ArrayKit\nAPP_ENV=local\n");

    try {
        $config = new Config;

        expect($config->loadEnvFile($path))->toBeTrue()
            ->and($config->get('APP_NAME'))->toBe('ArrayKit')
            ->and($config->loadEnvFile($path))->toBeFalse();

        $config->mergeEnvFile($path);

        expect($config->get('APP_ENV'))->toBe('local');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('exposes the dotenv parser from the facade', function () {
    expect(ArrayKit::dotenv()->parse("APP_NAME=ArrayKit\n"))->toBe([
        'APP_NAME' => 'ArrayKit',
    ]);
});

it('exposes the dotenv parser from the namespaced helper', function () {
    expect(dotenv()->parse("APP_NAME=ArrayKit\n"))->toBe([
        'APP_NAME' => 'ArrayKit',
    ]);
});

it('reads current environment values from the namespaced helper and facade', function () {
    $_ENV['ARRAYKIT_ENV_HELPER'] = 'from-env';

    try {
        expect(env('ARRAYKIT_ENV_HELPER'))->toBe('from-env')
            ->and(env('ARRAYKIT_MISSING', 'fallback'))->toBe('fallback')
            ->and(ArrayKit::env()->get('ARRAYKIT_ENV_HELPER'))->toBe('from-env')
            ->and(ArrayKit::env()->has('ARRAYKIT_ENV_HELPER'))->toBeTrue();
    } finally {
        unset($_ENV['ARRAYKIT_ENV_HELPER']);
    }
});
