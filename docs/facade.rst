Facade
======

Use ``Infocyph\ArrayKit\ArrayKit`` as a single entry point for the package.

Classes:

- ``Infocyph\ArrayKit\ArrayKit``
- ``Infocyph\ArrayKit\Facade\ModuleProxy``

Why this exists
---------------

The facade keeps top-level usage consistent while preserving each module's native API.

Module Entry Points
-------------------

These methods return a lightweight ``ModuleProxy`` that forwards calls to static module methods.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $isList = ArrayKit::single()->isList([1, 2, 3]);
    $flat = ArrayKit::multi()->flatten([[1], [2, [3]]]);
    $wrapped = ArrayKit::helper()->wrap('x');
    $name = ArrayKit::dot()->get(['user' => ['name' => 'Alice']], 'user.name');

Factory Entry Points
--------------------

Use these when you want object instances instead of static helpers.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $config = ArrayKit::config(['app' => ['env' => 'local']]);
    $lazy = ArrayKit::lazyConfig(__DIR__.'/config');

    $collection = ArrayKit::collection([1, 2, 3]);
    $hooked = ArrayKit::hookedCollection(['name' => 'alice']);
    $pipeline = ArrayKit::pipeline([1, 2, 3, 4]);

Behavior Notes
--------------

- ``single()``, ``multi()``, ``helper()``, and ``dot()`` return cached proxies.
- Proxy calls map directly to target static methods.
- Calling a missing method via proxy throws ``BadMethodCallException``.

Related Guides
--------------

- Array helpers: :doc:`array-helpers`
- Dot notation: :doc:`dot-notation`
- Collections: :doc:`collection`
- Configuration: :doc:`config`
