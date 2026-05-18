<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Array\DotNotation;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use UnexpectedValueException;

trait BaseConfigTrait
{
    /**
     * @var array<array-key, mixed> Internal storage for config items
     */
    protected array $items = [];

    protected bool $readOnly = false;

    /**
     * @var array<string, array<array-key, mixed>>
     */
    protected array $snapshots = [];

    /**
     * Retrieve all configuration items.
     *
     * @return array<array-key, mixed> The entire configuration array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Append a value to a configuration array at the specified key.
     *
     * @param string $key The dot-notation key referencing an array
     * @param mixed $value The value to append
     * @return bool True on success
     */
    public function append(string $key, mixed $value): bool
    {
        $this->assertWritable();

        $array = $this->get($key, []);
        if (!is_array($array)) {
            $array = [];
        }

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * Detect whether config changed compared to a named snapshot.
     */
    public function changed(string $snapshot = 'default'): bool
    {
        if (!array_key_exists($snapshot, $this->snapshots)) {
            return true;
        }

        return $this->snapshots[$snapshot] != $this->items;
    }

    /**
     * "Fill" config data where it's missing, i.e. DotNotation's fill logic.
     *
     * @param string|array<array-key, mixed> $key Dot-notation key or multiple [key => value]
     * @param mixed|null $value The value to set if missing
     */
    public function fill(string|array $key, mixed $value = null): bool
    {
        $this->assertWritable();

        DotNotation::fill($this->items, $key, $value);

        return true;
    }

    /**
     * Remove/unset a key (or keys) from configuration using dot notation + wildcard expansions.
     */
    /**
     * @param string|int|array<int, string|int> $key
     */
    public function forget(string|int|array $key): bool
    {
        $this->assertWritable();

        DotNotation::forget($this->items, $key);

        return true;
    }

    /**
     * Get one or multiple items from the configuration.
     * Includes wildcard support (e.g. '*'), {first}, {last}, etc.
     *
     * @param string|int|array<int, string|int>|null $key Dot-notation key(s) or null for entire config
     * @param mixed|null $default Default value if key not found
     * @return mixed The value(s) found or default
     */
    public function get(string|int|array|null $key = null, mixed $default = null): mixed
    {
        return DotNotation::get($this->items, $key, $default);
    }

    /**
     * Get an array value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     * @param array<array-key, mixed>|null $default
     * @return array<array-key, mixed>|null
     */
    public function getArray(string|int|array|null $key, ?array $default = null): ?array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Get a bool value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     */
    public function getBool(string|int|array|null $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : $default;
    }

    /**
     * Get an enum instance from a scalar stored config value.
     *
     * @template TEnum of \UnitEnum
     * @param string|int|array<int, string|int>|null $key
     * @param class-string<TEnum> $enumClass
     * @param TEnum|null $default
     * @return TEnum|null
     */
    public function getEnum(string|int|array|null $key, string $enumClass, ?\UnitEnum $default = null): ?\UnitEnum
    {
        if (!enum_exists($enumClass)) {
            throw new InvalidArgumentException("Enum class [{$enumClass}] does not exist.");
        }

        $value = $this->get($key, null);
        if ($value === null) {
            return $default;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class)) {
            if (!is_string($value) && !is_int($value)) {
                return $default;
            }

            return $enumClass::tryFrom($value) ?? $default;
        }

        if (!is_string($value)) {
            return $default;
        }

        foreach ($enumClass::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return $default;
    }

    /**
     * Get a float value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     */
    public function getFloat(string|int|array|null $key, ?float $default = null): ?float
    {
        $value = $this->get($key, $default);

        return is_float($value) ? $value : $default;
    }

    /**
     * Get an int value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     */
    public function getInt(string|int|array|null $key, ?int $default = null): ?int
    {
        $value = $this->get($key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * Get a list array value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     * @param array<int, mixed>|null $default
     * @return array<int, mixed>|null
     */
    public function getList(string|int|array|null $key, ?array $default = null): ?array
    {
        $value = $this->get($key, $default);
        if (!is_array($value) || !array_is_list($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Get a required configuration value or throw when missing.
     *
     * @param string|int|array<int, int|string>|null $key
     */
    public function getOrFail(string|int|array|null $key): mixed
    {
        $missing = new \stdClass();
        $value = $this->get($key, $missing);

        if ($value === $missing) {
            throw new OutOfBoundsException('Required config key is missing.');
        }

        return $value;
    }

    /**
     * Get a string value or fallback default when type does not match.
     *
     * @param string|int|array<int, string|int>|null $key
     */
    public function getString(string|int|array|null $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Check if one or multiple keys exist in the configuration (no wildcard).
     *
     * @param string|array<int, string> $keys Dot-notation key(s)
     * @return bool True if the key(s) exist
     */
    public function has(string|array $keys): bool
    {
        return DotNotation::has($this->items, $keys);
    }

    /**
     * Check if *any* of the given keys exist (no wildcard).
     *
     * @param string|array<int, string> $keys Dot-notation key(s)
     * @return bool True if at least one key exists
     */
    public function hasAny(string|array $keys): bool
    {
        return DotNotation::hasAny($this->items, $keys);
    }

    public function isReadonly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Load configuration directly from an array resource.
     *
     * @param array<array-key, mixed> $resource The array containing config items
     * @return bool True if loaded successfully, false if already loaded
     */
    public function loadArray(array $resource): bool
    {
        $this->assertWritable();

        if (count($this->items) === 0) {
            $this->items = $resource;

            return true;
        }

        return false;
    }

    /**
     * Load configuration from a specified file path (PHP returning array).
     *
     * @param string $path The file path to load
     * @return bool True if loaded successfully, false if already loaded or file missing
     */
    public function loadFile(string $path): bool
    {
        $this->assertWritable();

        if (count($this->items) === 0 && is_file($path) && is_readable($path)) {
            $loaded = include $path;
            if (!is_array($loaded)) {
                throw new UnexpectedValueException("Config file [{$path}] must return an array.");
            }

            $this->items = $loaded;

            return true;
        }

        return false;
    }

    /**
     * Merge a config array into current items.
     *
     * @param array<array-key, mixed> $items
     */
    public function merge(array $items): bool
    {
        $this->assertWritable();
        $this->items = array_replace_recursive($this->items, $items);

        return true;
    }

    /**
     * Overlay another config array on top of current items.
     *
     * @param array<array-key, mixed> $overlay
     */
    public function overlay(array $overlay): bool
    {
        return $this->merge($overlay);
    }

    /**
     * Prepend a value to a configuration array at the specified key.
     * (No direct wildcard usage, though underlying DotNotation can handle it if needed.)
     *
     * @param string $key The dot-notation key referencing an array
     * @param mixed $value The value to prepend
     * @return bool True on success
     */
    public function prepend(string $key, mixed $value): bool
    {
        $this->assertWritable();

        $array = $this->get($key, []);
        if (!is_array($array)) {
            $array = [];
        }

        array_unshift($array, $value);

        return $this->set($key, $array);
    }

    /**
     * Enable/disable read-only mode.
     */
    public function readonly(bool $enabled = true): static
    {
        $this->readOnly = $enabled;

        return $this;
    }

    /**
     * Reload configuration from an array or file path, replacing existing data.
     *
     * @param array<array-key, mixed>|string $source
     */
    public function reload(array|string $source): bool
    {
        $this->assertWritable();

        if (is_array($source)) {
            return $this->replace($source);
        }

        if (!is_file($source) || !is_readable($source)) {
            return false;
        }

        $loaded = include $source;
        if (!is_array($loaded)) {
            throw new UnexpectedValueException("Config file [{$source}] must return an array.");
        }

        return $this->replace($loaded);
    }

    /**
     * Replace the entire configuration storage with a new array.
     *
     * @param array<array-key, mixed> $items
     */
    public function replace(array $items): bool
    {
        $this->assertWritable();

        $this->items = $items;

        return true;
    }

    /**
     * Restore configuration from a named snapshot.
     */
    public function restore(string $name = 'default'): bool
    {
        $this->assertWritable();
        if (!array_key_exists($name, $this->snapshots)) {
            return false;
        }

        $this->items = $this->snapshots[$name];

        return true;
    }

    /**
     * Set a configuration value by dot-notation key (wildcard support),
     * optionally controlling overwrite vs. fill-like behavior.
     *
     * If no key is provided, replaces the entire config array with $value.
     *
     * @param string|array<array-key, mixed>|null $key Dot-notation key or [key => value] array
     * @param mixed|null $value The value to set
     * @param bool $overwrite Overwrite existing? Default true.
     * @return bool True on success
     */
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    {
        $this->assertWritable();

        return DotNotation::set($this->items, $key, $value, $overwrite);
    }

    /**
     * Save a named snapshot of current configuration.
     */
    public function snapshot(string $name = 'default'): bool
    {
        $this->snapshots[$name] = $this->items;

        return true;
    }

    protected function assertWritable(): void
    {
        if ($this->readOnly) {
            throw new RuntimeException('Configuration is read-only.');
        }
    }
}
