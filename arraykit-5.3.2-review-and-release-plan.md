# ArrayKit 5.3.2 review and release plan

Date: 2026-10-30 (Asia/Dhaka).

Reviewed source: `e77bf72a40c64b94f62e69f3b2662ce2dc47efec`, the published `5.3.1` tag. The working tree was clean before this report. This document records an audit and proposed work; none of the corrections below has been implemented by this review.

This plan follows the [PHPForge engineering principles](https://github.com/infocyph/PHPForge/blob/main/resources/engineering-principles.md) and [agent workflow](https://github.com/infocyph/PHPForge/blob/main/resources/AGENTS.md). Correctness, data integrity, bounded work, public contracts and host ownership take precedence over throughput. Performance claims require measurements with correct outputs.

## Release decision and scope

Recommend **5.3.2**, a patch containing the eleven identified corrections (R01–R09 from the original audit and R10–R11 from the supplemental review) and explicit compatibility coverage for Runwire 3.0. The existing passed-instance API already works with the published Runwire 3.0 source in the tests run here. The optional integration target is Runwire 3.0 only, without adding an ArrayKit API.

Use **5.4.0** only if implementation introduces an intentional new public capability or API beyond these corrections. Retire legacy Runwire compatibility claims and test jobs; target only Runwire ^3.0 for optional integration. Do not raise PHP 8.4, change named arguments, or turn Runwire into a mandatory runtime dependency as part of this patch.

Scope is ArrayKit's code, tests, docs, Composer constraints, benchmark fixtures and CI. Foundation adapters and qualification of Runwire's HTTP protocols, native workers, transports or host drivers are separate projects and are not ArrayKit release blockers for this work.

## Audit evidence

| Check | Result and limitation |
| --- | --- |
| Source review | All 37 production PHP files: array/set/query/sort operations, dot paths, collections/pipeline/replay, config/layering/generated caches, dotenv/environment, DTOs, hooks, facade and helpers; associated tests, benchmarks and workflow. Graphify was used for navigation; claims were checked in source and independent probes. |
| Installed PHPForge setup | Doctor healthy; bundled configs active; PHPStan max, class complexity 80 and function complexity 12. No detector, scope or threshold was weakened. |
| Local detailed suite | `composer ic:tests:details` passed: 401 tests, 8,797 assertions on PHP 8.5.4, plus formatting, architecture, static/security analysis and refactoring checks. |
| Local final full suite | `composer ic:tests` passed all 13 tasks. |
| Composer | `composer validate --strict` and `composer ic:release:constraints` passed. |
| Live locked dependency audit | Zero published advisories; development-only `doctrine/annotations` remains abandoned through benchmark tooling. Raw Composer exits nonzero for abandonment; this is distinct from an advisory and is non-blocking under existing PHPForge policy. ArrayKit has no third-party runtime package requirements. |
| Exact-source CI | [Security & Standards run 40161657499](https://github.com/infocyph/ArrayKit/actions/runs/40161657499) succeeded for `e77bf72…`: PHP 8.4/8.5 analysis, stable/lowest QA, clean installation, benchmarks, security report and Release Acceptance. Recent Dependabot-only runs were not counted as QA evidence. |
| Runwire 3.0 publication | Live [Composer metadata](https://repo.packagist.org/p2/infocyph/runwire.json) and the [3.0 source tag](https://github.com/infocyph/Runwire/tree/3.0) identify `054737bc6a8d4025a8d05fabb8ad390f66ee47ae`, published 2026-10-29. PHP and 64-bit PHP remain `^8.4`. |
| Runwire 3.0 composition | All 401 tests / 8,797 assertions passed using the exact published source in an isolated `/tmp` autoloader. Reflection confirmed that runtime and coroutine classes came from the published 3.0 source, not the locally installed package. This is runtime/source compatibility evidence, not a clean Composer matrix or a native host certification. |
| Runwire 3.0 worker soak | The existing persistent CLI harness passed with the published 3.0 source: 310.06 seconds including warm-up, 300 samples, initial/peak RSS 51.39 MiB and 0.00 MiB growth under the unchanged 16 MiB ceiling. It exercises a CLI worker with capability metadata; it does not launch a native RoadRunner host. |
| Additional parity probes | 480 strict membership comparisons against native `in_array()` behavior passed across lookup thresholds, mixed scalars, ordinary arrays, object identity, NaN, infinities and signed zero. Escaped dot-path flatten/expand probes passed. |
| Adversarial probes | The original audit reproduced R01–R09 outside the green suite. The supplemental branch review additionally identified R10/R11 in source, with independent native PHP probes for the overflow and method-visibility behavior. Repository regression tests must still reproduce both against the baseline. Green baseline tests are insufficient for release acceptance. |
| Branch QA status (supplemental review, 2026-10-30) | `patch/runwire` was one commit ahead of `main`, changing only this plan. No Actions runs existed for the branch at review time. The successful workflow evidence above applies to the 5.3.1 baseline, not the new release candidate. |

This audit did not establish an authentication bypass or arbitrary code execution. R01 is a conditional resource-safety failure when object graphs reach the guarded sorter; R02/R03 are data-integrity failures. PHP configuration/cache artifacts, callbacks and user-defined property methods remain trusted host code, not sandboxed input.

## Required corrections

Priorities are engineering priorities, not CVSS scores. P1 means correct before publication; P2 is a narrower confirmed defect included in this patch.

### R01 — P1: guarded sort compares uncounted object graphs

Owners: `src/Array/Concerns/ArrayMultiQuerySortTrait.php:909` and `:1234`.

`sortRecursiveWithGuards()` counts array entries and descends only into arrays. `compareSortValues()` subsequently uses PHP's regular comparison on object values, which can descend through arbitrarily large public object graphs outside the advertised budget.

Reproduction: two distinct `stdClass` values, each with `self` pointing to itself and different `tail` values, passed to `sortRecursiveGuarded([$left, $right], maxNodes: 2, throwOnTooDeep: true)` produce `Error: Nesting level too deep - recursive dependency?` from the comparator instead of controlled guard handling. Wide acyclic object graphs cross the same boundary without requiring cyclic PHP references. The documentation recommends this guarded entry point for untrusted/deep input and does not exclude object values there.

Correction: define and enforce a supported bounded-comparison domain before sorting. Charge supported object-data traversal to the same depth/node budget and detect active-path cycles, or reject object/comparison cases that cannot be bounded before invoking PHP comparison. Custom object/string conversion work must remain an explicit trusted-code boundary. Preserve normal `sortRecursive()` semantics. In non-throwing mode, propagate incompleteness to ancestors so no unsafe comparison runs; throwing mode must produce a controlled documented exception.

Acceptance: cyclic/distinct/shared objects, wide public object graphs, arrays containing such objects, opaque internal objects, all sort flags, exact boundaries, throw/non-throw behavior, scalar/array equivalence below limits, and fixed-budget work as object width grows. Do not claim arbitrary user callbacks can be sandboxed.

### R02 — P1: failed rename/move deletes the source

Owner: `src/Array/Concerns/DotNotationPublicApiTrait.php:361`.

`rename()` reads and removes the source before attempting the destination write. With `['source' => 42, 'target' => $object]` and a readonly `target.locked`, `rename($data, 'source', 'target.locked')` throws for the readonly property after `source` has already disappeared. `move()` and facade forwarding inherit the failure.

Correction: preflight/stage ordinary destination changes and commit source removal only when the move can succeed. Preserve source data on rejected writes and preserve established parent/child path behavior. Avoid a blanket swap of the two existing calls: overlapping source/destination paths require deliberate handling. Do not promise rollback of arbitrary external side effects inside application magic setters.

Acceptance: readonly/inaccessible/typed property failures, throwing setters, missing sources, same path, parent-to-child and child-to-parent moves, escaped paths, overwrite/fill flags, existing destination values, and native/facade named reference arguments. Failed supported moves must retain the source.

### R03 — P2: snapshots alias externally referenced scalar/array state

Owner: `src/Config/Concerns/BaseConfigTrait.php:498` and `:534`.

Reproduction: load `['app' => ['v' => &$value]]` with `$value = 1`; call `snapshot()`, `set('app.v', 2)`, `changed()`, `restore()`. `changed()` returns false and the restored value remains 2. Assignment into the snapshot retains the PHP reference cell, so the live mutation changes the saved state as well. Config supports referenced reads and documents restore as rollback.

Correction: make saved scalar/array state independent of live external reference cells, including repeated restores. Retain the established shallow identity contract for arbitrary objects; this is not a generic deep-clone feature. Handle internal aliases and cyclic arrays deliberately, with deterministic failure before replacing a valid snapshot if a graph cannot be represented under the chosen contract. Do not use `serialize()`/`unserialize()` as a generic copying mechanism or silently change ordinary live-reference reads/writes.

Acceptance: top-level/nested references, external mutation after snapshot, internal aliases, multiple named snapshots, repeated restore/mutate/restore, changed/read-cache parity, readonly restore, and Config/LazyFileConfig/LayeredLazyFileConfig use. Document exactly what remains shallow for object values.

### R04 — P2: explicit cache refresh retains old source-origin data

Owner: `src/Config/Concerns/LazyFileConfigCacheTrait.php:189`.

Reproduction: start with no cached `app` namespace, read source version 1, publish a generation containing source version 2 with a separate writer, then call `namespaceCache($sameRoot)` on the original reader. It still returns 1 while a fresh reader returns 2. Invalidation removes only cache-origin items and clears cache/missing flags; source-origin items survive and shadow the newly selected generation. Same-instance warm-up also needs coverage.

Correction: explicit refresh/rebuild must invalidate affected source-derived state as well as cached/flat/memo state, while retaining deliberate constructor/runtime overrides. Keep generation pinning coherent across exact and structural reads. Ordinary pinned readers must remain stable until an explicit refresh; do not add pointer polling to each hot read.

Acceptance: source-only reader before first publication; empty-cache generation; exact versus structural reads; same-root refresh, source/cache switching, warm-up and flush; multiple namespaces; ResilientLazyFileConfig; and preserved intentional runtime mutations. Do not infer a new public LayeredLazyFileConfig refresh API from this correction.

### R05 — P2: canonical numeric namespace names become integer map keys

Owners: `src/Config/LayeredLazyFileConfig.php:244`, `src/Config/Concerns/LazyFileConfigCacheTrait.php:167`, and namespace metadata in `src/Config/LazyFileConfig.php`.

`0` is accepted by the namespace grammar, but PHP coerces associative key `'0'` to integer `0`. A layered config with `namespaces: ['0']` fails during `all()` because `materializeNamespace(string)` receives an integer. Discovery of `0.php` similarly makes no-argument `warmNamespaceCache()` call `namespaceCacheWarmValue(string)` with an integer. Numeric names can also violate the documented string list returned by `loadedNamespaces()`.

Correction: normalize namespace identifiers to strings when crossing map-key/list boundaries, and consistently register numeric-looking constructor/runtime/source names. Preserve the current allowed namespace grammar and filename meaning; do not solve this by rejecting previously accepted names.

Acceptance: `'0'`, positive and negative canonical integers, noncanonical numeric-looking strings such as `'01'`, ordinary names, discovery, explicit warm-up, full retrieval, snapshots/exports, loaded-name lists, and lazy/layered/resilient behavior. Invalid names still fail at the existing validation boundary.

### R06 — P2: positive random sampling from an empty array misses validation

Owner: `src/Array/BaseArrayHelper.php:223`.

`random([], 1)` and `random([], 2)` return `[]`. Both requests exceed the available size, which the PHPDoc and user guide say must raise `InvalidArgumentException`. The empty-array early return bypasses that validation.

Correction: validate positive requested counts before the empty-result shortcut. Preserve empty single-pick behavior (`null`) and the established zero/nonpositive-count behavior unless a separate contract change is approved.

Acceptance: empty/nonempty arrays; omitted, zero, negative, exact-size and oversized counts; preserved keys; facade calls; no process-global randomness changes.

### R07 — P2: averaging can overflow or cast a valid float outside integer range

Owner: `src/Array/ArraySingle.php:21`, also exposed through the single-array facade. No new pipeline API is proposed.

`avg([1e20])` returns integer `7766279631452241920` on the reviewed 64-bit runtime instead of `1e20`. `avg([PHP_INT_MAX, PHP_INT_MAX])` returns `PHP_INT_MIN`, flipping the sign. The result is blindly cast to integer whenever `fmod($avg, 1.0)` is zero, without proving integer representability. PHP 8.5 also emits a warning. Separately, adding two finite `1e308` values overflows the intermediate total and returns infinity even though their mean is finite.

Correction: retain the ordinary fast path and established integer/float return behavior for representable results, but cast only after a range-safe check and use a numerically safe path when intermediate accumulation would overflow. Do not assume a float comparison with `PHP_INT_MAX` proves representability: converting the integer bound to float can round it upward. No arbitrary-precision dependency or broad aggregate rewrite is required.

Acceptance: positive/negative values outside integer range, both integer bounds and nearby floats, repeated `PHP_INT_MAX`, finite large floats whose sum overflows, mixed signs, infinities/NaN, numeric strings, fractional results, empty/non-numeric input, facade calls and `E_ALL` on both supported PHP versions. Preserve existing finite ordinary cases and define special-value behavior explicitly.

### R08 — P2: enum getters do not consistently use the supplied fallback

Owner: `src/Config/Concerns/BaseConfigTrait.php:186`.

The backed-enum branch accepts either integer or string values and calls `tryFrom()` in strict mode. An integer-backed enum with stored string `'1'`, or a string-backed enum with stored integer `1`, raises `TypeError`. The getter otherwise returns its supplied default for missing, unsupported and unmatched values, so a wrong backing scalar unexpectedly escapes that typed-getter boundary.

Correction: validate the enum's backing scalar type before calling `tryFrom()` and return the default on mismatch, without introducing implicit scalar coercion. Preserve the error for a nonexistent enum class and preserve unit-enum lookup by case name. Avoid repeated discovery/reflection on a hot lookup path when a simple bounded metadata reuse strategy is warranted.

Acceptance: integer-backed, string-backed and unit enums; correct values, unmatched values, numeric-looking strings, wrong backing scalar, null, boolean/array/object input, explicit defaults, missing config, and all config variants. A valid lookup continues to return the exact enum singleton.

### R09 — P2: NaN is classified as both positive and negative

Owner: `src/Array/ArraySingle.php:304` and `:329`.

`isPositive([NAN])` and `isNegative([NAN])` both return true, while native `NAN > 0` and `NAN < 0` are false. These functions reject only the opposite comparison; NaN is unordered, so it escapes both checks.

Correction: require the intended positive/negative predicate for every included numeric value. NaN must not establish either sign. Preserve ignored non-numeric values, the all-nonnumeric/empty result, zero handling and infinity signs; do not globally change `numericValue()` or unrelated arithmetic contracts merely to fix these predicates.

Acceptance: NaN alone and mixed with signed values, both infinities, positive/negative zero, finite values, numeric strings, empty/all-nonnumeric inputs and facade calls. Assert that NaN is neither sign.

### R10 — P2: even-length median overflows on finite inputs

Owner: `src/Array/ArraySingle.php:438`, including `Pipeline::median()` and facade forwarding.

For even-length numeric input, `median()` calculates `($values[$mid - 1] + $values[$mid]) / 2`. Two finite values such as `[1e308, 1e308]` overflow during addition and produce `INF`, although the correct median is finite. An independent PHP 8.4 probe confirms this arithmetic boundary. This issue is separate from R07's `avg()` implementation and must not be considered closed just because averaging is repaired.

Correction: use an overflow-safe two-value midpoint calculation for finite extremes without losing ordinary integer/float behavior. Address opposite-sign extremes, same-sign extremes, and integer-boundary precision deliberately; do not introduce an arbitrary-precision dependency or a generic aggregate rewrite. Keep sorting, median selection, and existing exclusion of nonnumeric values unchanged unless tests establish a conflicting existing contract.

Acceptance: even/odd counts, `[1e308, 1e308]`, negative large finite pairs, near-`PHP_INT_MAX`/min pairs, opposite-sign extremes, fractions, numeric strings, empty/all-nonnumeric input, nonfinite values with documented semantics, facade and pipeline calls, and warning-free `E_ALL` on PHP 8.4/8.5. Add a regression demonstrating the baseline failure before changing production code.

### R11 — P2: collection magic forwarding attempts private Pipeline methods

Owners: `src/Collection/Concerns/BaseCollectionTrait.php:51` and private helpers in `src/Collection/Pipeline.php:791`, `:798` and `:809`.

`Collection::__call()` checks `method_exists($pipeline, $method)` before forwarding. That check includes private methods, but the subsequent call from the collection trait cannot access them. For example, invoking `$collection->numericAggregate(null, true)` passes the check and raises a PHP `Error` about a private method instead of the documented `BadMethodCallException` for unavailable collection methods. Native PHP visibility behavior was independently probed.

Correction: forward only callable public pipeline operations. Reject private/internal methods through the existing controlled missing-method exception, without exposing private implementation helpers, adding proxies, or introducing a new reflection-heavy dispatch layer.

Acceptance: every publicly supported fluent operation remains callable through `Collection` and `HookedCollection`; private/internal helper names are rejected with `BadMethodCallException`; unknown method names preserve the same exception; argument forwarding and return/chain types remain unchanged. Add a regression demonstrating the baseline failure before changing production code.

## Runwire 3.0 support

### Compatibility and ownership decision

The current production binding consumes `RuntimeContext`, `RequestContext`, `CancellationToken`, `CoroutineScope`, `TaskLocal`, runtime coroutine capability, and their lifecycle/checkpoint methods. Those APIs remain available in the exact 3.0 source and all existing tests pass against them.

1. Require `^3.0` as the optional development integration target and test exact Runwire 3.0.0 on PHP 8.4/8.5. Do not claim or test support for prior major versions.
2. Update the optional Composer suggestion and integration docs to describe the tested versions. Keep the production dependency set unchanged.
3. Keep `withRunwire($runtime, $request, $scope, $checkpointEvery)` and explicit intermediary forwarding unchanged. No ambient/global runtime discovery, implicit binding, worker startup or event-loop creation.
4. Continue capability-gated yielding and request/scope cancellation/completion checks before source consumption and after resumption. Keep closed/invalid supplied lifecycle errors distinct from the normal missing-capability fallback. Preserve `take(0)` laziness.
5. Exercise genuine host-owned `CoroutineRuntime`/scope tasks, interleaved requests, cancellation/deadlines, fail-fast groups, source/callback errors, early abandonment, rebinding and reference release under Runwire 3.0. The host completes requests and closes scopes.
6. Verify installed/unbound and clean Runwire-absent production installs. Avoid reflection of private scheduler state or version checks on each item.

The relevant advantage is retaining cooperative fairness and cancellation while hosts adopt Runwire 3.0's runtime improvements. Array/DTO/config algorithms remain ArrayKit's responsibility. Runwire's outbound HTTP, WebSocket, transport, TLS and worker-pool features do not justify new integration in a pure-data path. Async file APIs do not make executable PHP config `include` asynchronous. Do not add an async config adapter, background worker or offload layer without a concrete caller need and representative throughput evidence.

## Execution sequence and structural constraints

| Batch | Work | Exit condition |
| --- | --- | --- |
| A | Add regressions for R01/R02, then fix guarded comparison and failed move handling. | Unsafe comparisons stop under the declared budget; rejected moves retain their source; ordinary APIs and facade contracts pass. |
| B | Add regressions and correct R03–R05 in existing config owners. | Snapshot rollback and source/cache generation coherence hold across all config variants; numeric namespaces work consistently. |
| C | Correct R06–R11 and expand optional Runwire development/docs/CI coverage. | Sampling, numeric and enum boundaries, median overflow and dynamic-method visibility pass; 3.0 and absent/unbound compositions pass without host ownership changes. |
| D | Run quality, performance, persistent-worker and final-revision CI acceptance. | Every applicable repository-owned gate below is satisfied and reported accurately. |

Expected public API additions: **zero**. Expected new production classes/interfaces/enums: **zero**. Keep behavior in the current cohesive owners, prefer private helpers only where they reduce actual duplication or make a safety boundary clear, and do not split types just to evade complexity limits. Any necessary departure must identify its concrete contract/value and measured cost before being included. Public signatures, named arguments, synchronous defaults, supported PHP versions and normal return/key ordering remain compatibility constraints.

Do not bundle unrelated formatter churn, generic serialization infrastructure, framework adapters or a runtime rewrite into these fixes. Review processor output and retain every active detector; no suppressions, baselines, exclusions or threshold increases.

## Branch implementation status

This is a rolling implementation tracker, not a release sign-off. The 5.3.1 baseline evidence earlier in this plan does not certify the current PR revision. Run the full final-revision matrix, release acceptance, and performance/worker checks before marking any batch release-complete.

| Batch | Status at review update | Remaining work |
| --- | --- | --- |
| A — R01–R02 | **Complete.** Guarded sort, staged moves and regression tests passed PHPForge QA on the release candidate. | None in scope. |
| B — R03–R05 | **Complete.** Reference-isolated snapshots, source-origin cache refresh, and numeric namespace handling passed PHP 8.4/8.5 stable and lowest QA, static analysis and regression coverage. | None in scope. |
| C — R06–R11 | **Complete.** All six corrections and regressions passed. Optional Runwire 3.0.0 passed PHP 8.4/8.5 compatibility tests and the PHP 8.5 persistent-worker soak. | None in scope. |
| D — release acceptance | **Complete within available shared-runner evidence.** Documentation passes Sphinx warnings-as-errors; clean/no-dev install, PHPForge matrix, 5.3.1-baseline benchmark validity, and separate 300-second Release Acceptance worker soak all passed on `eb9cbcbc`. | The 2% stable-environment performance comparator remains explicitly **unverified** (not falsely passed). Publication/tagging are separate authorized actions. |

## Release-candidate evidence boundaries

The immediately previous tag is `5.3.1` at `e77bf72a40c64b94f62e69f3b2662ce2dc47efec`. The benchmark now compares against that tag and validates representative workload output, including snapshots. GitHub's shared runner does not assert a stable performance environment: the configured 2% regression threshold is reported as *unverified*, not silently passed. This is a separate, explicitly disclosed performance-evidence limitation; correctness and QA checks are required to remain green.

The Sphinx HTML build with warnings treated as errors passed after resolving duplicate section labels, the lexer and heading issues. Runwire 3.0 remains optional, owned by the host application, with no Runwire 2.x qualification. **All required jobs succeeded** in [Security & Standards run 40997245493](https://github.com/infocyph/ArrayKit/actions/runs/40997245493) on code candidate `eb9cbcbc27695a6202a59593a83795a3fba111dd` (PHPForge QA stable/lowest 8.4/8.5, analysis, clean install, benchmark validity, Sphinx, exact Runwire 3.0 PHP 8.4/8.5, both persistent-worker soaks and Release Acceptance). This tracker-only synchronization commit changes no runtime, tests, workflow or benchmark files; verify its new final SHA before publication.

## Release acceptance

- [ ] Regressions reproduce all R01–R11 triggers on `5.3.1` and pass after their corrections. Include native/facade/collection/intermediary paths where applicable; retain all existing regressions.
- [x] Run PHPForge doctor/config inspection, sequential processors, `composer ic:tests:details`, and final `composer ic:tests` or `composer ic:release:guard`. Retain max analysis, 80/12 complexity limits, Psalm security checks, architecture, syntax/reference/duplicate/comment/skip checks, Rector, Pint and PHPCS. Keep live audit and manifest/constraint checks current.
- [x] Verify real PHP 8.4/8.5 stable/lowest dependency CI on the same final candidate commit, plus clean optimized `--no-dev` installation without Runwire. Record unavailable next-PHP-target checks explicitly rather than using platform emulation as execution evidence.
- [x] Make optional Runwire 3.0 verification reproducible on PHP 8.4 and 8.5 using Composer, rather than relying only on the original audit's temporary source-autoloader experiment.
- [x] Preserve the existing 300-second worker soak, 10-second warm-up, one-second sampling and 16 MiB RSS-growth ceiling; run repeated config/replay/request lifecycle work with Runwire 3.0 and correct host cleanup. Do not weaken the ceiling to pass a test.
- [x] Use **5.3.1** as the before/after performance baseline for this patch. The representative harness now uses the immediately previous 5.3.1 tag and includes snapshot behavior with result assertions. Check actual comparable CI evidence on the final candidate; the baseline change alone does not certify performance. Validate equivalent correct outputs before comparing throughput.
- [ ] Measure changed hot paths, config cold/warm/generated reads and snapshot cost, bounded failures, array/reference/one-shot replay, and bound/unbound Runwire pipelines. Include input-size scaling and a host-owned yielding workload so fairness/cancellation are measured, not merely synchronous materialization.
- [ ] Keep component evidence labelled component evidence. For an application-RPM claim, use an ArrayKit-owned minimal request/task consumer with warmed OPcache and optimized production loading, sustained synchronized measurement windows, several concurrency levels, at least three trials, validated successful RPM, p50/p95/p99, errors/timeouts, CPU and peak/steady RSS. Exclude setup/warm-up/cleanup from the steady-state denominator. Record exact source IDs, PHP/extensions/INI, OS/hardware, datasets, variance, queue/task state and runner stability.
- [x] Preserve the configured 2% throughput budget wherever a controlled matching stable environment is available. Predeclare workload latency/memory limits from baseline/host capacity. On a noisy/shared runner, report the comparator as skipped/unverified; never switch the stable flag on just to obtain a green badge. A host harness owned by ArrayKit can qualify library impact without making Foundation or other runtimes part of this task.
- [x] Keep user docs focused on behavior and migration. Put transient verification logs/results in CI or review artifacts, not in release documentation. Record limitations in the release decision without claiming unavailable external certification.
- [ ] Confirm every required workflow job against the final committed candidate; a green baseline or a newer Dependabot-only run is insufficient. Create/tag/publish the patch only as a separate authorized release action.

## Optional follow-ups, not patch requirements

- Unguarded cyclic/reference-containing membership/fingerprint inputs remain outside the current documented contract. An isolated cyclic `diff()` probe succeeded below the lookup threshold and exhausted a 32 MiB process at the optimized threshold. This is not counted among R01–R06 because the contract excludes that input; consider deterministic rejection only as a separately scoped hardening change with compatibility/performance tests.
- No compatible removal of the benchmark tool's abandoned development dependency was established here; track that upstream without removing benchmarks or weakening the audit.
- `ArraySingle::mode()` currently coerces canonical numeric-string values into integer keys through `array_count_values()`; for example `['1', '1']` yields the integer `1` rather than the original string. Decide whether the public mode contract should distinguish or preserve numeric strings before changing the semantics. This is a follow-up contract decision, not a silent 5.3.2 behavior change.
- Immutable cache generations have no built-in old-generation reclamation. The current documentation assigns retirement to deployment tooling after pinned workers have stopped; ensure the deployment process has a bounded retention/cleanup policy. Do not delete a generation while another worker may still have it pinned.
- Broad class/file-count reduction, multibyte text matching, new async APIs and new host-driver adapters have no demonstrated requirement in this audit and should not be added to the patch.

Temporary local audit artifacts use `/tmp/arraykit-audit-*`; they are supporting logs/probes, not committed acceptance tests. The implementation must turn the required reproductions into repository tests before closing R01–R11.
