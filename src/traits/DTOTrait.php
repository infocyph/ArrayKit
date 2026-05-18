<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\traits;

use ReflectionNamedType;
use ReflectionProperty;

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
     * @param array<array-key, mixed> $values Key-value pairs matching property names
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
     * @param array<array-key, mixed> $values Key-value pairs matching property names
     */
    public function fromArray(array $values): static
    {
        return $this->hydrate($values);
    }

    /**
     * Populate DTO with optional key mapping and coercion.
     *
     * @param array<array-key, mixed> $values
     * @param array<string, string> $mapping
     */
    public function hydrate(array $values, array $mapping = [], bool $coerce = false): static
    {
        foreach ($values as $key => $value) {
            $property = is_string($key) && isset($mapping[$key]) ? $mapping[$key] : $key;
            if (!is_string($property) || !property_exists($this, $property)) {
                continue;
            }

            $this->assignProperty($property, $value, $coerce);
        }

        return $this;
    }

    /**
     * Hydrate DTO using nested DTO constructors when property type is DTO-like.
     *
     * @param array<array-key, mixed> $values
     * @param array<string, string> $mapping
     */
    public function hydrateNested(array $values, array $mapping = [], bool $coerce = false): static
    {
        foreach ($values as $key => $value) {
            $property = is_string($key) && isset($mapping[$key]) ? $mapping[$key] : $key;
            if (!is_string($property) || !property_exists($this, $property)) {
                continue;
            }

            $value = $this->resolveNestedValue($property, $value);
            $this->assignProperty($property, $value, $coerce);
        }

        return $this;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<string, string> $mapping
     */
    public function replaceFromArray(array $values, array $mapping = [], bool $coerce = false): static
    {
        return $this->hydrate($values, $mapping, $coerce);
    }

    /**
     * Convert the current object’s public properties into an array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Export DTO recursively (nested DTO/array values are converted to arrays).
     *
     * @return array<array-key, mixed>
     */
    public function toArrayDeep(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            $result[$key] = $this->exportValue($value);
        }

        return $result;
    }

    private function assignProperty(string $property, mixed $value, bool $coerce): void
    {
        if (!$coerce) {
            $this->{$property} = $value;

            return;
        }

        $reflection = new ReflectionProperty($this, $property);
        $type = $reflection->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            $this->{$property} = $value;

            return;
        }

        $resolved = match ($type->getName()) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) || $value === null ? (string) $value : $value,
            'bool' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };

        $this->{$property} = $resolved;
    }

    private function exportValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->exportValue($item);
            }

            return $result;
        }

        if (is_object($value) && method_exists($value, 'toArrayDeep')) {
            return $value->toArrayDeep();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    private function resolveNestedValue(string $property, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $reflection = new ReflectionProperty($this, $property);
        $type = $reflection->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $value;
        }

        $className = $type->getName();
        if (method_exists($className, 'create')) {
            return $className::create($value);
        }

        if (!class_exists($className)) {
            return $value;
        }

        $instance = new $className();
        if (method_exists($instance, 'fromArray')) {
            return $instance->fromArray($value);
        }

        return $value;
    }
}
