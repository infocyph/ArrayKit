<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\ConfigMerge;
use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;
use Infocyph\ArrayKit\Config\ResilientLazyFileConfig;

function configLayeringWrite(string $directory, string $namespace, array $values): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . $namespace . '.php',
        "<?php\n\nreturn " . var_export($values, true) . ";\n",
    );
}

function configLayeringRemove(string $path): void
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

        configLayeringRemove($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
}

beforeEach(function () {
    $this->layerConfigPath = sys_get_temp_dir() . '/arraykit-layer-source-' . bin2hex(random_bytes(5));
    $this->layerCachePath = sys_get_temp_dir() . '/arraykit-layer-cache-' . bin2hex(random_bytes(5));
    mkdir($this->layerConfigPath, 0777, true);
    mkdir($this->layerCachePath, 0777, true);
});

afterEach(function () {
    configLayeringRemove($this->layerConfigPath);
    configLayeringRemove($this->layerCachePath);
});

it('recursively merges maps while replacing lists atomically', function () {
    $merged = ConfigMerge::merge(
        [
            'app' => ['name' => 'ArrayKit', 'debug' => false],
            'middleware' => ['auth', 'csrf'],
            'cache' => ['driver' => 'file'],
        ],
        [
            'app' => ['debug' => true],
            'middleware' => ['api'],
            'cache' => false,
        ],
    );

    expect($merged)->toBe([
        'app' => ['name' => 'ArrayKit', 'debug' => true],
        'middleware' => ['api'],
        'cache' => false,
    ]);
});

it('merges many configuration layers in order', function () {
    expect(ConfigMerge::mergeMany([
        ['app' => ['name' => 'base'], 'hosts' => ['a', 'b']],
        ['app' => ['debug' => false]],
        ['app' => ['name' => 'override'], 'hosts' => ['x']],
    ]))->toBe([
        'app' => ['name' => 'override', 'debug' => false],
        'hosts' => ['x'],
    ]);
});

it('keeps exact layered reads equivalent to fully merged namespace reads', function () {
    configLayeringWrite($this->layerConfigPath, 'app', [
        'servers' => ['a', 'b'],
        'cache' => ['driver' => 'redis'],
        'nested' => ['source' => true],
    ]);

    $config = new LayeredLazyFileConfig(
        directory: $this->layerConfigPath,
        fallback: [
            'app' => [
                'fallback' => true,
                'nested' => ['fallback' => true],
            ],
        ],
        overrides: [
            'app' => [
                'servers' => ['x'],
                'cache' => false,
                'nested' => ['override' => true],
            ],
        ],
        namespaces: ['app'],
    );

    expect($config->get('app.servers'))->toBe(['x'])
        ->and($config->get('app.servers.1', 'missing'))->toBe('missing')
        ->and($config->get('app.cache.driver', 'missing'))->toBe('missing')
        ->and($config->get('app.fallback'))->toBeTrue()
        ->and($config->get('app.nested'))->toBe([
            'fallback' => true,
            'source' => true,
            'override' => true,
        ]);
});

it('materializes layered namespaces with fallback source override precedence', function () {
    configLayeringWrite($this->layerConfigPath, 'db', [
        'host' => 'source',
        'options' => ['timeout' => 5],
    ]);

    $config = new LayeredLazyFileConfig(
        directory: $this->layerConfigPath,
        fallback: ['db' => ['host' => 'fallback', 'port' => 3306]],
        overrides: ['db' => ['host' => 'override']],
        namespaces: ['db'],
    );

    expect($config->all())->toBe([
        'db' => [
            'host' => 'override',
            'port' => 3306,
            'options' => ['timeout' => 5],
        ],
    ]);
});

it('falls back to source when a generated namespace cache returns invalid data', function () {
    configLayeringWrite($this->layerConfigPath, 'db', ['host' => 'source']);
    file_put_contents($this->layerCachePath . '/db.php', "<?php\n\nreturn 'invalid';\n");

    $config = new ResilientLazyFileConfig(
        $this->layerConfigPath,
        namespaceCacheDirectory: $this->layerCachePath,
    );

    expect($config->get('db.host'))->toBe('source');
});

it('falls back to source when generated cache php is malformed', function () {
    configLayeringWrite($this->layerConfigPath, 'db', ['host' => 'source']);
    file_put_contents($this->layerCachePath . '/db.php', "<?php\nreturn [;\n");
    file_put_contents($this->layerCachePath . '/__flat.php', "<?php\nreturn [;\n");

    $config = new ResilientLazyFileConfig(
        $this->layerConfigPath,
        namespaceCacheDirectory: $this->layerCachePath,
    );

    expect($config->get('db.host'))->toBe('source');
});

it('does not hide invalid source configuration behind resilient cache behavior', function () {
    file_put_contents($this->layerConfigPath . '/db.php', "<?php\n\nreturn 'invalid-source';\n");
    file_put_contents($this->layerCachePath . '/db.php', "<?php\n\nreturn 'invalid-cache';\n");

    $config = new ResilientLazyFileConfig(
        $this->layerConfigPath,
        namespaceCacheDirectory: $this->layerCachePath,
    );

    expect(fn () => $config->get('db.host'))->toThrow(UnexpectedValueException::class);
});
