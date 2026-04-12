<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Array\DotNotation;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class LazyFileConfig extends Config
{
    protected array $loadedNamespaces = [];

    public function __construct(
        protected string $directory,
        protected string $extension = 'php',
        array $items = [],
    ) {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->extension = ltrim($extension, '.');
        $this->items = $items;
    }

    #[\Override]
    public function all(): array
    {
        throw new RuntimeException('LazyFileConfig does not support full config retrieval. At least one key is required.');
    }

    #[\Override]
    public function fill(string|array $key, mixed $value = null): bool
    {
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
    public function forget(string|int|array $key): bool
    {
        if (is_array($key)) {
            foreach ($key as $path) {
                if (!is_string($path) && !is_int($path)) {
                    throw new InvalidArgumentException('Forget keys must be dot-notation strings.');
                }

                $this->forgetPath((string) $path);
            }
        } else {
            $this->forgetPath((string) $key);
        }

        return true;
    }

    #[\Override]
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
                if (!is_string($path) && !is_int($path)) {
                    throw new InvalidArgumentException('Config keys must be dot-notation strings.');
                }

                $results[(string) $path] = $this->getPath((string) $path, $default);
            }

            return $results;
        }

        return $this->getPath($key, $default);
    }

    #[\Override]
    public function has(string|array $keys): bool
    {
        $keys = (array) $keys;
        if ($keys === []) {
            return false;
        }

        foreach ($keys as $path) {
            if (!is_string($path)) {
                return false;
            }

            if (!$this->hasPath($path)) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function hasAny(string|array $keys): bool
    {
        $keys = (array) $keys;
        if ($keys === []) {
            return false;
        }

        foreach ($keys as $path) {
            if (!is_string($path)) {
                continue;
            }

            if ($this->hasPath($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the namespace has already been resolved (file found or missing).
     */
    public function isLoaded(string $namespace): bool
    {
        return isset($this->loadedNamespaces[$this->normalizeNamespace($namespace)]);
    }

    /**
     * @return string[] List of namespaces already resolved.
     */
    public function loadedNamespaces(): array
    {
        return array_keys($this->loadedNamespaces);
    }

    /**
     * Preload one or multiple top-level config namespaces.
     *
     * @param string|array $namespaces Namespace (e.g. "db") or list of namespaces.
     */
    public function preload(string|array $namespaces): static
    {
        foreach ((array) $namespaces as $namespace) {
            if (!is_string($namespace)) {
                throw new InvalidArgumentException('Preload namespaces must be strings.');
            }

            $this->loadNamespace($this->normalizeNamespace($namespace));
        }

        return $this;
    }

    #[\Override]
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
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
            return \isCallable($default) ? $default() : $default;
        }

        if ($rest === null || $rest === '') {
            return $this->items[$namespace];
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

        $file = $this->resolveNamespaceFile($namespace);
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

    protected function resolveNamespaceFile(string $namespace): ?string
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . $namespace . '.' . $this->extension;

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        return $file;
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
}
