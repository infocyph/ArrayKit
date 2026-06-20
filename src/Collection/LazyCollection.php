<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Generator;
use IteratorAggregate;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
final readonly class LazyCollection implements IteratorAggregate
{
    /**
     * @param \Closure(): iterable<TKey, TValue> $factory
     */
    private function __construct(private \Closure $factory) {}

    /**
     * @template TFromKey of array-key
     * @template TFromValue
     *
     * @param iterable<TFromKey, TFromValue> $source
     * @return self<TFromKey, TFromValue>
     */
    public static function from(iterable $source): self
    {
        return new self(static fn(): iterable => $source);
    }

    /**
     * @return self<array-key, mixed>
     */
    public static function make(mixed $data = []): self
    {
        if (is_array($data)) {
            return self::from($data);
        }

        if ($data instanceof Traversable) {
            return self::from(self::iterableToArray($data));
        }

        if ($data === null) {
            return self::from([]);
        }

        return self::from([$data]);
    }

    /**
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return iterator_to_array($this->cursor(), true);
    }

    /**
     * @return self<int, array<int|string, TValue>>
     */
    public function chunkLazy(int $size, bool $preserveKeys = false): self
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        return new self(function () use ($size, $preserveKeys): Generator {
            $chunk = [];
            foreach ($this->cursor() as $key => $value) {
                if ($preserveKeys) {
                    $chunk[$key] = $value;
                } else {
                    $chunk[] = $value;
                }

                if (count($chunk) === $size) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                yield $chunk;
            }
        });
    }

    /**
     * @return Generator<TKey, TValue>
     */
    public function cursor(): Generator
    {
        $factory = $this->factory;
        foreach ($factory() as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @param callable(TValue, TKey): bool $callback
     * @return self<TKey, TValue>
     */
    public function filterLazy(callable $callback): self
    {
        return new self(function () use ($callback): Generator {
            foreach ($this->cursor() as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return $this->cursor();
    }

    /**
     * @template TMapped
     *
     * @param callable(TValue, TKey): TMapped $callback
     * @return self<TKey, TMapped>
     */
    public function mapLazy(callable $callback): self
    {
        return new self(function () use ($callback): Generator {
            foreach ($this->cursor() as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * @return self<TKey, TValue>
     */
    public function take(int $limit): self
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('Take limit must be zero or greater.');
        }

        if ($limit === 0) {
            return self::from([]);
        }

        return new self(function () use ($limit): Generator {
            $count = 0;
            foreach ($this->cursor() as $key => $value) {
                if ($count >= $limit) {
                    break;
                }

                yield $key => $value;
                $count++;
            }
        });
    }

    /**
     * @param callable(TValue, TKey): bool $callback
     * @return self<TKey, TValue>
     */
    public function takeUntil(callable $callback): self
    {
        return new self(function () use ($callback): Generator {
            foreach ($this->cursor() as $key => $value) {
                if ($callback($value, $key)) {
                    break;
                }

                yield $key => $value;
            }
        });
    }

    /**
     * Normalize traversable input to array-key arrays for generic safety.
     *
     * @param Traversable<mixed, mixed> $source
     * @return array<array-key, mixed>
     */
    private static function iterableToArray(Traversable $source): array
    {
        $results = [];
        foreach ($source as $key => $value) {
            if (is_int($key) || is_string($key)) {
                $results[$key] = $value;

                continue;
            }

            $results[] = $value;
        }

        return $results;
    }
}
