<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Concerns;

use RuntimeException;
use UnexpectedValueException;

/** @internal */
trait LazyFileConfigCacheTrait
{
    private const string CACHE_FLAT_INDEX_FILE = '.arraykit-flat.php';

    private const string CACHE_GENERATION_POINTER = '.arraykit-generation';

    private const string CACHE_GENERATION_PREFIX = '.arraykit-gen-';

    private const string CACHE_LOCK_FILE = '.arraykit-cache.lock';

    private const string CACHE_STAGE_PREFIX = '.arraykit-stage-';

    protected function cachedNamespacePath(string $namespace): ?string
    {
        $directory = $this->activeNamespaceCacheDirectory();
        if ($directory === null) {
            return null;
        }

        if ($directory === $this->namespaceCacheDirectory && $namespace === '__flat') {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . $namespace . '.' . $this->extension;
    }

    /**
     * @param array<array-key, mixed> $namespaceData
     * @param array<string, scalar|null> $index
     */
    protected function collectFlatLeafIndex(string $namespace, array $namespaceData, array &$index, string $prefix = ''): void
    {
        foreach ($namespaceData as $key => $value) {
            if (!$this->isFlatPathSafeSegment((string) $key)) {
                continue;
            }

            $path = $prefix === ''
                ? $namespace . '.' . $key
                : $prefix . '.' . $key;

            if (is_array($value)) {
                $this->collectFlatLeafIndex($namespace, $value, $index, $path);

                continue;
            }

            if ($value === null || is_scalar($value)) {
                $index[$path] = $value;
            }
        }
    }

    /** @return string[] */
    protected function discoverNamespaces(): array
    {
        $namespaces = [];

        foreach ($this->items as $namespace => $_) {
            if (is_string($namespace) && preg_match('/^[A-Za-z0-9_-]+$/', $namespace) === 1) {
                $namespaces[$namespace] = true;
            }
        }

        $this->discoverNamespacesInDirectory($this->directory, $namespaces, false);

        $cacheDirectory = $this->activeNamespaceCacheDirectory();
        if ($cacheDirectory !== null) {
            $this->discoverNamespacesInDirectory(
                $cacheDirectory,
                $namespaces,
                $cacheDirectory === $this->namespaceCacheDirectory,
            );
        }

        return array_keys($namespaces);
    }





    protected function flatLeafIndexPath(): ?string
    {
        $directory = $this->activeNamespaceCacheDirectory();
        if ($directory === null) {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . self::CACHE_FLAT_INDEX_FILE;
    }

    protected function flatLeafValue(string $path): mixed
    {
        $this->loadFlatLeafIndex();

        return array_key_exists($path, $this->flatLeafIndex)
            ? $this->flatLeafIndex[$path]
            : $this->missingValueMarker();
    }

    protected function invalidateGeneratedNamespaceState(): void
    {
        foreach ($this->loadedNamespaceOrigins as $namespace => $origin) {
            if ($origin === 'cache') {
                unset($this->items[$namespace]);
            }

            if ($origin === 'cache' || $origin === 'missing') {
                unset($this->loadedNamespaces[$namespace], $this->loadedNamespaceOrigins[$namespace]);
            }
        }

        $this->flushReadCache();
    }

    protected function isCacheableLeafValue(mixed $value): bool
    {
        return $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value);
    }

    protected function isEligibleFlatLookupPath(string $path): bool
    {
        return str_contains($path, '.')
            && !str_contains($path, '*')
            && !str_contains($path, '\\')
            && !str_contains($path, '{');
    }

    protected function loadFlatLeafIndex(): void
    {
        if ($this->flatLeafIndexLoaded) {
            return;
        }

        $this->flatLeafIndex = [];
        $path = $this->flatLeafIndexPath();

        if ($path !== null && is_file($path) && is_readable($path)) {
            try {
                $loaded = include $path;
                if (is_array($loaded)) {
                    $this->flatLeafIndex = $this->filterFlatLeafIndex($loaded);
                }
            } catch (\Throwable) {
                // Generated indexes are disposable acceleration artifacts.
            }
        }

        $this->flatLeafIndexLoaded = true;
    }

    /**
     * @param string|array<int, string>|null $namespaces
     * @return string[]
     */
    /**
     * @return array<array-key, mixed>
     */
    protected function namespaceCacheWarmValue(string $namespace): array
    {
        if (
            ($this->loadedNamespaceOrigins[$namespace] ?? null) === 'runtime'
            && array_key_exists($namespace, $this->items)
        ) {
            $value = $this->items[$namespace];
            if (!is_array($value)) {
                throw new UnexpectedValueException("Lazy namespace [{$namespace}] must resolve to an array to be cached.");
            }

            return $value;
        }

        $sourceFile = $this->resolveNamespaceFile($namespace);
        if ($sourceFile !== null) {
            $value = include $sourceFile;
            if (!is_array($value)) {
                throw new UnexpectedValueException("Config file [{$sourceFile}] must return an array.");
            }

            return $value;
        }

        $cachedFile = $this->resolveCachedNamespaceFile($namespace);
        if ($cachedFile !== null) {
            $value = include $cachedFile;
            if (!is_array($value)) {
                throw new UnexpectedValueException("Config file [{$cachedFile}] must return an array.");
            }

            return $value;
        }

        if (array_key_exists($namespace, $this->items) && is_array($this->items[$namespace])) {
            return $this->items[$namespace];
        }

        throw new UnexpectedValueException("Lazy namespace [{$namespace}] must resolve to an array to be cached.");
    }

    protected function resolveWarmNamespaces(string|array|null $namespaces): array
    {
        if ($namespaces === null) {
            return $this->discoverNamespaces();
        }

        $resolved = [];
        foreach ((array) $namespaces as $namespace) {
            $resolved[] = $this->normalizeNamespace($namespace);
        }

        return array_values(array_unique($resolved));
    }



    private function activateGeneration(string $stage): void
    {
        $root = $this->namespaceCacheDirectory;
        if ($root === null) {
            throw new RuntimeException('Namespace cache directory is not configured.');
        }

        $generation = self::CACHE_GENERATION_PREFIX . bin2hex(random_bytes(8));
        $destination = $root . DIRECTORY_SEPARATOR . $generation;

        if (!rename($stage, $destination)) {
            throw new RuntimeException('Unable to publish lazy-config cache generation.');
        }

        try {
            $this->writeGenerationPointer($generation);
        } catch (\Throwable $error) {
            $this->removeGenerationDirectory($destination);

            throw $error;
        }
    }

    private function activeNamespaceCacheDirectory(): ?string
    {
        $root = $this->namespaceCacheDirectory;
        if ($root === null || !is_dir($root)) {
            return null;
        }

        $pointer = $this->generationPointerPath();
        if ($pointer === null || !is_file($pointer) || !is_readable($pointer)) {
            return $root;
        }

        $generation = trim((string) file_get_contents($pointer));
        if (preg_match('/^\\.arraykit-gen-[a-f0-9]+$/', $generation) !== 1) {
            return $root;
        }

        $directory = $root . DIRECTORY_SEPARATOR . $generation;

        return is_dir($directory) ? $directory : $root;
    }

    /** @return array<string, scalar|null> */
    private function buildFlatLeafIndexFromDirectory(string $directory): array
    {
        $index = [];

        foreach ($this->namespaceCacheEntries($directory, false) as [$namespace, $path]) {
            try {
                $loaded = include $path;
            } catch (\Throwable) {
                continue;
            }

            if (is_array($loaded)) {
                $this->collectFlatLeafIndex($namespace, $loaded, $index);
            }
        }

        return $index;
    }

    /**
     * @param array<string, true> $excluded
     */
    private function copyActiveNamespaceCacheFiles(string $stage, array $excluded): void
    {
        $active = $this->activeNamespaceCacheDirectory();
        if ($active === null) {
            return;
        }

        $legacy = $active === $this->namespaceCacheDirectory;

        foreach ($this->namespaceCacheEntries($active, $legacy) as [$namespace, $path]) {
            if (isset($excluded[$namespace])) {
                continue;
            }

            $destination = $stage . DIRECTORY_SEPARATOR . basename($path);
            if (!copy($path, $destination)) {
                throw new RuntimeException("Unable to copy namespace cache for [{$namespace}].");
            }
        }
    }







    private function createGenerationStage(): string
    {
        $root = $this->namespaceCacheDirectory;
        if ($root === null) {
            throw new RuntimeException('Namespace cache directory is not configured.');
        }

        $stage = $root . DIRECTORY_SEPARATOR . self::CACHE_STAGE_PREFIX . bin2hex(random_bytes(8));
        if (!mkdir($stage, 0755)) {
            throw new RuntimeException('Unable to create lazy-config cache staging directory.');
        }

        return $stage;
    }

    /**
     * @param array<string, true> $namespaces
     */
    private function discoverNamespacesInDirectory(string $directory, array &$namespaces, bool $legacy): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach ($this->namespaceCacheEntries($directory, $legacy) as [$namespace]) {
            $namespaces[$namespace] = true;
        }
    }

    /**
     * @param array<array-key, mixed> $loaded
     * @return array<string, scalar|null>
     */
    private function filterFlatLeafIndex(array $loaded): array
    {
        $index = [];

        foreach ($loaded as $key => $value) {
            if (!is_string($key) || !$this->isCacheableLeafValue($value)) {
                continue;
            }

            if ($value === null || is_scalar($value)) {
                $index[$key] = $value;
            }
        }

        return $index;
    }





    private function generationPointerPath(): ?string
    {
        return $this->namespaceCacheDirectory === null
            ? null
            : $this->namespaceCacheDirectory . DIRECTORY_SEPARATOR . self::CACHE_GENERATION_POINTER;
    }

    private function isFlatPathSafeSegment(string $segment): bool
    {
        return !str_contains($segment, '.')
            && !str_contains($segment, '\\')
            && !str_contains($segment, '*')
            && !str_contains($segment, '{');
    }

    /**
     * @return array<int, array{0:string, 1:string}>
     */
    private function namespaceCacheEntries(string $directory, bool $legacy): array
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $resolved = [];
        $suffix = '.' . $this->extension;

        foreach ($entries as $entry) {
            if (!str_ends_with($entry, $suffix) || $entry === self::CACHE_FLAT_INDEX_FILE) {
                continue;
            }

            $namespace = substr($entry, 0, -strlen($suffix));
            if (
                $namespace === ''
                || preg_match('/^[A-Za-z0-9_-]+$/', $namespace) !== 1
                || ($legacy && $namespace === '__flat')
            ) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) && is_readable($path)) {
                $resolved[] = [$namespace, $path];
            }
        }

        return $resolved;
    }

    /**
     * @param string[]|null $namespaces
     */
    private function publishFlushGeneration(?array $namespaces): void
    {
        $stage = $this->createGenerationStage();

        try {
            if ($namespaces !== null) {
                $this->copyActiveNamespaceCacheFiles($stage, array_fill_keys($namespaces, true));
            }

            $this->writeGenerationFlatIndex($stage);
            $this->activateGeneration($stage);
        } catch (\Throwable $error) {
            if (is_dir($stage)) {
                $this->removeGenerationDirectory($stage);
            }

            throw $error;
        }
    }

    /**
     * @param string[] $namespaces
     */
    private function publishWarmGeneration(array $namespaces): void
    {
        $stage = $this->createGenerationStage();

        try {
            $this->copyActiveNamespaceCacheFiles($stage, array_fill_keys($namespaces, true));

            foreach ($namespaces as $namespace) {
                $value = $this->materializeCacheValue($this->namespaceCacheWarmValue($namespace));
                if (!is_array($value)) {
                    throw new UnexpectedValueException("Lazy namespace [{$namespace}] must resolve to an array to be cached.");
                }

                $path = $stage . DIRECTORY_SEPARATOR . $namespace . '.' . $this->extension;
                $export = var_export($value, true);
                if (!$this->writeCacheFile($path, "<?php\n\nreturn {$export};\n")) {
                    throw new RuntimeException("Unable to write namespace cache for [{$namespace}].");
                }
            }

            $this->writeGenerationFlatIndex($stage);
            $this->activateGeneration($stage);
        } catch (\Throwable $error) {
            if (is_dir($stage)) {
                $this->removeGenerationDirectory($stage);
            }

            throw $error;
        }
    }





    /**
     * @var array<string, scalar|null>
     */
    protected array $flatLeafIndex = [];

    protected bool $flatLeafIndexLoaded = false;

    protected ?string $namespaceCacheDirectory = null;

    /**
     * @param string|array<int, string>|null $namespaces
     */
    public function flushNamespaceCache(string|array|null $namespaces = null): static
    {
        $directory = $this->namespaceCacheDirectory;
        if ($directory === null) {
            return $this;
        }

        if (!is_dir($directory)) {
            $this->flatLeafIndex = [];
            $this->flatLeafIndexLoaded = false;
            $this->invalidateGeneratedNamespaceState();

            return $this;
        }

        $resolved = $namespaces === null ? null : $this->resolveWarmNamespaces($namespaces);

        $this->withNamespaceCacheLock(function () use ($resolved): void {
            $this->publishFlushGeneration($resolved);
        });

        $this->flatLeafIndex = [];
        $this->flatLeafIndexLoaded = false;
        $this->invalidateGeneratedNamespaceState();

        return $this;
    }

    public function namespaceCache(?string $directory): static
    {
        $this->invalidateGeneratedNamespaceState();

        $this->namespaceCacheDirectory = $directory !== null
            ? rtrim($directory, DIRECTORY_SEPARATOR)
            : null;
        $this->flatLeafIndex = [];
        $this->flatLeafIndexLoaded = false;

        return $this;
    }

    public function namespaceCacheDirectory(): ?string
    {
        return $this->namespaceCacheDirectory;
    }

    /**
     * @param string|array<int, string>|null $namespaces
     */
    public function warmNamespaceCache(string|array|null $namespaces = null): static
    {
        $directory = $this->namespaceCacheDirectory;
        if ($directory === null) {
            throw new RuntimeException('Namespace cache directory is not configured.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create namespace cache directory [{$directory}].");
        }

        $resolved = $this->resolveWarmNamespaces($namespaces);

        $this->withNamespaceCacheLock(function () use ($resolved): void {
            $this->publishWarmGeneration($resolved);
        });

        $this->flatLeafIndex = [];
        $this->flatLeafIndexLoaded = false;
        $this->invalidateGeneratedNamespaceState();

        return $this;
    }

    private function removeGenerationDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function withNamespaceCacheLock(\Closure $operation): static
    {
        $directory = $this->namespaceCacheDirectory;
        if ($directory === null) {
            throw new RuntimeException('Namespace cache directory is not configured.');
        }

        $lock = fopen($directory . DIRECTORY_SEPARATOR . self::CACHE_LOCK_FILE, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Unable to open lazy-config cache lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire lazy-config cache lock.');
            }

            $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $this;
    }

    private function writeGenerationFlatIndex(string $directory): void
    {
        $index = $this->buildFlatLeafIndexFromDirectory($directory);
        ksort($index);

        $path = $directory . DIRECTORY_SEPARATOR . self::CACHE_FLAT_INDEX_FILE;
        if (!$this->writeCacheFile($path, "<?php\n\nreturn " . var_export($index, true) . ";\n")) {
            throw new RuntimeException('Unable to write flat lazy-config index cache.');
        }
    }

    private function writeGenerationPointer(string $generation): void
    {
        $path = $this->generationPointerPath();
        if ($path === null) {
            throw new RuntimeException('Namespace cache directory is not configured.');
        }

        $temporary = tempnam(dirname($path), '.arraykit-pointer-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create lazy-config generation pointer.');
        }

        $contents = $generation . PHP_EOL;
        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            unlink($temporary);

            throw new RuntimeException('Unable to write lazy-config generation pointer.');
        }

        if (!rename($temporary, $path)) {
            unlink($temporary);

            throw new RuntimeException('Unable to publish lazy-config generation pointer.');
        }
    }
}
