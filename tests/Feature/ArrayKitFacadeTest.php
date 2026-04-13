<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\HookedCollection;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Facade\ModuleProxy;
use Infocyph\ArrayKit\ArrayKit;

function arrayKitWriteArrayFile(string $directory, string $name, array $contents): void
{
    $export = var_export($contents, true);
    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . $name . '.php',
        "<?php\n\nreturn {$export};\n",
    );
}

function arrayKitDeleteDirectory(string $directory): void
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
            arrayKitDeleteDirectory($path);
            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}

beforeEach(function () {
    $this->configPath = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'arraykit-facade-'
        . uniqid('', true);

    mkdir($this->configPath, 0777, true);
});

afterEach(function () {
    arrayKitDeleteDirectory($this->configPath);
});

it('exposes array modules through one facade', function () {
    $single = ArrayKit::single();

    expect($single)->toBeInstanceOf(ModuleProxy::class)
        ->and(ArrayKit::single())->toBe($single)
        ->and($single->isList([1, 2, 3]))->toBeTrue()
        ->and(ArrayKit::multi()->flatten([[1], [2, [3]]]))->toBe([1, 2, 3])
        ->and(ArrayKit::helper()->wrap('x'))->toBe(['x'])
        ->and(ArrayKit::dot()->get(['a' => ['b' => 1]], 'a.b'))->toBe(1);
});

it('creates config instances from the facade', function () {
    $config = ArrayKit::config(['app' => ['name' => 'ArrayKit']]);

    expect($config)->toBeInstanceOf(Config::class)
        ->and($config->get('app.name'))->toBe('ArrayKit');
});

it('creates lazy config instances from the facade', function () {
    arrayKitWriteArrayFile($this->configPath, 'db', ['host' => 'localhost']);

    $config = ArrayKit::lazyConfig($this->configPath);

    expect($config)->toBeInstanceOf(LazyFileConfig::class)
        ->and($config->get('db.host'))->toBe('localhost');
});

it('creates collection and pipeline helpers from the facade', function () {
    $collection = ArrayKit::collection([1, 2, 3]);
    $hooked = ArrayKit::hookedCollection(['name' => 'alice']);
    $sum = ArrayKit::pipeline([1, 2, 3])->sum();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($hooked)->toBeInstanceOf(HookedCollection::class)
        ->and($sum)->toBe(6);
});
