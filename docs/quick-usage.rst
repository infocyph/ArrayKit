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

    $list = [1, 2, 'x', 2];
    $isList = ArraySingle::isList($list);      // true
    $dupes  = ArraySingle::duplicates($list);  // [2]
    $hasAll = ArraySingle::containsAll($list, [1, 2]); // true
    $hasAny = ArraySingle::containsAny($list, [9, 2]); // true
    $page   = ArraySingle::paginate($list, 1, 2); // [1, 2]
    $sum    = ArraySingle::sum($list);         // 5 (non-numeric ignored)
    $avg    = ArraySingle::avg($list);         // 5 / 3
    // ArraySingle::paginate($list, 0, 2) throws InvalidArgumentException

ArrayMulti Example
------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $data = [[1, 2], [3, [4, 5]]];
    $flat = ArrayMulti::flatten($data);      // [1, 2, 3, 4, 5]
    $flatZero = ArrayMulti::flatten($data, 0); // [[1, 2], [3, [4, 5]]]
    $flatOne = ArrayMulti::flatten($data, 1);  // [1, 2, 3, [4, 5]]
    $depth = ArrayMulti::depth($data);       // 3
    $sorted = ArrayMulti::sortRecursive($data);

    $rows = [
        ['status' => 'active', 'score' => 40, 'email' => 'a@example.com'],
        ['status' => 'archived', 'score' => 10, 'email' => 'b@example.com'],
    ];
    $like = ArrayMulti::whereLike($rows, 'email', '%@example.com');
    $first = ArrayMulti::firstWhereIn($rows, 'status', ['active', 'pending']);
    $sortedMany = ArrayMulti::sortByMany($rows, [['status', 'asc'], ['score', 'desc']]);

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
    $port = $config->getInt('db.port', 3306);
    $config->snapshot('before');
    $config->merge(['app' => ['env' => 'production']]);
    $config->restore('before');

LazyCollection Example
----------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $lazy = ArrayKit::lazyCollection(range(1, 20))
        ->mapLazy(fn ($v) => $v * 2)
        ->filterLazy(fn ($v) => $v % 4 === 0)
        ->takeUntil(fn ($v) => $v > 12)
        ->all();

ArrayShape Example
------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayShape;

    ArrayShape::require(
        ['id' => 1, 'email' => 'a@example.com', 'roles' => ['admin']],
        ['id' => 'int', 'email' => 'string', 'roles' => 'list<string>'],
    );

Namespaced Helper Example
-------------------------

.. code-block:: php

    <?php
    use function Infocyph\ArrayKit\array_get;
    use function Infocyph\ArrayKit\array_set;
    use function Infocyph\ArrayKit\collect;

    $data = ['user' => ['name' => 'Alice']];
    $name = array_get($data, 'user.name');
    array_set($data, 'user.email', 'alice@example.com');
    $c = collect([1, 2, 3]);

Optional Global Helpers
-----------------------

.. code-block:: php

    <?php
    require_once __DIR__ . '/vendor/infocyph/arraykit/src/functions.php';
    // now global array_get()/array_set()/collect()/chain() are available.
