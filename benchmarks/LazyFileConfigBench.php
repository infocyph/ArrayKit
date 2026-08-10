<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Config\LazyFileConfig;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class LazyFileConfigBench
{
    private string $cacheDirectory = '';

    private LazyFileConfig $flatConfig;

    private LazyFileConfig $loadedConfig;

    private LazyFileConfig $missingConfig;

    private LazyFileConfig $namespaceCacheConfig;

    private LazyFileConfig $sourceConfig;

    private string $sourceDirectory = '';

    private LazyFileConfig $warmConfig;

    public function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arraykit-lazy-bench-' . getmypid();
        $this->sourceDirectory = $base . DIRECTORY_SEPARATOR . 'source';
        $this->cacheDirectory = $base . DIRECTORY_SEPARATOR . 'cache';
        mkdir($this->sourceDirectory, 0777, true);
        mkdir($this->cacheDirectory, 0777, true);
        file_put_contents(
            $this->sourceDirectory . DIRECTORY_SEPARATOR . 'app.php',
            "<?php\n\nreturn ['name' => 'ArrayKit', 'nested' => ['value' => 42]];\n",
        );

        $warmer = new LazyFileConfig($this->sourceDirectory, 'php', [], $this->cacheDirectory);
        $warmer->warmNamespaceCache('app');

        $this->flatConfig = new LazyFileConfig($this->sourceDirectory, 'php', [], $this->cacheDirectory);
        $this->namespaceCacheConfig = new LazyFileConfig($this->sourceDirectory, 'php', [], $this->cacheDirectory);
        $this->loadedConfig = new LazyFileConfig($this->sourceDirectory, 'php', [], $this->cacheDirectory);
        $this->loadedConfig->preload('app');
        $this->sourceConfig = new LazyFileConfig($this->sourceDirectory);
        $this->missingConfig = new LazyFileConfig($this->sourceDirectory);
        $this->warmConfig = new LazyFileConfig($this->sourceDirectory, 'php', [], $this->cacheDirectory);
    }

    public function tearDown(): void
    {
        foreach (['app.php', '__flat.php'] as $file) {
            $cachePath = $this->cacheDirectory . DIRECTORY_SEPARATOR . $file;
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }

        $sourcePath = $this->sourceDirectory . DIRECTORY_SEPARATOR . 'app.php';
        if (is_file($sourcePath)) {
            unlink($sourcePath);
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
        if (is_dir($this->sourceDirectory)) {
            rmdir($this->sourceDirectory);
        }

        $base = dirname($this->sourceDirectory);
        if (is_dir($base)) {
            rmdir($base);
        }
    }

    public function benchAlreadyLoadedNamespace(): void
    {
        $this->loadedConfig->get('app.nested.value');
    }

    public function benchCacheWarm(): void
    {
        $this->warmConfig->warmNamespaceCache('app');
    }

    public function benchFlatLeafHit(): void
    {
        $this->flatConfig->get('app.nested.value');
    }

    public function benchMissingNamespace(): void
    {
        $this->missingConfig->get('missing.value');
    }

    public function benchNamespaceCacheLoad(): void
    {
        $this->namespaceCacheConfig->get('app');
    }

    public function benchRepeatedMissingLookup(): void
    {
        for ($index = 0; $index < 100; $index++) {
            $this->missingConfig->get('missing.value');
        }
    }

    public function benchSourceLoad(): void
    {
        $this->sourceConfig->get('app.nested.value');
    }
}
