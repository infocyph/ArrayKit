<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;

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
