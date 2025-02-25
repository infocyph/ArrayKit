<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Array\DotNotation;

trait BaseConfigTrait
{
    /**
     * @var array Internal storage for config items
     */
    protected array $items = [];

    /**
     * Load configuration from a specified file path (PHP returning array).
     *
     * @param string $path The file path to load
     * @return bool True if loaded successfully, false if already loaded or file missing
     */
    public function loadFile(string $path): bool
    {
        if (count($this->items) === 0 && file_exists($path)) {
            $this->items = include $path;
            return true;
        }
        return false;
    }

    /**
     * Load configuration directly from an array resource.
     *
     * @param array $resource The array containing config items
     * @return bool True if loaded successfully, false if already loaded
     */
    public function loadArray(array $resource): bool
    {
        if (count($this->items) === 0) {
            $this->items = $resource;
            return true;
        }
        return false;
    }

    /**
     * Retrieve all configuration items.
     *
     * @return array The entire configuration array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Check if one or multiple keys exist in the configuration (no wildcard).
     *
     * @param string|array $keys Dot-notation key(s)
     * @return bool True if the key(s) exist
     */
    public function has(string|array $keys): bool
    {
        return DotNotation::has($this->items, $keys);
    }

    /**
     * Check if *any* of the given keys exist (no wildcard).
     *
     * @param string|array $keys Dot-notation key(s)
     * @return bool True if at least one key exists
     */
    public function hasAny(string|array $keys): bool
    {
        return DotNotation::hasAny($this->items, $keys);
    }

    /**
     * Get one or multiple items from the configuration.
     * Includes wildcard support (e.g. '*'), {first}, {last}, etc.
     *
     * @param string|int|array|null $key Dot-notation key(s) or null for entire config
     * @param mixed|null            $default Default value if key not found
     * @return mixed The value(s) found or default
     */
    public function get(string|int|array $key = null, mixed $default = null): mixed
    {
        return DotNotation::get($this->items, $key, $default);
    }

    /**
     * Set a configuration value by dot-notation key (wildcard support),
     * optionally controlling overwrite vs. fill-like behavior.
     *
     * If no key is provided, replaces the entire config array with $value.
     *
     * @param string|array|null $key   Dot-notation key or [key => value] array
     * @param mixed|null        $value The value to set
     * @param bool              $overwrite Overwrite existing? Default true.
     * @return bool True on success
     */
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        return DotNotation::set($this->items, $key, $value, $overwrite);
    }

    /**
     * "Fill" config data where it's missing, i.e. DotNotation's fill logic.
     *
     * @param string|array $key Dot-notation key or multiple [key => value]
     * @param mixed|null   $value The value to set if missing
     * @return bool
     */
    public function fill(string|array $key, mixed $value = null): bool
    {
        DotNotation::fill($this->items, $key, $value);
        return true;
    }

    /**
     * Remove/unset a key (or keys) from configuration using dot notation + wildcard expansions.
     *
     * @param string|int|array $key
     * @return bool
     */
    public function forget(string|int|array $key): bool
    {
        DotNotation::forget($this->items, $key);
        return true;
    }

    /**
     * Prepend a value to a configuration array at the specified key.
     * (No direct wildcard usage, though underlying DotNotation can handle it if needed.)
     *
     * @param string $key   The dot-notation key referencing an array
     * @param mixed  $value The value to prepend
     * @return bool True on success
     */
    public function prepend(string $key, mixed $value): bool
    {
        $array = $this->get($key, []);
        array_unshift($array, $value);
        return $this->set($key, $array);
    }

    /**
     * Append a value to a configuration array at the specified key.
     *
     * @param string $key   The dot-notation key referencing an array
     * @param mixed  $value The value to append
     * @return bool True on success
     */
    public function append(string $key, mixed $value): bool
    {
        $array   = $this->get($key, []);
        $array[] = $value;
        return $this->set($key, $array);
    }
}
