ArrayKit 5.3.0
==============

Status: release candidate guidance. Tagging and publishing remain separate
actions after the release-acceptance gates are satisfied.

Highlights
----------

- Correctness hardening for bounded recursive traversal, strict membership,
  wildcard path presence, SQL-like matching, pagination, config memoization,
  generated cache publication, and one-shot lazy-stream failures.
- Immutable ``LazyFileConfig`` cache generations with atomic activation and an
  internal ``.arraykit-flat.php`` index that no longer reserves ``__flat``.
- Additive bounded DTO graph entry points:
  ``hydrateNestedGuarded()`` and ``toArrayDeepGuarded()``.
- Lower replay overhead for array-backed ``LazyCollection`` sources while
  retaining repeatability for one-shot iterators.
- Optional Runwire 2.1.1 integration through
  ``LazyCollection::withRunwire()``. The host passes its existing
  ``RuntimeContext`` and optional request/scope objects; ArrayKit does not own
  Runwire lifecycle management.
- Expanded regression, boundary, benchmark, lifecycle, and optional-integration
  coverage under the PHPForge quality gates.

Correctness Changes
-------------------

Strict membership
~~~~~~~~~~~~~~~~~

Threshold-optimized membership now preserves native PHP strict-comparison
semantics rather than relying on fingerprints for values that cannot safely be
represented by the fast lookup.

Wildcard presence
~~~~~~~~~~~~~~~~~

``DotNotation::matches()`` now answers path existence independently from the
resolved leaf value. Existing ``null``, ``false``, ``0``, empty strings, and
empty arrays are present values.

SQL-like matching
~~~~~~~~~~~~~~~~~

``ArrayMulti::whereLike()`` is anchored to the true beginning/end of the
subject, supports multiline wildcard consumption, and surfaces PCRE execution
failures instead of silently treating them as no match.

Pagination
~~~~~~~~~~

``ArraySingle::paginate()`` validates reachability before calculating an
offset, avoiding integer overflow for valid but extremely large page numbers.

Configuration and Cache Lifecycle
---------------------------------

Resolved read memoization is invalidated when configuration/cache sources
change. Generated namespace warm-up reads the authoritative source unless the
caller explicitly supplied or mutated that namespace in memory.

Namespace caches are built as immutable generations and activated only after a
successful build. A failed build leaves the previous valid generation active.
Unsupported/cyclic values are rejected before replacing a valid compiled
artifact.

Upgrade deployments should rebuild 5.2 namespace caches. The former internal
``__flat.php`` metadata must not be reused as the 5.3 flat index;
``.arraykit-flat.php`` is used inside the active generation instead. Retire old
generation directories only after workers that may still reference them have
been replaced. Apply the application's normal OPcache invalidation/restart
policy to generated PHP artifacts.

DTO Graph Guards
----------------

Use ``hydrateNestedGuarded()`` and ``toArrayDeepGuarded()`` for graphs that can
be large, recursive, or influenced by external input. They enforce one shared
depth/node budget per call and reject active-path object/array cycles while
allowing shared acyclic objects. Existing ``hydrateNested()`` and
``toArrayDeep()`` remain available for trusted, already-bounded graphs.

Optional Runwire Integration
----------------------------

Runwire is not a production dependency. ArrayKit 5.3 tests the optional
integration against ``infocyph/runwire`` 2.1.1.

``LazyCollection::withRunwire()`` accepts the host's exact ``RuntimeContext``
and optional ``RequestContext`` / ``CoroutineScope``. Cancellation is checked
at the traversal boundary and at the configured item cadence. Cooperative
``yieldNow()`` calls are made only when an active scope is passed and the
runtime advertises Runwire coroutine capability.

Bindings propagate through derived lazy operations and can be explicitly
rebound for a new request. ArrayKit never starts/stops a runtime or event loop,
completes a request, closes a scope, or stores request bindings globally.

Compatibility
-------------

The release remains PHP 8.4+ and keeps Runwire optional. Existing synchronous
LazyCollection use continues to work without Runwire installed. Public API
additions are additive; corrected edge cases listed above may change results
where 5.2 behavior was demonstrably inconsistent with the documented/native
contract.

See :doc:`migration`, :doc:`lazy-config`, :doc:`collection`,
:doc:`traits-and-helpers`, and :doc:`lifecycle` for operational details.
