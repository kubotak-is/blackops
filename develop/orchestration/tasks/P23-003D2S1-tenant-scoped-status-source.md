# P23-003D2S1: Tenant-scoped Status Source

Status: Authority Green — clean checkpoint/preflight required before Product dispatch; consult Report/STATE for progress.

## Goal

Deliver a latent tenant-scoped Status-source path before the D2S audit adapter.
Preserve existing public API and legacy unscoped runtime behavior. All new
subject/detail SQL must bind exact Operation ID and nullable tenant identity;
no foreign data is decrypted or decoded. The user approved this separate
prerequisite with continuation after the D2S scope-expansion question.

## Authority Preparation

Specification108 and D157 will fix the source API, SQL, snapshot, failure and
test contract. The earlier spec107/D156 three-type adapter proposal remains
incomplete and is not implementation authority. No worker may implement this
Task until its full packet is independently reviewed and the Orchestrator
explicitly dispatches the configured Luna worker.

Orchestrator may prepare this Task/Report, spec108/D157, STATE, spec/README,
TODO and update only the workflow headers in the existing D2S Task/Report,
spec107/D156. Production scope and exact commands will be sealed below before
dispatch. No push, runtime activation, public release or deployment.

## Next Action

The corrected exact11 authority packet is Green in all three independent lanes.
Create the reviewed authority checkpoint, run clean implementation preflight,
then explicitly dispatch the configured Luna worker. Product Acceptance remains
separate and requires every implementation/review/Final gate below.

## Source of Truth and Model

Read AGENTS -> STATE -> this Task -> spec/README -> spec108 -> spec99/105/106
relevant Status/tenant/release boundaries -> D157/D154/D155 and the prerequisite
D2 Report. Spec108 is the most-specific source contract. Profile files remain
authoritative: worker gpt-5.6-luna/max, General/Transaction gpt-5.6-sol/max,
Documentation gpt-5.6-sol/xhigh. Explicit rejected/unavailable/fallback profile
is a blocker; metadata absence alone is not.

## Product Prerequisites

- D2 final SHA `0cd699e5e1d031228bc4a05767164a8eb268a004` is an ancestor of
  the Orchestrator-supplied authority checkpoint; branch remains
  `agent/p23-001-blackops-1-3-feasibility`.
- General, Transaction and Documentation authority reviews separately Green,
  and the Orchestrator explicitly dispatches implementation.
- The eleven-path management preparation is reviewed before any local
  authority checkpoint commit. No unreviewed management bytes enter that commit.
- Worker starts from the supplied clean HEAD/staged0, exact individual hashes
  for existing Product paths, and the ordered authority hash inventory below.
  Hashes are supplied out of band to avoid self-reference. Drift stops work.
- Stable1.2.0; unreleased1.3; Framework13/Community5 migrations; StoragePurpose9;
  required package inventory85 before this Task.

## Files Allowed — Product/Test Exact10

```text
src/Internal/Status/TenantScopedOperationStatusSource.php
src/Internal/Status/TenantBoundOperationStatusSource.php
src/Internal/Status/PostgreSqlOperationStatusSource.php
src/Transport/PostgreSql/PostgreSqlStatusReader.php
tests/Internal/Status/TenantBoundOperationStatusSourceTest.php
tests/Transport/PostgreSql/PostgreSqlTenantStatusReaderTest.php
tests/Transport/PostgreSql/PostgreSqlTenantStatusQueryIntegrationTest.php
tests/Architecture/TenantScopedStatusArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Added6/modified4. Worker management paths are exactly:

```text
develop/orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md
develop/STATE.md
```

Worker scope exact12. No new helper file, test fixture file, migration,
Dependency/config/baseline/threshold change or public source is allowed.
Helper test classes belong in the permitted new test files. If scope is
insufficient, return a bounded blocker; do not edit another path.

## Immutable Authority Inventory

```text
develop/orchestration/tasks/P23-003D2S1-tenant-scoped-status-source.md
develop/spec/108-tenant-scoped-status-source.md
develop/decisions/157-tenant-scoped-status-source.md
develop/spec/99-tenant-isolation-and-protected-operation-data.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/decisions/155-operation-read-audit-session-foundation.md
develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md
```

All other existing paths are immutable during worker implementation as well.
In particular keep Audit Product/Test, existing Status tests, public
Status/HTTP, Default Query, Journal/Outcome Stores, schemas, and composers
unchanged. Reuse their APIs instead of repairing them in this Task.

## Implementation Requirements

Follow spec108's exact ports, marker, scoped SQL and snapshot protocol. Unit
tests must preserve original results/exceptions through the bound view. Real
PostgreSQL tests must observe query parameters, not only inspect source text.
No plain `?TenantRef=null` sentinel for unscoped private calls; use the explicit
closed marker. Thread scope through deferredOutcome and its hidden Outcome
read. No mutable per-query state or duplicated lifecycle algorithm.

SQL and decode fixtures must cover all7 source-read paths: subject, deferred
state, Journal, purge evidence, Outcome existence, dead-letter existence,
Outcome fetch. Tests distinguish scoped-null, named scope and legacy-unscoped.
No own-scope result may include a foreign-row decode or foreign existence probe.

For package85->87, preserve old85 order; insert the two new type paths after
UnavailableOperationDiagnosticsAuthorizer in interface/view order. Existing31
negative controls remain; add two individual new-path omissions, reverse-new-
block and duplicate-new-entry negatives, making35 total. Independently verify
the two inventories before updating the exact export-script SHA pin.

## Iteration Gate

Run from the Repository root and record literal commands, exits and counters:

```bash
git diff --check
docker compose run --rm app mago format --check src tests
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Status tests/Status tests/Http/Status tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php tests/Transport/PostgreSql/PostgreSqlTenantStatusReaderTest.php tests/Transport/PostgreSql/PostgreSqlTenantStatusQueryIntegrationTest.php tests/Architecture/TenantScopedStatusArchitectureTest.php
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/version-baseline.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash -n tests/Consumer/framework-package-export.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Before/after: exact12 scope with added6/modified6 (including Report/STATE),
staged0, unchanged HEAD/branch, immutable authority and non-scope bytes, exact
public-source no-diff, package87/35, migrations13+5, StoragePurpose9 and zero
Production view/entry activation. A management-only checkpoint does not
invalidate unchanged Product tests; changed Product/Test bytes invalidate prior
formal evidence and restart the Gate after correction.

## Complete Final Gate

After fresh General/Transaction/Documentation implementation reviews separately
Green, freeze unchanged Product/Test and repeat every Iteration command from
the beginning, plus all following commands before final preservation checks:

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app php vendor/bin/phpunit --display-warnings
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs
bash tests/Consumer/community-board-clean-install.sh
```

Validate each command path before dispatch; missing command is an authority
error, not permission to silently omit a gate. No package export while dirty.
Record non-failing PHPUnit notices/deprecations without claiming zero issues.
No browser/generated documentation/CI proof can be inferred from these tests.

## Acceptance

- All scoped reads enforce Operation+tenant predicates including explicit-null.
- Full snapshot/race/before-decode and result/error/legacy matrix Green.
- Exact source/API/mutation/no-activation/package87/35 evidence Green.
- Iteration, three independent reviews and Complete Final Green on unchanged
  Product bytes; Report/STATE accurate and scope/authority preserved.
- Orchestrator accepts this prerequisite alone, not D2S or parent D.

Worker does not stage/commit/push. After Acceptance the Orchestrator may commit
only the reviewed candidate, confirm clean same-SHA and run
`bash tests/Consumer/framework-package-export.sh`. Record Git/Composer inventory
and autoload proof; no push/deploy/release. Completion-only bookkeeping may
be folded into its own unpublished commit followed by final amended-SHA export;
never describe pre-amend proof as final-SHA evidence.

Remaining after Acceptance: revise the D2S adapter authority around this source,
then adapter implementation/gates/Acceptance, other reader children and parent
D integration. D-control remains dependent on Accepted F.
