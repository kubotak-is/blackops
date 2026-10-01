# P23-003D4B: Authorization Audit Delivery

Status: Accepted (local) — authority/source/security/evidence Green; frozen full PASS
Accepted At: 2026-09-21T23:13:30+09:00

## Goal and authority

Implement spec115/D164 after locally Accepted D4A. Read AGENTS/current profiles,
STATE, this Packet, spec109/114/115 and D147's exact authorization catalog tuples.
Independent Astra/max authority review must be Green before Luna Product work.
Preserve the D4A four-type foundation and the145 prior dirty entries captured in
/tmp/blackops-d4b-20260916/baseline.json at HEAD
8106c3556248dca1f9719b9d55cf1f647255a422. No commit/push/tag, CI/export or deploy.

## Team and exact files

Root owns spec115/D164/Task/Report/STATE/archive, spec index, parent spec106/D154
pointers and TODO; all PHP/Docker/DB/cache and formatting execution is serial under
Root. Workers report in chat or scoped /tmp notes, never change STATE or commit.
Independent Astra/max reviewer is read-only. Canonical composers, public docs,
Catalog, SQL, config/dependencies and release authority are unchanged.

Verification metadata amendment: the native Luna owner may update only the
existing InlineDispatcher `halstead` entry's `start_line`/`end_line` in
`mago-lint-baseline.toml` to the final location of the same `dispatchEnvelope`
method:235–358 →240–374 in Mago's zero-based JSON coordinates (rendered as
241–375) on the formatted candidate. Keep all176 entries, rule
codes, files and other locations unchanged.
This is one additional permitted quality-metadata file, outside the16 Product/Test
paths below; it adds no suppressed issue or new baseline. Independent review
must confirm the diagnostic correspondence before the location edit. Do not
regenerate the baseline or add expectations that hide this existing entry.

Luna foundation lane owns exactly seven files:

```text
src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php
src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php
src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php
src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php
src/Internal/Transaction/OperationTransactionCoordinator.php
tests/Internal/Audit/Authorization/AuthorizationAuditEventFactoryTest.php
tests/Internal/Audit/Authorization/AuthorizationAuditCoordinatorTest.php
```

Luna native lane owns exactly four files, after the new signatures are stable:

```text
src/Internal/Execution/InlineDispatcher.php
src/Internal/Execution/DeferredAcceptanceOrchestrator.php
src/Internal/Execution/DeferredWorkerRuntime.php
tests/Transport/PostgreSql/PostgreSqlAuthorizationAuditRuntimeIntegrationTest.php
```

Luna architecture lane owns exactly five files, following the new graph:

```text
tests/Architecture/AuthorizationAuditArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

At most4 active agents including Root; lanes may run independently on disjoint
files once contracts are reviewed. Only Root uses shared PostgreSQL/schema/cache.
After the foundation handoff, its review corrections are owned by the architecture
Luna worker together with the dependent exact graph updates. This sequential
handoff respects the live thread limit; the original foundation worker is idle.
Native tests use dedicated `blackops_authorization_audit` schema and restore all
fault/connection state. No new test helper library or production failure injection
API. Native fixture may reuse existing test patterns with its own small assembly.

## Acceptance evidence

- [x] Exact factory projection for both modes/allow/Unauthorized/Forbidden/missing
  principal, tenant/global, explicit actual/fallback executor; nondefault policy
  version independent of event version, exact Catalog and evidence, fresh repeated
  identities and null-vs-durable keys. No payload/rejection text in persisted JSON.
- [x] No-policy performs no clock/UUID/Audit/DB calls; backend failure is no decision.
  Invalid/corrupt evaluation/snapshot/reference, clock/UUID failure and serialization
  fail safely; reachable in-frame canaries, with caller trace limit documented.
- [x] One-shot request, append failure, wrong active/root/nested/auto-commit and
  distinct active connection cases; unsupported mutation fails before handler.
- [x] Real PostgreSQL Inline/acceptance/Worker paths in both modes: committed allow,
  original denial, no-policy, atomic rollback on confirmed pre-commit/handler failure,
  business rejection without invented deny, normal keyed replay/conflict with one
  policy evaluation and one event per returned replay/conflict. Exceptional replay
  preparation emits none. Preserve existing lifecycle/outcome behavior and its
  explicitly documented ambiguous-commit limits.
- [x] Inject real commit-then-throw in native PostgreSQL roots: committed rows
  remain, response fails, no blind reappend/enqueue/handler retry. Assert the
  inherited Inline failure Journal/internal-failure snapshot and same-key
  IdempotencyReplayFailure, acceptance recording-failure and Worker terminal fence
  behavior. Simulated pre/post-commit fault evidence is not a process-kill test.
- [x] Worker deny append AuditDeliveryException inside the reject callback rolls
  back state before commit and enters existing supervision; later commit failures
  retain their existing generic classification and do not imply rollback.
  retry reevaluates with new action ID. Existing schedule/fencing/observation tests
  remain covered; do not claim rollback of external effects or separate idempotency.
- [x] Exact closed graph and targeted AST negative controls; A2/C3 direct readers
  pass with no whole-file exemption. Package104→107 unique entries and at least58
  omission/control checks; existing controls retained.
- [x] Independent authority/source/security/evidence reviews Green; frozen final
  full preserves all3,446 baseline identities and records every newly added case.

## Verification plan

Root records argv/input hashes, images/runtime/date, monotonic elapsed/exit/counts
and logs under /tmp/blackops-d4b-20260916. PHP8.5.7/PHPUnit13.2.2, shared512M; no
per-command override or new baseline. During iteration select affected new units,
native integration and changed architecture classes; their unchanged successful
checks are reusable. Shell inventory can be owned/run once by architecture lane.

Final source/security review precedes freeze. On the reviewed final candidate:

```bash
docker compose run --rm --no-deps -T app mago format --check src tests
docker compose run --rm --no-deps -T app mago lint
docker compose run --rm --no-deps -T app mago lint --verify-baseline
docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d4b-full-junit.xml
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Do not run the full suite during iteration or rerun its same-input subsets at
closeout. A later test-only correction requires explicit dependency review before
composing affected success with unchanged full cases. Product/config/bootstrap
change or unbounded failure requires broader verification. Known D4A issue counts
1 deprecation/2 PHPUnit deprecations/13 notices are not warning-free evidence.

## Release documentation impact and closeout

Stable tuple remains Framework/Skeleton/site Experimental1.2.1; roadmap1.3.0
unreleased. Internal proposed capability audit.authorization_delivery.v1. All193
public source files/routes,421 generated search/site/LLM artifacts and six allowed
historical version references remain unchanged; compare hashes without claiming a
new browser/build/live test. Spec115 is implementer documentation; guide/internal
site updates wait for canonical composition and user-facing semantics. Release
same-SHA CI/export and documentation delivery remain future gates.

残り工程: D5 and ordinary composition, E/F/G, hardening, docs and release;
D-control after Accepted F. Parent D4/D and1.3 remain incomplete. Final
local documentation preview and user review must occur before release.
Inline ambiguous-commit lifecycle/idempotency reconciliation remains an explicit
integration/hardening obligation before release.
Next Action: Root prepares and independently reviews D5 retention/hold authority
from the completed mutation-root survey before a new scoped Luna implementation.
