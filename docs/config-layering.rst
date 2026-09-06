Configuration Layering
======================

ArrayKit provides explicit configuration-layer semantics for applications that
compose defaults, file-backed configuration, and runtime overrides.

ConfigMerge
-----------

``ConfigMerge`` recursively merges associative configuration maps while treating
lists as atomic values. A higher-precedence list replaces the lower list instead
of merging numeric indexes.

.. code-block:: php

    <?php

    use Infocyph\ArrayKit\Config\ConfigMerge;

    $config = ConfigMerge::merge(
        [
            'app' => ['name' => 'Example', 'debug' => false],
            'middleware' => ['auth', 'csrf'],
        ],
        [
            'app' => ['debug' => true],
            'middleware' => ['api'],
        ],
    );

    // middleware is ['api'], not ['api', 'csrf'].

Use ``ConfigMerge::mergeMany()`` when composing multiple layers in precedence
order.

LayeredLazyFileConfig
---------------------

``LayeredLazyFileConfig`` composes three layers with this precedence:

``fallback < lazy source < overrides``

Only the requested namespace is materialized. Exact path reads are resolved from
the fully merged namespace, so list replacement and scalar shadowing cannot leak
values from lower-precedence layers.

.. code-block:: php

    <?php

    use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;

    $config = new LayeredLazyFileConfig(
        directory: __DIR__.'/config',
        namespaceCacheDirectory: __DIR__.'/bootstrap/cache/config',
        fallback: [
            'app' => ['debug' => false],
        ],
        overrides: [
            'app' => ['debug' => true],
        ],
        namespaces: ['app', 'cache', 'database'],
    );

    $debug = $config->get('app.debug');

``all()`` materializes the configured namespace set. ``warmNamespaceCache()``
and ``clearNamespaceCache()`` delegate generated source-cache lifecycle to the
underlying lazy source.

Resilient Lazy Source Cache
---------------------------

``ResilientLazyFileConfig`` treats generated namespace-cache corruption as a
cache miss and retries the authoritative source namespace. Invalid source files
still fail normally; resilience applies only to disposable generated cache
artifacts.

Malformed or invalid ``__flat.php`` indexes are also treated as cache misses.
This keeps an acceleration artifact from preventing source configuration from
loading.

Environment Enumeration
-----------------------

``Environment::all()`` enumerates all three runtime sources with the same
precedence used by ``Environment::get()``:

1. ``$_ENV``;
2. non-HTTP ``$_SERVER`` values;
3. process values returned by ``getenv()``.

This means process-only variables are visible during complete environment
enumeration as well as direct lookup.
