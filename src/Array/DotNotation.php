<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Array;

use InvalidArgumentException;

class DotNotation
{
    /**
     * Flattens a multidimensional array to a single level, using dot notation to
     * represent nested keys.
     *
     * @param array $array The multidimensional array to flatten.
     * @param string $prepend A string to prepend to the keys of the flattened array.
     * @return array A flattened array with all nested arrays collapsed to the same level.
     */
    public static function flatten(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge(
                    $results,
                    static::flatten($value, $prepend . $key . '.')
                );
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }


    /**
     * Expands a flattened array (created by flatten) back into a nested structure.
     *
     * @param array $array A flattened array, where each key is a string with dot
     *                     notation representing the nested keys.
     * @return array A nested array with the same values as the input but with the
     *              nested structure restored.
     */
    public static function expand(array $array): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            static::set($results, $key, $value);
        }
        return $results;
    }

    /**
     * Determine if the given key or keys exist in the array.
     *
     * This method is the dot notation aware version of ArraySingle::has.
     *
     * @param array $array The array to search.
     * @param array|string $keys The key(s) to check for existence.
     * @return bool True if all the given keys exist in the array, false otherwise.
     */
    public static function has(array $array, array|string $keys): bool
    {
        if (empty($array) || empty($keys)) {
            return false;
        }

        // If single string key and found top-level:
        if (is_string($keys) && ArraySingle::exists($array, $keys)) {
            return true;
        }

        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (ArraySingle::exists($array, $key)) {
                continue;
            }
            // Fall back to a simple segment check (no wildcard)
            if (!static::segmentExact($array, $key, false)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check if *any* of the given keys exist (no wildcard).
     *
     * @param array $array The array to search.
     * @param array|string $keys The key(s) to check for existence.
     * @return bool True if at least one key exists
     */
    public static function hasAny(array $array, array|string $keys): bool
    {
        if (empty($array) || empty($keys)) {
            return false;
        }

        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (static::has($array, $key)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Get one or multiple items from the array using dot notation.
     *
     * The following cases are handled:
     *  - If no key is provided, the entire array is returned.
     *  - If an array of keys is provided, all values are returned in an array.
     *  - If a single key is provided, the value is returned directly.
     *
     * @param array $array The array to retrieve items from.
     * @param array|int|string|null $keys The key(s) to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value(s).
     */
    public static function get(array $array, array|int|string $keys = null, mixed $default = null): mixed
    {
        // If no key, return entire array
        if ($keys === null) {
            return $array;
        }

        // If multiple keys requested, gather each value:
        if (is_array($keys)) {
            $results = [];
            foreach ($keys as $k) {
                $results[$k] = static::getValue($array, $k, $default);
            }
            return $results;
        }

        // single key
        return static::getValue($array, $keys, $default);
    }


    /**
     * Set one or multiple items in the array using dot notation.
     *
     * If no key is provided, the entire array is replaced with $value.
     * If an array of key-value pairs is provided, each value is set.
     * If a single key is provided, the value is set directly.
     *
     * @param array $array The array to set items in.
     * @param array|string|null $keys The key(s) to set.
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, existing values are overwritten. If false, existing values are preserved.
     * @return bool True on success
     */
    public static function set(array &$array, array|string|null $keys = null, mixed $value = null, bool $overwrite = true): bool
    {
        // If no key, replace entire array with $value
        if ($keys === null) {
            $array = (array) $value;
            return true;
        }

        if (is_array($keys)) {
            // multiple sets
            foreach ($keys as $k => $val) {
                static::setValue($array, $k, $val, $overwrite);
            }
        } else {
            static::setValue($array, $keys, $value, $overwrite);
        }

        return true;
    }


    /**
     * Fill in data where missing (like set, but doesn't overwrite existing keys).
     *
     * @param array $array The array to fill in.
     * @param array|string $keys The key(s) to fill in.
     * @param mixed $value The value to set if missing.
     * @return void
     */
    public static function fill(array &$array, array|string $keys, mixed $value = null): void
    {
        static::set($array, $keys, $value, false);
    }

    /**
     * Remove one or multiple items from an array or object using dot notation.
     *
     * This method supports wildcard and dot notation for nested arrays or objects.
     * If a wildcard ('*') is encountered, it applies the forget operation to each
     * accessible element. For objects, it unsets the specified property.
     *
     * @param array $target The target array or object to remove items from.
     * @param array|string|int|null $keys The key(s) or path(s) to be removed.
     *                                    Supports dot notation and wildcards.
     * @return void
     */
    public static function forget(array &$target, array|string|int|null $keys): void
    {
        if ($keys === null || $keys === []) {
            return;
        }

        // Convert keys to segments.
        $segments = is_array($keys) ? $keys : explode('.', (string)$keys);
        $segment  = array_shift($segments);

        match (true) {
            // Case 1: Wildcard on an accessible array.
            $segment === '*' && BaseArrayHelper::accessible($target) =>
            count($segments) > 0 ? static::forgetEach($target, $segments) : null,

            // Case 2: Target is array-accessible (normal array).
            BaseArrayHelper::accessible($target) =>
            count($segments) > 0 && ArraySingle::exists($target, $segment)
                ? static::forget($target[$segment], $segments)
                : BaseArrayHelper::forget($target, $segment),

            // Case 3: Target is an object.
            is_object($target) =>
            count($segments) > 0 && isset($target->{$segment})
                ? static::forget($target->{$segment}, $segments)
                : (isset($target->{$segment}) ? static::unsetProperty($target, $segment) : null),

            default => null,
        };
    }


    /**
     * Recursively apply the forget logic to each element in an array.
     *
     * This function iterates over each element of the provided array
     * and applies the forget operation using the given segments.
     *
     * @param array $array The array whose elements will be processed.
     * @param array $segments The segments to use for the forget operation.
     * @return void
     */
    private static function forgetEach(array &$array, array $segments): void
    {
        foreach ($array as &$inner) {
            static::forget($inner, $segments);
        }
    }


    /**
     * Unset a property from an object.
     *
     * This method removes a specified property from an object by using
     * PHP's unset function. The property is directly removed from the
     * object if it exists.
     *
     * @param object $object The object from which the property should be removed.
     * @param string $property The name of the property to unset.
     * @return void
     */
    private static function unsetProperty(object &$object, string $property): void
    {
        unset($object->{$property});
    }

    /**
     * Retrieve a string value from the array using a dot-notation key.
     *
     * This function attempts to retrieve a value from the given array
     * using the specified key. If the retrieved value is not of type
     * string, an InvalidArgumentException is thrown. If the key is not
     * found, the default value is returned.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to use for retrieval.
     * @param mixed $default The default value to return if the key is not found.
     * @return string The retrieved string value.
     * @throws InvalidArgumentException If the retrieved value is not a string.
     */
    public static function string(array $array, string $key, mixed $default = null): string
    {
        $value = static::get($array, $key, $default);
        if (!is_string($value)) {
            throw new InvalidArgumentException("Expected string, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * Retrieve an integer value from the array using a dot-notation key.
     *
     * This method tries to fetch a value from the given array with the specified key.
     * If the value is not an integer, an InvalidArgumentException is thrown.
     * If the key is not found, the default value is returned.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to use for retrieval.
     * @param mixed $default The default value to return if the key is not found.
     * @return int The retrieved integer value.
     * @throws InvalidArgumentException If the retrieved value is not an integer.
     */
    public static function integer(array $array, string $key, mixed $default = null): int
    {
        $value = static::get($array, $key, $default);
        if (!is_int($value)) {
            throw new InvalidArgumentException("Expected int, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * Retrieve a float value from the array using a dot-notation key.
     *
     * This method tries to fetch a value from the given array with the specified key.
     * If the value is not a float, an InvalidArgumentException is thrown.
     * If the key is not found, the default value is returned.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to use for retrieval.
     * @param mixed $default The default value to return if the key is not found.
     * @return float The retrieved float value.
     * @throws InvalidArgumentException If the retrieved value is not a float.
     */
    public static function float(array $array, string $key, mixed $default = null): float
    {
        $value = static::get($array, $key, $default);
        if (!is_float($value)) {
            throw new InvalidArgumentException("Expected float, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * Retrieve a boolean value from the array using a dot-notation key.
     *
     * This method tries to fetch a value from the given array with the specified key.
     * If the value is not a boolean, an InvalidArgumentException is thrown.
     * If the key is not found, the default value is returned.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to use for retrieval.
     * @param mixed $default The default value to return if the key is not found.
     * @return bool The retrieved boolean value.
     * @throws InvalidArgumentException If the retrieved value is not a boolean.
     */
    public static function boolean(array $array, string $key, mixed $default = null): bool
    {
        $value = static::get($array, $key, $default);
        if (!is_bool($value)) {
            throw new InvalidArgumentException("Expected bool, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * Retrieve an array value from the array using a dot-notation key.
     *
     * This method tries to fetch a value from the given array with the specified key.
     * If the value is not an array, an InvalidArgumentException is thrown.
     * If the key is not found, the default value is returned.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to use for retrieval.
     * @param mixed $default The default value to return if the key is not found.
     * @return array The retrieved array value.
     * @throws InvalidArgumentException If the retrieved value is not an array.
     */
    public static function arrayValue(array $array, string $key, mixed $default = null): array
    {
        $value = static::get($array, $key, $default);
        if (!is_array($value)) {
            throw new InvalidArgumentException("Expected array, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * Pluck one or more values from an array.
     *
     * This method allows you to retrieve one or more values from an array
     * using dot-notation keys.
     *
     * @param array $array The array to retrieve values from.
     * @param array|string $keys The key(s) to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return array The retrieved values.
     */
    public static function pluck(array $array, array|string $keys, mixed $default = null): array
    {
        $keys = (array) $keys;
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = static::get($array, $key, $default);
        }
        return $results;
    }

    /**
     * Get all the given array.
     *
     * @param array $array
     * @return array
     */
    public static function all(array $array): array
    {
        return $array;
    }

    /**
     * Pass the array to the given callback and return it.
     *
     * Useful for tapping into a fluent method chain for debugging.
     *
     * @param array $array The array to be tapped.
     * @param callable $callback The callback to apply to the array.
     * @return array The original array.
     */
    public static function tap(array $array, callable $callback): array
    {
        $callback($array);
        return $array;
    }

    /**
     * Check if a given key exists in the array using dot notation.
     *
     * This method determines if the specified key is present
     * within the provided array. It leverages the dot notation
     * to access nested data structures.
     *
     * @param array $array The array to search.
     * @param string $key The dot-notation key to check for existence.
     * @return bool True if the key exists, false otherwise.
     */
    public static function offsetExists(array $array, string $key): bool
    {
        return static::has($array, $key);
    }

    /**
     * Retrieves a value from the array using dot notation.
     *
     * This method is a part of the ArrayAccess implementation.
     *
     * @param array $array The array to retrieve the value from.
     * @param string $key The dot-notation key to retrieve.
     * @return mixed The retrieved value.
     * @see Infocyph\ArrayKit\Array\DotNotation::get()
     */
    public static function offsetGet(array $array, string $key): mixed
    {
        return static::get($array, $key);
    }

    /**
     * Set a value in the array using dot notation.
     *
     * This method is a part of the ArrayAccess implementation.
     *
     * @param array &$array The array to set the value in.
     * @param string $key The dot-notation key to set.
     * @param mixed $value The value to set.
     * @return void
     * @see Infocyph\ArrayKit\Array\DotNotation::set()
     */
    public static function offsetSet(array &$array, string $key, mixed $value): void
    {
        static::set($array, $key, $value);
    }

    /**
     * Unset a value in the array using dot notation.
     *
     * This method removes a value from the provided array
     * at the specified dot-notation key. It leverages the
     * forget logic to handle nested arrays and supports
     * wildcard paths.
     *
     * @param array &$array The array from which to unset the value.
     * @param string $key The dot-notation key of the value to unset.
     * @return void
     */
    public static function offsetUnset(array &$array, string $key): void
    {
        static::forget($array, $key);
    }

    /**
     * Retrieve a value from the array using dot notation.
     *
     * This method supports retrieving values from the given array
     * using dot-notation keys. It will traverse the array as necessary
     * to retrieve the value. If the key is not found, the default value
     * is returned.
     *
     * @param array $target The array to retrieve the value from.
     * @param int|string $key The key to retrieve (supports dot notation).
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value.
     */
    private static function getValue(array $target, int|string $key, mixed $default): mixed
    {
        if (is_int($key) || ArraySingle::exists($target, $key)) {
            // Return top-level or integer index
            return $target[$key] ?? static::value($default);
        }
        if (!is_string($key) || !str_contains($key, '.')) {
            // If no dot path
            return static::value($default);
        }

        return static::traverseGet($target, explode('.', $key), $default);
    }



    /**
     * Traverses the target array/object to retrieve a value using dot notation.
     *
     * This method is called recursively by the `get` method to traverse the given
     * array or object using dot notation. It expects the target array or object,
     * the segments of the dot-notation key, and the default value to return if
     * the key is not found.
     *
     * @param mixed $target The array or object to traverse.
     * @param array $segments The segments of the dot-notation key.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value.
     */
    private static function traverseGet(mixed $target, array $segments, mixed $default): mixed
    {
        foreach ($segments as $i => $segment) {
            unset($segments[$i]);

            if ($segment === null) {
                return $target;
            }

            if ($segment === '*') {
                return static::traverseWildcard($target, $segments, $default);
            }

            $normalized = static::normalizeSegment($segment, $target);
            $target     = static::accessSegment($target, $normalized, $default);
            if ($target === static::value($default)) {
                return static::value($default);
            }
        }
        return $target;
    }


    /**
     * Normalize a dot-notation segment by replacing escaped values and resolving
     * special values such as '{first}' and '{last}'.
     *
     * @param string $segment The segment of the dot-notation key.
     * @param mixed $target The target array or object to resolve against.
     * @return mixed The normalized segment.
     */
    private static function normalizeSegment(string $segment, mixed $target): mixed
    {
        return match ($segment) {
            '\\*'       => '*',
            '\\{first}' => '{first}',
            '{first}'   => static::resolveFirst($target),
            '\\{last}'  => '{last}',
            '{last}'   => static::resolveLast($target),
            default     => $segment,
        };
    }


    /**
     * Access a segment in a target array or object.
     *
     * This method takes a target array or object, a segment, and a default value.
     * It returns the value of the segment in the target if it exists, or the default
     * value if it does not. It supports both array and object access. Array access
     * is attempted first, then object access.
     *
     * @param mixed $target The target array or object to access.
     * @param mixed $segment The segment to access.
     * @param mixed $default The default value to return if the segment does not exist.
     * @return mixed The value of the segment in the target, or the default value.
     */
    private static function accessSegment(mixed $target, mixed $segment, mixed $default): mixed
    {
        return match (true) {
            BaseArrayHelper::accessible($target) && ArraySingle::exists($target, $segment)
            => $target[$segment],
            is_object($target) && isset($target->{$segment})
            => $target->{$segment},
            default => static::value($default),
        };
    }


    /**
     * Traverse a target array/object using dot-notation with wildcard support.
     *
     * This method handles cases where a wildcard ('*') is present in the dot-notation key.
     * It iterates over each element of the target, applying the remaining segments to retrieve
     * the specified value. If segments contain another wildcard, the results are collapsed into
     * a single array. If the target is not accessible, the default value is returned.
     *
     * @param mixed $target The array or object to traverse.
     * @param array $segments The segments of the dot-notation key, including potential wildcards.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The retrieved value(s) from the target based on the dot-notation key.
     */
    private static function traverseWildcard(mixed $target, array $segments, mixed $default): mixed
    {
        $target = method_exists($target, 'all') ? $target->all() : $target;
        if (!BaseArrayHelper::accessible($target)) {
            return static::value($default);
        }

        $result = [];
        foreach ($target as $item) {
            $result[] = static::traverseGet($item, $segments, $default);
        }
        if (in_array('*', $segments, true)) {
            $result = ArrayMulti::collapse($result);
        }
        return $result;
    }


    /**
     * Sets a value in the target array/object using dot notation.
     *
     * This method sets a value in the target array or object using dot notation.
     * It supports wildcard and dot notation for nested arrays or objects.
     * If the segment path is not fully defined within the target array,
     * it will create nested arrays as necessary. If the `overwrite` flag is true,
     * it will replace any existing value at the final segment; otherwise,
     * it will only set the value if the property does not already exist.
     *
     * @param mixed &$target The target array or object to set the value in.
     * @param string $key The dot-notation key of the value to set.
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite any existing value.
     * @return void
     */
    private static function setValue(mixed &$target, string $key, mixed $value, bool $overwrite): void
    {
        $segments = explode('.', $key);
        $first    = array_shift($segments);

        if ($first === '*') {
            static::handleWildcardSet($target, $segments, $value, $overwrite);
            return;
        }

        if (BaseArrayHelper::accessible($target)) {
            static::setValueArray($target, $first, $segments, $value, $overwrite);
        } elseif (is_object($target)) {
            static::setValueObject($target, $first, $segments, $value, $overwrite);
        } else {
            static::setValueFallback($target, $first, $segments, $value, $overwrite);
        }
    }


    /**
     * Sets values in the target using dot-notation with wildcard support.
     *
     * This method handles cases where the first segment in the dot-notation key
     * is a wildcard ('*'). It iterates over each element of the target, applying
     * the remaining segments to set the specified value. If segments are present,
     * it continues setting values recursively. If the overwrite flag is true and
     * no segments remain, it sets each element in the target to the provided value.
     *
     * @param mixed &$target The target to set values in, typically an array.
     * @param array $segments The remaining segments of the dot-notation key.
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite existing values.
     * @return void
     */
    private static function handleWildcardSet(mixed &$target, array $segments, mixed $value, bool $overwrite): void
    {
        if (!BaseArrayHelper::accessible($target)) {
            $target = [];
        }
        if (!empty($segments)) {
            foreach ($target as &$inner) {
                static::setValue($inner, implode('.', $segments), $value, $overwrite);
            }
        } elseif ($overwrite) {
            foreach ($target as &$inner) {
                $inner = $value;
            }
        }
    }


    /**
     * Sets a value in the target array using dot-notation segments.
     *
     * If the segment path is not fully defined within the target array,
     * it will create nested arrays as necessary. If the `overwrite` flag is
     * true, it will replace any existing value at the final segment;
     * otherwise, it will only set the value if the property does not
     * already exist.
     *
     * @param array   &$target The target array to set the value in.
     * @param string  $segment The current segment of the dot-notation key.
     * @param array   $segments The remaining segments of the dot-notation key.
     * @param mixed   $value The value to set.
     * @param bool    $overwrite If true, overwrite any existing value.
     * @return void
     */
    private static function setValueArray(array &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        if (!empty($segments)) {
            if (!ArraySingle::exists($target, $segment)) {
                $target[$segment] = [];
            }
            static::setValue($target[$segment], implode('.', $segments), $value, $overwrite);
        } else {
            if ($overwrite || !ArraySingle::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        }
    }


    /**
     * Sets a value in an object using dot-notation segments.
     *
     * This function is responsible for setting a value in a given object
     * by traversing the object's properties using dot-notation segments.
     * If the segment path is not fully defined within the object, it will
     * create nested arrays as necessary. If the `overwrite` flag is true,
     * it will replace any existing value at the final segment; otherwise,
     * it will only set the value if the property does not already exist.
     *
     * @param object &$target The object to set the value in.
     * @param string $segment The current segment of the dot-notation key.
     * @param array $segments The remaining segments of the dot-notation key.
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite any existing value.
     * @return void
     */
    private static function setValueObject(object &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        if (!empty($segments)) {
            if (!isset($target->{$segment})) {
                $target->{$segment} = [];
            }
            static::setValue($target->{$segment}, implode('.', $segments), $value, $overwrite);
        } else {
            if ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        }
    }


    /**
     * Sets a value in a target that is not an array or object.
     *
     * This function is called when the target is not an array or object.
     * It creates an array and sets the value in the array.
     *
     * @param mixed &$target The target to set the value in.
     * @param string $segment The segment of the dot-notation key.
     * @param array $segments The segments of the dot-notation key.
     * @param mixed $value The value to set.
     * @param bool $overwrite If true, overwrite any existing value.
     * @return void
     */
    private static function setValueFallback(mixed &$target, string $segment, array $segments, mixed $value, bool $overwrite): void
    {
        $target = [];
        if (!empty($segments)) {
            static::setValue($target[$segment], implode('.', $segments), $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }


    /**
     * Retrieve a value from an array using an exact key path.
     *
     * If the key path is not found, the default value is returned.
     *
     * @param mixed $array The array to retrieve the value from.
     * @param string $path The key path to use for retrieval.
     * @param mixed $default The default value to return if the key path is not found.
     * @return mixed The retrieved value or default value.
     */
    private static function segmentExact(mixed $array, string $path, mixed $default): mixed
    {
        if (!str_contains($path, '.')) {
            return ArraySingle::exists($array, $path) ? $array[$path] : $default;
        }
        $parts = explode('.', $path);
        foreach ($parts as $part) {
            if (is_array($array) && ArraySingle::exists($array, $part)) {
                $array = $array[$part];
            } else {
                return $default;
            }
        }
        return $array;
    }


    /**
     * Resolve the {first} segment for an array-like target.
     *
     * @param mixed $target An array or collection-like object.
     * @return string|int|null The first key in the array or collection, or '{first}' if not resolved.
     */
    private static function resolveFirst(mixed $target): string|int|null
    {
        if (method_exists($target, 'all')) {
            $arr = $target->all();
            return array_key_first($arr);
        } elseif (is_array($target)) {
            return array_key_first($target);
        }
        return '{first}';
    }


    /**
     * Resolves the {last} segment for an array-like target.
     *
     * @param mixed $target An array or collection-like object.
     * @return string|int|null The last key in the array or collection, or '{last}' if not resolved.
     */
    private static function resolveLast(mixed $target): string|int|null
    {
        if (method_exists($target, 'all')) {
            $arr = $target->all();
            return array_key_last($arr);
        } elseif (is_array($target)) {
            return array_key_last($target);
        }
        return '{last}';
    }


    /**
     * Returns the given value if it's not a callable, otherwise calls it and returns the result.
     *
     * @param mixed $val
     * @return mixed
     */
    private static function value(mixed $val): mixed
    {
        return is_callable($val) ? $val() : $val;
    }


}
