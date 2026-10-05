<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

/**
 * Layered lazy configuration with explicit precedence:
 * fallback < lazy source < overrides < runtime mutations.
 *
 * Namespace materialization is used deliberately so exact-path reads always
 * match reads against the fully merged configuration, including list/scalar
 * shadowing semantics.
 */
class LayeredLazyFileConfig extends Config
{
    private readonly ResilientLazyFileConfig $source;

    /** @var array<string, true> */
    private array $knownNamespaces = [];

    /** @var array<string, true> */
    private array $materializedNamespaces = [];

    /**
     * @param array<string, mixed> $fallback
     * @param array<string, mixed> $overrides
     * @param list<string> $namespaces
     */
    public function __construct(
        string $directory,
        ?string $namespaceCacheDirectory = null,
        private array $fallback = [],
        private array $overrides = [],
        array $namespaces = [],
        string $extension = 'php',
    ) {
        $this->source = new ResilientLazyFileConfig(
            directory: $directory,
            extension: $extension,
            namespaceCacheDirectory: $namespaceCacheDirectory,
        );

        foreach ([...array_keys($this->fallback), ...array_keys($this->overrides), ...$namespaces] as $namespace) {
            if ($namespace !== '') {
                $this->knownNamespaces[$namespace] = true;
            }
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function all(): array
    {
        $this->materializeKnownNamespaces();

        $items = [];
        foreach (parent::all() as $key => $value) {
            if (is_string($key)) {
                $items[$key] = $value;
            }
        }

        return $items;
    }

    #[\Override]
    public function changed(string $snapshot = 'default'): bool
    {
        $this->materializeKnownNamespaces();

        return parent::changed($snapshot);
    }

    public function clearNamespaceCache(): static
    {
        $this->source->flushNamespaceCache();

        return $this;
    }

    #[\Override]
    public function exportCache(string $path): bool
    {
        $this->materializeKnownNamespaces();

        return parent::exportCache($path);
    }

    #[\Override]
    public function fill(string|array $key, mixed $value = null): bool
    {
        $this->materializeMutationTargets($key);

        return parent::fill($key, $value);
    }

    #[\Override]
    public function forget(string|int|array $key): bool
    {
        $this->materializeMutationTargets($key);

        return parent::forget($key);
    }

    #[\Override]
    public function get(string|int|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->all();
        }

        return parent::get($key, $default);
    }

    #[\Override]
    public function loadArray(array $resource): bool
    {
        $this->materializeKnownNamespaces();

        return parent::loadArray($resource);
    }

    #[\Override]
    public function loadFile(string $path): bool
    {
        $this->materializeKnownNamespaces();

        return parent::loadFile($path);
    }

    #[\Override]
    public function merge(array $items): bool
    {
        $this->materializeMutationTargets($items);

        return parent::merge($items);
    }

    public function namespaceCacheDirectory(): ?string
    {
        return $this->source->namespaceCacheDirectory();
    }

    #[\Override]
    public function overlay(array $overlay): bool
    {
        return $this->merge($overlay);
    }

    #[\Override]
    public function reload(array|string $source): bool
    {
        $result = parent::reload($source);
        if ($result) {
            $this->markAllKnownNamespacesMaterialized();
            $this->registerNamespaces($this->items);
        }

        return $result;
    }

    #[\Override]
    public function replace(array $items): bool
    {
        $result = parent::replace($items);
        if ($result) {
            $this->markAllKnownNamespacesMaterialized();
            $this->registerNamespaces($items);
        }

        return $result;
    }

    #[\Override]
    public function restore(string $name = 'default'): bool
    {
        $restored = parent::restore($name);
        if ($restored) {
            $this->markAllKnownNamespacesMaterialized();
            $this->registerNamespaces($this->items);
        }

        return $restored;
    }

    #[\Override]
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        if ($key === null) {
            $this->materializeKnownNamespaces();
            $result = parent::set($key, $value, $overwrite);
            if ($result) {
                $this->markAllKnownNamespacesMaterialized();
                $this->registerNamespaces($this->items);
            }

            return $result;
        }

        $this->materializeMutationTargets($key);

        return parent::set($key, $value, $overwrite);
    }

    #[\Override]
    public function snapshot(string $name = 'default'): bool
    {
        $this->materializeKnownNamespaces();

        return parent::snapshot($name);
    }

    /**
     * @param string|array<int, string>|null $namespaces
     */
    public function warmNamespaceCache(string|array|null $namespaces = null): static
    {
        $this->source->warmNamespaceCache($namespaces ?? array_keys($this->knownNamespaces));

        return $this;
    }

    #[\Override]
    protected function resolveRawValue(int|string $key): mixed
    {
        if (!is_string($key) || $key === '') {
            return parent::resolveRawValue($key);
        }

        $namespace = $this->namespaceFromPath($key);
        $this->materializeNamespace($namespace);

        return parent::resolveRawValue($key);
    }

    private function markAllKnownNamespacesMaterialized(): void
    {
        foreach (array_keys($this->knownNamespaces) as $namespace) {
            $this->materializedNamespaces[$namespace] = true;
        }
    }

    private function materializeKnownNamespaces(): void
    {
        foreach (array_keys($this->knownNamespaces) as $namespace) {
            $this->materializeNamespace($namespace);
        }
    }

    /**
     * @param string|int|array<array-key, mixed> $targets
     */
    private function materializeMutationTargets(string|int|array $targets): void
    {
        if (is_array($targets)) {
            foreach ($targets as $key => $value) {
                $path = is_int($key) ? $value : $key;
                if (is_int($path) || is_string($path)) {
                    $this->materializeNamespace($this->namespaceFromPath((string) $path));
                }
            }

            return;
        }

        $this->materializeNamespace($this->namespaceFromPath((string) $targets));
    }

    private function materializeNamespace(string $namespace): void
    {
        if ($namespace === '' || isset($this->materializedNamespaces[$namespace])) {
            return;
        }

        $missing = $this->missingValueMarker();
        $source = $this->source->get($namespace, $missing);
        $layers = [];

        if (array_key_exists($namespace, $this->fallback)) {
            $layers[] = [$namespace => $this->fallback[$namespace]];
        }

        if ($source !== $missing) {
            $layers[] = [$namespace => $source];
        }

        if (array_key_exists($namespace, $this->overrides)) {
            $layers[] = [$namespace => $this->overrides[$namespace]];
        }

        if ($layers !== []) {
            $merged = ConfigMerge::mergeMany($layers);
            if (array_key_exists($namespace, $merged)) {
                $this->items[$namespace] = $merged[$namespace];
            }
        }

        $this->knownNamespaces[$namespace] = true;
        $this->materializedNamespaces[$namespace] = true;
        $this->flushReadCache();
    }

    private function namespaceFromPath(string $path): string
    {
        $dot = strpos($path, '.');
        $namespace = $dot === false ? $path : substr($path, 0, $dot);
        $namespace = trim($namespace);

        return $namespace === '' ? $path : $namespace;
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function registerNamespaces(array $items): void
    {
        foreach ($items as $namespace => $_value) {
            if (is_string($namespace) && $namespace !== '') {
                $this->knownNamespaces[$namespace] = true;
                $this->materializedNamespaces[$namespace] = true;
            }
        }
    }
}
