# ArrayKit 5.3.0 implementation and release plan

Date: 2026-10-04 (Asia/Dhaka)

Status: review complete; scope consolidated for a single 5.3.0 release; implementation and release acceptance remain open.

Reviewed revision: `fdeff013d2892383761aa8ddf2515acfc429d7fe`.
Published baseline: **5.2.0**, source revision `053440b61071a17332b18879b12026f54a0ad144`.
The reviewed source, tests, benchmarks and Composer manifest are unchanged from that tag; the subsequent change removed the root CaptainHook configuration.

This plan follows the installed [PHPForge engineering principles](vendor/infocyph/phpforge/resources/engineering-principles.md) and [agent workflow](vendor/infocyph/phpforge/resources/AGENTS.md). It distinguishes defect corrections from additive enhancements, preserves public contracts, assigns changes to existing owners, and requires correctness, compatibility and representative performance evidence before tagging.

## Decision

Target **5.3.0 directly**, as requested. Deliver the **12 confirmed fixes**, including five P1 findings, the scoped improvements below, and additive Runwire 2.1.1 instance integration through one implementation sequence and one release candidate. There is no intermediate patch release or separate later Runwire release track.

Runwire integration is part of the 5.3.0 delivery scope. Its use remains optional for consumers: passed instances enable relevant capabilities, and unbound consumers retain the normal path. Performance and lifecycle evidence are release gates for the integration, not a reason to silently drop it from the plan.

Keep PHP 8.4 support. Do not change existing loose/strict comparison defaults, list-versus-map merge semantics, helper opt-in behavior, or established mutation signatures as incidental cleanup. If a proposed fix actually removes a supported contract, revise the version and migration decision before implementing it.

The highest-priority security-related finding is ineffective resource limiting in APIs explicitly advertised for untrusted/deep data. Exploitability depends on a host passing attacker-controlled data or paths into those APIs. This review did not establish a standalone remote-code-execution exploit or authentication bypass. PHP config files and generated PHP caches are executable, trusted deployment inputs; corruption fallback is not a sandbox.

## Consolidated 5.3.0 scope

| Workstream | Release commitment |
| --- | --- |
| Correctness and security boundaries | Resolve R01-R12 and cover native APIs, facades and collection/pipeline wrappers. |
| Configuration lifecycle | Coherent lazy/layered mutations, snapshots, memo invalidation, authoritative rebuilds and validated generated artifacts, with cache migration guidance. |
| Lazy collections | Preserve terminal source failures, measure and reduce avoidable replay allocations, and add explicit passed-instance Runwire binding with bounded cancellation/yield checkpoints. |
| DTO graph handling | Add compatible bounded graph entry points with cycle/depth/node handling; retain ordinary DTO contracts. |
| Ownership and structural improvements | Consolidate the reported repeated logic in its existing owners and document request/worker/cache/stream ownership. |
| Dependencies and tooling | Keep Runwire optional at runtime, verify its exact supported version in integration tests, assess development dependency hygiene, and retain all PHPForge detectors. |
| Release evidence | One final 5.3.0 SHA, complete compatibility/consumer/runtime checks, measured performance and soak evidence, CI, release notes and acceptance decision. |

Speculative APIs, unrelated rewrites and unsupported asynchronous filesystem claims remain outside this scope. The development-only abandoned dependency remains a recorded upstream maintenance item when no compatible replacement is available; it does not become a new blocker contrary to the established PHPForge audit policy.

## Verified evidence

| Check | Result and boundary |
| --- | --- |
| Review coverage | All 35 production PHP files; array/set/query operations, dot paths, collections/pipeline/lazy iteration, config/layering/caches, dotenv/environment, DTOs, hooks, facade/helpers; relevant tests, benchmarks, Composer and CI configuration. Graphify used for navigation; findings checked in source and runtime probes. |
| PHPForge doctor/config resolution | Healthy; bundled tool configurations active. Cognitive budgets: class 80, function 12, dependency tree 120; PHPStan level max. |
| Local detailed quality suite | `composer ic:tests:details` passed on PHP 8.5.4: 298 tests, 6,710 assertions; syntax, references, duplicate/comment checks, formatting, architecture, PHPStan, Psalm security analysis and Rector dry run passed. |
| Final local full suite | `composer ic:tests` passed on the same source revision. |
| Manifest and constraints | `composer validate --strict`, `composer ic:release:constraints`, and `composer ic:skipper` passed. |
| Dependency audit | Live `composer audit --locked --format=json`: zero advisories; one abandoned development dependency, `doctrine/annotations`, required by PHPBench 1.7.0. Raw Composer exits 1 for abandonment. `composer ic:release:audit` passes and reports abandonment as non-blocking. There are no third-party runtime package dependencies to audit under `--no-dev`. |
| Local benchmark smoke | `composer ic:bench:quick` passed: 99 subjects, zero failures/errors, zero performance assertions. PHP 8.5.4, Xdebug absent, CLI OPcache disabled. This is execution evidence, not production throughput or a regression comparison. |
| Exact revision CI | [Run 37180322867](https://github.com/infocyph/ArrayKit/actions/runs/37180322867) succeeded for the reviewed SHA: PHP 8.4/8.5 analysis, prefer-stable/prefer-lowest QA, benchmarks, clean install and security report. Benchmark-result validation and the regression comparison were skipped because representative result/baseline inputs were empty. |
| Release metadata | Live [ArrayKit package metadata](https://repo.packagist.org/p2/infocyph/arraykit.json) confirms 5.2.0. [Runwire metadata](https://repo.packagist.org/p2/infocyph/runwire.json) confirms 2.1.1 at `745b1c2bd7caa56aa5abf6d742c1aa8318fec494`; the matching local tag was inspected. |
| Independent probes | Reproductions below found failures outside the baseline suite. An additional 228 scalar/array membership cases across optimization thresholds matched native `in_array()`; native loose object-to-number comparisons produced expected PHP notices. |

Local PHP 8.4 and the next PHP upgrade target were not available for new probes. Existing CI covers baseline 8.4/8.5 behavior, not the new repro cases or future fixes. No representative host RPM comparison, persistent-worker soak, or downstream consumer acceptance was performed. No source fixes, dependency changes, release or tag were made during this review.

## Required findings

Priority P1 means correct before the next release; P2 means a confirmed defect with a narrower trigger, also included in the 5.3.0 scope. These are engineering priorities, not CVSS ratings.

### R01 — P1: traversal limits do not bound the advertised work

Owners: `src/Array/ArrayMulti.php`, `src/Array/Concerns/ArrayMultiQuerySortTrait.php`, `src/Array/DotNotationPathOps.php`, `src/Array/Concerns/DotNotationPublicApiTrait.php`.

- `flattenGuarded(range(1, 10000), maxNodes: 2, throwOnTooDeep: true)` returns all 10,000 values. `depthGuarded()` and `sortRecursiveGuarded()` similarly count recursive array calls rather than every inspected element; flat arrays escape the budget.
- `getSafe(['rows' => range(1, 10000)], 'rows.*', maxNodes: 2, throwOnTooDeep: true)` returns 10,000 values. Terminal wildcard fan-out consumes no additional node budget.
- Non-throwing `rows.*.id` traversal with a budget of 3 still returns 10,000 entries and invokes the default closure 9,999 times after exhaustion.
- A 12-level singleton array traversed through wildcard-only segments does not throw with `maxDepth: 2`; wildcard recursion does not advance/check depth consistently.

Fix the actual traversal owners. Define one documented node/depth accounting policy, count examined children and wildcard fan-out, and stop work globally when the budget is exhausted. Bound sort/comparison work and default-callback execution as well as recursion. Apply the budget consistently to multiple requested paths; document whether it is shared per call. Retain established non-throwing depth behavior where possible, with explicit bounded results on exhaustion. Do not silently disable guards for a fast path.

Acceptance: flat/wide/deep and cyclic reference arrays; terminal/repeated wildcards; multiple keys; exact budget boundaries; zero/negative limit contract; throw/non-throw modes; default-call counts; guarded-versus-normal equivalence below the limit. Demonstrate that processed elements and output allocations stop growing with input width after the budget is exhausted.

### R02 — P1: layered config inherits incompatible state operations

Owners: `src/Config/LayeredLazyFileConfig.php` and `src/Config/Concerns/BaseConfigTrait.php`.

- `set('app.name', 'caller')` before the first read is overwritten by source materialization; the read returns `source`.
- A cold `get()` returns `[]`, while `all()` returns the configured namespaces.
- Snapshot before first read, read, restore, then read returns the default: namespace-materialization flags no longer match restored items.
- Cold `exportCache()` returns success and writes an empty configuration even when known namespaces exist.

Give `LayeredLazyFileConfig` explicit ownership of materialization plus mutations and snapshots. Materialize affected namespaces before partial writes, preserve runtime writes at their intended precedence, and define complete replacement/reload semantics. Keep materialization flags, known namespaces and snapshot state consistent. Full retrieval/export must materialize the known namespace set, or explicitly reject unsupported operations before changing state. Preserve fallback < source < constructor overrides and atomic list replacement. Review inherited fill/forget/merge/overlay/loadArray/loadFile/reload/replace/set(null)/append/prepend/hooks/readonly behavior, not only `set()`.

Acceptance: every inherited operation before and after first read; fallback/source/override overlaps; nulls and lists; unknown namespaces; snapshot/restore/replacement; cold/warm exports; readonly mode; direct and intermediary consumer use.

### R03 — P1: facade calls lose reference mutations

Owners: `src/Facade/ModuleProxy.php`, `src/ArrayKit.php`, `docs/facade.rst`.

`ArrayKit::dot()->set($data, 'app.name', 'new')` returns success while `$data` remains unchanged. `ArrayKit::helper()->forget($data, 'remove')` also leaves the caller's array unchanged. Magic `__call()` receives a value argument array and cannot recover the original caller references. The facade documentation promises preservation of the native module API.

Add explicit reference-preserving mutator entry points at the existing facade boundary, with target-appropriate signatures and dispatch. Cover dot set/fill/forget/rename/move/offsetSet/offsetUnset and helper forget. Preserve existing proxy return types and cached-proxy identity. Avoid a new generic reflection layer on each call or a success response for an unsupported mutation. Native static methods remain the correctness reference.

Acceptance: caller array actually changes; return values match native calls; named arguments, overwrite/fill flags, literal/escaped paths and nested paths; invalid/missing/private methods still fail normally.

### R04 — P1: memoization can return stale mutable or cache-derived values

Owners: `src/Config/Concerns/BaseConfigTrait.php`, `src/Config/Concerns/LazyFileConfigCacheTrait.php`, `src/Config/LazyFileConfig.php`.

Two confirmed triggers:

1. Read `app.name` through an object stored in `Config`, mutate that object externally, then read again: the enabled-by-default memo returns the old scalar.
2. Resolve a leaf through `__flat.php`, then call `namespaceCache(null)`: the next read still returns the old cache leaf and the source namespace remains unloaded. Changing the cache source resets the flat index but not the resolved-value memo.

Memoize only values whose immutability can be established under the actual input contract. Avoid caching paths through externally mutable objects/references; do not prohibit supported mixed values merely to simplify the cache. Clear relevant memo entries when changing cache sources, invalidating/rebuilding flat artifacts, or materializing a previously flat-only namespace. Define which loaded runtime values remain authoritative so cache invalidation cannot discard intentional caller writes.

Acceptance: object mutation and PHP array references; read-cache enabled/disabled equivalence; cache directory A -> B -> null; selective/full flush; namespace structural reads after scalar reads; cached null and misses; loaded caller mutations remain intact.

### R05 — P2: the valid `__flat` namespace collides with an internal artifact

Owners: `src/Config/LazyFileConfig.php`, `src/Config/Concerns/LazyFileConfigCacheTrait.php`.

`__flat` passes namespace validation. Warming it writes its namespace data to `__flat.php`, then overwrites that file with the generated flat index. A fresh `get('__flat.name')` returns the default rather than the source value.

Move the internal flat artifact to a filename/layout outside the accepted namespace-file space. Keep valid caller namespaces usable. Treat generated cache layout as versioned/disposable metadata; document rebuilding old artifacts during upgrade and test legacy-reader behavior deliberately. Do not simply narrow the accepted namespace regex and call it compatibility-preserving.

Acceptance: `__flat` source namespace; all accepted filename forms; alternate extensions; selective/full cache flush; unrelated directory entries; cold/warm structural and exact reads.

### R06 — P2: cache warm-up can perpetuate stale generated configuration

Owner: `src/Config/Concerns/LazyFileConfigCacheTrait.php`; documentation: `docs/lazy-config.rst`.

Warm a source containing `Environment::ref()`, change the process value, create a new `LazyFileConfig`, and warm again: the new warmer reads the previous generated namespace and republishes the old value. Documentation says rerunning warm-up can update changed environment values.

Define an authoritative rebuild path that reads the source, resolves current environment references and generates consistent artifacts without first importing stale generated values. Preserve the documented ability to intentionally cache caller-provided in-memory overrides. Make that precedence explicit. Clear all affected memos. Use atomic publication and a deployment-owned immutable directory/generation for multi-file rebuilds; writer locking alone does not make unlocked readers see a coherent generation.

Acceptance: changed environment/source on a fresh warmer; same-instance behavior; in-memory overrides; cache-only deployments; failed writes leave the last valid generation usable; concurrent publishers/readers; OPcache/restart policy. Update docs to recommend build/deployment warm-up rather than normal request-time regeneration.

### R07 — P1: replayable lazy sources swallow failure on subsequent traversals

Owner: `src/Collection/LazyCollection.php`.

A one-shot generator yields one item then throws `RuntimeException`. First `all()` throws; second `all()` silently succeeds with only the cached first item. The source failure is lost after the generator closes, allowing incomplete data to be presented as a successful result. This matters for failed database cursors and host cancellation as well as ordinary generator errors.

Persist terminal source failure at its stream position and rethrow it on every traversal that reaches that boundary. Replaying a successfully consumed prefix remains valid. Handle failure during initialization, `valid()`, `current()` and `next()` consistently; release the exhausted source when practical. Preserve lazy consumption and interleaved-cursor behavior.

Acceptance: failure before first yield and after several yields; repeated full and prefix reads; interleaved cursors; consumer callback errors versus source errors; cancellation exceptions; factory-backed renewable sources retain their independent semantics.

### R08 — P2: compiled config export can report success for unusable PHP

Owner: `src/Config/Concerns/BaseConfigTrait.php`.

Exporting config with an anonymous object returns true but the generated cache raises `ParseError` when included. Named objects without a usable `__set_state()` can likewise produce non-loadable values; resources are not safely round-tripped by generic `var_export()`.

Define and enforce the cacheable value contract before publication: scalar/null/arrays, recursively resolved closures/EnvReference, and explicitly supported exportable values such as enums where verified. Reject unsupported objects/resources and cycles with a clear exception, or deliberately support a safe reconstruction contract. Keep an existing valid cache intact on rejection. Check full file-write completion and generated syntax without executing arbitrary source as a validation shortcut. Do not add request-time lint processes; generation is a build/admin operation.

Acceptance: valid scalar/null/enum and nested data; anonymous/named objects; resources; cyclic arrays/closures; failure preserves old artifact; warm whole-config and namespace exports round-trip identically.

### R09 — P2: row membership optimization changes strict resource equality

Owner: `src/Array/Concerns/ArrayMultiQuerySortTrait.php`; reusable engine: `src/Array/ArrayValueSetOps.php`.

For two distinct closed resources, native `in_array(..., true)` returns false. With a 256-entry value set containing the first resource, `whereIn()` incorrectly accepts the second, `whereNotIn()` removes it, and `firstWhereIn()` reports a match. The row lookup path handles NaN but does not share the set engine's closed-resource fallback; both resources receive the same non-identity fingerprint.

Reuse the existing equality owner's eligibility/fallback logic rather than extending an independent partial checklist. Keep exact PHP comparison semantics on both sides of the optimization threshold.

Acceptance: threshold-minus-one/threshold/threshold-plus-one; distinct and identical live/closed resources; resources nested in arrays; NaN; object identity; null/false/zero; direct row helpers and Pipeline/Collection composition.

### R10 — P2: wildcard presence treats an existing empty array as missing

Owners: `src/Array/Concerns/DotNotationPublicApiTrait.php`, `src/Array/DotNotationPathOps.php`.

`DotNotation::matches(['rows' => [['value' => []]]], 'rows.*.value')` returns false despite the value existing. The result checker recursively inspects value arrays as though they were only wildcard result containers, losing the distinction between an existing empty-array leaf and no match.

Resolve wildcard presence through path traversal with an explicit missing marker, distinguishing result structure from leaf values. Preserve any-match semantics and null presence. Do not replace presence checks with truthiness.

Acceptance: empty array/null/false/zero/empty-string leaves, missing leaves, empty parent lists, multiple wildcards, escaped selectors and literal wildcard keys.

### R11 — P2: SQL-like exact patterns accept a trailing newline

Owner: `src/Array/Concerns/ArrayMultiQuerySortTrait.php::whereLike()`.

`whereLike([['name' => "admin\n"]], 'name', 'admin')` returns the row. PCRE `$` accepts the position before a final newline, violating whole-value literal matching. The `%` and `_` conversions also need an explicit newline/byte/Unicode contract.

Use true whole-string anchoring, document the intended wildcard character semantics, and test them. Keep `preg_quote()` protection. Exercise adversarial wildcard patterns and handle PCRE failure distinctly from an ordinary no-match; choose a bounded matcher if measurements show pathological backtracking. Do not silently broaden matching or alter case defaults.

Acceptance: exact patterns with terminal/internal newlines; `%`/`_`; empty strings; regex metacharacters; case modes; configured PCRE limit exhaustion; wrapper equivalence.

### R12 — P2: valid large pagination inputs overflow before slicing

Owner: `src/Array/ArraySingle.php::paginate()`; wrapper: `src/Collection/Pipeline.php`.

`paginate([1, 2], PHP_INT_MAX, 2)` raises `TypeError` because `(page - 1) * perPage` becomes a float. Both inputs satisfy the published positive-integer preconditions.

Check whether the page is beyond the array's possible range before multiplying, using overflow-safe integer arithmetic. Return an empty page for a valid out-of-range request. Keep existing invalid-argument exceptions and key preservation.

Acceptance: empty arrays, first/last/out-of-range pages, `PHP_INT_MAX` boundaries, large per-page values, key preservation and Pipeline delegation.

## 5.3.0 improvements and engineering debt

- **I01 — Replay memory:** `LazyCollection::from()` memoizes all consumed entries, including array-backed sources; retained one-shot streams can grow without bound. `fromFactory()` already provides a renewable path without that replay memo. Measure array-backed versus generator/factory traversal and specialize array input when this removes allocations without changing replay, keys, laziness or failure semantics. Document bounded consumption and lifetime expectations. A new non-replayable API requires demonstrated need beyond the existing factory API; do not silently discard one-shot replay semantics.
- **I02 — DTO graph boundaries:** deep export and nested hydration have no cycle/depth/node budget. Cyclic DTO graphs can exhaust resources. Add bounded export/hydration entry points for arbitrary graphs, with explicit limits, cycle handling and clear failure behavior; keep ordinary DTO APIs compatible. Test self-cycles, mutual cycles, shared acyclic objects, deep/wide arrays, inherited properties and readonly behavior. Avoid reflection caches holding instances or request state.
- **I03 — Duplicate groups:** PHPProbe reports three passing clone groups (139 lines; 1.19%): contains-all/contains-any setup in `ArrayValueSetOps`, strict unique/derived-row loops, and config append/prepend setup. Inspect each group's shared responsibility and centralize repeated logic in its existing owner. Preserve distinct thresholds, short-circuit behavior and array-versus-row semantics; do not merge unrelated code merely to lower a metric. Verify every affected caller and throughput.
- **I04 — Tool dependency hygiene:** assess a compatible PHPForge/PHPBench update that removes abandoned `doctrine/annotations` when an upstream replacement is available. It is development-only, has no replacement declared, and is not a published advisory. Record the outcome in the 5.3.0 evidence; an unavailable upstream replacement remains a maintenance item under the existing non-blocking policy. Do not remove benchmark coverage, edit vendor or weaken audit policy to silence it.
- **I05 — Trust and lifecycle docs:** explicitly document trusted PHP source/cache directories, deployment-owned writes, secret-bearing artifacts, request-scoped mutable config/hooks, shallow collection copies, retained replay caches, Runwire binding lifetimes, and cleanup/replacement in persistent workers. Cache fallback cannot make an untrusted PHP file safe to include. Update examples and migration guidance alongside the corresponding implementation.

## Runwire 2.1.1 integration for 5.3.0

Exact upstream sources: [RuntimeContext](https://github.com/infocyph/Runwire/blob/2.1.1/src/RuntimeContext.php), [RequestContext](https://github.com/infocyph/Runwire/blob/2.1.1/src/RequestContext.php), [CoroutineScope](https://github.com/infocyph/Runwire/blob/2.1.1/src/Coroutine/CoroutineScope.php), [CoroutineRuntime](https://github.com/infocyph/Runwire/blob/2.1.1/src/Coroutine/CoroutineRuntime.php).

`RuntimeContext` is metadata/capabilities, not an event loop or asynchronous filesystem service. `RequestContext` supplies cancellation/deadlines and single-use lifecycle state. `CoroutineScope` supplies the active scheduler scope, task cancellation and `yieldNow()`.

| ArrayKit work | Potential benefit | Recommendation |
| --- | --- | --- |
| Long factory-backed lazy traversal | Request/task cancellation and bounded cooperative yielding improve fairness and stop obsolete work. | Implement consumer-opt-in instance integration and verify under representative concurrent host load. |
| Short config reads and array helpers | Capability inspection/checkpoint overhead can exceed the work. | Preserve the direct common path. |
| Dotenv parsing, config `include`, filesystem cache writes | Context flags do not make these native operations non-blocking. | Perform during bootstrap/build/admin stages; no asynchronous-I/O claim. |
| Cache warm-up | Cancellation between namespaces may help administrative tasks. | Optional; do not yield while holding the exclusive filesystem lock or expose partial generations. |
| Parallel array callbacks | Would change order, callback side effects, ownership and failure behavior. | No automatic worker/task spawning. |

The audit prototype used the exact 2.1.1 source with existing `LazyCollection::fromFactory()`. The host created and drove `CoroutineRuntime::runRequest()`; an intermediary forwarded the same context/request/scope instances into the factory. It produced `[2,4,6,8,10]`, let a peer task progress at checkpoints, propagated cancellation, rejected completed requests, and produced ordinary results without binding/coroutine capability. This proves API feasibility only; no speedup, full runtime-driver compatibility or production safety certification is claimed.

### Required integration contract

1. Accept the host's passed `RuntimeContext`, optional active `RequestContext`, and optional active `CoroutineScope` on the relevant operation/instance. A scope may be used for background tasks without a request context. The framework or an intermediate library forwards the same concrete instances; ArrayKit does not reconstruct them or discover a global runtime.
2. Favor an immutable operation/stream wrapper or explicit per-operation parameters. Do not attach a request token or scope to a shared cached facade proxy or worker-global config instance. A new operation must not inherit an earlier request's binding accidentally.
3. Honor both request and task cancellation/deadlines; reject a completed request or incompatible runtime binding. Missing capability is a normal fallback; cancellation, expired deadlines and invalid lifecycle state are terminal conditions, not reasons to restart through the normal path.
4. Enable cooperative yielding only when a passed active scope exists and the runtime advertises the relevant coroutine capability. Flags alone cannot authorize scheduler calls. Make checkpoint cadence bounded and configurable once at the boundary, after measuring it.
5. Check upstream consumption, including rows rejected by filters, and check again after resumption before invoking the next callback. Preserve ordering, keys, exceptions, backpressure and `take(0)` laziness. R07 must prevent a cancelled replay stream from later appearing successfully truncated.
6. The host owns loop driving, workers, listener/process creation, scope lifetime, cancellation sources and request completion. ArrayKit must not call `Runtime::run()`, `CoroutineRuntime::run()`/`runRequest()`/`attachRequest()`, `RequestContext::complete()`, or close the passed scope. It must not create a runtime simply because Runwire is installed.
7. Use the existing synchronous path when Runwire is absent or no relevant binding/capability is passed. No extra runtime work at Composer include time. Ship direct binding with a stable optional `suggest` relationship and isolated development integration tests against **2.1.1**, with a clean production install that has no Runwire package. Runwire requires PHP 8.4+ on 64-bit PHP; keep the ordinary ArrayKit path's existing platform support.
8. Publish direct framework -> ArrayKit and framework -> intermediary -> ArrayKit examples plus cleanup/fallback semantics. Do not introduce an adapter hierarchy or a new execution-context DTO when the upstream objects already express the boundary.

Acceptance matrix: Runwire absent; installed/unbound; metadata-only; bound request without scope; task scope without request; valid concurrent scope; missing coroutine capability; host-native loop ownership; pre-cancelled/expired/cancel-during-traversal; mismatched runtime; completed request; closed scope; failing source/callback; two interleaved requests; nested intermediary forwarding; early iterator abandonment; worker replacement. Verify no leaked callbacks, request tokens, scope references or replay state. Actual host-driver tests remain necessary; the native probe does not certify Swoole/OpenSwoole/RoadRunner/FrankenPHP integration.

Implement the binding at `LazyCollection`'s existing iteration owner, with an additive immutable instance method that accepts the upstream context/request/scope objects and returns a bound collection. Forward the binding through derived lazy operations so checkpoints cover upstream consumption, including filtered-out items. Finalize method signatures and checkpoint defaults against the existing generics and measured workloads before freezing the 5.3.0 API. Keep static array helpers and cached facade proxies free of request bindings.

Compare the direct binding against the existing factory-composition prototype for correctness, consumer complexity, fairness, cancellation and overhead. If a candidate design fails a gate, revise it within the 5.3.0 scope; do not split the release or claim acceptance from prototype feasibility alone.

## Implementation sequence

| Batch | Scope | Completion evidence |
| --- | --- | --- |
| A | Add failing regression cases for R01/R02/R03/R07, then fix those existing owners. | Guard budgets actually stop work; config state is coherent; reference calls mutate caller data; stream errors remain errors. |
| B | R04/R05/R06/R08 cache/memo/export corrections; update config and deployment docs together. | Cache transitions/refresh/round-trip correctness, valid artifact publication and concurrency/failure tests. |
| C | R09/R10/R11/R12 semantic boundary corrections and I03 duplicate consolidation. | Native comparison equivalence, presence semantics, whole-string matching and overflow-safe pagination; all wrappers covered. |
| D | I01 replay-memory measurement/improvement, I02 bounded DTO graph entry points, I04 dependency assessment, and I05 ownership/migration docs. | Compatible APIs, bounded graph behavior, measured allocation evidence, all relevant call sites and explicit maintenance outcomes. |
| E | Additive Runwire instance binding and propagation through lazy operations; direct/intermediary consumers and fallback coverage. | The full instance-forwarding/lifecycle matrix passes; bound/unbound performance and host ownership are verified. |
| F | Integrated quality/compatibility/consumer/performance/soak acceptance, then the single 5.3.0 release candidate. | Every applicable release gate below passes on the same final committed SHA. |

Do not run source-mutating tooling during this review-only stage. During implementation use PHPForge's routine flow: doctor/config checks; focused failing tests; smallest owner changes; sequential `composer ic:process`; detailed suite; final `composer ic:tests` or `composer ic:release:guard`. Review automatic changes and keep vendor untouched. Never raise thresholds, suppress findings, expand baselines, remove assertions, or skip required detectors to obtain a pass.

## Release acceptance gates

- [ ] R01-R12 regressions fail on 5.2.0 and pass on the candidate; original valid-input contracts and existing tests remain intact.
- [ ] I01-I05 have implementation/measurement/documentation evidence or the explicitly allowed upstream maintenance outcome; bounded DTO APIs and replay behavior are verified without weakening existing contracts.
- [ ] Full PHPForge flow passes with current rules and configured scopes; audit warning is recorded with its development-only origin.
- [ ] PHP 8.4 and 8.5 stable/lowest dependency CI and clean `--no-dev` installation pass on the final committed revision. Run compatibility/deprecation checks against the next intended PHP target and identify unavailable target evidence explicitly.
- [ ] Generated config fixtures are validated before activation; old-cache rebuild instructions and OPcache/worker restart behavior are verified. No secret values appear in failures, logs or benchmark results.
- [ ] Direct consumer smoke plus a representative intermediary consumer (for example Foundation's layered-config usage) pass for cold reads, runtime writes, snapshot/restore, cache refresh and persistent execution. Do not claim a consumer test from source inspection alone.
- [ ] Capture a baseline and candidate on the same stable production-equivalent runner: PHP/extensions, no-dev optimized Composer mode, enabled production OPcache, OS/hardware, datasets, traffic mix, concurrency and source SHAs recorded.
- [ ] Use at least three warmed steady-state trials at multiple concurrency levels; measure cold startup separately. Cover repeated config reads, first namespace materialization, generated exact/structural reads, array/set/query operations at threshold boundaries, bounded adversarial traversal, and lazy streaming within a representative host request/task.
- [ ] Compare median **validated successful RPM** with a maximum 2% regression budget; reject invalid/partial/error responses from the numerator. Record p50/p95/p99, error/timeout rates, peak/steady memory, CPU, queue growth and relevant cache/lifecycle metadata. Predeclare workload-specific latency/memory limits from baseline and host capacity. Changes serving different correctness contracts require valid-output baselines, not timing of the existing broken behavior.
- [ ] Persistent-worker soak has bounded memory, state reset and no cross-request/tenant data leakage; include cache-miss/failure and worker replacement. Keep mutable config/hooks and replay streams within their intended lifetime.
- [ ] Runwire instance binding and derived-operation propagation pass the complete acceptance matrix, including absence, unavailable capabilities and direct/intermediary forwarding; the host retains ownership of workers, event loops and scope completion.
- [ ] Separately report ordinary unbound regression and bound fairness/cancellation results, including a consumer that forwards instances through another library. Do not extrapolate microbenchmarks into host RPM.
- [ ] Configure PHPForge's existing representative benchmark result/baseline inputs and validate/compare their machine-readable contracts; do not invent a parallel workflow or treat the current skipped comparisons as passed.
- [ ] Record the final **5.3.0** candidate SHA and successful CI URL; review consolidated release notes and migration guidance covering fixes, additive APIs, Runwire optional usage and generated-cache rebuilds. Tagging and publishing remain separate actions after acceptance.

## Reproduction index

Temporary PHP probes and an extracted copy of the exact Runwire 2.1.1 source were used, then removed after review. The triggers above and examples below preserve the reproductions. Raw local check logs remain at `/tmp/arraykit-quality-review.log`, `/tmp/arraykit-final-quality-review.log`, and `/tmp/arraykit-benchmark-review.log`; these are temporary evidence, not release artifacts.

Example: traversal, facade, lazy failure and presence.

```php
<?php
require 'vendor/autoload.php';

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Collection\LazyCollection;

// R01: 5.2.0 returns 10000 despite the throwing budget of 2.
var_dump(count(ArrayMulti::flattenGuarded(range(1, 10000), maxNodes: 2, throwOnTooDeep: true)));
var_dump(count(DotNotation::getSafe(['rows' => range(1, 10000)], 'rows.*', maxNodes: 2, throwOnTooDeep: true)));

// R03: 5.2.0 leaves the original value unchanged.
$data = ['app' => ['name' => 'old']];
ArrayKit::dot()->set($data, 'app.name', 'new');
var_dump($data);

// R07: 5.2.0 throws on the first traversal, returns [1] on the second.
$stream = LazyCollection::from((function () {
    yield 1;
    throw new RuntimeException('source failure');
})());
foreach ([1, 2] as $attempt) {
    try {
        var_dump($stream->all());
    } catch (RuntimeException $error) {
        echo $error->getMessage(), PHP_EOL;
    }
}

// R10: 5.2.0 returns false for a present empty array.
var_dump(DotNotation::matches(['rows' => [['value' => []]]], 'rows.*.value'));
```

Example: layered mutation and snapshot state. Supply a temporary source directory containing `app.php` that returns `['name' => 'source']`.

```php
<?php
use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;

$config = new LayeredLazyFileConfig($directory, namespaces: ['app']);
$config->set('app.name', 'caller');
var_dump($config->get('app.name')); // 5.2.0: 'source'

$config = new LayeredLazyFileConfig($directory, namespaces: ['app']);
$config->snapshot();
$config->get('app.name');
$config->restore();
var_dump($config->get('app.name', 'missing')); // 5.2.0: 'missing'
```
