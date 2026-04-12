<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\traits;

/**
 * Trait DTOTrait
 *
 * Provides a quick way to create an object from an associative array
 * and to convert an object's public properties to an array.
 *
 * Usage Example:
 *  class MyDTO {
 *      use DTOTrait;
 *
 *      public string $name;
 *      public int $age;
 *  }
 *
 *  $dto = MyDTO::create(['name' => 'Alice', 'age' => 30]);
 */
trait DTOTrait
{
    /**
     * Create a new instance of the using class and populate
     * its public properties from the given array.
     *
     * Unknown keys are ignored. Only properties matching
     * class property names will be set.
     *
     * @param array $values Key-value pairs matching property names
     */
    public static function create(array $values): static
    {
        return new static()->fromArray($values);
    }

    /**
     * Populate the current object from an array of values.
     *
     * Unknown keys are ignored.
     *
     * @param array $values Key-value pairs matching property names
     */
    public function fromArray(array $values): static
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * Convert the current object’s public properties into an array.
     */
    public function toArray(): array
    {
        // get_object_vars($this) returns an associative array
        // of property name => value for all accessible (public) properties
        return get_object_vars($this);
    }
}
