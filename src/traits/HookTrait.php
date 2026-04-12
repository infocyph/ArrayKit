<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\traits;

/**
 * Trait HookTrait
 *
 * Enables attaching "on get" or "on set" hooks for specific offsets/keys.
 * Each hook is a callable that transforms the value at runtime.
 *
 * Example usage:
 *   $this->onGet('username', fn($value) => strtolower($value));
 *   $this->onSet('password', fn($plain) => password_hash($plain, PASSWORD_BCRYPT));
 */
trait HookTrait
{
    /**
     * Holds the registered hooks, structured as:
     *   [
     *       'someKey-get' => [...callables],
     *       'someKey-set' => [...callables],
     *   ]
     *
     * @var array<string, callable[]>
     */
    protected array $hooks = [];

    /**
     * Attach a callable hook that runs when a value is retrieved (on get) for the given offset.
     *
     * @param string   $offset   The key or offset
     * @param callable $callback A transformation: fn($value) => $newValue
     */
    public function onGet(string $offset, callable $callback): static
    {
        return $this->addHook($offset, 'get', $callback);
    }

    /**
     * Attach a callable hook that runs when a value is assigned (on set) for the given offset.
     *
     * @param string   $offset   The key or offset
     * @param callable $callback A transformation: fn($value) => $newValue
     */
    public function onSet(string $offset, callable $callback): static
    {
        return $this->addHook($offset, 'set', $callback);
    }

    /**
     * Register a hook callback for a given offset and direction ("get" or "set").
     *
     * @param mixed    $offset    The key or offset (string recommended)
     * @param string   $direction Either "get" or "set"
     * @param callable $callback  The transformation function
     */
    protected function addHook(mixed $offset, string $direction, callable $callback): static
    {
        $name = $this->getHookName((string) $offset, $direction);

        if (!in_array($callback, $this->hooks[$name] ?? [], true)) {
            $this->hooks[$name][] = $callback;
        }

        return $this;
    }

    /**
     * Construct the internal key for hooking, e.g. "offset-get" or "offset-set".
     *
     * @param string $hook       The offset or key
     * @param string $direction  Either "get" or "set"
     */
    protected function getHookName(string $hook, string $direction): string
    {
        return $hook . '-' . $direction;
    }

    /**
     * Apply any relevant hooks to a value before returning or storing it.
     *
     * @param mixed  $offset    The key or offset
     * @param mixed  $value     The value to be transformed
     * @param string $direction Either "get" or "set"
     * @return mixed The possibly transformed value
     */
    protected function processValue(mixed $offset, mixed $value, string $direction): mixed
    {
        $name  = $this->getHookName((string) $offset, $direction);
        $hooks = $this->hooks[$name] ?? [];

        foreach ($hooks as $hook) {
            $value = $hook($value);
        }

        return $value;
    }
}
