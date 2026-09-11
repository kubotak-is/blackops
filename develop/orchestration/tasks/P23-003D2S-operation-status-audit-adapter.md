# P23-003D2S: Operation Status Audit Adapter

Status: Implementation Accepted; required local verification Green; final-SHA export pending.

## Goal and Authorization

Continue the user's authorized 1.3 development after the P23-013 Stable1.2.1
sync. Implement and accept only the latent Status Audit adapter under revised
spec107/D156. Preserve current public API and runtime activation; the latter
belongs to P23-003D-integration. No D2J/D2X, D3/D4/D5, D-control or release work.

The former research-only checkpoint is replaced by this reviewed scope once
authority reviews are Green. Root may make local authority/accepted-candidate
commits needed for clean final-SHA export. Push/tag/deploy are not selected.

## Prerequisites and Authority

- D2 Accepted source: `0cd699e5e1d031228bc4a05767164a8eb268a004`.
- D2S1 Accepted source/export: `70f9c4733bed8bb0960165cddf8c451b78b96184`.
- P23-013 sync must be Accepted before Product dispatch. Root records the clean
  resulting HEAD and immutable input manifest in this Report before dispatch.
- AGENTS and current .codex profiles govern Astra/max orchestration/review and
  Luna/max Production implementation. Spec109 governs selected verification.
- Most-specific spec107/D156; Accepted source spec108/D157; D2 D155; parent
  spec106/D154; SQL isolation spec99/D147. Read only relevant sections.

Root reconciles the D155 trusted execution-source enumeration and spec108's
previous zero-bound-view-caller checkpoint with the exact exceptions in spec107.
Those links do not change the Accepted D2/D2S1 behavior or historical evidence.

## Team and Ownership

Root owns integration, Git, Task/Report, STATE/archive, authority, TODO/index,
shared PostgreSQL/Consumers and final verification. Three Luna/max workers split
the exact Product/Test scope below: Runtime worker owns the four Status source
files and three new Internal/Status unit files (7 paths).
Architecture worker owns the three architecture files and two package scripts
(5 paths). PostgreSQL fixture worker owns only the new PostgreSQL test (1 path).
No overlapping file ownership or shared Report writes. Worker returns chat handoff and command
receipts under `/tmp/blackops-d2s-20260911/worker`; no Git or STATE edits.
Root performs General Review; independent Transaction and Documentation lanes
review authority and resulting implementation. Reviewers are read-only.

Runtime worker initially owned the listed no-DB PHP iteration commands. Its
first attempt failed at Docker socket access before PHP started, so Root now
owns these commands and returns failures to the Runtime worker for scoped fixes.
Architecture worker may edit independently and run the two shell commands,
then coordinates with Root before any PHP format/test. Root serializes every
PHP process/cache user after this execution handoff.
Root alone owns DB tests, architecture execution, goldens and shared consumers.
The PostgreSQL worker writes its fixture independently, runs no PHP/DB process
and hands it back for Root execution. The former Runtime8 ownership was split
into Runtime7 and PostgreSQL1 during implementation; no Product scope or
Acceptance coverage changed, and no PG file existed at the transfer checkpoint.
Architecture work can prepare declarations/mutations from spec107 while source
is written, but final oracle matching waits for the Runtime handoff. Frozen
source/test changes invalidate only affected evidence; unchanged checks are
not duplicated. Team assignment changes verification ownership, not coverage.

## Exact Product/Test Scope

```text
src/Internal/Status/DefaultOperationStatusQuery.php
src/Internal/Status/AuditedOperationStatusQuery.php
src/Internal/Status/OperationStatusAuditTenantAuthorizer.php
src/Internal/Status/OperationStatusReadExecutionSource.php
tests/Internal/Status/AuditedOperationStatusQueryTest.php
tests/Internal/Status/OperationStatusAuditTenantAuthorizerTest.php
tests/Internal/Status/OperationStatusReadExecutionSourceTest.php
tests/Transport/PostgreSql/PostgreSqlAuditedOperationStatusQueryTest.php
tests/Architecture/AuditedOperationStatusArchitectureTest.php
tests/Architecture/AuditOperationReadArchitectureTest.php
tests/Architecture/TenantScopedStatusArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

No other Product/Test file, helper, fixture, dependency, baseline, schema,
migration, registry, public API, HTTP handler or Application composer change.
Existing scoped-source implementations and all D2 objects stay exact.
Default Query permits only the14 SensitiveParameter additions specified in
spec107, plus necessary formatting. No method/property/body/signature shape
change; Root verifies this exact annotation-only delta against the freeze.
If a missing seam or oracle forces broader scope, report it before editing.
The two existing architecture oracles admit only the exact adapter edges and
retain every earlier positive/negative fixture. Use existing AST/name-resolution
patterns, executable callsite checks and memory-bounded fixture maps; never
substitute text-search-only security proofs or broad directory exclusions.

Root-owned management scope: this Task/Report, STATE/archive, spec107/108,
D155/D156/D157, spec README and the current TODO pointer. Most-specific future
caller/identity authority is referenced once; do not duplicate progress into
historical specifications or rewrite completed source evidence.

Acceptance management reconciliation also permits a current-handoff link
correction in D154 and a short navigation notice in the P23-001 parent
Task/Report. Root owns these administrative deltas. Their previous contracts,
acceptance receipts and historical bodies remain exact; no Product/review gate
changes. This makes spec106's exact5 entrypoints resolve to current STATE/Report
without duplicating the adapter's progress across historical documents.

## Required Behavior and Fixtures

Follow spec107's exact constructor, per-read tenant binding, execution source,
transaction state, result/terminal and sensitive-state contracts. At minimum:

- Both query entrypoints: one requested, one bound view/Default Query and one
  terminal; requested visible before any source/authorization; terminal visible
  before release. Begin failure does zero reader and terminal work.
- Found/Expired/accessed; Unavailable and all four contracted query codes use
  unavailable. A terminal failure overrides pending result/failure with safe
  Storage and never dispatches an alternative terminal. Commit-then-throw and
  poststate failures withhold everything without foreign transaction repair.
- Null/global, same/different tenant type/id and alternating tenants on one
  query. Missing/foreign/denied rows and foreign ciphertext cause zero decode;
  a permissive authorizer cannot override mismatch. Guard-only mismatch test
  uses a deliberate foreign subject from a scoped-interface fixture.
- Actual PostgreSQL second-Connection visibility and mutation barriers:
  subject move/disappearance -> unavailable; same-scope authorization-subject
  identity drift -> Integrity; source snapshot ends before terminal. Reuse
  unchanged D2S1 same-snapshot race evidence with input hashes; do not duplicate
  its entire source test matrix in the new adapter suite.
- Per-read UUIDv7 generate-once, reused-source independent reads, invalid or
  throwing generator, nonce/Clock/Store UUID separation and zero secret leaks
  on ordinary debug/exception/serialization surfaces. Constructors do no I/O.
  Enable and restore `zend.exception_ignore_args=0` for four-code trace canaries
  through Default and the adapter. Prove exactly14 Default parameter attributes
  without changing behavior or relying on global trace-argument suppression.
- Actual architecture oracle mutations for unscoped/null fallback, scope reuse,
  missing/misordered tenant guard, dropped type/id comparison, duplicate begin/
  terminal, terminal retry/fallback, early release, altered exception identity,
  extra caller, direct writer/session-open, fake execution identity and mutable
  retained state. Include inert comments/strings and legitimate legacy positives.
  Unknown-result and adapter catch-all branches, unreachable through the sealed
  Default, use actual AST/mutation evidence. No mock delegate seam is introduced.
- Package 90 unique required paths, preserving old87 order; retain old35
  controls plus omission of each added path and reversed block (39 controls).
  Framework migrations13, Community migrations5 and StoragePurpose9 unchanged.

## Verification Plan

This changes tenant/security and transaction composition. Use related tests for
iteration, then a single full PHPUnit pass on the reviewed frozen candidate.
That full run covers the same-input Status/HTTP/D2/D2S1/architecture subsets;
do not repeat the iteration set at Final. All commands run from Repository Root.
Record exact argv, timestamps, exit/counts, seconds, image/runtime, relevant
source/test/config/lock hashes and logs; distinguish environment failures from
failed assertions. Preserve failures when retrying changed input.

Runtime slice iteration (no DB; Root executes after the environment handoff):

```bash
docker compose run --rm --no-deps -T app mago format --check src/Internal/Status/DefaultOperationStatusQuery.php src/Internal/Status/AuditedOperationStatusQuery.php src/Internal/Status/OperationStatusAuditTenantAuthorizer.php src/Internal/Status/OperationStatusReadExecutionSource.php tests/Internal/Status/AuditedOperationStatusQueryTest.php tests/Internal/Status/OperationStatusAuditTenantAuthorizerTest.php tests/Internal/Status/OperationStatusReadExecutionSourceTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/Status/AuditedOperationStatusQueryTest.php tests/Internal/Status/OperationStatusAuditTenantAuthorizerTest.php tests/Internal/Status/OperationStatusReadExecutionSourceTest.php
```

Architecture worker (shell only; Root schedules PHP after source handoff):

```bash
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

Root iteration runs new actual PostgreSQL and changed architecture suites once
for review feedback; only rerun affected failures or changed inputs. Final is
the union of still-valid iteration evidence and the following required checks,
not a second copy of those checks:

```bash
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm app composer validate --strict
docker compose run --rm app php vendor/bin/phpunit --display-warnings
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/community-board-clean-install.sh
```

Also run AGENTS' PHP management-ID scan, unchanged-source/package/migration/
nonactivation checks, global `mago format --check src tests` after both worker slices settle, diff and STATE check. PHP memory remains the configured
128MiB; no limit increase or baseline relaxation to disguise a test issue.
Public source/build/browser evidence from P23-013 is reused only for unchanged
public inputs; no new docs build/CI/deployment is inferred from this Task.

After three implementation review lanes and required checks pass, Root accepts
the candidate, commits only reviewed files, confirms clean HEAD and runs once:

```bash
bash tests/Consumer/framework-package-export.sh
```

Bind both Git/Composer export to that final SHA. Receipt-only status edits do
not require another Product test run, but a new candidate commit needs its own
required SHA-specific export. Do not create a receipt-hash recursion.

## Bounded Full-suite Memory Recovery

The first Final full PHPUnit run stopped at128MiB after approximately1286
of3289 tests, while the unchanged Audit ingress UTF-8 cap fixture encoded
MAX_BYTES+1. This is failed Final evidence, not Acceptance. The same fixture
passes alone at30MiB and after full discovery at82.5MiB; the failure depends
on cumulative suite memory. Independent read-only inspection found no retained
AST/token cache in the new adapter architecture tests.

Root General and independent Transaction review approved this amendment
with P1/P2/P3=0 before recovery dispatch. It adds exactly one existing Test-only path:

```text
tests/Internal/Audit/Protection/AuditIngressEnvelopeCodecTest.php
```

The assigned Luna worker may add the existing Repository's PHPUnit
RunInSeparateProcess / PreserveGlobalState(false) attributes to only
testIngressWireUsesInclusiveUtf8ByteCap, their imports and a short rationale.
No fixture, assertion, helper, test selection, Product code, dependency,
baseline, PHP memory limit or other test process model changes are permitted.
The original13 Product/Test paths and their reviewed contracts remain frozen.
This is a14th Test-only recovery path, not broader adapter scope. Root retains
all PHP/cache/DB and management ownership. No worker PHP execution.

Root verifies the exact attribute-only diff, unchanged method/helper bodies
and child PHP memory_limit=128M inheritance, then runs all ingress-codec tests
with full-suite discovery, including the affected case. One combined run
provides both checks. Review/evidence closure is limited
to this delta; unchanged Product/Transaction/Documentation reviews and static/
golden/package/version/public checks remain reusable. Global PHP format and
management-ID/diff checks cover the recovery path. Repeat full PHPUnit once
after this concrete failure and correction; no assertion or control is waived.
Community Board can run serially under Root while the worker edits this test,
because its Product/config/lock inputs remain unchanged. Final clean-SHA
Git/Composer export remains required after all checks and Acceptance.

### Second Recovery Amendment

The second full run passed the isolated ingress case, then stopped at128MiB
in testOversizedRecordBodyFailsBeforeActiveKeyLookup. A diagnostic with120MiB
of retained parent heap reproduced that failure and also identified
testOutboxBopdPlaintextCeilingIsInclusiveAndPreKey. The remaining eight relevant
large-fixture probes passed under that synthetic budget. These are diagnostic
results, not a replacement for the original full-suite Acceptance command.

Root General and independent Transaction approved this amendment with
P1/P2/P3=0 before dispatch. The same Luna Runtime worker may change one
additional existing Test-only path:

```text
tests/Internal/Audit/Protection/AuditProtectedBodyCodecTest.php
```

Only the two named methods may receive RunInSeparateProcess and
PreserveGlobalState(false), their imports and a short memory-isolation rationale.
Keep every method/helper body, fixture, assertion and original14 paths exact.
Total Product/Test scope becomes15 paths. No Product/config/dependency/baseline
or memory-limit change. Root owns all PHP/cache/DB and management execution.

Root reviews the exact attribute-only delta, then runs both recovery classes
with full-suite discovery and a logging-only bootstrap to prove unchanged128M
parent/child limits. Recheck the two reproduced synthetic failures after
isolation. Global format and management-ID/diff checks cover the new path.
After those results and delta review are Green, retry the original full PHPUnit
command. All unchanged successful static, golden, inventory, version, Community
and public evidence remains reusable; clean final-SHA export is still required.

## Release Documentation Impact

Authority currentStable Framework/Skeleton1.2.1, unchanged exact refs in
`develop/spec/release-authority.json`; capability
`audit.operation_status_adapter.v1` is Internal/unreleased1.3. Public source,
route, API, command and configuration inventories are empty. No old-version
occurrence changes or historical allowlist change. Spec107 is the implementer
contract; guide/internal runnable composition documentation belongs to the
later activation Task. No source/search/LLM publication, CI or deployment claim.

## Acceptance Criteria

- [x] Revised authority has separate General/Transaction/Documentation Green.
- [x] Exact per-read source/guard/UUID and result/transaction contracts pass.
- [x] All required real-PG and adversarial oracle controls pass without
  weakening D2/D2S1 evidence or changing runtime activation.
- [x] Required verification union and three implementation review lanes Green.
- [ ] Package90/39 and clean final-SHA Git/Composer export Green.
- [x] Report/STATE distinguish adapter completion from parent D/1.3 release.

## Remaining Work and Next Action

Implementation and the required local verification union are Accepted. Root
commits the reviewed candidate and confirms clean final-SHA Git/Composer export.
The user's additional tenant documentation request becomes a separate packet
next; D2J then continues the reader work. Other reader/operational adapters,
parent composition, signatures, query/export, runtime/docs and1.3 release
qualification remain separately scoped. No1.3 publication is selected.
