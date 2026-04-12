<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\traits\HookTrait;

/**
 * Class Config
 *
 * Provides base configuration storage with optional hook-aware variants.
 *
 * Core methods from BaseConfigTrait (`get`, `set`, `fill`) remain fast and
 * hook-free. Hook processing is explicit through `getWithHooks`,
 * `setWithHooks`, and `fillWithHooks`.
 */
class Config
{
    use BaseConfigTrait;
    use HookTrait;

    /**
     * Hook-aware variant of fill().
     */
    public function fillWithHooks(string|array $key, mixed $value = null): bool
    {
        if (is_array($key)) {
            $processed = [];
            foreach ($key as $path => $entry) {
                $processed[$path] = $this->processValue($path, $entry, 'set');
            }

            return $this->fill($processed);
        }

        $processed = $this->processValue($key, $value, 'set');

        return $this->fill($key, $processed);
    }

    /**
     * Hook-aware variant of get().
     */
    public function getWithHooks(int|string|array|null $key = null, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        if (is_array($key)) {
            foreach ($value as $path => $entry) {
                $value[$path] = $this->processValue($path, $entry, 'get');
            }

            return $value;
        }

        return $this->processValue($key, $value, 'get');
    }

    /**
     * Hook-aware variant of set().
     */
    public function setWithHooks(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        if (is_array($key)) {
            $processed = [];
            foreach ($key as $path => $entry) {
                $processed[$path] = $this->processValue($path, $entry, 'set');
            }

            return $this->set($processed, null, $overwrite);
        }

        $processedValue = $this->processValue($key, $value, 'set');

        return $this->set($key, $processedValue, $overwrite);
    }
}
