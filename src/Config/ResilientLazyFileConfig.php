<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use UnexpectedValueException;

/**
 * Lazy file configuration that treats generated namespace-cache corruption as
 * a cache miss while keeping source configuration failures visible.
 */
class ResilientLazyFileConfig extends LazyFileConfig
{
    #[\Override]
    protected function loadNamespace(string $namespace): void
    {
        if (isset($this->loadedNamespaces[$namespace])) {
            return;
        }

        $loaded = $this->loadCachedNamespace($namespace);
        if ($loaded === null) {
            $source = $this->resolveNamespaceFile($namespace);
            if ($source === null) {
                $this->loadedNamespaces[$namespace] = true;

                return;
            }

            $loaded = include $source;
            if (!is_array($loaded)) {
                throw new UnexpectedValueException("Config file [{$source}] must return an array.");
            }
        }

        $this->loadedNamespaces[$namespace] = true;

        if (!array_key_exists($namespace, $this->items)) {
            $this->items[$namespace] = $loaded;

            return;
        }

        if (is_array($this->items[$namespace])) {
            $this->items[$namespace] = array_replace_recursive($loaded, $this->items[$namespace]);
        }
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function loadCachedNamespace(string $namespace): ?array
    {
        $cache = $this->resolveCachedNamespaceFile($namespace);
        if ($cache === null) {
            return null;
        }

        try {
            $loaded = include $cache;
        } catch (\Throwable) {
            return null;
        }

        return is_array($loaded) ? $loaded : null;
    }
}
