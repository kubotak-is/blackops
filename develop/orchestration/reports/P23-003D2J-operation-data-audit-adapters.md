# P23-003D2J: Journal and Outcome Read Audit Adapters Report

Status: Accepted — local source and 512MiB PHPUnit verification

## Summary

The Journal and Outcome adapters commit a requested Audit event before lookup
or authorization and one terminal event before releasing data or a safe query
failure. A mandatory tenant guard rejects a mismatched scope before application
authorization or protected data access. Public query contracts and current
runtime bindings are unchanged. These Internal adapters remain unactivated;
this child does not complete parent D or release 1.3.

Three disjoint Luna/max workers implemented the frozen 18-path scope. Root
performed integration, source review and all PHP/DB verification. Independent
Astra/max authority and implementation reviews are Green. Scoped tests and
quality checks pass. The initial full PHPUnit hit its 128MiB cap; the user
requested a higher limit. The shared 512MiB PHPUnit setting is implemented and
independently reviewed, with actual parent/child settings verified. The original
full command passes: 3,352 tests / 6,009,578 assertions, peak 129MiB. Root accepts
the 19-path local candidate. Parent integration and release remain separate.

## Changed Files

The amended Task contains the exact 19 Product/Test/config paths. Final hashes are machine
captured in /tmp/blackops-d2j-20260914/candidate.json, not copied from chat hashes.

| Owner | Files | Change |
| --- | ---: | --- |
| Runtime worker | 5 new source | Two audited queries, mandatory tenant authorizer, two fixed execution sources |
| Runtime worker | 5 existing source | 46 native SensitiveParameter attributes; algorithms and SQL unchanged |
| Runtime worker | 3 unit files | Query ordering, tenant guard, execution identity and privacy fixtures |
| Architecture worker | 2 architecture files | Scoped AST controls and exact existing D2 caller allowances |
| Architecture worker | 2 shell scripts | Required package inventory: 95 paths, 45 negative controls |
| PostgreSQL worker | 1 integration file | Real required Store and tenant-reader behavior from two connections |
| Runtime worker | phpunit.xml | One shared finite memory_limit=512M setting; all other configuration unchanged |

Root owns this Task/Report, spec109's memory-budget guidance, spec111, D160, the precise D155 cross-reference,
spec index, TODO and STATE/archive. Root applied the existing formatter to only
the 16 assigned PHP files after worker freeze. Nine files changed mechanically;
all resolved executable ASTs and parameter attributes remain identical.
Earlier documentation and D2S management work is preserved.

## Decisions and Assumptions

- Construct one stateless concrete Default per adapter with a mandatory tenant
  guard. Null is the global scope, not permission to read every tenant.
- Fix AuditReadPurpose to Journal/Outcome and allocate a new validated server
  UUIDv7 for each read. Application purpose remains an authorization input.
  Execution identity, request nonce and canonical record IDs keep separate owners.
- Preserve both concrete result objects and every matching resource exception
  object/code after a successful unavailable terminal. Terminal delivery failure
  takes precedence and becomes fresh storage_failed without a previous cause.
- Fully consume Journal before its terminal. No prefix or lazy result escapes.
  Never retry a terminal, control an application transaction or repair its state.
- Retain two Store-owned write roots and current auto-commit subject/data reads.
  This child adds no Status-style repeatable-read or pre-decode actor/type race
  guarantee. Same-Connection runtime composition remains a parent gate.
- Protect inherited Framework exception arguments in four readers/Defaults and
  exactly the Outcome codec's three decode parameters. Arbitrary unannotated
  application/vendor frames remain outside this Framework correction.

## Review and Required Evidence

Authority review: the most-specific spec111/D160 and Packet are Green,
P1/P2/P3=0. One initial D155 navigation P3 was resolved before dispatch.
The dispatch contract is bound in authority.json and task-authority.md.

Independent implementation Round 1 requested three P2 test corrections:

1. Include the fixture's yielded event in the normal Journal expectation.
2. Fix the new oracle's installed parser API, Namespace class lookup, mutation
   inputs/syntax, enum and comparison checks; add bounded tenant-condition and
   unreachable unexpected-result integrity-fallback controls.
3. Exercise both query snapshots and actual serialization roundtrips with real
   retained dependencies/canaries; replace a never-seeded execution-source canary.
   The correction uses a valid snapshot plus an extra key, so zero-I/O assertions
   observe the same reader/writer dependencies. Uninitialized cases assert only
   the fixed safe failure. Root also requested post-begin/post-read idle faults.

All three are closed by limited re-review and successful scoped execution.
Round 2 is Green, P1/P2/P3=0. No Product logic defect was found. Product review
is inherited across formatter-only changes using format-proof.json. Root's
review additionally covers the actual PostgreSQL two-connection visibility,
nullable/type/ID tenant matrix, deny-before-decode, alternating callers, five
resource failure codes, terminal precedence and committed-prefix behavior.

## Commands and Results

Evidence base: /tmp/blackops-d2j-20260914. baseline.json binds 3,163 starting
tracked/untracked paths and HEAD 8106c3556248dca1f9719b9d55cf1f647255a422.
Every run directory stores literal argv, input/config/lock hashes, image,
timestamp, elapsed seconds, exit and separate logs. Tests use PHP 8.5.7 /
PHPUnit 13.2.2: initial runs used 128MiB; the user-authorized retry uses 512MiB
through phpunit.xml. Mago/Deptrac are the installed repository tools; no dependency,
baseline, SQL/schema or Framework runtime configuration was changed.
Image: sha256:ab739e1d3bb8d9c7b6696401f7d4821eff72ae870db703feb537015a4fa14f5d.

| Command / evidence | Result | Command time |
| --- | --- | ---: |
| bash syntax, two assigned Consumer scripts | PASS | <1s |
| required inventory | PASS: 95 ordered/unique paths, 45 rejected controls | 2s |
| mago lint | PASS | 1.928s |
| mago lint --verify-baseline | PASS | 1.532s |
| mago analyze, error-level count | PASS | 2.049s |
| Deptrac, fail-on-uncovered | PASS: 6,723 allowed, all violation/uncovered/warning/error counts 0 | 2.290s |
| Internal/OperationData PHPUnit iteration | PASS: 22 tests / 98 assertions, 26MiB | 1.343s |
| combined new/old architecture + PostgreSQL iteration | 53 tests / 4,118,391 assertions; 2 new-oracle errors; old D2 6 and PostgreSQL 44 cases pass | 680.375s |
| corrected new architecture only | PASS: 3 tests / 387 assertions, 24MiB | 2.361s |
| corrected privacy unit pair | PASS: 13 tests / 122 assertions, 24MiB | 1.024s |
| final global mago format --check src tests | PASS | 1.956s |
| final annotation-only AST proof | PASS: five inherited files / 46 parameters | 1.042s |
| formatter before/after proof | PASS: 16 files, nine formatting changes, identical resolved ASTs/attributes | 1.407s |
| initial full PHPUnit, original bootstrap and 128MiB | OOM at existing protected-body cap fixture; empty JUnit | 960.552s |
| installed PHPUnit XML schema | PASS: exactly one finite 512M memory setting | 0.855s |
| existing protection classes, full discovery with observation bootstrap | PASS: 23 tests / 642 assertions, 76.5MiB | 3.822s |
| parent/isolated-child configuration proof | PASS: all four processes report 512M at start and shutdown | 0.897s |
| full PHPUnit retry, original bootstrap and 512MiB | PASS: 3,352 tests / 6,009,578 assertions, 129MiB | 1086.659s |
| PHP management-ID scan; git diff --check; STATE check | PASS | see closeout evidence |

Static/unit/combined: run-20260914T213516888583. Corrected oracle:
run-20260914T214935861143. Corrected privacy tests and initial format check:
run-20260914T215013861939. Scoped mechanical formatting and final format:
run-20260914T215357559236. Final proofs and full PHPUnit:
run-20260914T220610059940. Memory setting and protection proof:
run-20260914T233337452465. Full retry: run-20260914T235103710240, started at
2026-09-14T23:51:03+09:00. Shell receipts: architecture-worker/.

The full command is:

```bash
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d2j-full-junit.xml
```

It started at 2026-09-14T22:06:12+09:00 and exited 2 after 960.552s. After the
1281 progress line and 14 more cases, AuditProtectedBodyCodecTest's detached
hydration/body-cap fixture failed to allocate 1,052,672 bytes at line 132 under
134,217,728 bytes. No assertion failure preceded it. Both JUnit copies are
empty. full-liveness.json confirms the earlier CPU-bound phase was running,
not stalled; host RSS was a separate measurement from PHP memory accounting.
No earlier partial or failed run is treated as the full gate.

### User-requested Memory Budget Change

The user asked to raise the limit after this failure. Root selected a finite
512MiB PHPUnit budget in the shared root phpunit.xml, used by ordinary local
commands and both existing root CI entrypoints. No Docker rebuild or duplicated
workflow setting is needed. This explicitly supersedes the Packet's initial
128MiB condition. The original 18 adapter paths remain frozen; the only added
19th path is phpunit.xml. Spec109 records the shared setting and budget-change
procedure. No existing fixture, assertion, isolation attribute or Product logic
is changed to recover this run.

Both authority and limited implementation/evidence reviews are Green, P1/P2/P3=0.
memory-delta-proof.json confirms the XML has only the approved three-line addition
and all original 18 adapter paths remain exact. The installed PHPUnit schema
accepts the configuration. The two existing protection classes pass unchanged
under full-suite discovery: 23 tests / 642 assertions, peak 76.5MiB.

A temporary bootstrap requires the original vendor/autoload.php and logs settings
without changing INI values. memory-config-proof.json records the parent and three
existing isolated children at bootstrap/shutdown: eight observations, all 512M.
The independent reviewer verified original protection fixture hashes against
baseline, run and current bytes. memory-amendment-review.json binds this review.
The final full retry uses the original bootstrap and command and passes. No extra
process isolation was introduced. memory-entrypoints-proof.json binds both CI
entrypoints and phpunit.xml's existing export-ignore rule; this is a development
test budget, not a Framework runtime memory requirement.

### Final Local Gate and Acceptance

The second full attempt completes exit0 in 1086.659s (PHPUnit-reported
18:04.763), with 3,352 tests / 6,009,578 assertions and peak 129MiB. The old
128MiB cap was insufficient for this complete suite. The JUnit contains zero
failures, errors or skips. Diagnostic counts match accepted D2S: one deprecation,
two PHPUnit deprecations and 13 PHPUnit notices. They are not hidden or described
as zero-issue output. No third full run or duplicate final subset is required.

full-pass-summary.json validates all 3,352 JUnit cases, the previously failing
detached-hydration cap fixture and all 63 new cases: Query7, tenant guard3,
execution sources6, architecture3 and PostgreSQL44. The complete JUnit SHA256 is
fcbf1dde92824b09e9ed93e120c39c7631e94afdf1047c0532f0e2a9a6012c00.
It also binds all 19 candidate paths to the actual run inputs and current bytes,
the unchanged HEAD and original full argv. candidate-128m.json preserves the
initial failed candidate; candidate.json binds the accepted 512MiB result.

Root accepts the reviewed implementation and configuration after this successful
full gate. root-acceptance.json and final-evidence.json bind the reviews, scope,
unchanged public/unrelated inputs, management checks and final STATE. Earlier
documentation, release tuple and current runtime wiring remain unchanged.

### Reuse and Recovery

The first annotation token proof incorrectly rejected formatting trailing
commas. Its original script/output are retained in run-20260914T080742360471.
The corrected installed-parser proof strips only native SensitiveParameter and
compares before/after ASTs, including SQL literals. It passes on the final bytes.

The first strict lint baseline check found four intentional else-clause
suggestions. Existing Status-style local expectations explain the shared
terminal boundary; no baseline or control-flow change was made. The combined
iteration's two protected-traverseArray errors are the independently identified
new-oracle defect; the corrected 3-case run closes them. Initial global format
reported nine files; the scoped mechanical formatter and final check close it.

Docker socket denials occurred before PHP and required escalated execution.
Permission wait time is separate from command duration. The explorer's early
trace probe never completed: survey-probe-status.json is NotVerified, and no
successful probe claim is reused. Actual Framework trace fixtures pass with
zend.exception_ignore_args=0 and restore it afterward.

final-static-reuse-proof.json binds 1,087 analysis inputs: 1,083 byte-identical,
four changed only by the formatter with identical ASTs/attributes. Test-only
corrections do not trigger duplicate static analysis. Old D2/PostgreSQL focused
checks are not repeated during correction of the separate new oracle. Final
full PHPUnit subsumes the successful related subsets; no same-input subset is
scheduled again after it. Accepted unchanged D2 Catalog/golden/Store and
Composer/schema/dependency consumers are reused. Clean Git/Composer export is
a later selected delivery gate; the changed script has its dedicated positive/
negative inventory evidence here.

## Release Documentation Impact

Current authority remains Experimental Stable 1.2.1 for Framework/Skeleton and
unreleased 1.3.0 for the roadmap. The complete immutable tuple is unchanged in
release-authority-before.json and develop/spec/release-authority.json. Proposed
internal capability: audit.operation_data_read_adapters.v1. Public query API,
endpoint/CLI/example/configuration and runtime activation are unchanged.

All public sources and the route inventory are byte-identical to this task's
start. No old-version occurrence or classification changes; all six historical
allowlist entries remain exact. Source/Search/LLM positive/negative fixtures
and prior accepted website artifacts are unaffected; no new website build or
browser proof is claimed. Spec111 is the implementer architecture contract;
docs/internal runtime composition documentation accompanies parent activation.
No docs/guide capability promotion is appropriate for these latent adapters.

No commit, push, tag, same-SHA CI/export or Production deployment is selected
or performed by this Packet. Local source acceptance does not waive those
later parent/release delivery gates. Production delivery of the already accepted
website changes remains separate.

## Acceptance Criteria

- Most-specific authority fixes trusted execution, tenant ordering, result/
  failure mapping, transaction guards and sensitive-state behavior: satisfied.
- Bounded implementation preserves signatures and current wiring: satisfied.
- Required positive/negative, transaction, privacy and package evidence: satisfied;
  the successful full PHPUnit run covers the final reviewed test inputs.
- Root/affected specialist review and final Report/STATE: satisfied; authority,
  implementation and memory amendment reviews are Green, P1/P2/P3=0, and Root
  accepts the final local candidate.

## Remaining Issues

No remaining local work for this child. Diagnostic counts above remain at the
accepted D2S baseline. This child leaves
D2X Diagnostics, Authentication/Authorization/operational adapters and parent
composition, application Audit authoring, signing/checkpoints, query/export/
retention, hardening, documentation and release gates. Stable 1.2.1 is unchanged.

## Suggested Next Action

Prepare the reserved D2X Diagnostics Audit Task Packet. Separate selected delivery must bind
clean source, same-SHA export/CI and public proof; do not reopen accepted D2S.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D2J+D2X unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
