Dot Notation
============

``Infocyph\ArrayKit\Array\DotNotation`` is the core nested access utility used across ArrayKit.

It supports:

- dot-path reads/writes (``user.profile.name``)
- multi-key reads and bulk set/fill
- wildcard traversal in reads and forget operations
- typed accessors with exceptions
- flatten/expand conversion

Basic Get/Set
-------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = ['user' => ['profile' => ['name' => 'Alice']]];

    $name = DotNotation::get($data, 'user.profile.name'); // Alice
    $missing = DotNotation::get($data, 'user.profile.email', 'n/a'); // n/a

    DotNotation::set($data, 'user.profile.email', 'alice@example.com');

    // Replace entire array (key = null)
    DotNotation::set($data, null, ['fresh' => true]);

Reading Multiple Keys
---------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = [
        'app' => ['name' => 'ArrayKit', 'env' => 'local'],
        'db' => ['host' => 'localhost'],
    ];

    $result = DotNotation::get($data, ['app.name', 'db.host'], 'default');
    // [
    //   'app.name' => 'ArrayKit',
    //   'db.host' => 'localhost',
    // ]

Fill vs Set
-----------

``set()`` writes values normally. ``fill()`` only writes when key is missing.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = ['app' => ['env' => 'prod']];

    DotNotation::set($data, 'app.env', 'local');    // overwrite -> local
    DotNotation::fill($data, 'app.env', 'staging'); // does not overwrite
    DotNotation::fill($data, 'app.debug', true);    // writes

Bulk Set/Fill
-------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = [];

    DotNotation::set($data, [
        'user.name' => 'Alice',
        'user.email' => 'alice@example.com',
    ]);

    DotNotation::fill($data, [
        'user.name' => 'Bob',          // ignored
        'user.role' => 'admin',        // added
    ]);

Existence Checks
----------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = ['user' => ['name' => 'Alice']];

    $has = DotNotation::has($data, 'user.name'); // true
    $hasBoth = DotNotation::has($data, ['user.name', 'user.email']); // false
    $hasAny = DotNotation::hasAny($data, ['x.y', 'user.name']); // true

Forgetting Keys
---------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = [
        'users' => [
            ['name' => 'A', 'secret' => 'x'],
            ['name' => 'B', 'secret' => 'y'],
        ],
    ];

    // Remove one nested path
    DotNotation::forget($data, 'users.0.secret');

    // Wildcard remove (all users.*.secret)
    DotNotation::forget($data, 'users.*.secret');

Wildcards and Special Segments in get()
---------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = [
        'users' => [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ],
    ];

    $names = DotNotation::get($data, 'users.*.name'); // ['Alice', 'Bob']
    $first = DotNotation::get($data, 'users.{first}.name'); // Alice
    $last = DotNotation::get($data, 'users.{last}.name');   // Bob

Flatten and Expand
------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $flat = DotNotation::flatten([
        'user' => ['name' => 'Alice', 'email' => 'alice@example.com'],
    ]);
    // [
    //   'user.name' => 'Alice',
    //   'user.email' => 'alice@example.com',
    // ]

    $expanded = DotNotation::expand($flat);

Typed Accessors
---------------

Typed helpers enforce value type and throw ``InvalidArgumentException`` on mismatch.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $cfg = [
        'app' => ['name' => 'ArrayKit', 'debug' => true],
        'port' => 8080,
        'ratio' => 0.75,
    ];

    $name = DotNotation::string($cfg, 'app.name');
    $debug = DotNotation::boolean($cfg, 'app.debug');
    $port = DotNotation::integer($cfg, 'port');
    $ratio = DotNotation::float($cfg, 'ratio');

ArrayAccess-style Helpers
-------------------------

``DotNotation`` also exposes static ``offset*`` methods:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $data = [];
    DotNotation::offsetSet($data, 'a.b', 1);
    $exists = DotNotation::offsetExists($data, 'a.b'); // true
    $value = DotNotation::offsetGet($data, 'a.b');     // 1
    DotNotation::offsetUnset($data, 'a.b');

Behavior Notes
--------------

- ``get($array, null)`` returns the full array.
- Defaults may be plain values or callables.
- Wildcard traversal in ``get`` returns arrays of matched results.
- ``set`` supports wildcard paths when wildcard is the first segment.
- ``forget`` supports wildcard and nested removal across arrays.
