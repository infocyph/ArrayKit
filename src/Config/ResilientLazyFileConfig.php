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

        $cache = $this->resolveCachedNamespaceFile($namespace);
        $loaded = $cache === null ? null : $this->loadCachedNamespace($cache);
        $origin = $loaded === null ? 'source' : 'cache';

        if ($loaded === null) {
            $source = $this->resolveNamespaceFile($namespace);
            if ($source === null) {
                $this->loadedNamespaces[$namespace] = true;
                $this->loadedNamespaceOrigins[$namespace] = 'missing';

                return;
            }

            $loaded = include $source;
            if (!is_array($loaded)) {
                throw new UnexpectedValueException("Config file [{$source}] must return an array.");
            }
        }

        $this->loadedNamespaces[$namespace] = true;
        $this->loadedNamespaceOrigins[$namespace] = $origin;

        if (!array_key_exists($namespace, $this->items)) {
            $this->items[$namespace] = $loaded;
            $this->flushReadCache();

            return;
        }

        if (is_array($this->items[$namespace])) {
            $this->items[$namespace] = array_replace_recursive($loaded, $this->items[$namespace]);
            $this->flushReadCache();
        }
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function loadCachedNamespace(string $cache): ?array
    {
        try {
            $loaded = include $cache;
        } catch (\Throwable) {
            return null;
        }

        return is_array($loaded) ? $loaded : null;
    }
}
