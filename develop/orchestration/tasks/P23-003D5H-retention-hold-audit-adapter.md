# P23-003D5H: Retention Hold Audit Adapter

Status: Accepted (local) — all eight criteria Green; BlackOps 1.3 unreleased

## Goal and authority

Implement spec116/D165 after independently Green authority. Read AGENTS/STATE,
current profiles, spec109, spec116 and D154 sections1/4/7; consult spec38/39 for
existing public semantics. D5H depends on Accepted C/D1 and the accepted D4B C3
identity helper. Freeze the exact trusted binding, root and failure contract before
Luna Product work. Root reviews/accepts; workers do not commit or change STATE.

Baseline: /tmp/blackops-d5h-20260921/baseline.json,3,253 Git-visible files/165 dirty
entries; SHA256be71b81b8631c9c31db8b5ff6784e0f90135df2bbb00b64182ad6de0a952d675.
HEAD8106c3556248dca1f9719b9d55cf1f647255a422; preserve earlier work. No commit,
stage/push/tag, CI/export or deployment in this Task.

Resumption: the original temporary evidence directory is absent. Current evidence
uses /tmp/blackops-d5h-20260924 with a fresh preservation baseline of3,265
Git-visible files/189 dirty entries at the same HEAD. Its hash and the recovered
predecessor JUnit are recorded in the Report; absent historical logs are not reused.

## Ownership and exact scope

Root owns spec116/D165, Packet/Report, STATE/archive, spec index, D154 pointer/DAG
note and TODO. Root serially owns all PHP/Docker/DB/cache and formatting commands.
Independent Astra/max reviewer is read-only. Maximum4 active agents including Root.

Luna writer/architecture lane owns exactly seven files:

```text
src/Internal/Audit/Retention/RetentionHoldAuditWriter.php
tests/Internal/Audit/Retention/RetentionHoldAuditWriterTest.php
tests/Architecture/RetentionHoldAuditArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Luna adapter/native lane owns exactly two files:

```text
src/Internal/Retention/AuditedRetentionHoldPort.php
tests/Transport/PostgreSql/PostgreSqlAuditedRetentionHoldPortTest.php
```

The writer is final and receives, in order: concrete HmacAuditOpaqueIdDeriver,
AuditDeliverySelector, AuditDeliveryCoordinator, ClockInterface, ActorContext,
AuditRecordCorrelation, AuditSourceRuntime, build ID. Its public methods are
`assertConnection(Connection): void`, `placed(RetentionHoldId, OperationId,
?TenantRef): void`, `released(...) : void`, plus safe debug/serialization hooks.
Private helper names are implementation details, frozen by final exact graph review.

The adapter is final, implements RetentionHoldPort and receives Connection,
RetentionHoldAuditWriter, schema='blackops', ClockInterface with the native system
clock default, and PostgreSqlRetentionHoldIdGenerator with its native default.
It privately constructs the unchanged Store/schema helper. Both lanes can work
independently after these signatures/authority are reviewed. Shared native fixture
uses dedicated `blackops_retention_hold_audit` schema and restores connections/faults.
Worker handoffs go to scoped /tmp notes/chat; Root owns the consolidated Report.

No edits to existing Production types, Core APIs, native Store, Catalog, SQL
migrations, composition, dependencies, config, baseline or public documentation.
If actual constraints require more scope, return the blocker before expanding it.

## Acceptance

- [x] Exact placed/released Catalog/projection, actor/execution/correlation and
  persisted tenant/global identity. Event/evidence/policy versions and fixed durable
  semantics; HMAC resource/action inputs and keys; no extra UUID allocator.
- [x] Construction/activeFor/native-or-subject failures do no Audit delivery or
  writer clock work. Admission failures do no SQL/native ID/native clock work;
  native mutation failures preserve the existing native clock/ID ownership. Each
  successful event captures one writer clock value; no payload/hold reason/legacy
  actor/caller timestamp leaks into persisted logical JSON or owned failure frames.
  Invalid/corrupt snapshot/reference/clock and serialization fail safely.
- [x] Identity mismatch, active/nested root and auto-commit disabled fail before
  SQL/ID/clock work. The private native Store/schema share the exact Connection;
  no C3 target in the root owner and no transaction ownership in the writer.
- [x] Real PostgreSQL place/release commit one corresponding durable row with the
  hold change. Global/tenant/Inline-Journal source and release after source deletion
  use persisted tenant. Missing/corrupt/partial/mismatched subject fails closed.
- [x] Native failure, typed-subject failure, projection/selector/append failure
  roll back the hold mutation and ingress on confirmed pre-commit paths. Actual
  committed-then-throw rows remain; response fails and no blind append/mutation retry.
  Repeated place remains a fresh native hold; repeated/missing release emits none.
- [x] activeFor and existing native public behavior remain; no false failed event,
  new authorization/recovery/locking guarantee or canonical activation. Real two-
  Connection/ambient-root and own-frame/persisted canaries make failures nonvacuous.
- [x] Exact non-PublicApi graph, constructor/SQL/source ownership and syntax-valid
  targeted negatives; A2 operational provider has only lookup+writer helper calls,
  C3 controls preserved; package107→109 with at least60 retained/new rejected controls.
- [x] Independent authority/source/security/evidence Green; one frozen full preserves
  all3,513 D4B baseline identities and records the exact newly added set.

## Verification

Root records literal argv, exact before/after inputs, environment/image, result,
monotonic time and logs under /tmp/blackops-d5h-20260921. PHP8.5.7/PHPUnit13.2.2;
shared512M phpunit.xml, no override. Select new writer/native/architecture classes
and changed A2/C3 during iteration. Architecture lane may run and record shell
syntax/inventory once, without PHP/Docker/shared caches.
Resumed commands use /tmp/blackops-d5h-20260924.

Transaction/tenant/security classification from spec109 requires one frozen full
after source review. Final gates, with unchanged successful inputs reusable:

```bash
docker compose run --rm --no-deps -T app mago format --check src tests
docker compose run --rm --no-deps -T app mago lint
docker compose run --rm --no-deps -T app mago lint --verify-baseline
docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d5h-full-junit.xml
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

No duplicate full/subset/static gates solely for handoff or management edits.
Known prior full aggregate issues1 deprecation/2 PHPUnit deprecations/13 notices
are not warning-free evidence. Later source/config/bootstrap changes invalidate
affected gates; bounded test-only corrections need explicit dependency review.

Root and the independent reviewer approved a bounded post-full correction for
D5H-E1: only RetentionHoldAuditArchitectureTest.php may add literal per-method
FuncCall/StaticCall inventories and syntax-valid free-I/O/SQL/static-helper
negatives. First preserve the original full manifest, receipt, JUnit and unchanged
before/after inputs. Then run all seven hold-architecture cases, whole PHP format,
comment-identifier and diff checks on the final guard. Record a separate final
candidate map with exactly this one file changed, the same38 additions, and every
other Product/Test/bootstrap/config/dependency input unchanged. Independent review
must close E1 before Acceptance. Within this boundary the original full plus the
focused supplement satisfy the verification plan; any wider change reopens the
affected gates. This does not claim that the original full ran the final guard bytes.

## Documentation and remaining work

Stable Framework/Skeleton/site Experimental1.2.1, roadmap1.3.0 unreleased.
Proposed internal capability audit.retention_hold_delivery.v1. Preserve193 public
sources/routes,421 generated search/site/LLM artifacts and six historical references
by hash; no public activation/API/config change is selected. Spec116 documents the
internal adapter; public docs/internal and guide updates wait canonical composition.
No new browser/build/live/same-SHA CI/export claim. Final local documentation URL
and user review remain mandatory before release.

残り工程: D5H内はなし。上位GoalにはD5 purge/rotation/replay/
generic Outbox, ordinary composition, E/F/G, D-control after Accepted F, hardening,
documentation/local review and release. Existing Inline ambiguous-commit lifecycle/
idempotency reconciliation remains a release obligation outside this Task.
Next Action: Root prepares the separate D5 purge authority/Task, then obtains
independent authority review before scoped Luna implementation.
