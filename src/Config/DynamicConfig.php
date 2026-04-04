<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\traits\HookTrait;

class DynamicConfig
{
    use BaseConfigTrait;
    use HookTrait;


    /**
     * "Fill" config data where it's missing, i.e. DotNotation's fill logic,
     * applying any "on set" hooks to the value.
     *
     * @param string|array $key Dot-notation key or multiple [key => value]
     * @param mixed|null   $value The value to set if missing
     * @return bool True on success
     */
    public function fill(string|array $key, mixed $value = null): bool
    {
        if (is_array($key)) {
            $processed = [];
            foreach ($key as $path => $entry) {
                $processed[$path] = $this->processValue($path, $entry, 'set');
            }

            DotNotation::fill($this->items, $processed);

            return true;
        }

        $processed = $this->processValue($key, $value, 'set');
        DotNotation::fill($this->items, $key, $processed);
        return true;
    }


    /**
     * Retrieves a configuration value by dot-notation key, applying any "on get" hooks.
     *
     * @param int|string|array|null $key The key(s) to retrieve (supports dot notation)
     * @param mixed $default The default value to return if the key is not found
     * @return mixed The retrieved value
     */
    public function get(int|string|array|null $key = null, mixed $default = null): mixed
    {
        $value = DotNotation::get($this->items, $key, $default);

        if (is_array($key)) {
            foreach ($value as $path => $entry) {
                $value[$path] = $this->processValue($path, $entry, 'get');
            }

            return $value;
        }

        return $this->processValue($key, $value, 'get');
    }


    /**
     * Sets a configuration value by dot-notation key, applying any "on set" hooks.
     *
     * @param string|array|null $key The key to set (supports dot notation)
     * @param mixed $value The value to set
     * @param bool $overwrite If true, overwrite existing values; otherwise, fill in missing (default true)
     * @return bool True on success
     */
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        if (is_array($key)) {
            $processed = [];
            foreach ($key as $path => $entry) {
                $processed[$path] = $this->processValue($path, $entry, 'set');
            }

            return DotNotation::set($this->items, $processed, null, $overwrite);
        }

        $processedValue = $this->processValue($key, $value, 'set');

        return DotNotation::set($this->items, $key, $processedValue, $overwrite);
    }
}
