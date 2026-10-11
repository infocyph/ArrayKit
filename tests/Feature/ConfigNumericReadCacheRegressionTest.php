<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;

it('resolves canonical numeric nested configuration paths with memoization enabled', function (): void {
    $config = new Config;
    $config->loadArray([
        'app' => [
            'servers' => ['first', 'second'],
            'negative' => [-1 => 'minus one'],
            'nested' => [['name' => 'primary']],
        ],
    ]);

    foreach (range(1, 3) as $read) {
        expect($config->get('app.servers.0'))->toBe('first')
            ->and($config->get('app.servers.1'))->toBe('second')
            ->and($config->get('app.negative.-1'))->toBe('minus one')
            ->and($config->get('app.nested.0.name'))->toBe('primary')
            ->and($config->has('app.servers.0'))->toBeTrue()
            ->and($config->get('app.servers.2', 'missing'))->toBe('missing');
    }

    $config->set('app.servers.0', 'updated');

    expect($config->get('app.servers.0'))->toBe('updated');
});

it('keeps numeric-looking literal string offsets distinct from integer offsets', function (): void {
    $config = new Config;
    $config->loadArray([
        'app' => [
            'literal' => [
                '01' => 'leading zero',
                '+1' => 'plus prefixed',
                '-0' => 'negative zero',
                '1' => 'canonical integer',
            ],
        ],
    ]);

    expect($config->get('app.literal.01'))->toBe('leading zero')
        ->and($config->get('app.literal.+1'))->toBe('plus prefixed')
        ->and($config->get('app.literal.-0'))->toBe('negative zero')
        ->and($config->get('app.literal.1'))->toBe('canonical integer');
});

it('does not memoize nested numeric array references', function (): void {
    $value = 'before';
    $items = [
        'app' => [
            'servers' => [&$value],
        ],
    ];
    $config = new Config;
    $config->loadArray($items);

    expect($config->get('app.servers.0'))->toBe('before');

    $value = 'after';

    expect($config->get('app.servers.0'))->toBe('after');
});

it('returns identical numeric-path values with and without read caching', function (): void {
    $items = [
        'app' => [
            'servers' => ['first', 'second'],
            'negative' => [-1 => 'below zero'],
            'literal' => ['01' => 'zero one'],
        ],
    ];

    $cached = new Config;
    $cached->loadArray($items);
    $uncached = new Config;
    $uncached->loadArray($items);
    $uncached->readCache(false);

    foreach (['app.servers.0', 'app.servers.1', 'app.negative.-1', 'app.literal.01', 'app.servers.9'] as $path) {
        expect($cached->get($path, 'missing'))->toBe($uncached->get($path, 'missing'));
    }
});
