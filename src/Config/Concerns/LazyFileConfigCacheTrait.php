<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Concerns;

use RuntimeException;
use UnexpectedValueException;

trait LazyFileConfigCacheTrait
{
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
        if ($this->namespaceCacheDirectory === null) {
            return $this;
        }

        if ($namespaces === null) {
            $this->flushAllNamespaceCacheFiles();

            return $this;
        }

        foreach ($this->resolveWarmNamespaces($namespaces) as $namespace) {
            $path = $this->cachedNamespacePath($namespace);
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }

        $this->writeFlatLeafIndexFromCacheDirectory();

        return $this;
    }

    public function namespaceCache(?string $directory): static
    {
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

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create namespace cache directory [{$directory}].");
        }

        foreach ($this->resolveWarmNamespaces($namespaces) as $namespace) {
            $this->loadNamespace($namespace);

            if (!array_key_exists($namespace, $this->items) || !is_array($this->items[$namespace])) {
                throw new UnexpectedValueException("Lazy namespace [{$namespace}] must resolve to an array to be cached.");
            }

            $export = var_export($this->items[$namespace], true);
            $path = $this->cachedNamespacePath($namespace);

            if ($path === null || file_put_contents($path, "<?php\n\nreturn {$export};\n") === false) {
                throw new RuntimeException("Unable to write namespace cache for [{$namespace}].");
            }
        }

        $this->writeFlatLeafIndexFromCacheDirectory();

        return $this;
    }

    protected function cachedNamespacePath(string $namespace): ?string
    {
        if ($this->namespaceCacheDirectory === null) {
            return null;
        }

        return $this->namespaceCacheDirectory . DIRECTORY_SEPARATOR . $namespace . '.' . $this->extension;
    }

    /**
     * @param array<array-key, mixed> $namespaceData
     * @param array<string, scalar|null> $index
     */
    protected function collectFlatLeafIndex(string $namespace, array $namespaceData, array &$index, string $prefix = ''): void
    {
        foreach ($namespaceData as $key => $value) {
            $path = $prefix === ''
                ? $namespace . '.' . $key
                : $prefix . '.' . $key;

            if (is_array($value)) {
                $this->collectFlatLeafIndex($namespace, $value, $index, $path);

                continue;
            }

            if ($this->isCacheableLeafValue($value)) {
                $this->addFlatLeafIndexValue($index, $path, $value);
            }
        }
    }

    /**
     * @return string[]
     */
    protected function discoverNamespaces(): array
    {
        $namespaces = [];

        foreach (array_keys($this->items) as $namespace) {
            if (is_string($namespace) && preg_match('/^[A-Za-z0-9_-]+$/', $namespace)) {
                $namespaces[$namespace] = true;
            }
        }

        if (!is_dir($this->directory)) {
            return array_keys($namespaces);
        }

        $entries = scandir($this->directory);
        if ($entries === false) {
            return array_keys($namespaces);
        }

        $suffix = '.' . $this->extension;
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, $suffix)) {
                continue;
            }

            $namespace = substr($entry, 0, -strlen($suffix));
            if ($namespace === '') {
                continue;
            }

            $namespaces[$this->normalizeNamespace($namespace)] = true;
        }

        return array_keys($namespaces);
    }

    protected function flatLeafIndexPath(): ?string
    {
        if ($this->namespaceCacheDirectory === null) {
            return null;
        }

        return $this->namespaceCacheDirectory . DIRECTORY_SEPARATOR . self::FLAT_INDEX_FILE;
    }

    protected function flatLeafValue(string $path): mixed
    {
        $this->loadFlatLeafIndex();

        return array_key_exists($path, $this->flatLeafIndex)
            ? $this->flatLeafIndex[$path]
            : $this->missingValueMarker();
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

        $this->flatLeafIndexLoaded = true;
        $this->flatLeafIndex = [];

        $path = $this->flatLeafIndexPath();
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return;
        }

        $loaded = include $path;
        if (!is_array($loaded)) {
            throw new UnexpectedValueException("Config file [{$path}] must return an array.");
        }

        $this->flatLeafIndex = $this->filterFlatLeafIndex($loaded);
    }

    /**
     * @param string|array<int, string>|null $namespaces
     * @return string[]
     */
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

    protected function writeFlatLeafIndexFromCacheDirectory(): void
    {
        $indexPath = $this->flatLeafIndexPath();
        $directory = $this->namespaceCacheDirectory;

        if ($indexPath === null || $directory === null) {
            return;
        }

        $index = $this->buildFlatLeafIndexFromDirectory($directory);

        ksort($index);
        if (file_put_contents($indexPath, "<?php\n\nreturn " . var_export($index, true) . ";\n") === false) {
            throw new RuntimeException('Unable to write flat lazy-config index cache.');
        }

        $this->flatLeafIndex = $index;
        $this->flatLeafIndexLoaded = true;
    }

    /**
     * @param array<string, scalar|null> $index
     */
    private function addFlatLeafIndexValue(array &$index, string $path, mixed $value): void
    {
        if (
            $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
        ) {
            $index[$path] = $value;
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function buildFlatLeafIndexFromDirectory(string $directory): array
    {
        $index = [];
        $entries = scandir($directory);
        if ($entries === false) {
            return $index;
        }

        $suffix = '.' . $this->extension;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === self::FLAT_INDEX_FILE || !str_ends_with($entry, $suffix)) {
                continue;
            }

            $namespace = substr($entry, 0, -strlen($suffix));
            if ($namespace === '') {
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $namespace)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $loaded = include $path;
            if (!is_array($loaded)) {
                throw new UnexpectedValueException("Config file [{$path}] must return an array.");
            }

            $this->collectFlatLeafIndex($namespace, $loaded, $index);
        }

        return $index;
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

            $this->addFlatLeafIndexValue($index, $key, $value);
        }

        return $index;
    }

    private function flushAllNamespaceCacheFiles(): void
    {
        $directory = $this->namespaceCacheDirectory;
        if ($directory === null) {
            return;
        }

        $entries = scandir($directory);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        $this->flatLeafIndex = [];
        $this->flatLeafIndexLoaded = false;
    }
}
