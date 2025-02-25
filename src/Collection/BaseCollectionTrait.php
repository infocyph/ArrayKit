<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use ArrayIterator;
use BadMethodCallException;
use JsonSerializable;
use Traversable;

trait BaseCollectionTrait
{
    /**
     * Holds the underlying array data for the collection.
     */
    protected array $data = [];

    protected Pipeline $pipeline;

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
     * Create a new collection instance from any arrayable input.
     */
    public static function make(mixed $data): static
    {
        $instance = new static([]);
        $instance->data = $instance->getArrayableItems($data);

        return $instance;
    }

    /**
     * Magic method __call to delegate undefined method calls.
     *
     * If a method isn't defined on the collection, the call is forwarded to
     * a new Pipeline instance (which offers a rich, chainable API).
     *
     * @return Pipeline|mixed
     *
     * @throws BadMethodCallException
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
     * Magic method __invoke allows the instance to be called as a function.
     *
     * When the collection object is used as a function, it returns the underlying data array.
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
     * Set the underlying array data for the collection.
     * This method is chainable.
     *
     * @param  array  $data  The new data to set.
     * @return static The current collection instance, for chaining.
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Retrieve the current collection instance.
     *
     * This method returns the current collection object itself, allowing
     * for further method chaining or operations on the existing collection.
     *
     * @return static The current collection instance.
     */
    public function get(): static
    {
        return $this;
    }

    /**
     * Magic getter to retrieve an item via property access: $collection->key
     */
    public function __get(string $key): mixed
    {
        return $this->offsetGet($key);
    }

    /**
     * Magic setter to set an item via property access: $collection->key = value
     */
    public function __set(string $key, mixed $value): void
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Magic isset to check existence of an item via property access: isset($collection->key)
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Magic unset to remove an item via property access: unset($collection->key)
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert various structures (collections, Traversable, etc.) to an array.
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

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]) || array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /*
    |--------------------------------------------------------------------------
    | Iterator Interface
    |--------------------------------------------------------------------------
    */

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function current(): mixed
    {
        return current($this->data);
    }

    public function key(): string|int|null
    {
        return key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    /*
    |--------------------------------------------------------------------------
    | Countable Interface
    |--------------------------------------------------------------------------
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

    public function jsonSerialize(): array
    {
        return array_map(
            static fn ($value) => $value instanceof JsonSerializable ? $value->jsonSerialize() : $value,
            $this->data,
        );
    }
}
