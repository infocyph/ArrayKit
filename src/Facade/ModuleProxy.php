<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Facade;

use BadMethodCallException;
use UnexpectedValueException;

final readonly class ModuleProxy
{
    /**
     * @param class-string $targetClass
     */
    public function __construct(
        private string $targetClass,
    ) {}

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->invoke($method, $arguments);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed>|string $keys
     */
    public function fill(array &$array, array|string $keys, mixed $value = null): void
    {
        $this->invoke('fill', [&$array, $keys, $value]);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, int|string>|int|string|null $keys
     */
    public function forget(array &$array, array|string|int|null $keys): void
    {
        $this->invoke('forget', [&$array, $keys]);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function move(array &$array, string $from, string $to, bool $overwrite = true): bool
    {
        return $this->invokeBool('move', [&$array, $from, $to, $overwrite]);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function offsetSet(array &$array, string $key, mixed $value): void
    {
        $this->invoke('offsetSet', [&$array, $key, $value]);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function offsetUnset(array &$array, string $key): void
    {
        $this->invoke('offsetUnset', [&$array, $key]);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function rename(array &$array, string $from, string $to, bool $overwrite = true): bool
    {
        return $this->invokeBool('rename', [&$array, $from, $to, $overwrite]);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed>|string|null $keys
     */
    public function set(array &$array, array|string|null $keys = null, mixed $value = null, bool $overwrite = true): bool
    {
        return $this->invokeBool('set', [&$array, $keys, $value, $overwrite]);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invoke(string $method, array $arguments): mixed
    {
        if (!is_callable([$this->targetClass, $method])) {
            throw new BadMethodCallException("Method {$this->targetClass}::{$method} does not exist.");
        }

        return $this->targetClass::$method(...$arguments);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokeBool(string $method, array $arguments): bool
    {
        $result = $this->invoke($method, $arguments);
        if (!is_bool($result)) {
            throw new UnexpectedValueException("Method {$this->targetClass}::{$method} must return bool.");
        }

        return $result;
    }
}
