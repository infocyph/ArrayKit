Traits and Helpers
==================

This page covers reusable building blocks outside the core helper classes:

- ``DTOTrait`` for data-transfer object hydration
- ``HookTrait`` for key-based get/set transforms
- namespaced helper functions (autoloaded by default)
- optional global helper functions from ``src/functions.php``

DTOTrait
--------

Namespace: ``Infocyph\ArrayKit\DTO\Concerns\DTOTrait``

Main methods:

- ``create(array $values): static`` (static constructor)
- ``fromArray(array $values): static`` (hydrate current instance)
- ``hydrate(array $values, array $mapping = [], bool $coerce = false): static``
- ``hydrateNested(array $values, array $mapping = [], bool $coerce = false): static``
- ``hydrateNestedGuarded(array $values, array $mapping = [], bool $coerce = false, int $maxDepth = 64, int $maxNodes = 100000): static``
- ``toArray(): array`` (export public properties)
- ``toArrayDeep(): array`` (recursive export)
- ``toArrayDeepGuarded(int $maxDepth = 64, int $maxNodes = 100000): array``
- ``replaceFromArray(array $values, array $mapping = [], bool $coerce = false): static``

Basic DTO Flow
~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

    class UserDTO
    {
        use DTOTrait;

        public string $name = '';
        public string $email = '';
        public int $age = 0;
    }

    $user = UserDTO::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'age' => 30,
    ]);

    $arr = $user->toArray();

Incremental Hydration
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    $user = new UserDTO();
    $user->fromArray(['name' => 'Bob']);
    $user->fromArray(['age' => 32]);

Bounded DTO Graphs
~~~~~~~~~~~~~~~~~~

Use the guarded entry points when nested DTO or array graphs can be large,
recursive, or influenced by external input. Hydration validates the input graph
before mutation. Deep export builds the actual output in one bounded traversal
of standard ``DTOTrait`` public properties. ``maxDepth`` and ``maxNodes`` are shared
across the whole call; both must be positive. Cyclic array references, cyclic
public object references, or a limit breach raise ``RuntimeException``.

Guarded export rejects custom ``toArray()`` / ``toArrayDeep()`` implementations
with ``InvalidArgumentException`` before invoking them, because their arbitrary
work and generated output cannot be bounded by the public-property budget.
Property getters are read once during export; their own execution must be
trusted and bounded by the host. Custom serializers remain supported by the
ordinary ``toArrayDeep()`` API.

Shared acyclic objects are valid and may appear in more than one branch. The
ordinary ``hydrateNested()`` and ``toArrayDeep()`` contracts are unchanged and
remain the lower-overhead choice for trusted, already-bounded graphs.

.. code-block:: php

    <?php
    $user->hydrateNestedGuarded(
        $payload,
        maxDepth: 32,
        maxNodes: 10_000,
    );

    $safe = $user->toArrayDeepGuarded(
        maxDepth: 32,
        maxNodes: 10_000,
    );

Unknown Keys
~~~~~~~~~~~~

Unknown keys are ignored (no dynamic properties are created):

.. code-block:: php

    <?php
    $user = UserDTO::create([
        'name' => 'Alice',
        'unknown_field' => 'ignored',
    ]);

    // toArray() contains only declared properties

HookTrait
---------

Namespace: ``Infocyph\ArrayKit\Concerns\HookTrait``

Main methods:

- ``onGet(string $offset, callable $callback): static``
- ``onSet(string $offset, callable $callback): static``

``HookTrait`` is used internally by:

- ``Infocyph\ArrayKit\Collection\HookedCollection``
- ``Infocyph\ArrayKit\Config\Config``
- ``Infocyph\ArrayKit\Config\LazyFileConfig``

HookedCollection Integration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\HookedCollection;

    $c = new HookedCollection(['name' => 'alice']);

    // get-time transform
    $c->onGet('name', fn ($v) => strtoupper((string) $v));

    // set-time transform
    $c->onSet('role', fn ($v) => "Role: $v");

    echo $c['name']; // ALICE
    $c['role'] = 'admin';
    echo $c['role']; // Role: admin

Config Integration
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;

    $config = new Config();
    $config->onSet('user.email', fn ($v) => trim((string) $v));
    $config->onGet('user.email', fn ($v) => strtolower((string) $v));

    $config->setWithHooks('user.email', '  ALICE@EXAMPLE.COM  ');
    echo $config->getWithHooks('user.email'); // alice@example.com

Multiple Hooks on Same Key
~~~~~~~~~~~~~~~~~~~~~~~~~~

Hooks run in registration order:

.. code-block:: php

    <?php
    $config->onSet('username', fn ($v) => trim((string) $v));
    $config->onSet('username', fn ($v) => strtolower((string) $v));

    $config->setWithHooks('username', '  ALICE  '); // becomes "alice"

Helper Functions
----------------

By default, Composer autoloads the namespaced helper functions
(``Infocyph\ArrayKit\*``). Global helper functions are optional.

Namespaced helpers (autoloaded):

- ``Infocyph\ArrayKit\compare(mixed $retrieved, mixed $value, ?string $operator = null): bool``
- ``Infocyph\ArrayKit\array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed``
- ``Infocyph\ArrayKit\array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool``
- ``Infocyph\ArrayKit\collect(mixed $data = []): Collection``
- ``Infocyph\ArrayKit\chain(mixed $data): Pipeline``

Optional global helpers (manual include):

- ``compare(mixed $retrieved, mixed $value, ?string $operator = null): bool``
- ``array_get(array $array, int|string|array|null $key = null, mixed $default = null): mixed``
- ``array_set(array &$array, string|array|null $key, mixed $value = null, bool $overwrite = true): bool``
- ``collect(mixed $data = []): Collection``
- ``chain(mixed $data): Pipeline``

To enable optional global helpers:

.. code-block:: php

    <?php
    require_once __DIR__ . '/vendor/infocyph/arraykit/src/functions.php';

array_get / array_set
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use function Infocyph\ArrayKit\array_get;
    use function Infocyph\ArrayKit\array_set;

    $data = ['user' => ['name' => 'Alice']];

    $name = array_get($data, 'user.name');            // Alice
    $missing = array_get($data, 'user.email', 'n/a'); // n/a

    array_set($data, 'user.email', 'alice@example.com');
    array_set($data, [
        'user.role' => 'admin',
        'user.active' => true,
    ]);

collect / chain
~~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use function Infocyph\ArrayKit\chain;
    use function Infocyph\ArrayKit\collect;

    $c = collect([1, 2, 3, 4]);
    $evens = $c->filter(fn ($v) => $v % 2 === 0)->all(); // [1 => 2, 3 => 4]

    $sum = chain([1, 2, 3])->sum(); // 6

compare Helper
~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use function Infocyph\ArrayKit\compare;

    compare(10, 5, '>');   // true
    compare(10, 10, '==='); // true
    compare('5', 5, '!=='); // true
    compare(10, 10);        // true (default ==)

When to Use These Helpers
-------------------------

- Use ``DTOTrait`` for lightweight request/response data objects.
- Use ``HookTrait`` consumers when you need transparent value transforms.
- Use namespaced helper functions by default; include global helpers only when explicitly desired.

Laravel Compatibility Layer
---------------------------

ArrayKit's default helper surface is namespaced:

- ``Infocyph\ArrayKit\array_get``
- ``Infocyph\ArrayKit\array_set``
- ``Infocyph\ArrayKit\collect``
- ``Infocyph\ArrayKit\chain``
