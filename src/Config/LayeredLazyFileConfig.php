<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

/**
 * Layered lazy configuration with explicit precedence:
 * fallback < lazy source < overrides.
 *
 * Namespace materialization is used deliberately so exact-path reads always
 * match reads against the fully merged configuration, including list/scalar
 * shadowing semantics.
 */
class LayeredLazyFileConfig extends Config
{
    /** @var array<string, mixed> */
    private array $fallback;

    /** @var array<string, true> */
    private array $knownNamespaces = [];

    /** @var array<string, true> */
    private array $materializedNamespaces = [];

    /** @var array<string, mixed> */
    private array $overrides;

    private ResilientLazyFileConfig $source;

    /**
     * @param array<string, mixed> $fallback
     * @param array<string, mixed> $overrides
     * @param list<string> $namespaces
     */
    public function __construct(
        string $directory,
        ?string $namespaceCacheDirectory = null,
        array $fallback = [],
        array $overrides = [],
        array $namespaces = [],
        string $extension = 'php',
    ) {
        $this->source = new ResilientLazyFileConfig(
            directory: $directory,
            extension: $extension,
            namespaceCacheDirectory: $namespaceCacheDirectory,
        );
        $this->fallback = $fallback;
        $this->overrides = $overrides;

        foreach ([...array_keys($fallback), ...array_keys($overrides), ...$namespaces] as $namespace) {
            if (is_string($namespace) && $namespace !== '') {
                $this->knownNamespaces[$namespace] = true;
            }
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function all(): array
    {
        foreach (array_keys($this->knownNamespaces) as $namespace) {
            $this->materializeNamespace($namespace);
        }

        $items = [];
        foreach (parent::all() as $key => $value) {
            if (is_string($key)) {
                $items[$key] = $value;
            }
        }

        return $items;
    }

    public function clearNamespaceCache(): static
    {
        $this->source->flushNamespaceCache();

        return $this;
    }

    public function namespaceCacheDirectory(): ?string
    {
        return $this->source->namespaceCacheDirectory();
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

    private function materializeNamespace(string $namespace): void
    {
        if (isset($this->materializedNamespaces[$namespace])) {
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

        if ($namespace === '') {
            return $path;
        }

        return $namespace;
    }
}
