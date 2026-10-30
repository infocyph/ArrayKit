<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;

it('detaches scalar and nested references for snapshots, changes and repeated restores', function () {
    $scalar = 1;
    $nested = ['v' => 10];
    $config = new Config();
    $config->loadArray(['app' => ['scalar' => &$scalar, 'nested' => &$nested]]);
    expect($config->snapshot('before'))->toBeTrue();

    $scalar = 5;
    $nested['v'] = 30;
    expect($config->changed('before'))->toBeTrue()
        ->and($config->restore('before'))->toBeTrue()
        ->and($config->get('app.scalar'))->toBe(1)
        ->and($config->get('app.nested.v'))->toBe(10);

    $config->set('app.scalar', 99);
    expect($config->restore('before'))->toBeTrue()
        ->and($config->get('app.scalar'))->toBe(1)
        ->and($config->changed('before'))->toBeFalse();
});

it('keeps shallow object identity but detaches repeated internal array references', function () {
    $shared = ['value' => 1];
    $object = new stdClass();
    $config = new Config();
    $config->loadArray(['left' => &$shared, 'right' => &$shared, 'obj' => $object]);
    $config->snapshot();
    $shared['value'] = 2;

    expect($config->restore())->toBeTrue()
        ->and($config->get('left.value'))->toBe(1)
        ->and($config->get('right.value'))->toBe(1)
        ->and($config->get('obj'))->toBe($object);
});

it('detaches snapshot references across lazy and layered configuration owners', function () {
    $lazyValue = 1;
    $lazy = new LazyFileConfig(
        sys_get_temp_dir() . '/arraykit-532-nonexistent',
        items: ['app' => ['v' => &$lazyValue]],
    );
    expect($lazy->snapshot())->toBeTrue();
    $lazyValue = 8;
    expect($lazy->restore())->toBeTrue()
        ->and($lazy->get('app.v'))->toBe(1);

    $layeredValue = 2;
    $layered = new LayeredLazyFileConfig(
        sys_get_temp_dir() . '/arraykit-532-nonexistent',
        fallback: ['app' => ['v' => &$layeredValue]],
        namespaces: ['app'],
    );
    $layered->snapshot();
    $layeredValue = 9;
    expect($layered->restore())->toBeTrue()
        ->and($layered->get('app.v'))->toBe(2);
});

it('rejects cyclic snapshots without replacing a valid snapshot', function () {
    $config = new Config();
    $config->loadArray(['safe' => ['v' => 1]]);
    $config->snapshot('baseline');
    $recursive = [];
    $recursive['self'] = &$recursive;
    $config->replace(['cyclic' => &$recursive]);

    expect(fn () => $config->snapshot('baseline'))->toThrow(UnexpectedValueException::class);
    $config->replace(['safe' => ['v' => 42]]);
    expect($config->restore('baseline'))->toBeTrue()
        ->and($config->get('safe.v'))->toBe(1);
});

it('refreshes source-origin namespace state after a new generation is published', function () {
    $root = sys_get_temp_dir() . '/arraykit-532-' . bin2hex(random_bytes(6));
    $source = $root . '/source';
    $cache = $root . '/cache';
    mkdir($root);
    mkdir($source);
    mkdir($cache);
    try {
        file_put_contents($source . '/app.php', "<?php return ['version' => 1];");
        $reader = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        expect($reader->get('app.version'))->toBe(1);
        file_put_contents($source . '/app.php', "<?php return ['version' => 2];");
        (new LazyFileConfig($source, namespaceCacheDirectory: $cache))->warmNamespaceCache('app');
        expect($reader->get('app.version'))->toBe(1);
        $reader->namespaceCache($cache);
        expect($reader->get('app.version'))->toBe(2);
        $reader->set('app.version', 7);
        $reader->namespaceCache($cache);
        expect($reader->get('app.version'))->toBe(7);
    } finally {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
});

it('discovers and returns canonical numeric namespace names in lazy and layered config', function () {
    $root = sys_get_temp_dir() . '/arraykit-532-' . bin2hex(random_bytes(6));
    $source = $root . '/source';
    $cache = $root . '/cache';
    mkdir($root);
    mkdir($source);
    mkdir($cache);
    try {
        foreach (['0', '-1', '01'] as $name) {
            file_put_contents($source . '/' . $name . '.php', "<?php return ['value' => '{$name}'];");
        }

        $lazy = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        expect($lazy->get('0.value'))->toBe('0')
            ->and($lazy->get('-1.value'))->toBe('-1')
            ->and($lazy->get('01.value'))->toBe('01')
            ->and($lazy->loadedNamespaces())->toBe(['0', '-1', '01']);

        $lazy->warmNamespaceCache();
        $fresh = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        expect($fresh->get('0.value'))->toBe('0');

        $layered = new LayeredLazyFileConfig($source, $cache, namespaces: ['0', '-1', '01']);
        expect($layered->get('0.value'))->toBe('0')
            ->and($layered->all()[0]['value'])->toBe('0')
            ->and($layered->all()[-1]['value'])->toBe('-1')
            ->and($layered->all()['01']['value'])->toBe('01');
        $layered->warmNamespaceCache();
    } finally {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
});
