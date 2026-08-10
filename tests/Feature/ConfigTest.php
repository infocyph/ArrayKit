<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\Support\Environment;
use Infocyph\ArrayKit\Tests\Fixtures\ConfigMode;

$config = new Config;

it('can load an array into config', function () use ($config) {
    $success = $config->loadArray(['app' => ['name' => 'ArrayKit']]);
    expect($success)
        ->toBeTrue()
        ->and($config->loadArray(['another' => 'test']))->toBeFalse();
    // subsequent calls return false because items are no longer empty
});

it('retrieves config items via dot notation', function () use ($config) {
    expect($config->get('app.name'))->toBe('ArrayKit');
});

it('sets config items via dot notation', function () use ($config) {
    $config->set('db.host', 'localhost');
    expect($config->get('db.host'))->toBe('localhost');
});

it('checks if a config key exists', function () use ($config) {
    expect($config->has('app.name'))
        ->toBeTrue()
        ->and($config->has('app.unknown'))->toBeFalse();
});

it('supports replace and reload operations', function () {
    $cfg = new Config;
    $cfg->loadArray(['app' => ['name' => 'ArrayKit']]);

    $cfg->replace(['app' => ['name' => 'ArrayKitX']]);
    expect($cfg->get('app.name'))->toBe('ArrayKitX');

    $cfg->reload(['db' => ['host' => 'localhost']]);
    expect($cfg->get('db.host'))->toBe('localhost')
        ->and($cfg->has('app.name'))->toBeFalse();
});

it('supports getOrFail for required keys', function () {
    $cfg = new Config;
    $cfg->loadArray(['app' => ['name' => 'ArrayKit']]);

    expect($cfg->getOrFail('app.name'))->toBe('ArrayKit')
        ->and(fn () => $cfg->getOrFail('app.missing'))->toThrow(\OutOfBoundsException::class);
});

it('supports typed getters with default fallbacks', function () {
    $cfg = new Config;
    $cfg->loadArray([
        'app' => ['name' => 'ArrayKit', 'debug' => true],
        'port' => 8080,
        'ratio' => 0.75,
        'tags' => ['a', 'b'],
    ]);

    expect($cfg->getString('app.name'))->toBe('ArrayKit')
        ->and($cfg->getBool('app.debug'))->toBeTrue()
        ->and($cfg->getInt('port'))->toBe(8080)
        ->and($cfg->getFloat('ratio'))->toBe(0.75)
        ->and($cfg->getArray('tags'))->toBe(['a', 'b'])
        ->and($cfg->getList('tags'))->toBe(['a', 'b'])
        ->and($cfg->getString('port', 'fallback'))->toBe('fallback');
});

it('supports merge/overlay/snapshot/restore/changed/readonly', function () {
    $cfg = new Config;
    $cfg->loadArray(['db' => ['host' => 'localhost', 'port' => 3306]]);
    $cfg->snapshot('baseline');

    $cfg->merge(['db' => ['port' => 3307]]);
    expect($cfg->get('db.port'))->toBe(3307)
        ->and($cfg->changed('baseline'))->toBeTrue();

    $cfg->overlay(['db' => ['ssl' => true]]);
    expect($cfg->get('db.ssl'))->toBeTrue();

    $cfg->restore('baseline');
    expect($cfg->get('db.port'))->toBe(3306)
        ->and($cfg->changed('baseline'))->toBeFalse();

    $cfg->set('db.port', '3306');
    expect($cfg->changed('baseline'))->toBeTrue();

    $cfg->readonly();
    expect($cfg->isReadonly())->toBeTrue()
        ->and(fn () => $cfg->set('db.host', '127.0.0.1'))->toThrow(\RuntimeException::class);
});

it('supports getEnum for backed enums', function () {
    $cfg = new Config;
    $cfg->loadArray(['app' => ['mode' => 'prod']]);

    expect($cfg->getEnum('app.mode', ConfigMode::class))->toBe(ConfigMode::Prod)
        ->and($cfg->getEnum('app.missing', ConfigMode::class, ConfigMode::Local))->toBe(ConfigMode::Local);
});

it('supports compiled config cache export and reload', function () {
    $cfg = new Config;
    $cfg->loadArray([
        'app' => ['name' => 'ArrayKit'],
        'db' => ['host' => 'localhost'],
    ]);

    $cachePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arraykit-config-cache-'.uniqid('', true).'.php';

    try {
        expect($cfg->exportCache($cachePath))->toBeTrue();

        $loaded = new Config;
        expect($loaded->loadCache($cachePath))->toBeTrue()
            ->and($loaded->get('app.name'))->toBe('ArrayKit')
            ->and($loaded->get('db.host'))->toBe('localhost');

        $loaded->reload(['db' => ['host' => 'db.internal']]);
        expect($loaded->get('db.host'))->toBe('db.internal');
    } finally {
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }
});

it('materializes environment references and closures when exporting cache', function () {
    $_ENV['ARRAYKIT_CACHE_HOST'] = 'cache.internal';

    $cfg = new Config;
    $cfg->loadArray([
        'db' => [
            'host' => Environment::ref('ARRAYKIT_CACHE_HOST', 'localhost'),
            'port' => fn (): int => 5432,
            'missing' => Environment::ref('ARRAYKIT_CACHE_MISSING', 'fallback'),
        ],
    ]);

    $cachePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arraykit-config-cache-'.uniqid('', true).'.php';

    try {
        expect($cfg->exportCache($cachePath))->toBeTrue();

        unset($_ENV['ARRAYKIT_CACHE_HOST']);

        $loaded = new Config;
        expect($loaded->loadCache($cachePath))->toBeTrue()
            ->and($loaded->get('db.host'))->toBe('cache.internal')
            ->and($loaded->get('db.port'))->toBe(5432)
            ->and($loaded->get('db.missing'))->toBe('fallback');
    } finally {
        unset($_ENV['ARRAYKIT_CACHE_HOST']);
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }
});

it('memoizes reads without returning stale values after mutation', function () {
    $cfg = new Config;
    $cfg->loadArray(['app' => ['name' => 'ArrayKit']]);

    expect($cfg->readCacheEnabled())->toBeTrue()
        ->and($cfg->get('app.name'))->toBe('ArrayKit');

    $cfg->set('app.name', 'ArrayKitX');
    expect($cfg->get('app.name'))->toBe('ArrayKitX')
        ->and($cfg->has('app.name'))->toBeTrue()
        ->and($cfg->readCache(false)->readCacheEnabled())->toBeFalse()
        ->and($cfg->get('app.name'))->toBe('ArrayKitX');
});

it('bypasses memoization for direct top-level reads', function () {
    $cfg = new Config;
    $cfg->loadArray([
        'debug' => false,
        'zero' => 0,
        'nullable' => null,
    ]);

    for ($index = 0; $index < 100; $index++) {
        expect($cfg->get('debug'))->toBeFalse()
            ->and($cfg->get('zero'))->toBe(0)
            ->and($cfg->get('nullable', 'fallback'))->toBeNull()
            ->and($cfg->get('missing', 'fallback'))->toBe('fallback');
    }

    $cacheSize = (fn (): int => count($this->resolvedValueCache))->call($cfg);
    expect($cacheSize)->toBe(0);
});

it('invalidates memoized nested reads after every mutation family', function () {
    $cfg = new Config;
    $cfg->loadArray(['app' => ['value' => 1]]);

    expect($cfg->get('app.value'))->toBe(1);

    $cfg->set('app.value', 2);
    expect($cfg->get('app.value'))->toBe(2);

    $cfg->set(['app.value' => 3, 'app.extra' => 'set']);
    expect($cfg->get('app.value'))->toBe(3);

    $cfg->fill('app.filled', 4);
    expect($cfg->get('app.filled'))->toBe(4);

    $cfg->forget('app.filled');
    expect($cfg->get('app.filled', 'missing'))->toBe('missing');

    $cfg->replace(['app' => ['value' => 5]]);
    expect($cfg->get('app.value'))->toBe(5);

    $cfg->merge(['app' => ['value' => 6]]);
    expect($cfg->get('app.value'))->toBe(6);

    $cfg->overlay(['app' => ['value' => 7]]);
    expect($cfg->get('app.value'))->toBe(7);

    $cfg->snapshot();
    $cfg->set('app.value', 8);
    expect($cfg->get('app.value'))->toBe(8)
        ->and($cfg->restore())->toBeTrue()
        ->and($cfg->get('app.value'))->toBe(7);

    $cfg->reload(['app' => ['value' => 9]]);
    expect($cfg->get('app.value'))->toBe(9);
});

it('bounds the in-memory read cache for long-running processes', function () {
    $cfg = new Config;

    for ($index = 0; $index < 2048; $index++) {
        $cfg->get('missing.'.$index);
    }

    $cacheSize = (fn (): int => count($this->resolvedValueCache))->call($cfg);

    expect($cacheSize)->toBeLessThanOrEqual(1024);
});
