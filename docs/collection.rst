Collections
===========

ArrayKit collections provide an object-oriented array wrapper with:

- dot-notation read/write
- full ``ArrayAccess`` + ``Iterator`` + ``Countable`` behavior
- a chainable pipeline of transformation methods
- optional get/set hooks via ``HookedCollection``

Available classes:

- ``Infocyph\ArrayKit\Collection\Collection``
- ``Infocyph\ArrayKit\Collection\HookedCollection``
- ``Infocyph\ArrayKit\Collection\Pipeline``

Creating Collections
--------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    // Constructor
    $c1 = new Collection(['a' => 1, 'b' => 2]);

    // Static factories (accept array-able values)
    $c2 = Collection::make(['x' => 10]);
    $c3 = Collection::from(['y' => 20]);

    // Global helper (autoloaded from src/functions.php)
    $c4 = collect(['z' => 30]);

Reading and Writing
-------------------

``Collection`` supports direct array access, dot notation, and helper methods.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $c = new Collection([
        'user' => ['name' => 'Alice'],
        'active' => true,
    ]);

    // Dot notation get/set
    $name = $c->get('user.name');             // Alice
    $c->set('user.email', 'alice@example.com');

    // ArrayAccess with dot notation
    $email = $c['user.email'];                // alice@example.com
    $c['user.role'] = 'admin';

    // Multi-key fetch
    $subset = $c->get(['user.name', 'user.role']);

    // Existence checks
    $hasName = $c->has('user.name');          // true
    $hasAny = $c->hasAny(['x', 'user.role']); // true

    // Append with null offset
    $c[] = 'tail-value';

    // Remove key
    unset($c['user.role']);

Collection Utility Methods
--------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $c = new Collection(['a' => 1, 'b' => 2]);

    $all = $c->all();            // full array
    $items = $c->items();        // alias of all()
    $keys = $c->keys();          // ['a', 'b']
    $array = $c->toArray();      // array output
    $json = $c->toJson();        // JSON string
    $count = $c->count();        // 2
    $empty = $c->isEmpty();      // false

    $c->merge(['c' => 3]);       // now a,b,c
    $c->clear();                 // now empty

Iteration and Interfaces
------------------------

``Collection`` is directly iterable.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $c = new Collection(['a' => 1, 'b' => 2]);

    foreach ($c as $key => $value) {
        // $key, $value
    }

    // Supports json_encode() through JsonSerializable
    $json = json_encode($c);

HookedCollection
----------------

``HookedCollection`` extends ``Collection`` and adds per-key hooks.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\HookedCollection;

    $c = new HookedCollection(['name' => 'alice', 'user' => ['city' => 'dhaka']]);

    // Run callback(s) when reading key
    $c->onGet('name', fn ($v) => strtoupper((string) $v));

    // Run callback(s) when setting key
    $c->onSet('role', fn ($v) => "Role: $v");

    // Dot-notation hooks are supported
    $c->onGet('user.city', fn ($v) => ucfirst((string) $v));

    echo $c['name'];      // ALICE
    $c['role'] = 'admin';
    echo $c['role'];      // Role: admin
    echo $c['user.city']; // Dhaka

Pipeline Basics
---------------

Every transformation method is exposed through ``Pipeline``.
You can start it either with ``process()`` or directly by calling pipeline methods on collection (via ``__call``).

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $c = Collection::make([1, 2, 3, 4, 5]);

    // Dynamic passthrough to pipeline:
    $result = $c->filter(fn ($v) => $v > 2)
        ->map(fn ($v) => $v * 10)
        ->all();

    // Explicit pipeline:
    $sum = $c->process()->sum();

Pipeline Methods by Category
----------------------------

Selection and filtering:

- ``only()``, ``except()``
- ``filter()``, ``reject()``
- ``where()``, ``whereCallback()``
- ``whereIn()``, ``whereNotIn()``, ``whereNull()``, ``whereNotNull()``
- ``between()``

Slicing and positional:

- ``slice()``, ``skip()``, ``skipWhile()``, ``skipUntil()``
- ``nth()``, ``paginate()``, ``chunk()``

Structure and reshape:

- ``flatten()``, ``flattenByKey()``, ``collapse()``
- ``groupBy()``, ``pluck()``, ``transpose()``
- ``wrap()``, ``unWrap()``

Ordering and uniqueness:

- ``sortBy()``, ``sortRecursive()``, ``shuffle()``
- ``unique()``, ``duplicates()``, ``partition()``

Terminal methods (end chain with scalar/array/bool):

- ``sum()``, ``first()``, ``last()``, ``reduce()``
- ``any()``, ``median()``, ``mode()``, ``isMultiDimensional()``

Flow-control helpers:

- ``tap()``, ``pipe()``, ``when()``, ``unless()``

Detailed Pipeline Examples
--------------------------

Filtering and set-style operations:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Collection\Collection;

    $users = Collection::make([
        ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'age' => 30],
        ['id' => 2, 'name' => 'Bob', 'role' => 'editor', 'age' => 21],
        ['id' => 3, 'name' => 'Cara', 'role' => null, 'age' => 25],
    ]);

    $admins = $users->where('role', 'admin')->all();
    $notNullRole = $users->whereNotNull('role')->all();
    $adultEditors = $users->where('age', '>=', 21)->whereIn('role', ['editor'])->all();

Slicing and paging:

.. code-block:: php

    <?php
    $list = collect([10, 20, 30, 40, 50, 60]);

    $page1 = $list->paginate(1, 2)->all();         // first 2 items
    $everySecond = $list->nth(2)->all();
    $skipped = $list->skip(3)->all();
    $until40 = $list->skipUntil(fn ($v) => $v === 40)->all();

Grouping and reshaping:

.. code-block:: php

    <?php
    $rows = collect([
        ['team' => 'A', 'score' => 10],
        ['team' => 'B', 'score' => 20],
        ['team' => 'A', 'score' => 30],
    ]);

    $grouped = $rows->groupBy('team')->all();
    $scores = $rows->pluck('score')->all();         // [10, 20, 30]
    $sorted = $rows->sortBy('score', desc: true)->all();

Terminal calculations:

.. code-block:: php

    <?php
    $numbers = collect([1, 2, 3, 4, 5]);

    $sum = $numbers->process()->sum();              // 15
    $median = $numbers->process()->median();        // 3
    $mode = $numbers->process()->mode();            // [1,2,3,4,5] (all equal freq)
    $hasEven = $numbers->process()->any(fn ($v) => $v % 2 === 0); // true

Behavior Notes
--------------

- Most pipeline methods return the underlying ``Collection`` for chaining.
- Terminal methods return scalar/array/bool and stop the chain.
- Dot-notation works in collection accessors and in ``HookedCollection`` get/set overrides.
- ``merge()`` follows PHP ``array_merge`` semantics (string-key overwrite, numeric append/reindex).
