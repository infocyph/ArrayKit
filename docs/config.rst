Configuration
=============

ArrayKit configuration objects provide dot-notation access to nested settings.

Classes:

- ``Infocyph\ArrayKit\Config\Config``
- ``Infocyph\ArrayKit\Config\LazyFileConfig``
- ``Infocyph\ArrayKit\Config\DynamicConfig``

``DynamicConfig`` extends ``Config`` by adding value hooks.
``LazyFileConfig`` loads namespace files only on first keyed access.

Loading Configuration
---------------------

You can load config from an array or a PHP file that returns an array.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;

    $config = new Config();

    $ok = $config->loadArray([
        'app' => ['name' => 'ArrayKit', 'env' => 'local'],
        'db' => ['host' => 'localhost', 'port' => 3306],
    ]);

    // Or from file:
    // $ok = $config->loadFile(__DIR__.'/config.php');

Important behavior:

- ``loadArray()`` and ``loadFile()`` only load when config is currently empty.
- If already loaded, they return ``false`` and do not overwrite existing items.

Reading Values
--------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;

    $config = new Config();
    $config->loadArray([
        'app' => ['name' => 'ArrayKit', 'env' => 'local'],
        'queue' => ['driver' => 'sync'],
    ]);

    $all = $config->all();
    $name = $config->get('app.name');                         // ArrayKit
    $fallback = $config->get('app.debug', false);             // false
    $many = $config->get(['app.name', 'queue.driver']);

    $has = $config->has('app.name');                          // true
    $hasAny = $config->hasAny(['missing.path', 'queue.driver']); // true

Writing Values
--------------

Single key:

.. code-block:: php

    <?php
    $config->set('cache.driver', 'file');
    $config->set('db.port', 5432);

Bulk set:

.. code-block:: php

    <?php
    $config->set([
        'app.env' => 'production',
        'cache.prefix' => 'arraykit_',
    ]);

Overwrite control:

.. code-block:: php

    <?php
    // Do not overwrite existing value
    $config->set('app.env', 'local', overwrite: false);

Fill Missing Values
-------------------

``fill()`` writes only if target key does not already exist.

.. code-block:: php

    <?php
    $config->fill('mail.driver', 'smtp');
    $config->fill([
        'mail.host' => 'localhost',
        'mail.port' => 1025,
    ]);

    // Existing keys are preserved
    $config->fill('app.env', 'staging');

Removing Values
---------------

.. code-block:: php

    <?php
    $config->forget('cache.prefix');
    $config->forget(['mail.host', 'mail.port']);

Array-Value Helpers
-------------------

``prepend()`` and ``append()`` are useful for list-type config nodes.

.. code-block:: php

    <?php
    $config->set('middleware', ['auth']);
    $config->append('middleware', 'throttle');
    $config->prepend('middleware', 'cors');

    // ['cors', 'auth', 'throttle']
    $middleware = $config->get('middleware');

DynamicConfig Hooks
-------------------

``DynamicConfig`` allows per-key transformation on read/write.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\DynamicConfig;

    $config = new DynamicConfig();

    $config->onSet('user.name', fn ($v) => strtoupper((string) $v));
    $config->onGet('user.name', fn ($v) => strtolower((string) $v));

    $config->set('user.name', 'Alice');
    echo $config->get('user.name'); // alice

Bulk operations with hooks:

.. code-block:: php

    <?php
    $config->onSet('user.email', fn ($v) => trim((string) $v));

    $config->set([
        'user.name' => 'JOHN',
        'user.email' => ' john@example.com ',
    ]);

    $vals = $config->get(['user.name', 'user.email']);

Practical Pattern
-----------------

Use config as a mutable runtime container for app setup:

.. code-block:: php

    <?php
    $config = new DynamicConfig();
    $config->loadFile(__DIR__.'/config.php');

    // Normalize selected runtime values
    $config->onSet('app.timezone', fn ($v) => trim((string) $v));
    $config->onGet('app.timezone', fn ($v) => strtoupper((string) $v));

    $config->set('app.timezone', ' utc ');
    $tz = $config->get('app.timezone'); // UTC

LazyFileConfig
--------------

Use ``LazyFileConfig`` when configuration is split into top-level namespace files
like ``db.php``, ``cache.php``, ``queue.php``.

Rules:

- Key format is ``namespace.path.to.key``.
- On first access, only ``{directory}/{namespace}.php`` is loaded.
- Remaining key segments are resolved using dot notation.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\LazyFileConfig;

    $config = new LazyFileConfig(__DIR__.'/config');

    // Loads only config/db.php:
    $host = $config->get('db.host', '127.0.0.1');

    // Optional warm-up:
    $config->preload(['db', 'cache']);

    $loaded = $config->loadedNamespaces(); // ['db', 'cache']

Important behavior:

- ``get()`` requires at least one key.
- ``all()`` is intentionally disabled and throws.
- Namespace file must return an array.
- Missing namespace file returns the provided default.

Method Summary
--------------

Config methods:

- ``loadFile()``, ``loadArray()``, ``all()``
- ``get()``, ``has()``, ``hasAny()``
- ``set()``, ``fill()``, ``forget()``
- ``prepend()``, ``append()``

LazyFileConfig methods:

- ``get()`` (requires key)
- ``has()``, ``hasAny()``
- ``set()``, ``fill()``, ``forget()``
- ``preload()``, ``isLoaded()``, ``loadedNamespaces()``
- ``all()`` (throws by design)

DynamicConfig methods:

- ``get()`` (hook-aware override)
- ``set()`` (hook-aware override)
- ``fill()`` (hook-aware override)
- ``onGet()``, ``onSet()``
