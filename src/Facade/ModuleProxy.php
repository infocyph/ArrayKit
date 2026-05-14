<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Facade;

use BadMethodCallException;

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
        if (!method_exists($this->targetClass, $method)) {
            throw new BadMethodCallException("Method {$this->targetClass}::{$method} does not exist.");
        }

        return $this->targetClass::$method(...$arguments);
    }
}
