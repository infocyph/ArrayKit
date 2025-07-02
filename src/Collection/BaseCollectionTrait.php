<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use ArrayIterator;
use BadMethodCallException;
use Infocyph\ArrayKit\Array\DotNotation;
use JsonSerializable;
use Traversable;

trait BaseCollectionTrait
{
    /**
     * Holds the underlying array data for the collection.
     */
    protected array $data = [];

    protected ?Pipeline $pipeline = null;

    /**
     * Constructor. Initializes the collection with the given array data.
     *
     * @param  array  $data  The initial data for the collection.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Create a new instance of the collection from the given data.
     *
     * This method acts as a wrapper around the `make` method,
     * allowing for the creation of a collection instance using
     * the provided data. The data is processed to ensure it is
     * in an array format suitable for the collection.
     *
     * @param mixed $data The data to initialize the collection with.
     * @return static A new instance of the collection.
     */
    public static function from(mixed $data): static
    {
        return static::make($data);
    }

    /**
     * Create a new instance of the collection with the given data.
     *
     * If the given data is not an array, it will be converted to an array.
     *
     * @param  mixed  $data  The data to initialize the collection with.
     * @return static
     */
    public static function make(mixed $data): static
    {
        $instance = new static([]);
        $instance->data = $instance->getArrayableItems($data);

        return $instance;
    }

    /**
     * Dynamically handle calls to methods on the collection.
     *
     * This magic method allows for dynamic method calls to be
     * processed through the collection's pipeline. If the
     * method exists on the pipeline, it will be invoked with
     * the provided arguments. An exception is thrown if the
     * method does not exist.
     *
     * @param string $method The name of the method being called.
     * @param array $arguments The arguments to pass to the method.
     * @return mixed The result of the method call.
     * @throws BadMethodCallException If the method does not exist.
     */
    public function __call(string $method, array $arguments): mixed
    {
        $pipeline = $this->process();
        if (method_exists($pipeline, $method)) {
            return $pipeline->$method(...$arguments);
        }
        throw new BadMethodCallException("Method $method does not exist in ".static::class);
    }


    /**
     * Invokes the collection and returns the underlying array data.
     *
     * When the collection is invoked as a function (e.g. `$collection()`),
     * the underlying array data is returned directly.
     *
     * @return array The array data of this collection.
     */
    public function __invoke(): array
    {
        return $this->data;
    }

    /**
     * Create and return a new Pipeline instance using the current collection's data.
     *
     * This method initializes a processing pipeline, allowing method chaining
     * for array transformations or operations.
     *
     * @return Pipeline A new pipeline instance for further processing.
     */
    public function process(): Pipeline
    {
        return $this->pipeline ??= new Pipeline($this->data, $this);
    }


    /**
     * Retrieve an item from the collection by key or keys.
     *
     * The following cases are handled:
     *  - If no key is provided, the entire collection is returned.
     *  - If an array of keys is provided, all values are returned in an array.
     *  - If a single key is provided, the value is returned directly.
     *
     * @param string|array $keys The key(s) to retrieve.
     * @return mixed The retrieved value(s).
     */
    public function get(string|array $keys): mixed
    {
        return DotNotation::get($this->data, $keys);
    }


    /**
     * Determine if the given key or keys exist in the collection.
     *
     * @param string|array $keys The key(s) to check for existence.
     * @return bool True if all the given keys exist in the collection, false otherwise.
     */
    public function has(string|array $keys): bool
    {
        return DotNotation::has($this->data, $keys);
    }


    /**
     * Check if at least one of the given keys exists in the collection.
     *
     * This method determines whether any of the specified keys are present
     * within the collection's data. It supports checking a single key
     * or an array of keys.
     *
     * @param string|array $keys The key(s) to check for existence.
     * @return bool True if at least one key exists, false otherwise.
     */
    public function hasAny(string|array $keys): bool
    {
        return DotNotation::hasAny($this->data, $keys);
    }


    /**
     * Set one or multiple items in the collection using dot notation.
     *
     * If no key is provided, the entire collection is replaced with $value.
     * If an array of key-value pairs is provided, each value is set.
     * If a single key is provided, the value is set directly.
     *
     * @param array|string|null $keys The key(s) to set.
     * @param mixed $value The value to set.
     * @return bool True on success.
     */
    public function set(array|string|null $keys = null, mixed $value = null): bool
    {
        return DotNotation::set($this->data, $keys, $value);
    }

    /**
     * Magic getter to retrieve an item via property access: $collection->key
     *
     * This method allows for accessing collection items using property syntax.
     * It internally calls the offsetGet method to retrieve the value.
     *
     * @param string $key The key of the item to retrieve.
     * @return mixed The value associated with the given key.
     */
    public function __get(string $key): mixed
    {
        return $this->offsetGet($key);
    }


    /**
     * Magic setter to set an item via property access: $collection->key = $value
     *
     * This method allows for setting collection items using property syntax.
     * It internally calls the offsetSet method to set the value.
     *
     * @param string $key The key of the item to set.
     * @param mixed $value The value to set.
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->offsetSet($key, $value);
    }


    /**
     * Magic isset to check for existence of an item via property access: isset($collection->key)
     *
     * This method allows for checking if an item exists using property syntax.
     * It internally calls the offsetExists method to check for existence.
     *
     * @param string $key The key of the item to check for existence.
     * @return bool True if the item exists, false otherwise.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }


    /**
     * Magic unset to remove an item via property access: unset($collection->key)
     *
     * This method allows for removing collection items using property syntax.
     * It internally calls the offsetUnset method to remove the value.
     *
     * @param string $key The key of the item to remove.
     * @return void
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }


    /**
     * Normalizes the given items to an array.
     *
     * If the $items is an instance of {@see self}, it will call the `items()` method on it.
     * If the $items is an instance of {@see JsonSerializable}, it will call the `jsonSerialize()` method on it.
     * If the $items is a traversable, it will convert it to an array using the `iterator_to_array()` function.
     * Otherwise, it will cast the $items to an array.
     *
     * @param mixed $items The items to normalize.
     * @return array The normalized items.
     */
    public function getArrayableItems(mixed $items): array
    {
        return match (true) {
            $items instanceof self => $items->items(),
            $items instanceof JsonSerializable => $items->jsonSerialize(),
            $items instanceof Traversable => iterator_to_array($items),
            default => (array) $items,
        };
    }


    /**
     * Returns the entire array of items in this collection.
     *
     * This is an alias for the `items()` method.
     *
     * @return array The entire array of items in this collection.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Return the raw array of items in this collection.
     */
    public function items(): array
    {
        return $this->data;
    }

    /**
     * Get the collection of items as a JSON string.
     *
     * @param  int  $options  JSON encoding options
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->data, $options);
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Convert the collection to a JSON string when treated as a string.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get the collection of items as a plain array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Return an array of all the keys in the collection.
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Provide custom debug information.
     */
    public function __debugInfo(): array
    {
        return [
            'data' => $this->data,
            'count' => $this->count(),
        ];
    }

    /**
     * Clear all items from the collection.
     */
    public function clear(): void
    {
        $this->data = [];
    }

    /*
    |--------------------------------------------------------------------------
    | ArrayAccess Interface
    |--------------------------------------------------------------------------
    */

    /**
     * Determine if an item exists in the collection by key.
     *
     * This method is a part of the ArrayAccess interface and is used to
     * check if an item exists in the collection by its key. The method
     * returns true if the item exists, or false otherwise.
     *
     * @param mixed $offset The key to check.
     * @return bool True if the item exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (is_string($offset) && str_contains($offset, '.')) {
            return DotNotation::offsetExists($this->data, $offset);
        }
        return isset($this->data[$offset]);
    }

    /**
     * Get an item from the collection by key.
     *
     * This method is a part of the ArrayAccess interface and is used to
     * retrieve an item from the collection by its key. If the key is not
     * found, the method returns null.
     *
     * @param mixed $offset The key of the item to retrieve.
     * @return mixed The item associated with the given key, or null if not found.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (is_string($offset) && str_contains($offset, '.')) {
            return DotNotation::get($this->data, $offset);
        }
        return $this->data[$offset] ?? null;
    }

    /**
     * Set an item in the collection by key.
     *
     * This method is a part of the ArrayAccess interface and is used to
     * set an item in the collection by its key. If the key is null,
     * the item is appended to the end of the collection.
     *
     * @param mixed $offset The key of the item to set, or null to append.
     * @param mixed $value The value of the item to set.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        match (true) {
            $offset === null => $this->data[] = $value,

            is_string($offset) && str_contains($offset, '.') =>
            DotNotation::set($this->data, $offset, $value),

            default => $this->data[$offset] = $value,
        };
    }


    /**
     * Remove an item from the collection by key.
     *
     * This method is a part of the ArrayAccess interface and is used to
     * remove an item from the collection by its key. It directly calls
     * the unset() language construct to delete the item from the
     * collection's internal data array.
     *
     * @param mixed $offset The key of the item to remove.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset) && str_contains($offset, '.')) {
            DotNotation::forget($this->data, $offset);
            return;
        }
        unset($this->data[$offset]);
    }


    /*
    |--------------------------------------------------------------------------
    | Iterator Interface
    |--------------------------------------------------------------------------
    */

    /**
     * Retrieve an external iterator.
     *
     * This method returns an instance of ArrayIterator that can be used
     * to iterate over the collection's data. It is part of the IteratorAggregate
     * interface, allowing for external iteration of the collection.
     *
     * @return Traversable An iterator for the collection's data.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Returns the current element in the collection.
     *
     * This is part of the Iterator interface.
     *
     * @return mixed The current element in the collection.
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * Return the key of the current element.
     *
     * This is part of the Iterator interface.
     *
     * @return string|int|null The key of the current element, or null if the
     *                         internal pointer is not valid.
     */
    public function key(): string|int|null
    {
        return key($this->data);
    }

    /**
     * Advances the internal pointer to the next element.
     *
     * This is part of the Iterator interface.
     */
    public function next(): void
    {
        next($this->data);
    }

    /**
     * Checks if the current element is valid.
     *
     * This is part of the Iterator interface.
     *
     * @return bool True if the current element is valid, false otherwise.
     */
    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    /**
     * Rewinds the internal pointer of the collection to the first element.
     *
     * This is part of the Iterator interface.
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /*
    |--------------------------------------------------------------------------
    | Countable Interface
    |--------------------------------------------------------------------------
    */

    /**
     * Returns the number of items in the collection.
     *
     * @return int The number of items in the collection.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /*
    |--------------------------------------------------------------------------
    | JsonSerializable Interface
    |--------------------------------------------------------------------------
    */

    /**
     * Convert the collection of items to an array suitable for JSON serialization.
     *
     * This method ensures that each item within the collection is converted
     * to an array representation if it implements the JsonSerializable interface.
     * Non-serializable items are returned as-is.
     *
     * @return array The array representation of the collection, ready for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn ($value) => $value instanceof JsonSerializable ? $value->jsonSerialize() : $value,
            $this->data,
        );
    }
}
