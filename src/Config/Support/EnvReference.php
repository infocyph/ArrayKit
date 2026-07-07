<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

final readonly class EnvReference
{
    public function __construct(
        private string $key,
        private mixed $default = null,
    ) {}

    public function resolve(): mixed
    {
        return Environment::get($this->key, $this->default);
    }
}
