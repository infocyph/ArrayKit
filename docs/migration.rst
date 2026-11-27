Migration and Compatibility
===========================

This page highlights behavior and API additions that may affect usage patterns.

5.4 Upgrade
-----------

The 5.4 release line retains PHP 8.4 as its minimum and tests PHP 8.4, 8.5
and 8.6. Before upgrading from 5.3.2:

1. Ensure ``ext-filter``, ``ext-hash`` and ``ext-tokenizer`` are installed.
2. If using optional Runwire bindings, upgrade the host to Runwire ``^4.0.1``.
   Exact 4.0.1 is tested; older Runwire lines are not qualified by this release.
3. Replace ``SORT_LOCALE_STRING`` with ``ArrayMulti::SORT_LOCALE`` (integer
   value 5). C/POSIX collation uses bytewise comparison; other locales require
   ``ext-intl`` and use ICU, whose order can differ from libc.
4. Review any fixtures or application logic that depend on a particular seeded
   shuffle permutation. The deterministic engine changed from Mt19937 to
   Xoshiro256StarStar; identical seeds remain reproducible within the engine.
   Unseeded shuffles use secure randomness and do not advance global Mt19937.

Strict duplicate detection now reports repeated ``null`` values. Numeric
accumulation retains integer precision where PHP can represent the result;
backed enum getters reject mismatched scalar types. A namespace file that
becomes unreadable after resolution raises ``UnexpectedValueException``
without marking that namespace loaded. Resilient generated-cache fallback
remains available through ``ResilientLazyFileConfig``.

See :doc:`release-5.4.0` for the release summary and verification limits.

5.3 Upgrade
-----------

ArrayKit 5.3 is a correctness and runtime-hardening release. Existing ordinary
array, collection, config, DTO, and lazy-collection entry points remain
available; the bounded DTO and Runwire APIs are additive.

Before deploying 5.3:

1. Rebuild ``LazyFileConfig`` generated namespace caches. Do not carry the old
   5.2 ``__flat.php`` acceleration artifact forward as internal metadata.
2. Replace/restart workers according to the application's normal deployment and
   OPcache policy after publishing new generated PHP cache files.
3. If nested DTO graphs cross an external-data boundary, prefer
   ``hydrateNestedGuarded()`` / ``toArrayDeepGuarded()`` with limits appropriate
   to that boundary.
4. Runwire integration is optional. Applications that use it should forward the
   exact host-owned ``RuntimeContext`` and optional ``RequestContext`` /
   ``CoroutineScope`` to ``LazyCollection::withRunwire()``. ArrayKit does not
   create or drive a runtime, event loop, request lifecycle, or coroutine scope.

Notable 5.3 behavior corrections include strict membership equivalence at
lookup thresholds, wildcard path-presence detection independent of leaf
truthiness, true whole-string SQL-like matching, overflow-safe pagination,
coherent config read-memo invalidation, source-authoritative namespace cache
warming, failure-safe generated-cache publication, and repeatable terminal
errors for one-shot lazy streams.

Generated-cache metadata now lives in ``.arraykit-flat.php`` inside an immutable
generation selected by ``.arraykit-generation``. The caller namespace
``__flat`` is therefore no longer ambiguous with ArrayKit's internal flat
index.

5.3.2 Patch Notes
-----------------

The patch fixes failed dot-path moves (the source is preserved if a destination
write fails), guarded sort boundaries for arbitrary object values, snapshot
reference aliasing, and lazy config refresh against newly published source
generations. It also fixes numeric namespace discovery, numeric and enum edge
cases, median overflow and private pipeline method dispatch.

The 5.3.2 maintenance line qualified optional Runwire integration against
Runwire 3.0. Production installation did not require Runwire. For the current
compatibility target, see the 5.4 upgrade notes above.

Normal array/snapshot APIs retain their signatures. Snapshotting now visits
nested arrays to detach external references; object values remain shallow.
A cyclic array reference is rejected at snapshot creation rather than saved.
Snapshot creation also rejects depth beyond 256 or more than 100,000 visited
entries before replacing an existing snapshot. Reused referenced subgraphs are
detached once per snapshot and shared through PHP copy-on-write storage.

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
- Seeded ``ArraySingle::shuffle()`` uses an isolated randomizer rather than
  reseeding PHP's global Mersenne Twister state. See the 5.4 upgrade notes
  for the subsequent engine and unseeded behavior changes.
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
