Usage Reference
===================

This page is usage-first.
Use it to pick the right feature quickly, then go deeper in the dedicated guide pages.

If you need exact method signatures, see the ``Exact API Signatures`` section at the end of this page.

How To Use This Page
--------------------

1. Start from the feature that matches your task.
2. Open the linked guide for full examples and behavior notes.
3. Use the signature appendix only when you need exact parameter and return types.

Feature Entry Points
--------------------

Array helpers (list/map/nested array operations):
    :doc:`array-helpers`

Dot-notation read/write for nested data:
    :doc:`dot-notation`

Object-style data pipeline and fluent transformations:
    :doc:`collection`

Configuration storage with optional get/set hooks:
    :doc:`config`

Single facade entrypoint for modules and factories:
    :doc:`facade`

DTO and hook traits plus global helper functions:
    :doc:`traits-and-helpers`

Common Workflows
----------------

Nested data access (read/write with fallback):

.. code-block:: php

    <?php
    $user = ['profile' => ['name' => 'Alice']];
    $name = array_get($user, 'profile.name', 'Guest');
    array_set($user, 'profile.email', 'alice@example.com');

Collection transformation pipeline:

.. code-block:: php

    <?php
    $result = collect([1, 2, 3, 4])
        ->filter(fn ($v) => $v % 2 === 0)
        ->map(fn ($v) => $v * 10)
        ->all(); // [1 => 20, 3 => 40]

Runtime configuration with hooks:

.. code-block:: php

    <?php
    $config = new \Infocyph\ArrayKit\Config\Config();
    $config->onSet('app.name', fn ($v) => trim((string) $v));
    $config->setWithHooks('app.name', '  ArrayKit  ');
    echo $config->getWithHooks('app.name'); // ArrayKit

Static array utilities for data shaping:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $rows = [
        ['team' => 'A', 'score' => 10],
        ['team' => 'B', 'score' => 30],
    ];

    $sorted = ArrayMulti::sortBy($rows, 'score', true);
    $scores = ArrayMulti::pluck($rows, 'score');

Exact API Signatures
--------------------

This appendix maps the current public API surface in ``src/`` one-to-one.

Global Helper Functions
-----------------------

.. code-block:: php

    function compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    function isCallable(mixed $value): bool
    function array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed
    function array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool
    function collect(mixed $data = []): Collection
    function chain(mixed $data): Pipeline
    function Infocyph\ArrayKit\compare(mixed $retrieved, mixed $value, ?string $operator = null): bool
    function Infocyph\ArrayKit\array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed
    function Infocyph\ArrayKit\array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool
    function Infocyph\ArrayKit\collect(mixed $data = []): Collection
    function Infocyph\ArrayKit\chain(mixed $data): Pipeline

ArrayKit Facade
---------------

.. code-block:: php

    public static function single(): ModuleProxy
    public static function multi(): ModuleProxy
    public static function helper(): ModuleProxy
    public static function dot(): ModuleProxy
    public static function config(array $items = []): Config
    public static function lazyConfig(string $directory, string $extension = 'php', array $items = []): LazyFileConfig
    public static function collection(mixed $data = []): Collection
    public static function hookedCollection(mixed $data = []): HookedCollection
    public static function pipeline(mixed $data): Pipeline

Facade ModuleProxy
------------------

.. code-block:: php

    public function __call(string $method, array $arguments): mixed

BaseArrayHelper
---------------------------------------

.. code-block:: php

    public static function isMultiDimensional(mixed $array): bool
    public static function wrap(mixed $value): array
    public static function unWrap(mixed $value): mixed
    public static function unwrap(mixed $value): mixed
    public static function haveAny(array $array, callable $callback): bool
    public static function isAll(array $array, callable $callback): bool
    public static function findKey(array $array, callable $callback): int|string|null
    public static function accessible(mixed $value): bool
    public static function has(array $array, int|string|array $keys): bool
    public static function hasAny(array $array, int|string|array $keys): bool
    public static function range(int $start, int $end, int $step = 1): array
    public static function times(int $number, ?callable $callback = null): array
    public static function any(array $array, callable $callback): bool
    public static function all(array $array, callable $callback): bool
    public static function tap(array $array, callable $callback): array
    public static function forget(array &$array, int|string|array $keys): void
    public static function random(array $array, ?int $number = null, bool $preserveKeys = false): mixed
    public static function doReject(array $array, mixed $callback): array

ArraySharedOps (Internal)
---------------------------------------

.. code-block:: php

    public static function each(array $array, callable $callback): array
    public static function every(array $array, callable $callback): bool
    public static function partition(array $array, callable $callback): array
    public static function skip(array $array, int $count): array
    public static function skipUntil(array $array, callable $callback): array
    public static function skipWhile(array $array, callable $callback): array
    public static function normalizeArrayKey(mixed $value): int|string
    public static function asString(mixed $value): string

ArraySingle
-----------------------------------

.. code-block:: php

    public static function exists(array $array, int|string $key): bool
    public static function only(array $array, array|string $keys): array
    public static function separate(array $array): array
    public static function isList(array $array): bool
    public static function isAssoc(array $array): bool
    public static function prepend(array $array, mixed $value, mixed $key = null): array
    public static function isPositive(array $array): bool
    public static function isNegative(array $array): bool
    public static function shuffle(array $array, ?int $seed = null): array
    public static function isInt(array $array): bool
    public static function nonEmpty(array $array): array
    public static function avg(array $array): float|int
    public static function isUnique(array $array): bool
    public static function positive(array $array): array
    public static function negative(array $array): array
    public static function nth(array $array, int $step, int $offset = 0): array
    public static function duplicates(array $array): array
    public static function paginate(array $array, int $page, int $perPage): array
    public static function combine(array $keys, array $values): array
    public static function where(array $array, ?callable $callback = null): array
    public static function search(array $array, mixed $needle): int|string|null
    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    public static function map(array $array, callable $callback): array
    public static function mapWithKeys(array $array, callable $callback): array
    public static function each(array $array, callable $callback): array
    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    public static function some(array $array, callable $callback): bool
    public static function every(array $array, callable $callback): bool
    public static function contains(array $array, mixed $valueOrCallback, bool $strict = false): bool
    public static function countBy(array $array, ?callable $by = null): array
    public static function sum(array $array, ?callable $callback = null): float|int
    public static function unique(array $array, bool $strict = false): array
    public static function values(array $array): array
    public static function reject(array $array, mixed $callback = true): array
    public static function intersect(array $array, array $values, bool $strict = false): array
    public static function diff(array $array, array $values, bool $strict = false): array
    public static function symmetricDiff(array $left, array $right, bool $strict = false): array
    public static function same(array $left, array $right, bool $strict = false): bool
    public static function slice(array $array, int $offset, ?int $length = null): array
    public static function skip(array $array, int $count): array
    public static function skipWhile(array $array, callable $callback): array
    public static function skipUntil(array $array, callable $callback): array
    public static function partition(array $array, callable $callback): array
    public static function mode(array $array): array
    public static function median(array $array): float|int
    public static function min(array $array): float|int|null
    public static function max(array $array): float|int|null
    public static function minBy(array $array, callable $callback): mixed
    public static function maxBy(array $array, callable $callback): mixed
    public static function except(array $array, array|string $keys): array
    public static function rekey(array $array, array|callable $mapper): array

ArrayMulti
----------------------------------

.. code-block:: php

    public static function only(array $array, array|string $keys): array
    public static function collapse(array $array): array
    public static function depth(array $array): int
    public static function flatten(array $array, float|int $depth = \INF): array
    public static function flattenByKey(array $array): array
    public static function values(array $array): array
    public static function rekey(array $array, array|callable $mapper): array
    public static function sortRecursive(array $array, int $options = \SORT_REGULAR, bool $descending = false): array
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    public static function firstWhere(array $array, string $key, mixed $operator = null, mixed $value = null, mixed $default = null): mixed
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    public static function between(array $array, string $key, float|int $from, float|int $to): array
    public static function whereCallback(array $array, ?callable $callback = null, mixed $default = null): mixed
    public static function where(array $array, string $key, mixed $operator = null, mixed $value = null): array
    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    public static function map(array $array, callable $callback): array
    public static function each(array $array, callable $callback): array
    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    public static function some(array $array, callable $callback): bool
    public static function every(array $array, callable $callback): bool
    public static function contains(array $array, mixed $valueOrCallback, bool $strict = false): bool
    public static function unique(array $array, bool $strict = false): array
    public static function reject(array $array, mixed $callback = true): array
    public static function partition(array $array, callable $callback): array
    public static function skip(array $array, int $count): array
    public static function skipWhile(array $array, callable $callback): array
    public static function skipUntil(array $array, callable $callback): array
    public static function sum(array $array, string|callable|null $keyOrCallback = null): float|int
    public static function min(array $array, string|callable $keyOrCallback): float|int|null
    public static function max(array $array, string|callable $keyOrCallback): float|int|null
    public static function minBy(array $array, string|callable $keyOrCallback): mixed
    public static function maxBy(array $array, string|callable $keyOrCallback): mixed
    public static function countBy(array $array, string|callable $groupBy): array
    public static function whereIn(array $array, string $key, array $values, bool $strict = false): array
    public static function whereNotIn(array $array, string $key, array $values, bool $strict = false): array
    public static function whereNull(array $array, string $key): array
    public static function whereNotNull(array $array, string $key): array
    public static function groupBy(array $array, string|callable $groupBy, bool $preserveKeys = false): array
    public static function keyBy(array $array, string|callable $keyBy): array
    public static function indexBy(array $array, string|callable $indexBy): array
    public static function mapWithKeys(array $array, callable $callback): array
    public static function sortBy(array $array, string|callable $by, bool $desc = false, int $options = \SORT_REGULAR): array
    public static function sortByDesc(array $array, string|callable $by, int $options = \SORT_REGULAR): array
    public static function transpose(array $matrix): array
    public static function pluck(array $array, string $column, ?string $indexBy = null): array
    public static function mergeRecursiveDistinct(array $base, array $overrides): array
    public static function replaceRecursive(array $base, array $replacements): array
    public static function overlay(array $base, array $overlay): array

DotNotation
-----------------------------------

.. code-block:: php

    public static function flatten(array $array, string $prepend = ''): array
    public static function expand(array $array): array
    public static function has(array $array, array|string $keys): bool
    public static function hasAny(array $array, array|string $keys): bool
    public static function get(array $array, array|int|string|null $keys = null, mixed $default = null): mixed
    public static function set(array &$array, array|string|null $keys = null, mixed $value = null, bool $overwrite = true): bool
    public static function fill(array &$array, array|string $keys, mixed $value = null): void
    public static function forget(array &$target, array|string|int|null $keys): void
    public static function string(array $array, string $key, mixed $default = null): string
    public static function integer(array $array, string $key, mixed $default = null): int
    public static function float(array $array, string $key, mixed $default = null): float
    public static function boolean(array $array, string $key, mixed $default = null): bool
    public static function arrayValue(array $array, string $key, mixed $default = null): array
    public static function pluck(array $array, array|string $keys, mixed $default = null): array
    public static function all(array $array): array
    public static function tap(array $array, callable $callback): array
    public static function offsetExists(array $array, string $key): bool
    public static function offsetGet(array $array, string $key): mixed
    public static function offsetSet(array &$array, string $key, mixed $value): void
    public static function offsetUnset(array &$array, string $key): void

Collection
---------------------------------------

Collection uses ``BaseCollectionTrait``. Public API:

.. code-block:: php

    public function __construct(array $data = [])
    public static function from(mixed $data): static
    public static function make(mixed $data): static
    public function __call(string $method, array $arguments): mixed
    public function __invoke(): array
    public function process(): Pipeline
    public function get(string|array $keys): mixed
    public function has(string|array $keys): bool
    public function hasAny(string|array $keys): bool
    public function set(array|string|null $keys = null, mixed $value = null): bool
    public function __get(string $key): mixed
    public function __set(string $key, mixed $value): void
    public function __isset(string $key): bool
    public function __unset(string $key): void
    public function getArrayableItems(mixed $items): array
    public function all(): array
    public function items(): array
    public function toJson(int $options = 0): string
    public function isEmpty(): bool
    public function __toString(): string
    public function toArray(): array
    public function keys(): array
    public function __debugInfo(): array
    public function clear(): void
    public function copy(): static
    public function immutable(): static
    public function merge(mixed $items): static
    public function offsetExists(mixed $offset): bool
    public function offsetGet(mixed $offset): mixed
    public function offsetSet(mixed $offset, mixed $value): void
    public function offsetUnset(mixed $offset): void
    public function getIterator(): Traversable
    public function current(): mixed
    public function key(): string|int|null
    public function next(): void
    public function valid(): bool
    public function rewind(): void
    public function count(): int
    public function jsonSerialize(): array

HookedCollection
---------------------------------------------

HookedCollection extends ``Collection`` and adds hook behavior (from ``HookTrait``):

.. code-block:: php

    public function offsetGet(mixed $offset): mixed
    public function offsetSet(mixed $offset, mixed $value): void
    public function onGet(string $offset, callable $callback): static
    public function onSet(string $offset, callable $callback): static

Pipeline
-------------------------------------

.. code-block:: php

    public function __construct(protected array &$working, private readonly Collection $collection)
    public function only(array|string $keys): Collection
    public function values(): Collection
    public function rekey(array|callable $mapper): Collection
    public function nth(int $step, int $offset = 0): Collection
    public function duplicates(): Collection
    public function slice(int $offset, ?int $length = null): Collection
    public function paginate(int $page, int $perPage): Collection
    public function combine(array $values): Collection
    public function map(callable $callback): Collection
    public function mapWithKeys(callable $callback): Collection
    public function filter(callable $callback): Collection
    public function chunk(int $size, bool $preserveKeys = false): Collection
    public function unique(bool $strict = false): Collection
    public function reject(mixed $callback = true): Collection
    public function intersect(array $values, bool $strict = false): Collection
    public function diff(array $values, bool $strict = false): Collection
    public function symmetricDiff(array $values, bool $strict = false): Collection
    public function same(array $values, bool $strict = false): bool
    public function skip(int $count): Collection
    public function skipWhile(callable $callback): Collection
    public function skipUntil(callable $callback): Collection
    public function partition(callable $callback): Collection
    public function flatten(float|int $depth = \INF): Collection
    public function flattenByKey(): Collection
    public function sortRecursive(int $options = SORT_REGULAR, bool $descending = false): Collection
    public function collapse(): Collection
    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): Collection
    public function keyBy(string|callable $keyBy): Collection
    public function indexBy(string|callable $indexBy): Collection
    public function between(string $key, float|int $from, float|int $to): Collection
    public function firstWhere(string $key, mixed $operator = null, mixed $value = null, mixed $default = null): mixed
    public function whereCallback(?callable $callback = null, mixed $default = null): Collection
    public function where(string $key, mixed $operator = null, mixed $value = null): Collection
    public function whereIn(string $key, array $values, bool $strict = false): Collection
    public function whereNotIn(string $key, array $values, bool $strict = false): Collection
    public function whereNull(string $key): Collection
    public function whereNotNull(string $key): Collection
    public function sortBy(string|callable $by, bool $desc = false, int $options = SORT_REGULAR): Collection
    public function isMultiDimensional(): bool
    public function wrap(): Collection
    public function unWrap(): Collection
    public function unwrap(): Collection
    public function shuffle(?int $seed = null): Collection
    public function sum(?callable $callback = null): float|int
    public function min(string|callable|null $keyOrCallback = null): float|int|null
    public function max(string|callable|null $keyOrCallback = null): float|int|null
    public function first(?callable $callback = null, mixed $default = null): mixed
    public function last(?callable $callback = null, mixed $default = null): mixed
    public function reduce(callable $callback, mixed $initial = null): mixed
    public function any(callable $callback): bool
    public function countBy(callable|string|null $groupBy = null): array
    public function except(array|string $keys): Collection
    public function median(): float|int
    public function mode(): array
    public function minBy(string|callable $keyOrCallback): mixed
    public function maxBy(string|callable $keyOrCallback): mixed
    public function pluck(string $column, ?string $indexBy = null): Collection
    public function transpose(): Collection
    public function mergeRecursiveDistinct(array $overlay): Collection
    public function replaceRecursive(array $replacements): Collection
    public function overlay(array $overlay): Collection
    public function tap(callable $callback): Collection
    public function pipe(callable $callback): Collection
    public function when(bool $condition, callable $callback, ?callable $default = null): Collection
    public function unless(bool $condition, callable $callback, ?callable $default = null): Collection

Config
-------------------------------

Config uses ``BaseConfigTrait``. Public API:

.. code-block:: php

    public function loadFile(string $path): bool
    public function loadArray(array $resource): bool
    public function all(): array
    public function has(string|array $keys): bool
    public function hasAny(string|array $keys): bool
    public function get(string|int|array|null $key = null, mixed $default = null): mixed
    public function getOrFail(string|int|array|null $key): mixed
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    public function fill(string|array $key, mixed $value = null): bool
    public function forget(string|int|array $key): bool
    public function prepend(string $key, mixed $value): bool
    public function append(string $key, mixed $value): bool
    public function replace(array $items): bool
    public function reload(array|string $source): bool

LazyFileConfig
--------------------------------------

LazyFileConfig loads top-level config files on first keyed access:

.. code-block:: php

    public function get(string|int|array|null $key = null, mixed $default = null): mixed
    public function has(string|array $keys): bool
    public function hasAny(string|array $keys): bool
    public function set(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    public function fill(string|array $key, mixed $value = null): bool
    public function forget(string|int|array $key): bool
    public function preload(string|array $namespaces): static
    public function isLoaded(string $namespace): bool
    public function loaded(string $namespace): bool
    public function loadedNamespaces(): array
    public function all(): array // throws (design choice)

Config Hook-Aware Variants
--------------------------------------

Both Config and LazyFileConfig expose explicit hook-aware methods:

.. code-block:: php

    public function getWithHooks(int|string|array|null $key = null, mixed $default = null): mixed
    public function setWithHooks(string|array|null $key = null, mixed $value = null, bool $overwrite = true): bool
    public function fillWithHooks(string|array $key, mixed $value = null): bool
    public function onGet(string $offset, callable $callback): static
    public function onSet(string $offset, callable $callback): static

DTOTrait
---------------------------------

.. code-block:: php

    public static function create(array $values): static
    public function fromArray(array $values): static
    public function toArray(): array

HookTrait
----------------------------------

.. code-block:: php

    public function onGet(string $offset, callable $callback): static
    public function onSet(string $offset, callable $callback): static
