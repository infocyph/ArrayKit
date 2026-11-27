ArrayKit 5.4.0
==============

Highlights
----------

- Optional Runwire integration targets ``^4.0.1``, with exact 4.0.1
  exercised on PHP 8.4, 8.5 and 8.6.
- PHP 8.4 remains the minimum; all three PHP versions are included in the
  quality and integration matrices.
- Locale-aware sorting no longer relies on deprecated PHP locale APIs.
- Strict duplicate detection, numeric precision, lazy namespace loading and
  deterministic shuffling have additional regression coverage.

Compatibility and Upgrade
-------------------------

Install the required ``ext-filter``, ``ext-hash`` and ``ext-tokenizer``
extensions. Install optional ``ext-intl`` if using non-C locale sorting.
Runwire remains optional for consumers; ordinary synchronous use does not
need a Runwire installation.

Replace the deprecated locale sort flag with ``ArrayMulti::SORT_LOCALE``
(value 5). ICU collation for non-C locales can differ from earlier libc
ordering. Seeded shuffles use Xoshiro256StarStar instead of Mt19937, so exact
permutations from older releases are not guaranteed; unseeded shuffling uses
secure randomness. Seeded shuffles are not suitable for secrets.

See :doc:`migration`, :doc:`installation` and :doc:`array-helpers`.

Correctness and Lifecycle
------------------------

Strict duplicates include repeated null values without merging distinct
types. Integer sums retain native precision where representable; enum getters
preserve strict backing-type matching. A resolved configuration namespace
that becomes unreadable fails without marking the namespace loaded.
Generated-cache resilience remains opt-in via ``ResilientLazyFileConfig``.

ArrayKit forwards host-owned Runwire request and coroutine bindings; it does
not manage a runtime or complete a host request. See :doc:`collection`,
:doc:`lazy-config` and :doc:`config-layering`.

Verification
------------

The release candidate passed PHPStan, Psalm, both dependency-resolution QA
matrices, benchmark result-contract checks, documentation and the exact
Runwire integration matrix. Two 300-second persistent-worker soaks passed
the 16 MiB memory-growth limit. Hosted benchmark results do not certify the
strict 2% regression budget because a stable benchmark environment was not
asserted.

The release qualification applies to the exercised configurations and
workloads, not every possible application or deployment.
