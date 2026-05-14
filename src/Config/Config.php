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
     *
     * @param string|array<array-key, mixed> $key
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
     *
     * @param int|string|array<int, string|int>|null $key
     */
    public function getWithHooks(int|string|array|null $key = null, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        if (is_array($key)) {
            if (!is_array($value)) {
                return $value;
            }

            foreach ($value as $path => $entry) {
                $value[$path] = $this->processValue($path, $entry, 'get');
            }

            return $value;
        }

        if ($key === null) {
            return $value;
        }

        return $this->processValue($key, $value, 'get');
    }

    /**
     * Hook-aware variant of set().
     *
     * @param string|array<array-key, mixed>|null $key
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

        if ($key === null) {
            return $this->set($key, $value, $overwrite);
        }

        $processedValue = $this->processValue($key, $value, 'set');

        return $this->set($key, $processedValue, $overwrite);
    }
}
