<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\LazyFileConfig;

function lazyConfigWriteArrayFile(string $directory, string $name, array $contents): void
{
    $export = var_export($contents, true);
    file_put_contents(
        $directory.DIRECTORY_SEPARATOR.$name.'.php',
        "<?php\n\nreturn {$export};\n",
    );
}

function lazyConfigDeleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $entries = scandir($directory);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$entry;

        if (is_dir($path)) {
            lazyConfigDeleteDirectory($path);

            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}

/**
 * @return array<string, mixed>
 */
function lazyConfigItems(LazyFileConfig $config): array
{
    return (fn (): array => $this->items)->call($config);
}

/**
 * @return array<string, scalar|null>
 */
function lazyConfigFlatIndex(string $directory): array
{
    $path = $directory.DIRECTORY_SEPARATOR.'__flat.php';

    if (! is_file($path)) {
        return [];
    }

    /** @var array<string, scalar|null> $index */
    $index = include $path;

    return $index;
}

beforeEach(function () {
    $this->configPath = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR
        .'arraykit-lazy-config-'
        .uniqid('', true);

    $this->cachePath = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR
        .'arraykit-lazy-cache-'
        .uniqid('', true);

    mkdir($this->configPath, 0777, true);
    mkdir($this->cachePath, 0777, true);
});

afterEach(function () {
    lazyConfigDeleteDirectory($this->configPath);
    lazyConfigDeleteDirectory($this->cachePath);
});

it('loads only the first namespace file on first dot-path access', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost', 'port' => 3306]);
    lazyConfigWriteArrayFile($this->configPath, 'app', ['name' => 'ArrayKit']);

    $config = new LazyFileConfig($this->configPath);

    expect($config->get('db.host'))->toBe('localhost')
        ->and(array_keys(lazyConfigItems($config)))->toBe(['db']);
});

it('returns full namespace array when key contains only the first segment', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost', 'port' => 3306]);

    $config = new LazyFileConfig($this->configPath);

    expect($config->get('db'))->toBe(['host' => 'localhost', 'port' => 3306]);
});

it('can preload selected namespaces for faster first access', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);
    lazyConfigWriteArrayFile($this->configPath, 'app', ['name' => 'ArrayKit']);

    $config = new LazyFileConfig($this->configPath);
    $config->preload('db');

    expect($config->isLoaded('db'))->toBeTrue()
        ->and($config->isLoaded('app'))->toBeFalse()
        ->and($config->get('db.host'))->toBe('localhost');
});

it('tracks resolved namespaces for observability', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);
    lazyConfigWriteArrayFile($this->configPath, 'cache', ['driver' => 'file']);

    $config = new LazyFileConfig($this->configPath);
    $config->preload(['db', 'cache']);

    $loaded = $config->loadedNamespaces();
    sort($loaded);

    expect($loaded)->toBe(['cache', 'db']);
});

it('supports loaded() alias for namespace checks', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);

    $config = new LazyFileConfig($this->configPath);
    $config->get('db.host');

    expect($config->loaded('db'))->toBeTrue()
        ->and($config->loaded('app'))->toBeFalse();
});

it('loads all requested namespaces for multi-key lookup', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);
    lazyConfigWriteArrayFile($this->configPath, 'app', ['name' => 'ArrayKit']);

    $config = new LazyFileConfig($this->configPath);

    expect($config->get(['db.host', 'app.name']))->toBe([
        'db.host' => 'localhost',
        'app.name' => 'ArrayKit',
    ]);

    $keys = array_keys(lazyConfigItems($config));
    sort($keys);
    expect($keys)->toBe(['app', 'db']);
});

it('merges lazy-loaded defaults with runtime overrides', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', [
        'host' => 'localhost',
        'port' => 3306,
        'options' => [
            'ssl' => false,
            'timeout' => 5,
        ],
    ]);

    $config = new LazyFileConfig($this->configPath);
    $config->set('db.host', 'db.internal');
    $config->set('db.options.timeout', 10);

    expect($config->get('db'))->toBe([
        'host' => 'db.internal',
        'port' => 3306,
        'options' => [
            'ssl' => false,
            'timeout' => 10,
        ],
    ]);
});

it('throws if full config retrieval is requested without a key', function () {
    lazyConfigWriteArrayFile($this->configPath, 'app', ['name' => 'ArrayKit']);

    $config = new LazyFileConfig($this->configPath);

    expect(fn () => $config->get())->toThrow(RuntimeException::class)
        ->and(fn () => $config->all())->toThrow(RuntimeException::class);
});

it('returns default for missing namespace file without side effects', function () {
    $config = new LazyFileConfig($this->configPath);

    expect($config->get('cache.driver', 'file'))->toBe('file')
        ->and(lazyConfigItems($config))->toBe([]);
});

it('evaluates closure defaults for missing or non-array nested paths', function () {
    $config = new LazyFileConfig($this->configPath, items: [
        'db' => 'scalar',
    ]);

    expect($config->get('cache.driver', fn () => 'file'))->toBe('file')
        ->and($config->get('db.host', fn () => 'fallback-host'))->toBe('fallback-host');
});

it('throws when a namespace file does not return an array', function () {
    file_put_contents(
        $this->configPath.DIRECTORY_SEPARATOR.'db.php',
        "<?php\n\nreturn 'invalid';\n",
    );

    $config = new LazyFileConfig($this->configPath);

    expect(fn () => $config->get('db.host'))->toThrow(UnexpectedValueException::class);
});

it('throws for invalid preload namespaces', function () {
    $config = new LazyFileConfig($this->configPath);

    expect(fn () => $config->preload('invalid.namespace'))->toThrow(InvalidArgumentException::class);
});

it('supports hook-aware lazy get and set variants', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);

    $config = new LazyFileConfig($this->configPath);
    $config->onSet('db.host', fn ($value) => strtoupper((string) $value));
    $config->onGet('db.host', fn ($value) => strtolower((string) $value));

    $config->setWithHooks('db.host', 'INTERNAL');

    expect($config->get('db.host'))->toBe('INTERNAL')
        ->and($config->getWithHooks('db.host'))->toBe('internal');
});

it('supports hook-aware lazy bulk operations', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost', 'port' => 3306]);

    $config = new LazyFileConfig($this->configPath);
    $config->onSet('db.host', fn ($value) => strtoupper((string) $value));
    $config->onGet('db.host', fn ($value) => strtolower((string) $value));

    $config->setWithHooks([
        'db.host' => 'INTERNAL',
        'db.port' => 5432,
    ]);

    expect($config->getWithHooks(['db.host', 'db.port']))->toBe([
        'db.host' => 'internal',
        'db.port' => 5432,
    ]);
});

it('supports replace/reload and required key access in lazy config', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);

    $config = new LazyFileConfig($this->configPath);
    $config->get('db.host');

    $config->replace(['app' => ['name' => 'ArrayKit']]);
    expect($config->get('app.name'))->toBe('ArrayKit')
        ->and($config->getOrFail('app.name'))->toBe('ArrayKit')
        ->and(fn () => $config->getOrFail('queue.driver'))->toThrow(OutOfBoundsException::class);

    $config->reload(['cache' => ['driver' => 'file']]);
    expect($config->get('cache.driver'))->toBe('file');
});

it('treats seeded in-memory namespaces as already resolved', function () {
    lazyConfigWriteArrayFile($this->configPath, 'app', ['name' => 'from-file', 'debug' => true]);

    $config = new LazyFileConfig($this->configPath, items: [
        'app' => ['name' => 'from-memory'],
    ]);

    expect($config->loaded('app'))->toBeTrue()
        ->and($config->get('app'))->toBe(['name' => 'from-memory']);
});

it('does not re-merge namespace files after replace merge or reload with in-memory data', function () {
    lazyConfigWriteArrayFile($this->configPath, 'app', [
        'name' => 'from-file',
        'debug' => true,
    ]);

    $config = new LazyFileConfig($this->configPath);

    $config->replace([
        'app' => ['name' => 'replaced'],
    ]);
    expect($config->get('app'))->toBe(['name' => 'replaced']);

    $config->merge([
        'cache' => ['driver' => 'array'],
    ]);
    expect($config->get('cache'))->toBe(['driver' => 'array']);

    $config->reload([
        'app' => ['name' => 'reloaded'],
    ]);
    expect($config->get('app'))->toBe(['name' => 'reloaded']);
});

it('supports namespace cache warmup and fallback retrieval', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', [
        'host' => 'localhost',
        'port' => 3306,
        'options' => ['timeout' => 5],
    ]);

    $config = new LazyFileConfig($this->configPath);
    $config->namespaceCache($this->cachePath)->warmNamespaceCache('db');

    unlink($this->configPath.DIRECTORY_SEPARATOR.'db.php');

    $fresh = new LazyFileConfig($this->configPath, namespaceCacheDirectory: $this->cachePath);

    expect($fresh->get('db.host'))->toBe('localhost')
        ->and($fresh->get('db.port'))->toBe(3306)
        ->and($fresh->get('db.options'))->toBe(['timeout' => 5]);
});

it('writes a flat leaf index containing only final scalar values', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', [
        'host' => 'localhost',
        'port' => 3306,
        'options' => ['timeout' => 5],
        'replicas' => ['db-1', 'db-2'],
    ]);

    $config = new LazyFileConfig($this->configPath, namespaceCacheDirectory: $this->cachePath);
    $config->warmNamespaceCache('db');

    expect(lazyConfigFlatIndex($this->cachePath))->toBe([
        'db.host' => 'localhost',
        'db.options.timeout' => 5,
        'db.port' => 3306,
        'db.replicas.0' => 'db-1',
        'db.replicas.1' => 'db-2',
    ]);
});

it('can resolve exact scalar paths from the flat index when namespace structure is unavailable', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', [
        'host' => 'localhost',
        'options' => ['timeout' => 5],
    ]);

    $config = new LazyFileConfig($this->configPath, namespaceCacheDirectory: $this->cachePath);
    $config->warmNamespaceCache('db');

    unlink($this->configPath.DIRECTORY_SEPARATOR.'db.php');
    unlink($this->cachePath.DIRECTORY_SEPARATOR.'db.php');

    $fresh = new LazyFileConfig($this->configPath, namespaceCacheDirectory: $this->cachePath);

    expect($fresh->get('db.host'))->toBe('localhost')
        ->and($fresh->get('db.options.timeout'))->toBe(5)
        ->and($fresh->get('db.options', 'missing'))->toBe('missing')
        ->and($fresh->get('db', 'missing'))->toBe('missing');
});
