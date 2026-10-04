<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;

function batchARemoveDirectory(string $path): void
{
    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        batchARemoveDirectory($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
}

it('bounds guarded traversal work across flat and wildcard inputs', function () {
    expect(fn () => ArrayMulti::flattenGuarded(
        range(1, 10000),
        maxNodes: 2,
        throwOnTooDeep: true,
    ))->toThrow(RuntimeException::class);

    expect(fn () => DotNotation::getSafe(
        ['rows' => range(1, 10000)],
        'rows.*',
        maxNodes: 2,
        throwOnTooDeep: true,
    ))->toThrow(RuntimeException::class);
});

it('shares wildcard node budgets and stops default resolution after exhaustion', function () {
    $defaults = 0;

    $result = DotNotation::getSafe(
        ['rows' => array_fill(0, 10000, [])],
        'rows.*.id',
        function () use (&$defaults): string {
            $defaults++;

            return 'missing';
        },
        maxNodes: 3,
    );

    expect(count($result))->toBeLessThanOrEqual(3)
        ->and($defaults)->toBeLessThanOrEqual(3);
});

it('advances guarded depth across wildcard traversal', function () {
    $value = 'leaf';
    for ($i = 0; $i < 12; $i++) {
        $value = [$value];
    }

    expect(fn () => DotNotation::getSafe(
        ['rows' => [$value]],
        'rows.*.*.*.*.*.*.*.*.*.*.*.*',
        maxDepth: 2,
        throwOnTooDeep: true,
    ))->toThrow(RuntimeException::class);
});

it('preserves layered runtime writes made before first materialization', function () {
    $directory = sys_get_temp_dir() . '/arraykit-batch-a-' . bin2hex(random_bytes(5));
    mkdir($directory, 0777, true);
    file_put_contents(
        $directory . '/app.php',
        "<?php\n\nreturn ['name' => 'source', 'debug' => false];\n",
    );

    try {
        $config = new LayeredLazyFileConfig($directory, namespaces: ['app']);
        $config->set('app.name', 'caller');

        expect($config->get('app.name'))->toBe('caller')
            ->and($config->get())->toBe($config->all());
    } finally {
        batchARemoveDirectory($directory);
    }
});

it('restores layered materialization state with snapshots', function () {
    $directory = sys_get_temp_dir() . '/arraykit-batch-a-' . bin2hex(random_bytes(5));
    mkdir($directory, 0777, true);
    file_put_contents(
        $directory . '/app.php',
        "<?php\n\nreturn ['name' => 'source'];\n",
    );

    try {
        $config = new LayeredLazyFileConfig($directory, namespaces: ['app']);
        $config->snapshot();
        expect($config->get('app.name'))->toBe('source');

        $config->restore();

        expect($config->get('app.name'))->toBe('source');
    } finally {
        batchARemoveDirectory($directory);
    }
});

it('materializes known layered namespaces before exporting compiled config', function () {
    $directory = sys_get_temp_dir() . '/arraykit-batch-a-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-a-cache-' . bin2hex(random_bytes(5)) . '.php';
    mkdir($directory, 0777, true);
    file_put_contents(
        $directory . '/app.php',
        "<?php\n\nreturn ['name' => 'source'];\n",
    );

    try {
        $config = new LayeredLazyFileConfig($directory, namespaces: ['app']);

        expect($config->exportCache($cache))->toBeTrue()
            ->and(include $cache)->toBe(['app' => ['name' => 'source']]);
    } finally {
        batchARemoveDirectory($directory);
        if (is_file($cache)) {
            unlink($cache);
        }
    }
});

it('preserves by-reference mutations through facade proxies', function () {
    $data = ['app' => ['name' => 'old'], 'remove' => true];

    expect(ArrayKit::dot()->set($data, 'app.name', 'new'))->toBeTrue()
        ->and($data['app']['name'])->toBe('new');

    ArrayKit::helper()->forget($data, 'remove');

    expect($data)->not->toHaveKey('remove');

    ArrayKit::dot()->fill($data, 'app.debug', true);
    expect($data['app']['debug'])->toBeTrue();

    expect(ArrayKit::dot()->rename($data, 'app.debug', 'app.enabled'))->toBeTrue()
        ->and($data['app']['enabled'])->toBeTrue();
});

it('replays terminal lazy source failures instead of presenting truncated success', function () {
    $lazy = LazyCollection::from((function () {
        yield 1;
        throw new RuntimeException('source failure');
    })());

    foreach ([1, 2] as $attempt) {
        try {
            $lazy->all();
            test()->fail("Traversal {$attempt} unexpectedly succeeded.");
        } catch (RuntimeException $error) {
            expect($error->getMessage())->toBe('source failure');
        }
    }
});

it('replays failures before the first lazy yield', function () {
    $lazy = LazyCollection::from((function () {
        if (true) {
            throw new RuntimeException('initial failure');
        }

        yield 1;
    })());

    expect(fn () => $lazy->all())->toThrow(RuntimeException::class, 'initial failure')
        ->and(fn () => $lazy->all())->toThrow(RuntimeException::class, 'initial failure');
});
