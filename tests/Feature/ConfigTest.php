<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;

enum ConfigMode: string
{
    case Local = 'local';
    case Prod = 'prod';
}

$config = new Config();

it('can load an array into config', function () use ($config)  {
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
    $cfg = new Config();
    $cfg->loadArray(['app' => ['name' => 'ArrayKit']]);

    $cfg->replace(['app' => ['name' => 'ArrayKitX']]);
    expect($cfg->get('app.name'))->toBe('ArrayKitX');

    $cfg->reload(['db' => ['host' => 'localhost']]);
    expect($cfg->get('db.host'))->toBe('localhost')
        ->and($cfg->has('app.name'))->toBeFalse();
});

it('supports getOrFail for required keys', function () {
    $cfg = new Config();
    $cfg->loadArray(['app' => ['name' => 'ArrayKit']]);

    expect($cfg->getOrFail('app.name'))->toBe('ArrayKit')
        ->and(fn () => $cfg->getOrFail('app.missing'))->toThrow(OutOfBoundsException::class);
});

it('supports typed getters with default fallbacks', function () {
    $cfg = new Config();
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
    $cfg = new Config();
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

    $cfg->readonly();
    expect($cfg->isReadonly())->toBeTrue()
        ->and(fn () => $cfg->set('db.host', '127.0.0.1'))->toThrow(RuntimeException::class);
});

it('supports getEnum for backed enums', function () {
    $cfg = new Config();
    $cfg->loadArray(['app' => ['mode' => 'prod']]);

    expect($cfg->getEnum('app.mode', ConfigMode::class))->toBe(ConfigMode::Prod)
        ->and($cfg->getEnum('app.missing', ConfigMode::class, ConfigMode::Local))->toBe(ConfigMode::Local);
});
