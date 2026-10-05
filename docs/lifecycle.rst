Runtime Ownership and Trust Boundaries
=======================================

ArrayKit keeps runtime state local to the object that owns it. This matters in
persistent workers, queue consumers, application servers, and long-running
CLI processes where process lifetime is longer than a single request or job.

Ownership Model
---------------

``Config``
    Configuration items, snapshots, hooks, and resolved-value memoization are
    instance-owned. Mutations invalidate the affected memo state. Reuse an
    instance across requests only when cross-request configuration state is
    intentional.

``LazyFileConfig``
    Loaded namespaces, source/cache origin tracking, and read memoization are
    instance-owned. Namespace cache files are deployment-owned generated
    artifacts. Build or refresh them during deployment/admin work rather than
    normal request handling.

``LazyCollection``
    Array sources are replayed directly from the captured array and do not
    allocate a per-entry replay memo. One-shot iterators/generators retain the
    consumed prefix for repeatable traversal and preserve a terminal source
    failure at its stream boundary. That replay state lives as long as the
    collection. Use ``fromFactory()`` when a fresh source can be created per
    traversal or when retaining a long/unbounded consumed prefix is undesirable.

DTO graph guards
    ``hydrateNestedGuarded()`` and ``toArrayDeepGuarded()`` use call-local
    depth/node accounting and active-path cycle detection. No graph state is
    stored after the call.

Environment references
    ``Environment::ref()`` is process-environment backed and resolves when the
    value is materialized. Generated configuration caches intentionally freeze
    the resolved value for that cache generation.

Trusted and Untrusted Inputs
----------------------------

- PHP configuration source files and generated PHP cache files are executable
  deployment inputs. Keep their directories deployment-owned and non-writable
  by untrusted request data.
- Dot-path and guarded array APIs can accept user-controlled structures when
  callers set limits appropriate to the request budget. ``getSafe()``,
  ``flattenGuarded()``, ``depthGuarded()``, and ``sortRecursiveGuarded()`` are
  the bounded entry points.
- Ordinary deep DTO export/hydration is intended for trusted, already-bounded
  graphs. Use the guarded variants at external-data boundaries.
- Callbacks, closures, hooks, and factories execute application code. Their
  own CPU, I/O, and side effects are application-owned and are not sandboxed by
  ArrayKit traversal limits.

Generated Cache Lifecycle
-------------------------

Lazy namespace cache publication uses immutable generations plus an active
generation pointer. A rebuild is prepared separately, validated, and only then
activated. Readers therefore keep using the previous valid generation if a
new build fails. The flat leaf index is internal metadata named
``.arraykit-flat.php``; ``__flat`` remains a valid caller namespace.

On upgrade to 5.3, rebuild generated lazy-config artifacts instead of copying
old ``__flat.php`` metadata forward. Old generation directories are disposable
after they are no longer active. If OPcache is used for generated PHP cache
files, deployment tooling remains responsible for its normal invalidation or
restart policy.

Persistent Worker Guidance
--------------------------

- Prefer request/job-scoped ``Config`` and ``LazyFileConfig`` objects when
  runtime mutation is request-specific.
- Do not keep one-shot ``LazyCollection`` instances globally when an unbounded
  stream can be consumed indefinitely; replay state is intentionally retained
  for repeatability.
- Prefer ``LazyCollection::fromFactory()`` for renewable database cursors,
  event streams, and worker jobs that can create a fresh iterator.
- Treat generated cache warm-up as deployment/admin work, not a request-time
  recovery path.
- Avoid static/global application bindings for request cancellation or worker
  context. Optional runtime integrations should be passed explicitly to the
  collection that uses them.

Mutation and Concurrency
------------------------

ArrayKit objects do not provide cross-thread synchronization for in-memory
mutation. Keep mutable instances within one request/job execution context.
Generated lazy-config publication uses filesystem locking for writers and
immutable generations for readers; the generation pointer is the publication
boundary.
