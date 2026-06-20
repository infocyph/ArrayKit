<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Config\Concerns\LazyFileConfigCacheTrait;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class LazyFileConfig extends Config
{
    use LazyFileConfigCacheTrait;

    private const string FLAT_INDEX_FILE = '__flat.php';

    /**
     * @var array<string, bool>
     */
    protected array $loadedNamespaces = [];

    /**
     * @param array<array-key, mixed> $items
     */
    public function __construct(
        protected string $directory,
        protected string $extension = 'php',
        array $items = [],
        ?string $namespaceCacheDirectory = null,
    ) {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->extension = ltrim($extension, '.');
        $this->items = $items;
        $this->namespaceCacheDirectory = $namespaceCacheDirectory !== null
            ? rtrim($namespaceCacheDirectory, DIRECTORY_SEPARATOR)
            : null;
        $this->syncLoadedNamespacesFromItems();
    }

    #[\Override]
    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        throw new RuntimeException('LazyFileConfig does not support full config retrieval. At least one key is required.');
    }

    #[\Override]
    /**
     * @param string|array<array-key, mixed> $key
     */
    public function fill(string|array $key, mixed $value = null): bool
    {
        $this->assertWritable();
        $this->flushReadCache();

        if (is_array($key)) {
            foreach ($key as $path => $entry) {
                if (!is_string($path)) {
                    throw new InvalidArgumentException('Fill keys must be dot-notation strings.');
                }

                $this->setPath($path, $entry, false);
            }
        } else {
            $this->setPath($key, $value, false);
        }

        return true;
    }

    #[\Override]
    /**
     * @param string|int|array<int, string|int> $key
     */
    public function forget(string|int|array $key): bool
    {
        $this->assertWritable();
        $this->flushReadCache();

        if (is_array($key)) {
            foreach ($key as $path) {
                $this->forgetPath((string) $path);
            }
        } else {
            $this->forgetPath((string) $key);
        }

        return true;
    }

    #[\Override]
    /**
     * @param string|int|array<int, string|int>|null $key
     */
    public function get(string|int|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            throw new RuntimeException('At least one key is required for LazyFileConfig::get().');
        }

        if (is_int($key)) {
            throw new InvalidArgumentException('Config key must be a dot-notation string.');
        }

        if (is_array($key)) {
            if ($key === []) {
                throw new RuntimeException('At least one key is required for LazyFileConfig::get().');
            }

            $results = [];
            foreach ($key as $path) {
                $results[(string) $path] = $this->getResolvedValue((string) $path, $default);
            }

            return $results;
        }

        return $this->getResolvedValue($key, $default);
    }

    #[\Override]
    /**
     * @param string|array<int, string> $keys
     */
    public function has(string|array $keys): bool
    {
        $keys = (array) $keys;
        if ($keys === []) {
            return false;
        }

        return array_all($keys, fn($path) => $this->hasResolvedValue($path));
    }

    #[\Override]
    /**
     * @param string|array<int, string> $keys
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = (array) $keys;
        if ($keys === []) {
            return false;
        }

        return array_any($keys, fn($path) => $this->hasResolvedValue($path));
    }

    /**
     * Returns true if the namespace has already been resolved (file found or missing).
     */
    public function isLoaded(string $namespace): bool
    {
        return isset($this->loadedNamespaces[$this->normalizeNamespace($namespace)]);
    }

    #[\Override]
    /**
     * @param array<array-key, mixed> $resource
     */
    public function loadArray(array $resource): bool
    {
        $loaded = parent::loadArray($resource);
        if ($loaded) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $loaded;
    }

    /**
     * Alias of isLoaded().
     */
    public function loaded(string $namespace): bool
    {
        return $this->isLoaded($namespace);
    }

    /**
     * @return string[] List of namespaces already resolved.
     */
    public function loadedNamespaces(): array
    {
        return array_keys($this->loadedNamespaces);
    }

    #[\Override]
    public function loadFile(string $path): bool
    {
        $loaded = parent::loadFile($path);
        if ($loaded) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $loaded;
    }

    #[\Override]
    /**
     * @param array<array-key, mixed> $items
     */
    public function merge(array $items): bool
    {
        $merged = parent::merge($items);
        if ($merged) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $merged;
    }

    /**
     * Preload one or multiple top-level config namespaces.
     *
     * @param string|array<int, string> $namespaces Namespace (e.g. "db") or list of namespaces.
     */
    public function preload(string|array $namespaces): static
    {
        foreach ((array) $namespaces as $namespace) {
            $this->loadNamespace($this->normalizeNamespace($namespace));
        }

        return $this;
    }

    #[\Override]
    /**
     * @param array<array-key, mixed>|string $source
     */
    public function reload(array|string $source): bool
    {
        $this->assertWritable();
        $reloaded = parent::reload($source);
        if ($reloaded) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $reloaded;
    }

    #[\Override]
    /**
     * @param array<array-key, mixed> $items
     */
    public function replace(array $items): bool
    {
        $this->assertWritable();
        $replaced = parent::replace($items);
        if ($replaced) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $replaced;
    }

    #[\Override]
    public function restore(string $name = 'default'): bool
    {
        $restored = parent::restore($name);
        if ($restored) {
            $this->syncLoadedNamespacesFromItems();
        }

        return $restored;
    }

    #[\Override]
    /**
     * @param string|array<array-key, mixed>|null $key
     */
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        $this->assertWritable();
        $this->flushReadCache();

        if ($key === null) {
            throw new RuntimeException('At least one key is required for LazyFileConfig::set().');
        }

        if (is_array($key)) {
            foreach ($key as $path => $entry) {
                if (!is_string($path)) {
                    throw new InvalidArgumentException('Set keys must be dot-notation strings.');
                }

                $this->setPath($path, $entry, $overwrite);
            }
        } else {
            $this->setPath($key, $value, $overwrite);
        }

        return true;
    }

    protected function forgetPath(string $path): void
    {
        [$namespace, $rest] = $this->splitPath($path);
        $this->loadNamespace($namespace);

        if (!array_key_exists($namespace, $this->items)) {
            return;
        }

        if ($rest === null || $rest === '') {
            unset($this->items[$namespace]);

            return;
        }

        if (!is_array($this->items[$namespace])) {
            return;
        }

        DotNotation::forget($this->items[$namespace], $rest);
    }

    protected function getPath(string $path, mixed $default): mixed
    {
        [$namespace, $rest] = $this->splitPath($path);
        $this->loadNamespace($namespace);

        if (!array_key_exists($namespace, $this->items)) {
            return $this->resolveDefault($default);
        }

        if ($rest === null || $rest === '') {
            return $this->items[$namespace];
        }

        if (!is_array($this->items[$namespace])) {
            return $this->resolveDefault($default);
        }

        return DotNotation::get($this->items[$namespace], $rest, $default);
    }

    protected function hasPath(string $path): bool
    {
        [$namespace, $rest] = $this->splitPath($path);
        $this->loadNamespace($namespace);

        if (!array_key_exists($namespace, $this->items)) {
            return false;
        }

        if ($rest === null || $rest === '') {
            return true;
        }

        if (!is_array($this->items[$namespace])) {
            return false;
        }

        return DotNotation::has($this->items[$namespace], $rest);
    }

    protected function loadNamespace(string $namespace): void
    {
        if (isset($this->loadedNamespaces[$namespace])) {
            return;
        }

        $this->loadedNamespaces[$namespace] = true;

        $file = $this->resolveCachedNamespaceFile($namespace) ?? $this->resolveNamespaceFile($namespace);
        if ($file === null) {
            return;
        }

        $loaded = include $file;
        if (!is_array($loaded)) {
            throw new UnexpectedValueException("Config file [{$file}] must return an array.");
        }

        if (!array_key_exists($namespace, $this->items)) {
            $this->items[$namespace] = $loaded;

            return;
        }

        if (is_array($this->items[$namespace])) {
            $this->items[$namespace] = array_replace_recursive($loaded, $this->items[$namespace]);
        }
    }

    protected function normalizeNamespace(string $namespace): string
    {
        $trimmed = trim($namespace);
        if ($trimmed === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
            throw new InvalidArgumentException("Invalid config namespace [{$namespace}].");
        }

        return $trimmed;
    }

    protected function resolveCachedNamespaceFile(string $namespace): ?string
    {
        $path = $this->cachedNamespacePath($namespace);

        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        return $path;
    }

    protected function resolveLazyRawValue(string $path): mixed
    {
        [$namespace, $rest] = $this->splitPath($path);

        if (array_key_exists($namespace, $this->items)) {
            if ($rest === null || $rest === '') {
                return $this->items[$namespace];
            }

            if (!is_array($this->items[$namespace])) {
                return $this->missingValueMarker();
            }

            return DotNotation::get($this->items[$namespace], $rest, $this->missingValueMarker());
        }

        if ($this->isLoaded($namespace)) {
            return $this->missingValueMarker();
        }

        if ($rest !== null && $this->isEligibleFlatLookupPath($path)) {
            $flatValue = $this->flatLeafValue($path);
            if ($flatValue !== $this->missingValueMarker()) {
                return $flatValue;
            }
        }

        $this->loadNamespace($namespace);

        if (!array_key_exists($namespace, $this->items)) {
            return $this->missingValueMarker();
        }

        if ($rest === null || $rest === '') {
            return $this->items[$namespace];
        }

        if (!is_array($this->items[$namespace])) {
            return $this->missingValueMarker();
        }

        return DotNotation::get($this->items[$namespace], $rest, $this->missingValueMarker());
    }

    protected function resolveNamespaceFile(string $namespace): ?string
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . $namespace . '.' . $this->extension;

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        return $file;
    }

    #[\Override]
    protected function resolveRawValue(int|string $key): mixed
    {
        if (!is_string($key)) {
            return parent::resolveRawValue($key);
        }

        if (!$this->readCacheEnabled()) {
            return $this->resolveLazyRawValue($key);
        }

        $cacheKey = $this->valueCacheKey($key);
        if (array_key_exists($cacheKey, $this->resolvedValueCache)) {
            return $this->resolvedValueCache[$cacheKey];
        }

        return $this->resolvedValueCache[$cacheKey] = $this->resolveLazyRawValue($key);
    }

    protected function setPath(string $path, mixed $value, bool $overwrite): void
    {
        [$namespace, $rest] = $this->splitPath($path);
        $this->loadNamespace($namespace);

        if ($rest === null || $rest === '') {
            if ($overwrite || !array_key_exists($namespace, $this->items)) {
                $this->items[$namespace] = $value;
            }

            return;
        }

        $namespaceConfig = $this->items[$namespace] ?? [];
        if (!is_array($namespaceConfig)) {
            $namespaceConfig = [];
        }

        DotNotation::set($namespaceConfig, $rest, $value, $overwrite);
        $this->items[$namespace] = $namespaceConfig;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function splitPath(string $path): array
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Config key must not be empty.');
        }

        $dotPosition = strpos($trimmed, '.');
        if ($dotPosition === false) {
            $namespace = $this->normalizeNamespace($trimmed);

            return [$namespace, null];
        }

        $namespace = $this->normalizeNamespace(substr($trimmed, 0, $dotPosition));
        $rest = substr($trimmed, $dotPosition + 1);

        return [$namespace, $rest === '' ? null : $rest];
    }

    protected function syncLoadedNamespacesFromItems(): void
    {
        parent::flushReadCache();
        $this->loadedNamespaces = [];

        foreach (array_keys($this->items) as $namespace) {
            if (!is_string($namespace) || !preg_match('/^[A-Za-z0-9_-]+$/', $namespace)) {
                continue;
            }

            $this->loadedNamespaces[$namespace] = true;
        }
    }
}
