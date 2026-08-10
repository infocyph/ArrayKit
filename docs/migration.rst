Migration and Compatibility
===========================

This page highlights behavior and API additions that may affect usage patterns.

Recent Additions
----------------

- ``ArrayMulti`` now includes ``keyBy()``, ``indexBy()``, ``countBy()``, ``firstWhere()``, ``mapWithKeys()``, ``min/max``, ``minBy/maxBy``, ``values()``, ``rekey()``, and deep merge helpers.
- ``ArraySingle`` now includes ``countBy()``, ``mapWithKeys()``, ``min/max``, ``minBy/maxBy``, ``values()``, ``rekey()``, set helpers (``intersect/diff/symmetricDiff/same``), and optimized strict lookups.
- ``DotNotation`` supports escaped dot-path segments (for literal key dots) and path compilation cache.
- ``Collection`` now implements ``IteratorAggregate`` semantics for safe nested iteration and provides ``copy()`` / ``immutable()`` snapshots.
- ``Config`` / ``LazyFileConfig`` now include ``replace()``, ``reload()``, and ``getOrFail()``.
- ``LazyFileConfig`` includes ``loaded()`` alias for ``isLoaded()``.
- ``Config`` now supports compiled cache export/load plus in-memory read memoization.
- ``LazyFileConfig`` adds namespace-cache warming and full compiled-cache generation.
- Namespaced helpers (``Infocyph\ArrayKit\*``) are now the default autoloaded helper surface; globals are optional via manual include of ``src/functions.php``.
- ``ArrayMulti::flatten($array, 0)`` now returns unchanged top-level values.
- ``ArraySingle::avg()``, ``sum()``, ``isPositive()``, and ``isNegative()`` now ignore non-numeric values consistently.
- ``ArraySingle::paginate()`` now validates ``$page``/``$perPage`` and throws for values below ``1``.
- ``ArrayMulti`` callback-based sort/sum/min/max-by helpers support ``($row, $key)``.
- ``ArrayMulti`` adds ``uniqueBy()``, ``duplicatesBy()``, ``sortByMany()``, ``whereBetween()``, ``whereLike()``, ``whereStartsWith()``, ``whereEndsWith()``, ``whereContains()``, and ``firstWhereIn()``.
- ``DotNotation`` adds ``hasWildcard()``, ``paths()``, ``matches()``, ``rename()``, and ``move()``.
- ``Config`` adds typed getters (``getString/getInt/getFloat/getBool/getArray/getList/getEnum``), merge/state helpers (``merge/overlay/snapshot/restore/changed``), and ``readonly()`` mode.
- ``Collection`` adds ``immutableProcess()`` / ``pipeImmutable()`` explicit immutable-style pipeline entry.
- ``ArrayKit`` facade adds ``lazyCollection()`` and package now includes ``LazyCollection`` (generator-backed operations).
- ``LazyCollection::from()`` now safely replays consumed values from one-shot iterables; use ``fromFactory()`` for a fresh source on every traversal.
- New optional helper: ``ArrayShape`` validator.

Compatibility Notes
-------------------

- ``wrap()`` and ``unWrap()`` remain array-helper methods but are no longer
  exposed on ``Pipeline`` because pipeline state is always an array.
- Pipeline methods are mutable by design: most transformation methods update the same collection instance and return it.
- Use ``copy()`` or ``immutable()`` before pipeline operations when functional immutability is preferred.

Behavior Changes
----------------

- ``wrap()`` now treats only ``null`` as absence. Falsey non-null scalars are
  wrapped instead of being discarded.
- Seeded ``ArraySingle::shuffle()`` uses an isolated randomizer and no longer
  reseeds or advances PHP's global Mersenne Twister state.
- ``groupBy()``, ``keyBy()`` / ``indexBy()``, and ``countBy()`` skip rows whose
  derived field is missing. Present values must produce integer or string keys;
  ``null`` and other invalid key types now throw ``InvalidArgumentException``.
- ``Collection`` relies on ``IteratorAggregate``. Calls to the former manual
  pointer surface (``current()``, ``key()``, ``next()``, ``rewind()``, and
  ``valid()``) should be replaced with ``foreach`` or ``getIterator()``.
- Plain dotted strings now consistently identify DotNotation paths across reads
  and mutations. Escape literal dots (for example ``service\\.name``).
- ``duplicates()`` now mirrors ``unique()`` with loose comparison by default and
  an optional ``$strict`` flag.
- Strings in ``string|callable`` row APIs always identify field names. Use a
  closure or another non-string callable to select callback behavior.
- Numeric selection and accumulation preserve integer precision instead of
  coercing all values to ``float``.

Recommended Upgrade Checklist
-----------------------------

1. Prefer direct static calls (``ArraySingle`` / ``ArrayMulti`` / ``DotNotation``) for hot paths.
2. Use escaped paths (for example ``service\\.name``) when reading/writing literal dot keys.
3. Replace manual row indexing/grouping loops with ``keyBy()``, ``countBy()``, and ``firstWhere()`` where applicable.
4. Use ``getOrFail()`` for required config values in boot/runtime-critical code.
