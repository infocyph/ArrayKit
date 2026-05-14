<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\LazyFileConfig;

function lazyConfigWriteArrayFile(string $directory, string $name, array $contents): void
{
    $export = var_export($contents, true);
    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . $name . '.php',
        "<?php\n\nreturn {$export};\n",
    );
}

function lazyConfigDeleteDirectory(string $directory): void
{
    if (!is_dir($directory)) {
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

        $path = $directory . DIRECTORY_SEPARATOR . $entry;

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

beforeEach(function () {
    $this->configPath = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'arraykit-lazy-config-'
        . uniqid('', true);

    mkdir($this->configPath, 0777, true);
});

afterEach(function () {
    lazyConfigDeleteDirectory($this->configPath);
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

it('throws when a namespace file does not return an array', function () {
    file_put_contents(
        $this->configPath . DIRECTORY_SEPARATOR . 'db.php',
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
    $config->onSet('db.host', fn($value) => strtoupper((string) $value));
    $config->onGet('db.host', fn($value) => strtolower((string) $value));

    $config->setWithHooks('db.host', 'INTERNAL');

    expect($config->get('db.host'))->toBe('INTERNAL')
        ->and($config->getWithHooks('db.host'))->toBe('internal');
});

it('supports hook-aware lazy bulk operations', function () {
    lazyConfigWriteArrayFile($this->configPath, 'db', ['host' => 'localhost', 'port' => 3306]);

    $config = new LazyFileConfig($this->configPath);
    $config->onSet('db.host', fn($value) => strtoupper((string) $value));
    $config->onGet('db.host', fn($value) => strtolower((string) $value));

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
