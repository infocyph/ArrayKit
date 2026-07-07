Configuration
=============

ArrayKit configuration objects provide dot-notation access to nested settings.

Classes:

- ``Infocyph\ArrayKit\Config\Config``

``Config`` supports optional hooks via explicit ``getWithHooks()``,
``setWithHooks()``, and ``fillWithHooks()`` methods.

For split per-namespace config files, see :doc:`lazy-config`.

Loading Configuration
---------------------

You can load config from an array, a PHP file that returns an array, or a
``.env`` file.

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

    // Or from a .env file:
    // $ok = $config->loadEnvFile(__DIR__.'/.env');

Important behavior:

- ``loadArray()`` and ``loadFile()`` only load when config is currently empty.
- If already loaded, they return ``false`` and do not overwrite existing items.
- ``replace()`` always replaces in-memory config items.
- ``reload()`` replaces from array or readable file path.
- ``exportCache()`` writes a compiled PHP cache file of current items.
- ``loadCache()`` loads a compiled PHP cache file through the normal file loader.
- ``loadEnvFile()`` loads parsed ``.env`` values only when config is empty.
- ``mergeEnvFile()`` merges parsed ``.env`` values into existing config.
- Facade-based config creation is documented in :doc:`facade`.

Environment Values and .env Parsing
-----------------------------------

ArrayKit separates runtime environment reads from ``.env`` file parsing.

Runtime environment reads use ``env()`` or ``ArrayKit::env()``. They check
``$_ENV`` first, then non-HTTP ``$_SERVER`` values, then ``getenv()``.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $env = env('APP_ENV', 'local');
    $debug = ArrayKit::env()->get('APP_DEBUG', false);
    $hasSecret = ArrayKit::env()->has('APP_SECRET');

``.env`` file parsing uses ``dotenv()``, ``ArrayKit::dotenv()``, or
``EnvParser`` directly.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;
    use Infocyph\ArrayKit\Config\EnvParser;

    $values = EnvParser::parse("APP_ENV=local\nDB_PORT=3306\n");
    $fileValues = ArrayKit::dotenv()->parseFile(__DIR__.'/.env');
    $rawValues = dotenv()->parseRaw("URL=https://example.com/\${APP_ENV}\n");

``env()`` is not the ``.env`` parser. It reads values already available in the
runtime environment.

Environment References in Config
--------------------------------

Config arrays can contain environment values in three different ways.

Immediate resolution happens when the config PHP file is included:

.. code-block:: php

    <?php
    return [
        'db' => [
            'port' => env('DB_PORT', 3306),
        ],
    ];

Delayed environment resolution uses ``Environment::ref()``. This keeps an
explicit env reference in config memory until a cache file is exported or a lazy
namespace cache is warmed.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Support\Environment;

    return [
        'db' => [
            'host' => Environment::ref('DB_HOST', 'localhost'),
            'port' => Environment::ref('DB_PORT', 3306),
        ],
    ];

Closures are also supported for custom delayed logic:

.. code-block:: php

    <?php
    return [
        'db' => [
            'url' => fn () => sprintf(
                'mysql://%s:%s@%s/%s',
                env('DB_USER', 'root'),
                env('DB_PASSWORD', ''),
                env('DB_HOST', 'localhost'),
                env('DB_NAME', 'app'),
            ),
        ],
    ];

Use ``Environment::ref()`` for simple environment lookups. Use closures only
when you need custom computation.

Full Process: .env to Config Cache
----------------------------------

This example shows a typical bootstrap flow:

1. Parse ``.env`` values.
2. Build runtime config using env values.
3. Export a compiled config cache.
4. Load config from cache on later requests.

Example ``.env`` file:

.. code-block:: dotenv

    APP_NAME=ArrayKit Demo
    APP_ENV=local
    APP_DEBUG=true
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=arraykit

Example bootstrap file:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;
    use Infocyph\ArrayKit\Config\Support\Environment;

    $basePath = dirname(__DIR__);
    $cacheFile = $basePath.'/bootstrap/cache/config.php';

    $config = new Config();

    if (is_file($cacheFile)) {
        // Fast path for normal runtime requests.
        $config->loadCache($cacheFile);
    } else {
        // First boot, local development, or after cache clear.
        $config->loadEnvFile($basePath.'/.env');

        $config->merge([
            'app' => [
                'name' => env('APP_NAME', 'ArrayKit'),
                'env' => env('APP_ENV', 'production'),
                'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
            ],
            'db' => [
                // Resolved only when exportCache() writes the cache file.
                'host' => Environment::ref('DB_HOST', 'localhost'),
                'port' => fn () => (int) env('DB_PORT', 3306),
                'database' => Environment::ref('DB_DATABASE', 'app'),
            ],
        ]);

        $config->exportCache($cacheFile);
    }

    $appName = $config->getString('app.name');
    $dbHost = $config->getString('db.host');
    $dbPort = $config->getInt('db.port');

Important details:

- ``loadEnvFile()`` stores parsed ``.env`` entries as config items.
- ``env()`` reads the current runtime environment. It does not parse files.
- ``Environment::ref()`` and closures are materialized by ``exportCache()``.
- The generated cache file contains concrete resolved values only.
- If an environment value changes after the cache file exists, rebuild the cache
  before expecting config reads to change.
- On cached boot, prefer ``loadCache()`` first. ``loadEnvFile()`` fills an empty
  config object, so calling ``loadEnvFile()`` before ``loadCache()`` makes
  ``loadCache()`` return ``false`` unless you are using a separate config object.

If you want to parse ``.env`` without loading it into ``Config``:

.. code-block:: php

    <?php
    $values = dotenv()->parseFile(__DIR__.'/.env');
    $rawValues = dotenv()->parseFileRaw(__DIR__.'/.env');

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
    $required = $config->getOrFail('app.name');               // throws if missing

Typed Getters
-------------

Use nullable/default-friendly typed getters when you want soft type access:

.. code-block:: php

    <?php
    $name = $config->getString('app.name');
    $port = $config->getInt('db.port', 3306);
    $ratio = $config->getFloat('metrics.sample_ratio', 0.5);
    $debug = $config->getBool('app.debug', false);
    $hosts = $config->getList('cluster.hosts', []);
    $cache = $config->getArray('cache', []);
    $mode = $config->getEnum('app.mode', AppMode::class, AppMode::Prod);

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

Merging, Snapshots, and Read-Only Mode
--------------------------------------

.. code-block:: php

    <?php
    $config->snapshot('before-runtime');

    $config->merge(['app' => ['env' => 'production']]);   // deep merge
    $config->overlay(['features' => ['beta' => true]]);   // top-level overlay

    $changed = $config->changed('before-runtime');         // true/false
    $config->restore('before-runtime');                    // rollback

    $config->readonly();                                   // lock writes
    $locked = $config->isReadonly();                       // true
    $config->readonly(false);                              // unlock

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

Config Hooks (Explicit)
-----------------------

``Config`` allows per-key transformation on read/write, while keeping
``get()/set()/fill()`` hook-free for maximum base-path performance.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;

    $config = new Config();

    $config->onSet('user.name', fn ($v) => strtoupper((string) $v));
    $config->onGet('user.name', fn ($v) => strtolower((string) $v));

    $config->setWithHooks('user.name', 'Alice');
    echo $config->getWithHooks('user.name'); // alice

Bulk operations with hooks:

.. code-block:: php

    <?php
    $config->onSet('user.email', fn ($v) => trim((string) $v));

    $config->setWithHooks([
        'user.name' => 'JOHN',
        'user.email' => ' john@example.com ',
    ]);

    $vals = $config->getWithHooks(['user.name', 'user.email']);

Practical Pattern
-----------------

Use config as a mutable runtime container for app setup:

.. code-block:: php

    <?php
    $config = new Config();
    $config->loadFile(__DIR__.'/config.php');

    // Normalize selected runtime values
    $config->onSet('app.timezone', fn ($v) => trim((string) $v));
    $config->onGet('app.timezone', fn ($v) => strtoupper((string) $v));

    $config->setWithHooks('app.timezone', ' utc ');
    $tz = $config->getWithHooks('app.timezone'); // UTC

Compiled Cache + Read Memoization
---------------------------------

``Config`` also supports two cache layers:

- in-memory read memoization for repeated dot-path lookups
- compiled cache export/load through PHP files
- cache materialization of ``Environment::ref()`` values and closures

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Support\Environment;

    $config = new Config();
    $config->loadArray([
        'app' => ['name' => 'ArrayKit'],
        'db' => [
            'host' => Environment::ref('DB_HOST', 'localhost'),
            'port' => fn () => env('DB_PORT', 3306),
        ],
    ]);

    $config->readCache(); // enabled by default
    $config->exportCache(__DIR__.'/bootstrap/cache/config.php');

    $cached = new Config();
    $cached->loadCache(__DIR__.'/bootstrap/cache/config.php');
    $host = $cached->get('db.host');

When ``exportCache()`` writes the PHP cache file, ``Environment::ref()`` values
and closures are recursively resolved first. The generated cache contains only
the resolved values, not closures or reference objects.

Method Summary
--------------

Config methods:

- ``loadFile()``, ``loadArray()``, ``all()``
- ``get()``, ``has()``, ``hasAny()``
- ``getOrFail()``
- ``set()``, ``fill()``, ``forget()``
- ``prepend()``, ``append()``
- ``replace()``, ``reload()``
- ``exportCache()``, ``loadCache()``
- ``loadEnvFile()``, ``mergeEnvFile()``
- ``readCache()``, ``readCacheEnabled()``, ``flushReadCache()``
- ``getString()/getInt()/getFloat()/getBool()/getArray()/getList()/getEnum()``
- ``merge()``, ``overlay()``
- ``snapshot()``, ``restore()``, ``changed()``
- ``readonly()``, ``isReadonly()``

Hook-aware methods:

- ``getWithHooks()``
- ``setWithHooks()``
- ``fillWithHooks()``
- ``onGet()``, ``onSet()``
