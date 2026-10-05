<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Generator;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\RuntimeContext;
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
     * @param \Closure(?RunwireLazyBinding): iterable<TKey, TValue> $factory
     */
    private function __construct(
        private \Closure $factory,
        private ?RunwireLazyBinding $runwire = null,
        private bool $factoryChecksRunwire = false,
    ) {}

    /**
     * @template TFromKey of array-key
     * @template TFromValue
     *
     * @param iterable<TFromKey, TFromValue> $source
     * @return self<TFromKey, TFromValue>
     */
    public static function from(iterable $source): self
    {
        if (is_array($source)) {
            return new self(static fn(?RunwireLazyBinding $binding): array => $source);
        }

        return new self(self::replayableFactory($source));
    }

    /**
     * Create a lazy collection from a factory that returns a fresh iterable for
     * every traversal.
     *
     * @template TFactoryKey of array-key
     * @template TFactoryValue
     *
     * @param \Closure(): iterable<TFactoryKey, TFactoryValue> $factory
     * @return self<TFactoryKey, TFactoryValue>
     */
    public static function fromFactory(\Closure $factory): self
    {
        return new self(
            static fn(?RunwireLazyBinding $binding): iterable => $factory(),
        );
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
            return self::fromTraversable($data);
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

        return new self(
            function (?RunwireLazyBinding $binding) use ($size, $preserveKeys): Generator {
                $chunk = [];
                foreach ($this->cursorWithBinding($binding) as $key => $value) {
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
            },
            $this->runwire,
            true,
        );
    }

    /**
     * @return Generator<TKey, TValue>
     */
    public function cursor(): Generator
    {
        yield from $this->cursorWithBinding($this->runwire);
    }

    /**
     * @param callable(TValue, TKey): bool $callback
     * @return self<TKey, TValue>
     */
    public function filterLazy(callable $callback): self
    {
        return new self(
            function (?RunwireLazyBinding $binding) use ($callback): Generator {
                foreach ($this->cursorWithBinding($binding) as $key => $value) {
                    if ($callback($value, $key)) {
                        yield $key => $value;
                    }
                }
            },
            $this->runwire,
            true,
        );
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
        return new self(
            function (?RunwireLazyBinding $binding) use ($callback): Generator {
                foreach ($this->cursorWithBinding($binding) as $key => $value) {
                    yield $key => $callback($value, $key);
                }
            },
            $this->runwire,
            true,
        );
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
            return new self(
                static fn(?RunwireLazyBinding $binding): array => [],
                $this->runwire,
                true,
            );
        }

        return new self(
            function (?RunwireLazyBinding $binding) use ($limit): Generator {
                $count = 0;
                foreach ($this->cursorWithBinding($binding) as $key => $value) {
                    yield $key => $value;
                    $count++;

                    if ($count >= $limit) {
                        return;
                    }
                }
            },
            $this->runwire,
            true,
        );
    }

    /**
     * @param callable(TValue, TKey): bool $callback
     * @return self<TKey, TValue>
     */
    public function takeUntil(callable $callback): self
    {
        return new self(
            function (?RunwireLazyBinding $binding) use ($callback): Generator {
                foreach ($this->cursorWithBinding($binding) as $key => $value) {
                    if ($callback($value, $key)) {
                        break;
                    }

                    yield $key => $value;
                }
            },
            $this->runwire,
            true,
        );
    }

    /**
     * Bind explicit Runwire runtime/request/scope instances to this collection.
     *
     * @return self<TKey, TValue>
     */
    public function withRunwire(
        RuntimeContext $runtime,
        ?RequestContext $request = null,
        ?CoroutineScope $scope = null,
        int $checkpointEvery = 256,
    ): self {
        return new self(
            $this->factory,
            new RunwireLazyBinding($runtime, $request, $scope, $checkpointEvery),
            $this->factoryChecksRunwire,
        );
    }

    /**
     * Normalize traversable input without forcing eager materialization.
     *
     * @param Traversable<array-key, mixed> $source
     * @return self<array-key, mixed>
     */
    private static function fromTraversable(Traversable $source): self
    {
        return new self(self::replayableFactory($source));
    }

    /**
     * Adapt any iterable into a repeatable lazy source without eagerly
     * materializing it. Values already consumed from a one-shot iterator are
     * memoized, while later values are fetched only when a cursor needs them.
     *
     * @template TSourceKey of array-key
     * @template TSourceValue
     *
     * @param iterable<TSourceKey, TSourceValue> $source
     * @return \Closure(?RunwireLazyBinding): iterable<TSourceKey, TSourceValue>
     */
    private static function replayableFactory(iterable $source): \Closure
    {
        /** @var list<array{0: TSourceKey, 1: TSourceValue}> $cache */
        $cache = [];
        $sourceCursor = null;
        $sourceAdvancePending = false;
        $exhausted = false;
        $state = new class {
            public ?\Throwable $failure = null;
        };

        return static function (?RunwireLazyBinding $binding) use (
            $source,
            &$cache,
            &$sourceCursor,
            &$sourceAdvancePending,
            &$exhausted,
            $state,
        ): Generator {
            $position = 0;

            while (true) {
                if (isset($cache[$position])) {
                    [$key, $value] = $cache[$position];
                    yield $key => $value;
                    $position++;

                    continue;
                }

                if ($state->failure !== null) {
                    throw $state->failure;
                }

                if ($exhausted) {
                    return;
                }

                $sourceCursor ??= (static function () use ($source): Generator {
                    yield from $source;
                })();

                try {
                    if ($sourceAdvancePending) {
                        $sourceCursor->next();
                        $sourceAdvancePending = false;
                    }

                    if (!$sourceCursor->valid()) {
                        $exhausted = true;
                        $sourceCursor = null;

                        return;
                    }

                    $entry = [$sourceCursor->key(), $sourceCursor->current()];
                } catch (\Throwable $error) {
                    $state->failure = $error;
                    $sourceCursor = null;
                    $sourceAdvancePending = false;

                    throw $error;
                }

                $cache[] = $entry;
                $sourceAdvancePending = true;

                yield $entry[0] => $entry[1];
                $position++;
            }
        };
    }

    /**
     * @return Generator<TKey, TValue>
     */
    private function cursorWithBinding(?RunwireLazyBinding $binding): Generator
    {
        if ($this->factoryChecksRunwire) {
            $factory = $this->factory;
            yield from $factory($binding);

            return;
        }

        yield from $this->sourceCursorWithBinding($binding);
    }

    /**
     * @return Generator<TKey, TValue>
     */
    private function sourceCursorWithBinding(?RunwireLazyBinding $binding): Generator
    {
        $binding?->checkpoint();

        $factory = $this->factory;
        $iterable = $factory($binding);
        $iterator = (static function () use ($iterable): Generator {
            yield from $iterable;
        })();

        $processed = 0;
        while ($iterator->valid()) {
            yield $iterator->key() => $iterator->current();
            $processed++;

            if (
                $binding !== null
                && ($processed % $binding->checkpointEvery) === 0
            ) {
                $binding->checkpoint();
            }

            $iterator->next();
        }
    }
}
