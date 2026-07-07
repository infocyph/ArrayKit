Lazy File Configuration
=======================

Use ``LazyFileConfig`` when configuration is split into top-level namespace files
like ``db.php``, ``cache.php``, ``queue.php``.

Class:

- ``Infocyph\ArrayKit\Config\LazyFileConfig``

For single in-memory config arrays and compiled whole-config cache files, see
:doc:`config`.

Basic Usage
-----------

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
    $isLoaded = $config->loaded('db');     // alias of isLoaded()

Important Behavior
------------------

- ``get()`` requires at least one key.
- ``all()`` is intentionally disabled and throws.
- Namespace file must return an array.
- Missing namespace file returns the provided default.
- ``replace()`` and ``reload()`` reset resolved-namespace tracking.
- read-only mode applies to ``set/fill/forget/replace/reload``-style mutators.
- Runtime writes only affect in-memory state until cache files are explicitly rebuilt.

Namespace Cache
---------------

``LazyFileConfig`` can warm one cache file per namespace plus a shared exact-leaf
index.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\LazyFileConfig;

    $config = new LazyFileConfig(
        __DIR__.'/config',
        namespaceCacheDirectory: __DIR__.'/bootstrap/cache/config',
    );

    $config->warmNamespaceCache(['db', 'cache']);
    $host = $config->get('db.host');

Cache behavior:

- ``namespaceCache()`` configures an optional per-namespace cache directory.
- ``warmNamespaceCache()`` writes cached namespace files and a shared ``__flat.php`` exact-leaf index.
- Exact-key scalar reads check ``__flat.php`` first.
- Structural, wildcard, and namespace reads fall back to namespace cache files.
- ``Environment::ref()`` values and closures are resolved before namespace cache files are written.

Environment Values in Namespace Files
-------------------------------------

Example namespace file with delayed environment values:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Support\Environment;

    // config/db.php
    return [
        'host' => Environment::ref('DB_HOST', 'localhost'),
        'port' => fn () => env('DB_PORT', 3306),
    ];

``warmNamespaceCache('db')`` resolves those values before writing
``bootstrap/cache/config/db.php`` and before adding scalar leaves to
``bootstrap/cache/config/__flat.php``.

Generated namespace cache file:

.. code-block:: php

    <?php

    // bootstrap/cache/config/db.php
    return [
        'host' => 'localhost',
        'port' => 3306,
    ];

Generated flat leaf index:

.. code-block:: php

    <?php

    // bootstrap/cache/config/__flat.php
    return [
        'db.host' => 'localhost',
        'db.port' => 3306,
    ];

Full LazyFileConfig Process
---------------------------

For split config files, cache warming is per namespace. First request or
deployment warm-up reads namespace files, resolves env references/closures, and
writes cache files. Later requests can read from the namespace cache without the
original namespace file.

Example ``config/db.php``:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Support\Environment;

    return [
        'host' => Environment::ref('DB_HOST', 'localhost'),
        'port' => fn () => (int) env('DB_PORT', 3306),
        'database' => Environment::ref('DB_DATABASE', 'app'),
    ];

Warm the lazy cache during deployment or first boot:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\LazyFileConfig;

    $basePath = dirname(__DIR__);

    $config = new LazyFileConfig(
        $basePath.'/config',
        namespaceCacheDirectory: $basePath.'/bootstrap/cache/config',
    );

    // Writes bootstrap/cache/config/db.php and updates __flat.php.
    $config->warmNamespaceCache('db');

Read from the warmed cache on later requests:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\LazyFileConfig;

    $basePath = dirname(__DIR__);

    $config = new LazyFileConfig(
        $basePath.'/config',
        namespaceCacheDirectory: $basePath.'/bootstrap/cache/config',
    );

    // Exact scalar reads can come from __flat.php without loading db.php.
    $host = $config->get('db.host');

    // Structural reads load bootstrap/cache/config/db.php when available.
    $database = $config->get('db');

Important lazy-cache details:

- ``warmNamespaceCache('db')`` writes ``bootstrap/cache/config/db.php``.
- ``warmNamespaceCache(['db', 'cache'])`` writes one file per namespace.
- ``__flat.php`` stores exact scalar/null leaves such as ``db.host``.
- Original source files are preferred only when namespace cache files do not
  exist or are not readable.
- If env values change, rerun ``warmNamespaceCache()`` or flush and rebuild the
  namespace cache.

Method Summary
--------------

LazyFileConfig methods:

- ``get()`` (requires key)
- ``has()``, ``hasAny()``
- ``set()``, ``fill()``, ``forget()``
- ``preload()``, ``isLoaded()``, ``loaded()``, ``loadedNamespaces()``
- ``namespaceCache()``, ``namespaceCacheDirectory()``
- ``warmNamespaceCache()``, ``flushNamespaceCache()``
- ``replace()``, ``reload()``
- ``exportCache()``, ``loadCache()``
- ``all()`` (throws by design)

Hook-aware methods:

- ``getWithHooks()``
- ``setWithHooks()``
- ``fillWithHooks()``
- ``onGet()``, ``onSet()``
