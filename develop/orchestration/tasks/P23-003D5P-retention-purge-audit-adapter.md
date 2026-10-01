# P23-003D5P: Retention Purge Audit Adapter

Status: Accepted (local) — frozen full and independent source/evidence Green

## Goal and authority

Define and implement the most-specific retention purge adapter under
specifications38/39/105/106/109/117 and D154/D166. This child follows Accepted
D5H but does not reuse or change its Product types. It consumes Accepted C/D1
and adds an explicit D4B dependency for C3's Connection identity predicate.
Root freezes source identity, actual native root units and failure ordering before
Product work. Authority preparation is complete only after independent Green;
that milestone is not implementation Acceptance.

Baseline: `/tmp/blackops-d5p-20260926/baseline.json`,3,269 Git-visible files/193
dirty entries, SHA256
`93a4bb245f34978c3068a8532c33f3ef09c4ff8f8f6acfefa60e2c2441d37fef`.
HEAD `8106c3556248dca1f9719b9d55cf1f647255a422`. D5H's final9 candidate,
1,693 runtime and2,196 broader input hashes match the live tree. Its3,551-case
full JUnit and final guard supplement are retained as predecessor evidence.
Preserve prior work. No stage/commit/push/tag, remote CI/export or deployment.

## Scope and ownership

During preparation Root owns only spec117/D166, this Packet/Report, STATE/archive,
spec index, D154 dependency/status pointer and TODO. Read-only native feasibility
survey and independent Astra/max authority review may run in parallel with
authoring; no PHP/Docker/DB, Product/Test edit or generation occurs in preparation.

After authority Green and Root's implementation-start checkpoint, use two bounded
Luna/max lanes. Root owns the consolidated Report, all serial PHP/Docker/DB/cache
commands, formatting, integration and Acceptance. No worker edits STATE or commits.
The native fixture has exclusive schema `blackops_retention_purge_audit` and must
restore faults/connections. Maximum four agents including Root.

Writer/architecture lane owns exactly:

```text
src/Internal/Audit/Retention/RetentionPurgeAuditWriter.php
tests/Internal/Audit/Retention/RetentionPurgeAuditWriterTest.php
tests/Architecture/RetentionPurgeAuditArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Architecture/RetentionHoldAuditArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Native adapter lane owns exactly:

```text
src/Internal/Retention/AuditedRetentionPurgeAuditPort.php
src/Internal/Retention/AuditedRetentionPurgeService.php
tests/Transport/PostgreSql/PostgreSqlAuditedRetentionPurgeServiceTest.php
```

Existing native services/planner/store, Core APIs, Catalog bytes, schema/migrations,
D5H Product, composition/config/dependencies/baselines and public documentation
are outside scope. Any required expansion returns to Root before implementation.
Exact signatures and graph must be settled in spec117 before delegation.

## Acceptance

- [x] Exact Catalog/event/projection and trusted actor/execution/correlation/runtime/
  build binding. Per-effect native purge-audit ID supplies action identity; target
  plus Operation ID supplies resource identity. Fixed durable policy/evidence;
  no extra UUID allocator or inferred legacy actor/time/policy provenance.
- [x] Admission rejects mismatched Connection, active/nested and non-autocommit
  before planner SQL/native IDs/writer clock. Constructor and no-effect paths do
  no Audit work. Only the writer captures one clock per emitted event, after
  subject/reference validation. Privacy hooks and owned failure frames are safe.
- [x] Private native graph uses one exact Connection/schema, preserves five
  category-root units/order and introduces no whole-invocation transaction. Hook
  runs only at level1; only writer targets C3 and owns no database transaction.
- [x] Real PostgreSQL positive effects retain native result semantics and commit
  mutation, native purge-audit row and durable ingress together. All five targets,
  global/tenant, Inline source, multi-item and multi-category cases are covered.
  Payload tenant is hydrated from the surviving mutated Operations row; strict
  ID/full-pair validation fails closed without treating missing/corrupt as global.
- [x] Current-category failures roll back its effects and both database audit
  surfaces. Earlier committed categories survive later failures. Actual
  committed-then-throw persists exactly the completed root and fails the call;
  no false failure event, blind append/mutation retry or all-or-nothing invocation
  claim. Require real parent::commit()-then-throw on both manual dead-letter and
  DBAL transactional outcome roots, exact current/earlier per-target rows and
  untouched later categories, no later mutation/clock/ID/delivery work and no
  retry. Retain actual rollback behavior; rollback-suppressing doubles alone are
  insufficient. Initial planning SQL is not later category work. Legacy logging/
  commit uncertainty limitations are explicit.
- [x] Existing native planner/hold/cutoff behavior, canonical schema prerequisite,
  category count rules and no-operations-row-deletion behavior are preserved.
  Real probes, malformed hydration, logger/store/delivery faults and owned canaries
  make assertions nonvacuous. No authorization, stronger locking or activation.
- [x] Exact three-type non-PublicApi graph and native-construction/SQL ownership;
  method/function/static/new-call inventories with syntax-valid targeted negatives.
  A2 operational provider permits only lookup, hold writer and purge writer;
  C3/hold controls remain. Package109→112 and all60 predecessor rejected controls
  plus controls for the three new required paths are retained.
- [x] Independent authority/source/security/evidence Green; one frozen full retains
  every3,551 predecessor identity and records the exact new identity set. Final
  scope/docs/artifact/Release Authority preservation and management checks pass.

## Verification plan

Preparation uses source/contract review, exact baseline/preservation comparison,
local link/ownership checks, `git diff --check` and STATE check/write/check.
Record literal commands, input hashes, environment, result, monotonic time and
logs under `/tmp/blackops-d5p-20260926`. Do not rerun unchanged D5H Product gates.

Implementation crosses transaction/tenant/security boundaries. Root runs focused
writer/native/new-architecture and changed A2/C3/hold gates during iteration.
Freeze the reviewed final Product/Test inputs and exact collected identities before
the single full. Shared `phpunit.xml` memory is512M without command overrides.

```bash
docker compose run --rm --no-deps -T app mago format --check src tests
docker compose run --rm --no-deps -T app mago lint
docker compose run --rm --no-deps -T app mago lint --verify-baseline
docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d5p-full-junit.xml
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Reuse unchanged successful inputs; no repeated full/subset/static gate solely for
handoff or status edits. A bounded test-only correction after full needs explicit
dependency review, immutable original evidence and affected focused validation.
Predecessor issue aggregates1 deprecation/2 PHPUnit deprecations/13 notices are
not warning-free evidence; compare actual results rather than assuming equality.

## Release/documentation and next action

Framework/Skeleton/site remain Experimental Stable1.2.1;1.3.0 is unreleased.
Proposed internal capability `audit.retention_purge_delivery.v1`. No public source,
route, command, config or active composition change. Preserve193 public source/
asset files,421 generated site/Search/LLM artifacts, six historical references and
Release Authority. Spec117 carries internal architecture authority; public
guide/internal documentation waits ordinary integration. No build/browser/live or
same-SHA delivery claim. Local documentation user review remains a release gate.

Concurrent-work verification amendment: after the initial preparation preservation
check passed, unowned LandingReel/index edits, reel assets and authoring files,
and generated site output changes appeared in this shared workspace. D5P owns none of
those documentation files and does not revert them. Retain the original193-source/
421-artifact maps, the failed later comparisons and exact external deltas in the
Report; do not claim the whole public tree or broader repository map stayed
unchanged. Acceptance must instead prove the exact D5P Product/Test/runtime graph,
all other original source inputs and Release Authority, and independently review
the dependency disposition of the enumerated concurrent paths. This amendment
changes the evidence boundary only; it neither adds public-documentation scope
nor weakens any Product gate.

残り工程: D5P内はなし。上位Goalは
remaining D5 rotation/replay/generic Outbox, ordinary composition, E/F/G,
D-control after Accepted F, hardening, documentation/local user review and release.
Next Action: Root preserves the uncommitted Accepted candidate and completes the
separately authorized P23-018 delivery. Further D5 children use separate Packets;
ordinary composition, integration/hardening and release remain pending.
