Quick Usage
===========

This page shows copy-paste examples for common ArrayKit operations.

ArrayKit Facade Example
----------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $isList = ArrayKit::single()->isList([1, 2, 3]);
    $flat = ArrayKit::multi()->flatten([[1], [2, [3]]]);
    $name = ArrayKit::dot()->get(['user' => ['name' => 'Alice']], 'user.name');

    $config = ArrayKit::config(['app' => ['env' => 'local']]);
    $env = $config->get('app.env');

ArraySingle Example
-------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $list = [1, 2, 3, 2];
    $isList = ArraySingle::isList($list);      // true
    $dupes  = ArraySingle::duplicates($list);  // [2]
    $page   = ArraySingle::paginate($list, 1, 2); // [1, 2]

ArrayMulti Example
------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $data = [[1, 2], [3, [4, 5]]];
    $flat = ArrayMulti::flatten($data);     // [1, 2, 3, 4, 5]
    $depth = ArrayMulti::depth($data);      // 3
    $sorted = ArrayMulti::sortRecursive($data);

DotNotation Example
-------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\DotNotation;

    $user = ['profile' => ['name' => 'Alice']];
    $name = DotNotation::get($user, 'profile.name'); // Alice
    DotNotation::set($user, 'profile.email', 'alice@example.com');
    DotNotation::forget($user, 'profile.name');

Collection + Pipeline Example
-----------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $collection = new Collection([1, 2, 3, 4]);
    $evens = $collection->filter(fn ($v) => $v % 2 === 0)->all(); // [1 => 2, 3 => 4]
    $sum = $collection->process()->sum(); // 10

Config + Hooks Example
----------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Config\Config;

    $config = new Config();
    $config->set('auth.password', 'secret');
    $config->onGet('auth.password', fn ($v) => strtoupper((string) $v));
    echo $config->getWithHooks('auth.password'); // SECRET

Global Helper Example
---------------------

.. code-block:: php

    <?php
    $data = ['user' => ['name' => 'Alice']];
    $name = array_get($data, 'user.name');
    array_set($data, 'user.email', 'alice@example.com');
    $c = collect([1, 2, 3]);
