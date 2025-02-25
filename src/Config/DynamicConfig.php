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
     * Retrieves a configuration value by dot-notation key, applying any "on get" hooks.
     *
     * @param int|string|null $key The key to retrieve (supports dot notation)
     * @param mixed $default The default value to return if the key is not found
     * @return mixed The retrieved value
     */
    public function get(int|string $key = null, mixed $default = null): mixed
    {
        $value = DotNotation::get($this->items, $key, $default);
        return $this->processValue($key, $value, 'get');
    }


    /**
     * Sets a configuration value by dot-notation key, applying any "on set" hooks.
     *
     * @param string|null $key The key to set (supports dot notation)
     * @param mixed $value The value to set
     * @param bool $overwrite If true, overwrite existing values; otherwise, fill in missing (default true)
     * @return bool True on success
     */
    public function set(?string $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        // The user might want the dynamic config to also accept $overwrite param for fill-like usage
        $processedValue = $this->processValue($key, $value, 'set');
        return DotNotation::set($this->items, $key, $processedValue, $overwrite);
    }


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
        $processed = $this->processValue($key, $value, 'set');
        DotNotation::fill($this->items, $key, $processed);
        return true;
    }
}
