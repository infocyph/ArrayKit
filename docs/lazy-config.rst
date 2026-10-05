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

``LazyFileConfig`` publishes generated namespace caches for deployment/bootstrap
use. Publication uses immutable generations rather than overwriting files that
active workers may already be reading.

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

- ``namespaceCache()`` configures an optional generated-cache root.
- ``warmNamespaceCache()`` builds a complete hidden generation and atomically switches ``.arraykit-generation`` only after publication succeeds.
- Each generation contains one file per cached namespace plus ``.arraykit-flat.php`` for exact scalar/null leaves.
- Exact-key scalar reads may use the flat index without materializing the namespace.
- Structural, wildcard, and namespace reads use the same pinned immutable generation as exact reads.
- ``Environment::ref()`` values and closures are resolved before publication.
- Warm-up rereads the authoritative source unless the caller explicitly supplied or mutated that namespace in memory; an older generated cache does not feed a new source-backed generation.
- Readers do not take the writer lock. The first cache lookup pins a generation for that instance. External publication does not change its view. Construct a new instance or call ``namespaceCache()`` explicitly to refresh; warm-up and flush on the instance also reset its generated state, retaining intentional runtime overrides.
- Writers copy unmodified namespaces from the latest published generation even when that writer was previously a pinned reader. Partial merges retain source/cache origins for untouched namespaces.

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

``warmNamespaceCache('db')`` resolves those values before writing the namespace
inside the newly published generation and before adding scalar leaves to that
generation's ``.arraykit-flat.php``.

Generated namespace cache file:

.. code-block:: php

    <?php

    // bootstrap/cache/config/.arraykit-gen-<id>/db.php
    return [
        'host' => 'localhost',
        'port' => 3306,
    ];

Generated flat leaf index:

.. code-block:: php

    <?php

    // bootstrap/cache/config/.arraykit-gen-<id>/.arraykit-flat.php
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

    // Publishes a new immutable generation containing db.php + .arraykit-flat.php.
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

    // Exact scalar reads can come from the active .arraykit-flat.php without loading db.php.
    $host = $config->get('db.host');

    // Structural reads load bootstrap/cache/config/db.php when available.
    $database = $config->get('db');

Important lazy-cache details:

- ``warmNamespaceCache(['db', 'cache'])`` publishes both namespace files and one shared ``.arraykit-flat.php`` inside a new immutable generation.
- ``.arraykit-generation`` is the atomic pointer to the active generation.
- Source-backed warm-up rereads current source/environment values; explicit in-memory runtime overrides remain authoritative.
- ``flushNamespaceCache()`` publishes a generation with the selected cached namespaces removed; unrelated root files are preserved.
- Namespace and generated cache PHP files are trusted deployment-owned inputs, not a sandbox boundary.
- Prefer deployment/bootstrap/admin warm-up, not request-time regeneration.
- Retire old generation directories only after workers that may still reference them have been replaced.


5.3 Cache Migration
-------------------

ArrayKit 5.2 used direct root namespace files plus ``__flat.php`` as an internal
flat index. ArrayKit 5.3 keeps ``__flat`` valid as a caller namespace and moves
internal metadata to ``.arraykit-flat.php`` inside immutable generations.

After upgrading, rebuild the namespace cache. The old ``__flat.php`` acceleration
artifact is deliberately not interpreted as the 5.3 flat index because it is
ambiguous with the valid ``__flat`` namespace. Ordinary legacy namespace files
remain a compatibility fallback until a 5.3 generation is published.

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
