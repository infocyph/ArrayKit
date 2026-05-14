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
- Namespaced helper alternatives are available in ``Infocyph\ArrayKit\*`` alongside global helpers.

Compatibility Notes
-------------------

- ``unWrap()`` remains supported; ``unwrap()`` is now the preferred alias in helper and pipeline usage.
- Pipeline methods are mutable by design: most transformation methods update the same collection instance and return it.
- Use ``copy()`` or ``immutable()`` before pipeline operations when functional immutability is preferred.

Recommended Upgrade Checklist
-----------------------------

1. Prefer direct static calls (``ArraySingle`` / ``ArrayMulti`` / ``DotNotation``) for hot paths.
2. Use escaped paths (for example ``service\\.name``) when reading/writing literal dot keys.
3. Replace manual row indexing/grouping loops with ``keyBy()``, ``countBy()``, and ``firstWhere()`` where applicable.
4. Use ``getOrFail()`` for required config values in boot/runtime-critical code.
