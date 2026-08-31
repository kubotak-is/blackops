# P23-003C1: Audit Outbox State Foundation

Status: Accepted — Complete Final Gate and fresh Sol Max implementation review Green; candidate commit pending

Updated At: 2026-09-01T04:40:14+09:00

## Current Checkpoint

P23-003B1／B2 and parent P23-003B are Accepted.
Reviewed B2 candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` and its clean-candidate package export are Green.
The management closeout is committed at clean HEAD `e772242ed2156ee259ae177f69a6b0a9189fef68`.

Accepted B2 exact26 included the shared
`tests/Architecture/AuditRecordArchitectureTest.php`. C1 intentionally changes
only that shared guard. The other Accepted B2 exact25 remain byte-identical;
their Task-order sha256sum-output aggregate is
`4b605e2a23dd77b1c41f8aed95244a46776aef599d2607c88a7d63ee8a2f0354`.

D150 splits P23-003C into serial C1→C2→C3→C4 children.
This packet owns only C1's pure state foundation.
The first Sol Max preflight is Historical. Focused execution proved that direct
`AuditRecordTime` reuse conflicts with the Accepted A2 source-registration
guard unless that guard receives one bounded Delivery exception. The corrected
preflight, implementation, Iteration Gate, Product／Test exact11 freeze
`d0bfa64e35ce8d113909a2aba710c57ed7bb33fd6711c51df207e71d33919fad`,
Complete Final Gate, and fresh Sol Max implementation review are Green.
The final review returned `P1=0/P2=0/P3=0`; P23-003C1 is Accepted.
Candidate commit and same-SHA package export remain as closeout only.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

## Goal

Implement one unregistered Internal component that validates the exact six-field Audit Outbox state snapshot and computes claim, retry, recovery, relay, and poison results without side effects.

The component must close state nullability, attempt bounds, expected-version／token fencing, due／expiry equality, exact +1 versioning, deterministic saturated backoff, and overflow-safe timestamp arithmetic.

This packet does not persist the 15-field `AuditOutboxRecord v1` row and does not activate any delivery path.

## Model and Workflow

- Orchestrator／research／review: repository-configured `gpt-5.6-sol`／`max`.
- Production Worker: repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer: repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- The Production Worker starts only after fresh preflight Green.
- The Worker changes only the exact Files Allowed below.
- The Worker updates Report／STATE checkpoints and does not commit, stage, push, publish, or deploy.
- Any Product change invalidates prior Product gate evidence.

Missing Worker Process model metadata alone is not a blocker.
Explicit model／effort rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. HEAD is `e772242ed2156ee259ae177f69a6b0a9189fef68` unless a later Orchestrator-only packet correction commit is recorded.
3. Staged path count is zero.
4. P23-003B2 and parent P23-003B remain Accepted.
5. Accepted B2 Product exact26 remains `ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
6. Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

If a prerequisite drifts, stop before Product edit and record a Blocker.

## Source of Truth

Read in this order:

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task
4. `develop/spec/README.md`
5. `develop/spec/92-documentation-review-agent.md`
6. `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
7. `develop/spec/105-blackops-1-3-capability-contract.md`
8. `develop/spec/106-phase-23-delivery-plan.md`
9. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
10. `develop/decisions/147-audit-catalog-and-safe-projection-boundary.md`
11. `develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md`
12. `develop/decisions/149-audit-postgresql-canonical-store-boundary.md`
13. `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
14. `develop/orchestration/reports/P23-003B2-audit-postgresql-store.md`
15. `develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md`
16. current Audit／Time／Architecture source and tests required by this packet

D150 is the most-specific P23-003C split and C1 API authority.
If authority and implementation conflict, do not resolve it only in Product code; stop and report the conflict.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.outbox_state.v1
publicSourceInventory=[]
publicRouteInventory=[]
```

No public Source or route changes.
No Guide／Website source, generated HTML／Raw／Search／LLM artifact, runtime, deployment, or browser verification is in scope.
The Report must record those artifact and delivery surfaces as unchanged or Not Verified, never as Green by inference.

## In Scope

### 1. Exact types

Add exactly these six Production types under `src/Internal/Audit/Delivery`:

1. `AuditOutboxState.php`
2. `AuditOutboxFailure.php`
3. `AuditOutboxException.php`
4. `AuditOutboxConfiguration.php`
5. `AuditOutboxStateSnapshot.php`
6. `AuditOutboxTransition.php`

Their public signatures, enum values, defaults, failure message, and no-leak behavior must match D150 exactly.
No type carries `#[PublicApi]`.

### 2. State snapshot

The exact ordered snapshot is:

```text
{state,attempt,availableAt,leaseFencingToken,leaseExpiresAt,version}
```

The raw and `toArray()` form uses a state string, native attempt／version integers, and string-or-null scheduling／lease fields.
Only `state()` returns `AuditOutboxState`.

`pending()` creates `pending,0,availableAt,null,null,1`.
`fromArray()` checks `count===6` before list detection or key extraction, accepts only the exact associative shape, and rejects wide input before copying any member.
It then validates the fixed-width values against the supplied configuration.
It captures a detached value and does not retain the caller array by reference.

The exact state matrix is D150 §4.
Timestamps use existing `AuditRecordTime` canonical UTC six-fraction validation.
Lease tokens are exact canonical lowercase UUIDv7 strings.
Versions are `1..9007199254740991`.

### 3. Pure transitions

Implement exactly five operations:

- `pending -> leased` claim;
- active `leased -> pending` retry;
- expired `leased -> pending|poisoned` recovery;
- active `leased -> relayed` settlement;
- active `leased -> poisoned` settlement.

Every success returns a new snapshot and increments version exactly once.
Only claim increments attempt.
All conflict paths return no snapshot by throwing `transition_conflict`.
Capacity failure is `resource_unavailable`.

One caller-supplied `trustedNow` drives each operation.
C1 never reads a Clock.
The active boundary is strict `<`; equality is expired.

### 4. Backoff and checked time

Use only the D150 saturating integer algorithm.
Do not use exponentiation, floating point, jitter, provider retry time, wall-clock rereads, or unbounded loops.

Lease and backoff addition must preserve exact UTC six-fraction format and a four-digit year.
An unrepresentable result fails `resource_unavailable` without producing a snapshot.

### 5. Failure precedence and no-leak

Use D150 §7 exact order.
All exceptions have fixed message, finite code, and null previous exception.

Mark caller arrays, lease tokens, and timestamps with `#[SensitiveParameter]` where they can enter a trace.
Unique canaries must be absent from exception message, previous, trace string and arguments, debug, serialization, JSON, and `var_export()` surfaces.

### 6. Architecture

Add one focused Architecture test that fixes:

- exact six-file／six-type Production inventory;
- exact enum, class modifier, constructor, factory, transition, accessor, and magic-method signatures;
- exact zero `#[PublicApi]` markers;
- only allowed dependency edges;
- no DBAL, Clock, UUID generator, B1 protection codec, B2 Store, Application, Container, Transport, CLI, HTTP, or generic Outbox dependency;
- no Production reference from outside `src/Internal/Audit/Delivery`;
- direct reflection／source fixtures for added／missing type, public signature, enum case, PublicApi marker, and forbidden dependency.

The exact worktree path guard, rather than a second general-purpose PHP call-graph parser, prevents outside Production callers in C1.

`AuditRecordArchitectureTest` receives one bounded correction: Delivery may
reference only `AuditRecordTime`; every other A2 type remains forbidden there.
The correction must include positive and negative mutation fixtures and must
not authorize A2 runtime registration or any other outside caller.

Existing `AuditPublicApiTest`, `PublicApiArchitectureTest`, and
`AuditStoreArchitectureTest` remain unchanged, and all four existing suites
must stay Green.

## Out of Scope

- `AuditOutboxRecord v1` 15-field row composition or codec
- PostgreSQL migration, table, ordinal, idempotency, FIFO query, row lock, or CAS
- `PostgreSqlAuditProtectedIngressHydrator`
- Clock or UUID generator integration
- required same-root business transaction orchestration
- durable ingress persistence
- policy evaluator or Catalog delivery selection
- opaque-ID／Audit-protection Application key provider and Container registration
- canonical append, relay, retry worker, poison queue, operational command, or scheduler
- changes to existing generic operation Outbox types
- changes to B1／B2 Production or tests
- changes to Accepted A2 Product; only the named Architecture reuse guard may move
- public API, Guide, Website, CLI, HTTP route, release, deploy, or browser work
- commit／stage／push by Worker

## Files Allowed

Production／Test exact11:

1. `src/Internal/Audit/Delivery/AuditOutboxState.php`
2. `src/Internal/Audit/Delivery/AuditOutboxFailure.php`
3. `src/Internal/Audit/Delivery/AuditOutboxException.php`
4. `src/Internal/Audit/Delivery/AuditOutboxConfiguration.php`
5. `src/Internal/Audit/Delivery/AuditOutboxStateSnapshot.php`
6. `src/Internal/Audit/Delivery/AuditOutboxTransition.php`
7. `tests/Internal/Audit/Delivery/AuditOutboxConfigurationTest.php`
8. `tests/Internal/Audit/Delivery/AuditOutboxStateSnapshotTest.php`
9. `tests/Internal/Audit/Delivery/AuditOutboxTransitionTest.php`
10. `tests/Architecture/AuditOutboxStateArchitectureTest.php`
11. `tests/Architecture/AuditRecordArchitectureTest.php`

Worker management exact2:

12. `develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md`
13. `develop/STATE.md`

Orchestrator-only immutable exact6:

- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/orchestration/tasks/P23-003C1-audit-outbox-state-foundation.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

Before Worker start, freeze the exact six immutable SHA-256 values and the Report／STATE start hashes.
During implementation, only Product／Test exact11 plus Report／STATE may move.
Any other path is a Blocker.

## Required Fixtures

### Configuration

- all defaults
- every inclusive minimum and maximum
- each lower-bound minus one and upper-bound plus one independently
- base greater than cap remains valid and produces `min(base,cap)`
- fixed code／message／previous and no-leak surfaces

### Snapshot

- genesis pending exact six fields and order
- pending attempts `0` and `maxAttempts-1`
- leased and both terminal states at attempts `1` and `maxAttempts`
- system-independent timestamps and two distinct UUIDv7 tokens
- missing／extra／list／wrong-order／wrong scalar／float／numeric string
- wide top-level map and deep unknown value rejected before member copy or recursion
- invalid state token; uppercase／non-v7／malformed lease token
- pending at max; leased／terminal at zero and max+1
- every state/nullability mismatch independently
- version `1`, MAX, `0`, MAX+1
- timestamp invalid date, offset, missing or seven fractional digits, year overflow
- caller-array mutation after construction does not alter the snapshot
- debug／serialize／unserialize／JSON／var_export and actual canary checks

### Transitions

- claim at due-1us conflict, equality success, due+1us success
- claim attempt `0`, `maxAttempts-1 -> maxAttempts`, and invalid max+1 prevention
- lease expiry exact addition and four-digit-year overflow
- retry at expiry-1us success; equality and +1us conflict
- recovery at expiry-1us conflict; equality and +1us success
- recovery attempt below max returns pending with same attempt
- recovery attempt max returns poisoned
- relay／poison at expiry-1us success; equality and +1us conflict
- terminal second settlement and every wrong-operation state conflict
- expected version mismatch and gap; token mismatch; invalid token grammar
- version MAX resource failure on every operation
- compound version-MAX plus timestamp-overflow proves version capacity wins
- exact +1 version and input snapshot unchanged on every success／failure
- exact one trustedNow input with no Clock dependency

### Backoff

- attempt 1 base-below-cap, base-equals-cap, and base-above-cap
- doubling below half, exact half, greater than half, and cap saturation
- attempts 1 and 32
- no float, exponentiation, jitter, or overflow
- availableAt exact timestamp for each representative delay and year-overflow negative

### Architecture and release boundary

- exact inventory, signature, and dependency fixtures are non-vacuous
- zero PublicApi markers
- forbidden dependency negatives and exact-scope outside-caller prevention
- existing Audit architecture tests Green
- Deptrac zero violation／uncovered／error
- Stable version baseline Green
- Guide／Website source status empty
- no CLI／HTTP／Application／Container registration
- no management identifiers in Production／Test comments
- staged path count zero

## Acceptance Criteria

1. Corrected D150 and this Task received fresh Sol Max preflight Green before Product edit resumed.
2. Worker diff is exactly Product／Test exact11 plus Report／STATE.
3. Exact state/configuration/transition/failure APIs match D150.
4. All state, attempt, nullability, time, lease, version, and backoff boundaries have direct mutation-resistant fixtures.
5. No Product side effect or dependency outside the pure C1 boundary exists.
6. Existing Audit and PublicApi Architecture suites remain Green; Accepted B2
   exact25 remain at aggregate
   `4b605e2a23dd77b1c41f8aed95244a46776aef599d2607c88a7d63ee8a2f0354`,
   and the shared Architecture guard is the sole intentional B2 exact26 delta.
7. Iteration Gate and one Complete Final Gate after the final Product freeze are Green.
8. Fresh Sol Max implementation review is Green before Acceptance.
9. Stable `1.2`, public docs, runtime, routes, and release behavior remain unchanged.
10. Worker leaves staged0 and does not commit or push.

## Iteration Gate

Run after every Product change and stop at the first failure.

```bash
set -euo pipefail

expected_paths="$(printf '%s\n' \
  develop/STATE.md \
  develop/TODO.md \
  develop/decisions/150-audit-outbox-state-and-delivery-split.md \
  develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md \
  develop/orchestration/tasks/P23-003C1-audit-outbox-state-foundation.md \
  develop/spec/105-blackops-1-3-capability-contract.md \
  develop/spec/106-phase-23-delivery-plan.md \
  develop/spec/README.md \
  src/Internal/Audit/Delivery/AuditOutboxConfiguration.php \
  src/Internal/Audit/Delivery/AuditOutboxException.php \
  src/Internal/Audit/Delivery/AuditOutboxFailure.php \
  src/Internal/Audit/Delivery/AuditOutboxState.php \
  src/Internal/Audit/Delivery/AuditOutboxStateSnapshot.php \
  src/Internal/Audit/Delivery/AuditOutboxTransition.php \
  tests/Architecture/AuditOutboxStateArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Internal/Audit/Delivery/AuditOutboxConfigurationTest.php \
  tests/Internal/Audit/Delivery/AuditOutboxStateSnapshotTest.php \
  tests/Internal/Audit/Delivery/AuditOutboxTransitionTest.php | LC_ALL=C sort)"
actual_paths="$(git status --short --untracked-files=all | cut -c4- | LC_ALL=C sort)"
diff -u <(printf '%s\n' "$expected_paths") <(printf '%s\n' "$actual_paths")

docker compose run --rm app mago format --check \
  src/Internal/Audit/Delivery \
  tests/Internal/Audit/Delivery \
  tests/Architecture/AuditOutboxStateArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php

docker compose run --rm app php vendor/bin/phpunit \
  tests/Internal/Audit/Delivery \
  tests/Architecture/AuditOutboxStateArchitectureTest.php \
  tests/Architecture/AuditPublicApiTest.php \
  tests/Architecture/PublicApiArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Architecture/AuditStoreArchitectureTest.php

docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered

bash tests/Consumer/version-baseline.sh
test -z "$(git status --short --untracked-files=all -- docs/guide docs/website)"
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

After Green, record every Product／Test exact11 SHA-256 value and the ordered aggregate.
Any exact11 change invalidates the freeze.

## Complete Final Gate

Run once from the unchanged exact11 freeze after Orchestrator pre-gate review.
Stop at the first failure and restart from command 1 after any Product change.

```bash
set -euo pipefail

docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app php vendor/bin/phpunit
docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/framework-package-required-inventory.sh
test -z "$(git status --short --untracked-files=all -- docs/guide docs/website)"
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

At the end, repeat the exact19 scope check, immutable exact6 hashes, Product
exact11 hashes, Accepted B2 exact25 aggregate, prior／current shared
Architecture guard hashes, and staged0 check.
Generated Documentation artifacts and browser behavior remain Not Verified.

## Post-Acceptance Commit and Package Gate

Only the Orchestrator may perform these steps after fresh implementation review Green and Acceptance:

1. commit the reviewed candidate;
2. verify a clean worktree and exact commit SHA;
3. run `bash tests/Consumer/framework-package-export.sh` on that committed SHA;
4. rerun the package gate after any package-visible source-changing closeout commit;
5. do not push unless separately authorized.

## Report Requirements

The Report must include:

- Summary
- exact Changed Files
- Decisions and Assumptions
- Release Documentation Impact
- Commands and Results with exit codes and counts
- immutable exact6 start/end hashes
- Product exact11 individual hashes and aggregate
- Accepted B2 exact25 preservation and the sole shared Architecture guard delta
- state／time／backoff／failure／no-leak evidence
- Architecture／PublicApi／nonactivation evidence
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

Any unexecuted command is recorded as Not Run with its reason.

## Remaining Issues

P23-003C1 Product and Acceptance work is complete. Candidate commit and
same-SHA package export remain as C1 closeout.
The parent Goal also requires P23-003C2／C3／C4, parent P23-003C Acceptance, P23-003D..G, P23-004, P23-005, and P23-006.

## Suggested Next Action

The Orchestrator commits the reviewed exact19 candidate, verifies a clean exact
SHA, and runs `framework-package-export.sh` on that committed SHA. Then record
the C1 closeout and draft P23-003C2 without activating a partial runtime.
