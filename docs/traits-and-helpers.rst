Traits and Helpers
==================

This page covers reusable building blocks outside the core helper classes:

- ``DTOTrait`` for data-transfer object hydration
- ``HookTrait`` for key-based get/set transforms
- namespaced helper functions (autoloaded by default)
- optional global helper functions from ``src/functions.php``

DTOTrait
--------

Namespace: ``Infocyph\ArrayKit\traits\DTOTrait``

Main methods:

- ``create(array $values): static`` (static constructor)
- ``fromArray(array $values): static`` (hydrate current instance)
- ``hydrate(array $values, array $mapping = [], bool $coerce = false): static``
- ``hydrateNested(array $values, array $mapping = [], bool $coerce = false): static``
- ``toArray(): array`` (export public properties)
- ``toArrayDeep(): array`` (recursive export)
- ``replaceFromArray(array $values, array $mapping = [], bool $coerce = false): static``

Basic DTO Flow
~~~~~~~~~~~~~~

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\traits\DTOTrait;

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

Namespace: ``Infocyph\ArrayKit\traits\HookTrait``

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
