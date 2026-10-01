# P23-003D2X: Operation Diagnostics Read Audit Adapter

Status: Accepted — local candidate; runtime activation and delivery remain parent work

## Goal

Continue the authorized 1.3 work after locally Accepted D2J. Add a latent
Diagnostics read Audit adapter that commits requested before authorization or
Diagnostics Store access, and one terminal before releasing a safe aggregate
or failure. Preserve the existing trusted local/global Diagnostics surface and
its result/type/code contract. Parent runtime activation and release are later.

## Scope and Authority

Follow AGENTS/current profiles and spec109. Read spec65's Internal Query Boundary,
Availability and Visibility, Safe Diagnostics Projection and local surface gates;
spec99's Diagnostics protection boundary; spec106's ordinary D split; D154/D155's
read session and Diagnostics authorization contract. D2J is a reviewed pattern,
not a requirement to copy its tenant-scoped data semantics or retained exceptions.
The most-specific contract is spec112 with rationale D161; it explicitly
specializes the Diagnostics exception identity rule in D155.

Root owns this Task/Report, the new most-specific spec112 and rationale D161,
the precise D155 child cross-reference, spec index, current TODO pointer and
STATE/archive. The source survey is complete. The exact Product/Test scope and
verification contract below require independent authority review before dispatch.

Preserve all prior dirty work, Accepted D2J Product, shared PHPUnit512M and the
current release tuple. The exact scope extends the shared D2 architecture
allowlist and package inventory for new Diagnostics types. No new public API,
tenant-scoped Diagnostics SQL, migration, dependency, route, runtime registration,
commit/push/tag, CI or deployment is selected.

## Team and Verification Ownership

Root owns authority, integration, Report/STATE, Git and all PHP/DB/cache verification.
The read-only Astra/max explorer mapped current Diagnostics source seams and
inherited exception privacy. Independent Astra/max authority and security review
precedes Luna/max implementation and reviews the candidate before acceptance.

Luna/max Runtime owns the two new source files and two new unit files. Luna/max
Architecture owns the new architecture file, the exact shared D2 allowance and
two package scripts; only that worker may run the assigned shell checks. Luna/max
PostgreSQL owns the one new integration file. Workers return chat handoffs and
hashes; none edits shared Report/STATE. Root serializes all PHP and PostgreSQL
commands. No worker starts Docker, DB, Composer, cache, website or other Consumers.

## Exact Product and Test Scope

```text
src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php
src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php
tests/Internal/Diagnostics/AuditedOperationDiagnosticsQueryTest.php
tests/Internal/Diagnostics/OperationDiagnosticsReadExecutionSourceTest.php
tests/Architecture/AuditedOperationDiagnosticsArchitectureTest.php
tests/Architecture/AuditOperationReadArchitectureTest.php
tests/Transport/PostgreSql/PostgreSqlAuditedOperationDiagnosticsQueryTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Exactly9 paths. No existing Production file changes. The shared D2 test and
package scripts extend only the two new types' exact edges/inventory/controls;
all D2J Product/unit/new-oracle bytes remain exact. A needed wider change is
reported to Root before editing. No worker commits or stages before acceptance.

## Required Fixtures

- Constructor no I/O; repeated requests and clone carry no prior caller/result.
  Default/Deny/ResourceUnavailable and non-null currentTenant release the same
  Unavailable. Non-null tenant does zero authorizer and reader work. Null tenant
  can read a tenant-owned Operation only after the explicitly supplied trusted
  global policy returns Allow; this is not a tenant-less-only target filter.
- Requested commits before authorization and all Diagnostics reader access.
  Allow invokes the existing concrete aggregate once, preserving Inline/Deferred
  safe Found, fully consumed Journal, missing/fully-purged Unavailable, partial
  retention and dangling-Dead-Letter IntegrityFailed semantics.
- Authorizer throws (including a Diagnostics exception) and invalid return type
  select StorageFailed with no reader access. All three existing query codes
  become fresh matching exceptions only after unavailable. Unexpected query
  throwable/code corruption is StorageFailed; unexpected result is IntegrityFailed.
- Entry, post-begin, post-authorizer, post-query and post-terminal idle checks.
  Invalid post-authorizer state does zero query work. Begin failure does zero
  authorization/query/terminal. Terminal failure wins over Found, Unavailable
  and every code. No retry/alternate terminal/transaction control or repair;
  commit-then-throw leaves only the actually committed Audit prefix.
- Real two-Connection PostgreSQL fixtures use the required encrypted Audit Store
  and native global Diagnostics sources/Journal/Outcome. Verify committed
  requested visibility inside authorizer/readers and terminal visibility before
  release; exercise global/null target and tenant-owned target, denied/default,
  privacy projection, missing/purged, storage/decode/integrity and terminal failure.
  The file owns isolated schema names and cleans only its own schemas.
- Privacy canaries in actual legacy readerFailure chained errors and raw Journal/
  projector frames, with zend.exception_ignore_args=0 restored afterward. Verify
  fresh exception identity and fixed type/code/message/code0/null previous across
  trace/debug/serialize/JSON/var_export. Actual retained reader/writer/generator
  canaries, corrupt/uninitialized snapshots, serialize-to-unserialize and clone are
  meaningful; zero-I/O assertions use the same retained dependencies.
- Fixed Diagnostics purpose and operation_diagnostics_read server UUIDv7, one
  generation per call, independently owned nonce/record identities, invalid UUID
  and throwing generator. No raw original exception or data enters Audit evidence.
- Scoped AST/name-resolution positive/negative controls: fixed scope/purpose,
  pre-read policy, idle-before-query, typed enum-only failure retention, fresh
  exception construction after terminal, no direct writer/session-open/retry,
  snapshot/parameter protection and no activation. Preserve prior D2 controls.
- Package97 ordered unique entries, retaining prior45 and adding3 controls (48).

## Verification Plan

Every Root run records literal argv, source/test/config/lock hashes, image/runtime,
start/elapsed/exit/counts and separate logs under /tmp/blackops-d2x-20260915.
Current shared phpunit.xml supplies512M. No memory override or baseline relaxation.

After worker freeze, Root may run focused iteration for feedback:

```bash
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Internal/Diagnostics/AuditedOperationDiagnosticsQueryTest.php tests/Internal/Diagnostics/OperationDiagnosticsReadExecutionSourceTest.php tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php tests/Internal/Diagnostics/OperationDiagnosticsAuthorizationTest.php
docker compose run --rm --no-deps -T app php vendor/bin/phpunit tests/Architecture/AuditedOperationDiagnosticsArchitectureTest.php tests/Architecture/AuditOperationReadArchitectureTest.php --filter '(AuditedOperationDiagnosticsArchitectureTest::|AuditOperationReadArchitectureTest::testTokenAwareDependencyAndCallGraphOracleIsExact$)'
docker compose run --rm -T app php vendor/bin/phpunit tests/Transport/PostgreSql/PostgreSqlAuditedOperationDiagnosticsQueryTest.php
```

The shared D2 graph check is selected without its heavy mutation method during
iteration; the complete existing mutation oracle runs once in the final full suite.
Only failed/changed or insufficiently evidenced portions repeat after corrections.

Architecture worker runs only:

```bash
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

This change crosses authorization, protected data and transaction boundaries.
Root Final on the reviewed frozen candidate is:

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d2x-full-junit.xml
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Full PHPUnit subsumes final unit/integration/architecture/regression checks;
do not repeat the same-input subsets at Final. Reuse successful quality commands
on unchanged analysis inputs and unchanged D2 Catalog/Store/Composer/schema/website
evidence. Required package controls verify the edited scripts; this Task does not
claim an uncommitted clean-SHA Git/Composer export. Later selected delivery retains
same-SHA CI/export/public-proof gates. Root checks exact scope, unchanged public/
runtime/SQL/release inputs, management links and final candidate/JUnit binding.

## Preparation Evidence

/tmp/blackops-d2x-20260915/baseline.json captures 3,182 initial tracked/untracked
paths, HEAD8106c3556248dca1f9719b9d55cf1f647255a422 and the unchanged Accepted
D2J19-path candidate. Git status, exact release authority and prior STATE are
captured beside it. No new Product verification is claimed during preparation.

## Acceptance Criteria

- [x] Most-specific authority fixes trusted global scope, pre-read authorization,
  execution identity, safe exception reconstruction and terminal precedence.
- [x] Bounded implementation and required behavior/negative controls pass.
- [x] Root and independent affected reviews pass on the frozen candidate.
- [x] Report/STATE preserve the local delivery boundary and remaining 1.3 work.

## Release Documentation Impact

Framework/Skeleton remain Experimental Stable1.2.1; roadmap1.3.0 is unreleased.
The exact authority tuple is captured in release-authority-before.json.
Proposed Internal capability: audit.operation_diagnostics_read_adapter.v1.
No public source/route/configuration/version change is selected. Existing public
Source/Search/LLM artifacts and historical allowlist remain unchanged. Public
documentation or runtime composition changes belong to the later activation;
spec112 will describe the implementer contract. No production delivery is implied.

## Completion

Report: develop/orchestration/reports/P23-003D2X-operation-diagnostics-audit-adapter.md.
Remaining parent work includes Auth/Authorization/operational adapters and
composition, authoring, signatures/checkpoints, query/export/retention, hardening,
documentation and release. Accepted website delivery remains a separate step.
