<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Config\Support\Environment;


enum BatchBCacheMode: string
{
    case Production = 'production';
}

function batchBRemoveDirectory(string $path): void
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

        batchBRemoveDirectory($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
}

function batchBWriteConfig(string $directory, string $namespace, array $value): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . $namespace . '.php',
        "<?php\n\nreturn " . var_export($value, true) . ";\n",
    );
}

it('does not memoize scalar reads through externally mutable objects or references', function () {
    $object = (object) ['name' => 'before'];
    $referenced = 'before';

    $config = new Config();
    $config->loadArray([
        'object' => $object,
        'reference' => ['name' => &$referenced],
    ]);

    expect($config->get('object.name'))->toBe('before')
        ->and($config->get('reference.name'))->toBe('before');

    $object->name = 'after';
    $referenced = 'after';

    expect($config->get('object.name'))->toBe('after')
        ->and($config->get('reference.name'))->toBe('after');
});

it('invalidates flat and resolved reads when the namespace cache source changes', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'source']);

    try {
        $warmer = new LazyFileConfig(
            $source,
            items: ['db' => ['host' => 'cache']],
            namespaceCacheDirectory: $cache,
        );
        $warmer->warmNamespaceCache('db');

        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        expect($config->get('db.host'))->toBe('cache')
            ->and($config->loaded('db'))->toBeFalse();

        $config->namespaceCache(null);

        expect($config->get('db.host'))->toBe('source')
            ->and($config->loaded('db'))->toBeTrue();
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('keeps the valid __flat namespace distinct from internal flat-index metadata', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, '__flat', ['name' => 'namespace']);

    try {
        $warmer = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $warmer->warmNamespaceCache('__flat');

        $fresh = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        expect($fresh->get('__flat.name'))->toBe('namespace');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('rebuilds generated namespace caches from authoritative source values', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);

    file_put_contents(
        $source . '/db.php',
        <<<'PHP'
<?php

use Infocyph\ArrayKit\Config\Support\Environment;

return ['host' => Environment::ref('ARRAYKIT_BATCH_B_HOST', 'fallback')];
PHP,
    );

    try {
        $_ENV['ARRAYKIT_BATCH_B_HOST'] = 'first';

        $first = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $first->warmNamespaceCache('db');

        $_ENV['ARRAYKIT_BATCH_B_HOST'] = 'second';

        $second = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $second->warmNamespaceCache('db');

        $fresh = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        expect($fresh->get('db.host'))->toBe('second');
    } finally {
        unset($_ENV['ARRAYKIT_BATCH_B_HOST']);
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('publishes namespace cache rebuilds as an immutable generation', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'app', ['name' => 'ArrayKit']);
    batchBWriteConfig($source, 'db', ['host' => 'localhost']);

    try {
        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $config->warmNamespaceCache(['app', 'db']);

        $pointer = $cache . '/.arraykit-generation';

        expect(is_file($pointer))->toBeTrue();

        $generation = trim((string) file_get_contents($pointer));
        $generationDirectory = $cache . DIRECTORY_SEPARATOR . $generation;

        expect(str_starts_with($generation, '.arraykit-gen-'))->toBeTrue()
            ->and(is_dir($generationDirectory))->toBeTrue()
            ->and(is_file($generationDirectory . '/app.php'))->toBeTrue()
            ->and(is_file($generationDirectory . '/db.php'))->toBeTrue()
            ->and(is_file($generationDirectory . '/.arraykit-flat.php'))->toBeTrue()
            ->and(is_file($cache . '/app.php'))->toBeFalse()
            ->and(is_file($cache . '/db.php'))->toBeFalse();
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('rejects unsupported compiled-cache values without replacing the last valid artifact', function () {
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-export-' . bin2hex(random_bytes(5)) . '.php';

    try {
        $valid = new Config();
        $valid->loadArray(['app' => ['name' => 'stable']]);
        expect($valid->exportCache($cache))->toBeTrue();

        $invalid = new Config();
        $invalid->loadArray([
            'app' => [
                'value' => new class {
                    public string $name = 'unsupported';
                },
            ],
        ]);

        expect(fn () => $invalid->exportCache($cache))
            ->toThrow(UnexpectedValueException::class);

        expect(include $cache)->toBe(['app' => ['name' => 'stable']]);
    } finally {
        if (is_file($cache)) {
            unlink($cache);
        }
    }
});

it('switches generated cache roots without retaining cache-derived state', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cacheA = sys_get_temp_dir() . '/arraykit-batch-b-cache-a-' . bin2hex(random_bytes(5));
    $cacheB = sys_get_temp_dir() . '/arraykit-batch-b-cache-b-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cacheA, 0777, true);
    mkdir($cacheB, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'source']);

    try {
        (new LazyFileConfig($source, items: ['db' => ['host' => 'cache-a']], namespaceCacheDirectory: $cacheA))
            ->warmNamespaceCache('db');
        (new LazyFileConfig($source, items: ['db' => ['host' => 'cache-b']], namespaceCacheDirectory: $cacheB))
            ->warmNamespaceCache('db');

        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cacheA);

        expect($config->get('db.host'))->toBe('cache-a');

        $config->namespaceCache($cacheB);
        expect($config->get('db.host'))->toBe('cache-b');

        $config->namespaceCache(null);
        expect($config->get('db.host'))->toBe('source');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cacheA);
        batchBRemoveDirectory($cacheB);
    }
});

it('preserves runtime namespace mutations when generated cache roots change', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cacheA = sys_get_temp_dir() . '/arraykit-batch-b-cache-a-' . bin2hex(random_bytes(5));
    $cacheB = sys_get_temp_dir() . '/arraykit-batch-b-cache-b-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cacheA, 0777, true);
    mkdir($cacheB, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'source']);

    try {
        (new LazyFileConfig($source, items: ['db' => ['host' => 'cache-a']], namespaceCacheDirectory: $cacheA))
            ->warmNamespaceCache('db');
        (new LazyFileConfig($source, items: ['db' => ['host' => 'cache-b']], namespaceCacheDirectory: $cacheB))
            ->warmNamespaceCache('db');

        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cacheA);
        $config->set('db.host', 'runtime');

        $config->namespaceCache($cacheB);

        expect($config->get('db.host'))->toBe('runtime');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cacheA);
        batchBRemoveDirectory($cacheB);
    }
});

it('loads namespace structure coherently after an exact flat-index hit', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'localhost', 'options' => ['timeout' => 5]]);

    try {
        (new LazyFileConfig($source, namespaceCacheDirectory: $cache))->warmNamespaceCache('db');

        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        expect($config->get('db.host'))->toBe('localhost')
            ->and($config->loaded('db'))->toBeFalse()
            ->and($config->get('db'))->toBe([
                'host' => 'localhost',
                'options' => ['timeout' => 5],
            ])
            ->and($config->loaded('db'))->toBeTrue();
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('supports selective generated-cache flushes without disturbing other namespaces', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'app', ['name' => 'ArrayKit']);
    batchBWriteConfig($source, 'db', ['host' => 'localhost']);

    try {
        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $config->warmNamespaceCache(['app', 'db'])->flushNamespaceCache('db');

        unlink($source . '/app.php');
        unlink($source . '/db.php');

        $fresh = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        expect($fresh->get('app.name'))->toBe('ArrayKit')
            ->and($fresh->get('db.host', 'missing'))->toBe('missing');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('supports valid cache namespace names and alternate source extensions', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);

    file_put_contents($source . '/foo-bar.inc', "<?php\n\nreturn ['value' => 'dash'];\n");
    file_put_contents($source . '/foo_bar.inc', "<?php\n\nreturn ['value' => 'underscore'];\n");
    file_put_contents($source . '/__flat.inc', "<?php\n\nreturn ['value' => 'flat-namespace'];\n");

    try {
        $config = new LazyFileConfig($source, 'inc', namespaceCacheDirectory: $cache);
        $config->warmNamespaceCache(['foo-bar', 'foo_bar', '__flat']);

        $fresh = new LazyFileConfig($source, 'inc', namespaceCacheDirectory: $cache);

        expect($fresh->get('foo-bar.value'))->toBe('dash')
            ->and($fresh->get('foo_bar.value'))->toBe('underscore')
            ->and($fresh->get('__flat.value'))->toBe('flat-namespace');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('rebuilds environment-backed caches on the same warmer instance', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);

    file_put_contents(
        $source . '/db.php',
        <<<'PHP'
<?php

use Infocyph\ArrayKit\Config\Support\Environment;

return ['host' => Environment::ref('ARRAYKIT_BATCH_B_SAME_HOST', 'fallback')];
PHP,
    );

    try {
        $warmer = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        $_ENV['ARRAYKIT_BATCH_B_SAME_HOST'] = 'first';
        $warmer->warmNamespaceCache('db');

        $_ENV['ARRAYKIT_BATCH_B_SAME_HOST'] = 'second';
        $warmer->warmNamespaceCache('db');

        expect((new LazyFileConfig($source, namespaceCacheDirectory: $cache))->get('db.host'))
            ->toBe('second');
    } finally {
        unset($_ENV['ARRAYKIT_BATCH_B_SAME_HOST']);
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('can republish a generated namespace in a cache-only deployment', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'cached']);

    try {
        (new LazyFileConfig($source, namespaceCacheDirectory: $cache))->warmNamespaceCache('db');
        unlink($source . '/db.php');

        (new LazyFileConfig($source, namespaceCacheDirectory: $cache))->warmNamespaceCache('db');

        expect((new LazyFileConfig($source, namespaceCacheDirectory: $cache))->get('db.host'))
            ->toBe('cached');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});

it('round-trips supported compiled-cache values including nulls and enums', function () {
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-export-' . bin2hex(random_bytes(5)) . '.php';

    try {
        $config = new Config();
        $config->loadArray([
            'app' => [
                'enabled' => true,
                'mode' => BatchBCacheMode::Production,
                'nullable' => null,
                'ports' => [80, 443],
            ],
        ]);

        expect($config->exportCache($cache))->toBeTrue()
            ->and(include $cache)->toBe([
                'app' => [
                    'enabled' => true,
                    'mode' => BatchBCacheMode::Production,
                    'nullable' => null,
                    'ports' => [80, 443],
                ],
            ]);
    } finally {
        if (is_file($cache)) {
            unlink($cache);
        }
    }
});

it('rejects resources named objects and cyclic compiled-cache graphs', function (string $case) {
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-export-' . bin2hex(random_bytes(5)) . '.php';
    $resource = null;

    try {
        $value = match ($case) {
            'resource' => $resource = fopen('php://memory', 'r'),
            'object' => new stdClass(),
            'array-cycle' => (static function (): array {
                $cycle = [];
                $cycle['self'] = &$cycle;

                return $cycle;
            })(),
            'closure-cycle' => (static function (): Closure {
                $closure = null;
                $closure = static function () use (&$closure): Closure {
                    return $closure;
                };

                return $closure;
            })(),
        };

        $config = new Config();
        $config->loadArray(['value' => $value]);

        expect(fn () => $config->exportCache($cache))
            ->toThrow(UnexpectedValueException::class);
    } finally {
        if (is_resource($resource)) {
            fclose($resource);
        }
        if (is_file($cache)) {
            unlink($cache);
        }
    }
})->with(['resource', 'object', 'array-cycle', 'closure-cycle']);

it('keeps the previous generation active when namespace publication rejects a value', function () {
    $source = sys_get_temp_dir() . '/arraykit-batch-b-source-' . bin2hex(random_bytes(5));
    $cache = sys_get_temp_dir() . '/arraykit-batch-b-cache-' . bin2hex(random_bytes(5));
    mkdir($source, 0777, true);
    mkdir($cache, 0777, true);
    batchBWriteConfig($source, 'db', ['host' => 'stable']);

    try {
        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);
        $config->warmNamespaceCache('db');
        $config->set('db.invalid', new stdClass());

        expect(fn () => $config->warmNamespaceCache('db'))
            ->toThrow(UnexpectedValueException::class);

        expect((new LazyFileConfig($source, namespaceCacheDirectory: $cache))->get('db.host'))
            ->toBe('stable');
    } finally {
        batchBRemoveDirectory($source);
        batchBRemoveDirectory($cache);
    }
});
