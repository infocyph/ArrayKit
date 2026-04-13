Array Helpers
=============

ArrayKit ships static helpers grouped by data shape:

- ``ArraySingle`` for one-dimensional arrays
- ``ArrayMulti`` for nested arrays / row collections
- ``BaseArrayHelper`` for lower-level shared operations

If you prefer one entry point, use ``Infocyph\ArrayKit\ArrayKit``:

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\ArrayKit;

    $isList = ArrayKit::single()->isList([1, 2, 3]);
    $flat = ArrayKit::multi()->flatten([[1], [2, [3]]]);
    $wrapped = ArrayKit::helper()->wrap('x');

Choosing the Right Helper
-------------------------

Use ``ArraySingle`` when your data is a simple list or key-value map:

.. code-block:: php

    <?php
    $tags = ['php', 'array', 'docs'];
    $settings = ['env' => 'prod', 'debug' => false];

Use ``ArrayMulti`` for row sets and nested arrays:

.. code-block:: php

    <?php
    $users = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

ArraySingle: Structure and Existence
------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $arr = ['name' => 'Alice', 'age' => 30];
    $list = [10, 20, 30];

    $exists = ArraySingle::exists($arr, 'name'); // true
    $isList = ArraySingle::isList($list);         // true
    $isAssoc = ArraySingle::isAssoc($arr);        // true
    $isUnique = ArraySingle::isUnique([1, 2, 3]); // true

ArraySingle: Selection and Transformation
-----------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $arr = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];

    $only = ArraySingle::only($arr, ['a', 'd']);      // ['a' => 1, 'd' => 4]
    $except = ArraySingle::except($arr, ['b']);        // ['a' => 1, 'c' => 3, 'd' => 4]
    $mapped = ArraySingle::map($arr, fn ($v) => $v * 10);
    $filtered = ArraySingle::where($arr, fn ($v) => $v > 2);
    $rejected = ArraySingle::reject($arr, fn ($v) => $v > 3);

ArraySingle: Positional Operations
----------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $arr = [10, 20, 30, 40, 50, 60];

    $slice = ArraySingle::slice($arr, 1, 3);            // [1 => 20, 2 => 30, 3 => 40]
    $skip = ArraySingle::skip($arr, 2);                 // [2 => 30, 3 => 40, ...]
    $nth = ArraySingle::nth($arr, 2);                   // [10, 30, 50]
    $page = ArraySingle::paginate($arr, 2, 2);          // page 2 => [2 => 30, 3 => 40]
    $chunks = ArraySingle::chunk($arr, 2);              // [[10,20], [30,40], [50,60]]
    $until40 = ArraySingle::skipUntil($arr, fn ($v) => $v === 40);

ArraySingle: Search, Partition, Aggregation
-------------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $arr = [1, 2, 2, 3, 4, 5];

    $foundKey = ArraySingle::search($arr, fn ($v) => $v === 3); // 3
    [$even, $odd] = ArraySingle::partition($arr, fn ($v) => $v % 2 === 0);
    $dupes = ArraySingle::duplicates($arr);              // [2]
    $unique = ArraySingle::unique($arr);                 // [1,2,3,4,5]
    $sum = ArraySingle::sum($arr);                       // 17
    $avg = ArraySingle::avg($arr);                       // 17/6
    $median = ArraySingle::median($arr);                 // 2.5
    $mode = ArraySingle::mode($arr);                     // [2]

ArraySingle: Numeric and Value Helpers
--------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArraySingle;

    $values = [-2, -1, 0, 1, 2, 3];

    $positive = ArraySingle::positive($values);  // [1,2,3]
    $negative = ArraySingle::negative($values);  // [-2,-1]
    $isInt = ArraySingle::isInt([1, 2, 3]);      // true
    $isPositive = ArraySingle::isPositive([1, 2]); // true
    $isNegative = ArraySingle::isNegative([-1, -2]); // true

ArrayMulti: Flattening and Shape
--------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $nested = [[1, 2], [3, [4, 5]]];

    $collapse = ArrayMulti::collapse($nested);          // [1, 2, 3, [4, 5]]
    $flat = ArrayMulti::flatten($nested);               // [1,2,3,4,5]
    $flatOne = ArrayMulti::flatten($nested, 1);         // flatten one level
    $depth = ArrayMulti::depth($nested);                // 3
    $flatByKey = ArrayMulti::flattenByKey($nested);     // flattened values

ArrayMulti: Row Filtering
-------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $rows = [
        ['name' => 'Alice', 'age' => 30, 'role' => 'admin'],
        ['name' => 'Bob', 'age' => 21, 'role' => null],
        ['name' => 'Cara', 'age' => 25, 'role' => 'editor'],
    ];

    $adults = ArrayMulti::where($rows, 'age', '>=', 25);
    $inRole = ArrayMulti::whereIn($rows, 'role', ['admin', 'editor']);
    $notInRole = ArrayMulti::whereNotIn($rows, 'role', ['guest']);
    $nullRole = ArrayMulti::whereNull($rows, 'role');
    $notNullRole = ArrayMulti::whereNotNull($rows, 'role');
    $between = ArrayMulti::between($rows, 'age', 22, 30);
    $custom = ArrayMulti::whereCallback($rows, fn ($row) => $row['name'] === 'Alice');

ArrayMulti: Grouping, Ordering, and Projection
----------------------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $rows = [
        ['team' => 'A', 'score' => 10],
        ['team' => 'B', 'score' => 30],
        ['team' => 'A', 'score' => 20],
    ];

    $grouped = ArrayMulti::groupBy($rows, 'team');
    $sorted = ArrayMulti::sortBy($rows, 'score', true);      // desc
    $sortedRecursive = ArrayMulti::sortRecursive($rows);
    $scores = ArrayMulti::pluck($rows, 'score');             // [10,30,20]
    $transposed = ArrayMulti::transpose($rows);

ArrayMulti: Row Set Operations
------------------------------

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\ArrayMulti;

    $rows = [
        ['id' => 1, 'name' => 'A'],
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
    ];

    $unique = ArrayMulti::unique($rows);
    [$passed, $failed] = ArrayMulti::partition($rows, fn ($row) => $row['id'] === 1);
    $mapped = ArrayMulti::map($rows, fn ($row) => $row['name']);
    $reduced = ArrayMulti::reduce($rows, fn ($carry, $row) => $carry + $row['id'], 0);
    $sumById = ArrayMulti::sum($rows, 'id');

BaseArrayHelper
---------------

``BaseArrayHelper`` includes shared primitives used by higher-level helpers.

.. code-block:: php

    <?php
    use Infocyph\ArrayKit\Array\BaseArrayHelper;

    $wrapped = BaseArrayHelper::wrap('x');                 // ['x']
    $unwrapped = BaseArrayHelper::unWrap(['only']);        // 'only'
    $accessible = BaseArrayHelper::accessible(['a' => 1]); // true

    $has = BaseArrayHelper::has(['a' => 1], 'a');          // true
    $hasAny = BaseArrayHelper::hasAny(['a' => 1], ['x', 'a']); // true

    $range = BaseArrayHelper::range(1, 5);                 // [1,2,3,4,5]
    $times = BaseArrayHelper::times(3, fn ($i) => "Row $i"); // ['Row 1','Row 2','Row 3']
    $randomOne = BaseArrayHelper::random([10, 20, 30]);

    $any = BaseArrayHelper::any([1, 2, 3], fn ($v) => $v > 2); // true
    $all = BaseArrayHelper::all([1, 2, 3], fn ($v) => $v > 0); // true
    $key = BaseArrayHelper::findKey(['x' => 3], fn ($v) => $v === 3); // x

Behavior Notes
--------------

- Many methods preserve original keys (especially ``slice``, ``where``, ``skip`` variants).
- ``ArraySingle::unique()`` has loose mode (default) and strict mode.
- ``ArrayMulti::where()`` uses the global ``compare()`` helper semantics for operators.
- ``BaseArrayHelper::random()`` throws ``InvalidArgumentException`` when requested count exceeds array size.
