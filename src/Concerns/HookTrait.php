<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Concerns;

/**
 * Enables on-get and on-set value transformation hooks.
 */
trait HookTrait
{
    /**
     * @var array<string, callable[]>
     */
    protected array $hooks = [];

    public function onGet(string $offset, callable $callback): static
    {
        return $this->addHook($offset, 'get', $callback);
    }

    public function onSet(string $offset, callable $callback): static
    {
        return $this->addHook($offset, 'set', $callback);
    }

    protected function addHook(string|int $offset, string $direction, callable $callback): static
    {
        $name = $this->getHookName((string) $offset, $direction);

        if (!in_array($callback, $this->hooks[$name] ?? [], true)) {
            $this->hooks[$name][] = $callback;
        }

        return $this;
    }

    protected function getHookName(string $hook, string $direction): string
    {
        return $hook . '-' . $direction;
    }

    protected function processValue(string|int $offset, mixed $value, string $direction): mixed
    {
        $name = $this->getHookName((string) $offset, $direction);
        $hooks = $this->hooks[$name] ?? [];

        foreach ($hooks as $hook) {
            $value = $hook($value);
        }

        return $value;
    }
}
