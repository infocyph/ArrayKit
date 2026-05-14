<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\traits\HookTrait;

/**
 * Class HookedCollection
 *
 * A collection that supports get/set hooks for dynamic transformations.
 */
class HookedCollection extends Collection
{
    use HookTrait;

    /**
     * Gets an item at the given offset.
     *
     * Applies any "on get" hooks associated with that offset.
     *
     * @param mixed $offset The array key
     * @return mixed The transformed value or null if not found
     */
    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }

        $value = is_string($offset) && str_contains($offset, '.')
            ? DotNotation::get($this->data, $offset)
            : parent::offsetGet($offset);

        if (!is_string($offset) && !is_int($offset)) {
            return $value;
        }

        return $this->processValue($offset, $value, 'get');
    }

    /**
     * Sets an item at the given offset.
     *
     * Applies any "on set" hooks associated with that offset.
     *
     * @param mixed $offset The array key
     * @param mixed $value The value to set
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset) && !is_int($offset)) {
            parent::offsetSet($offset, $value);

            return;
        }

        $processed = $this->processValue($offset, $value, 'set');

        if (is_string($offset) && str_contains($offset, '.')) {
            DotNotation::set($this->data, $offset, $processed);

            return;
        }

        parent::offsetSet($offset, $processed);
    }
}
