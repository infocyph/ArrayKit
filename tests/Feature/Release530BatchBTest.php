<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Config\Support\Environment;

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
