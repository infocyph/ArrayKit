<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Config\LazyFileConfig;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * PhpBench discovers this benchmark through its attributes.
 * @api
 */
#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class LazyFileConfigBench
{
    private string $cacheDirectory = '';

    private ?LazyFileConfig $flatConfig = null;

    private ?LazyFileConfig $loadedConfig = null;

    private ?LazyFileConfig $missingConfig = null;

    private ?LazyFileConfig $namespaceCacheConfig = null;

    private ?LazyFileConfig $sourceConfig = null;

    private string $sourceDirectory = '';

    private ?LazyFileConfig $warmConfig = null;

    /**
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function setUp(): void
    {
        $pid = getmypid();
        if ($pid === false) {
            throw new \RuntimeException('Unable to determine benchmark process ID.');
        }
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arraykit-lazy-bench-' . $pid;
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
        self::removePath(dirname($this->sourceDirectory));
    }

    /** @throws \LogicException */
    public function benchAlreadyLoadedNamespace(): void
    {
        ($this->loadedConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('app.nested.value');
    }

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function benchCacheWarm(): void
    {
        ($this->warmConfig ?? throw new \LogicException('Benchmark setup was not run.'))->warmNamespaceCache('app');
    }

    /** @throws \LogicException */
    public function benchFlatLeafHit(): void
    {
        ($this->flatConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('app.nested.value');
    }

    /** @throws \LogicException */
    public function benchMissingNamespace(): void
    {
        ($this->missingConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('missing.value');
    }

    /** @throws \LogicException */
    public function benchNamespaceCacheLoad(): void
    {
        ($this->namespaceCacheConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('app');
    }

    /** @throws \LogicException */
    public function benchRepeatedMissingLookup(): void
    {
        for ($index = 0; $index < 100; $index++) {
            ($this->missingConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('missing.value');
        }
    }

    /** @throws \LogicException */
    public function benchSourceLoad(): void
    {
        ($this->sourceConfig ?? throw new \LogicException('Benchmark setup was not run.'))->get('app.nested.value');
    }

    private static function removePath(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::removePath($path . DIRECTORY_SEPARATOR . $entry);
        }

        rmdir($path);
    }
}
