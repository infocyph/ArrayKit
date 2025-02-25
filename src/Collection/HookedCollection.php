<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Infocyph\ArrayKit\traits\HookTrait;

/**
 * Class HookedCollection
 *
 * An array-based collection (implements ArrayAccess, Iterator, Countable,
 * and JsonSerializable) that supports get/set hooks for dynamic transformations.
 */
class HookedCollection implements ArrayAccess, Iterator, Countable, JsonSerializable
{
    use BaseCollectionTrait;
    use HookTrait;

    /**
     * Gets an item at the given offset.
     *
     * Applies any "on get" hooks associated with that offset.
     *
     * @param mixed $offset The array key
     * @return mixed The transformed value or null if not found
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }

        return $this->processValue($offset, $this->data[$offset], 'get');
    }

    /**
     * Sets an item at the given offset.
     *
     * Applies any "on set" hooks associated with that offset.
     *
     * @param mixed $offset The array key
     * @param mixed $value  The value to set
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // If offset is null, append to the array.
        if ($offset === null) {
            $this->data[] = $this->processValue($offset, $value, 'set');
        } else {
            $this->data[$offset] = $this->processValue($offset, $value, 'set');
        }
    }
}
