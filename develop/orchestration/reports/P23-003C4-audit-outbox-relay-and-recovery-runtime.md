# P23-003C4 Report: Audit Outbox Relay and Recovery Runtime

Status: Complete — accepted commit and clean same-SHA export Green

Updated At: 2026-09-04T12:00:22+09:00

## Summary

At `2026-09-04T11:04:10+09:00`, fresh General, Transaction, and Documentation
reviews are independently Green with P1/P2/P3 `0/0/0`. The unchanged
post-review Product/Test exact23 is
`0780592d2d4eaefe1c503266e3532356e0d94ff9ceff6613116d97c65997e66d`,
the reviewed pre-Acceptance exact25 is
`aceb709ca0a0637061471826bbddac454510caf8a90d3170517ce1147ebd6582`,
and immutable exact12 remains
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`.
The Complete Final Gate restarted at command 1 and completed Green without a
Product/Test change. Full PHPUnit is `3,170 tests / 1,806,825 assertions`;
Deptrac is `1,036 analyzed / 0 violations / 0 skipped / 0 uncovered / 6,341
allowed / 0 warnings / 0 errors`; all Audit Node goldens, exact75 package and
`29` mutations, Community Board clean-install, Stable generic Outbox `10/69`,
migration `13 + 5 = 18`, StoragePurpose exact9, nonactivation/docs/ID/diff,
and ending staged0/exact freezes pass. The Orchestrator accepts P23-003C4 and
then parent P23-003C. The accepted exact25 candidate was committed as
`e7176759bdd7f851b51f4a5f84e0027312e5c9c0`; its tree was clean/staged0 and
the same-SHA framework package export passed. This completion checkpoint is
folded into that local commit once and the export is rerun from the final
amended SHA before handoff. Push remains unauthorized; release, deploy, public
activation, generated Documentation artifacts, and browser behavior remain
Not Verified.

### Complete Final Gate after Fresh Reviews (current)

The accepted run used the exact commands below in Task order and every command
exited `0`:

```text
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint --reporting-format=short
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-report-level=error
docker compose run --rm app mago analyze --minimum-report-level=error --reporting-format=count
docker compose run --rm app php vendor/bin/phpunit
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/community-board-clean-install.sh
bash -n tests/Consumer/version-baseline.sh tests/Consumer/framework-package-required-inventory.sh tests/Consumer/framework-package-export.sh tests/Consumer/community-board-clean-install.sh
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Outbox/OutboxRelayRuntimeTest.php tests/Internal/Application/ApplicationOutboxRelayConfigurationTest.php tests/Internal/Console/OutboxRelayCommandsTest.php tests/Internal/Scheduler/OutboxRelayMaintenanceTaskTest.php
docker compose run --rm app php -r 'require "vendor/autoload.php"; $cases=\BlackOps\StorageProtection\StoragePurpose::cases(); if (count($cases) !== 9) { fwrite(STDERR, "unexpected StoragePurpose count\n"); exit(1); } printf("StoragePurpose cases=%d\n", count($cases));'
```

Full format, Composer strict validation, lint, baseline verification, and both
analyzer formats are Green with zero current issues/errors. Full PHPUnit
reports `3,170 tests / 1,806,825 assertions`, with the existing non-failing
`1` deprecation, `2` PHPUnit deprecations, and `13` PHPUnit notices. Deptrac
reports `1,036 / 0 / 0 / 0 / 6,341 / 0 / 0`. The PostgreSQL golden retains
`mutations=15`, `mutationKills=2`, and inventory SHA
`629c262d1d8fe6ccea4260be981568b579b04ae0d5e48d0dc9967d0526e22343`.
Version remains published `1.2.0` and historical `1.1.0`; package inventory is
exact75 and rejects all `29` mutations (`15 + 14`). Community Board
clean-install passes Composer/dependency installation, generates `18`
frontend files, Svelte check `0/0`, Vitest `8 files / 46 tests`, production
build, migrations, and its container journey.

The final fail-closed static post-guard also exited `0`: shell syntax,
Framework `13` plus Community `5` equals `18`, README and Consumer expressions
are exact1, Stable generic Outbox is `10/69`, StoragePurpose is exact9, C4
runtime/canonicalizer/adapter/composer reference inventories are `3/3/2/1`,
Audit-specific activation and forbidden management IDs are zero, Guide and
Website status/diff are clean, Accepted C3 ancestry holds, package-script
hashes match, `git diff --check` passes, and branch/HEAD/status exact25/staged0
remain unchanged. The final pre-Acceptance hashes are:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=41abbfe891f226fd1756fc6e7905e23f762604a5
staged=0
status=exact25
exact23=0780592d2d4eaefe1c503266e3532356e0d94ff9ceff6613116d97c65997e66d
exact25=aceb709ca0a0637061471826bbddac454510caf8a90d3170517ce1147ebd6582
immutable12=e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4
```

Two earlier invocations are not accepted Gate evidence. The first reached
command 10 and stopped on an Orchestrator-escaped `php -r` Parse Error; the
second restarted at command 1 and stopped before Deptrac execution on a
sandbox-only Docker socket denial. Product/Test did not change. The accepted
run above then restarted again at command 1 with Docker access fixed and
completed without interruption.

### Historical: Bounded Mago Lint and Analyzer Correction

At `2026-09-04T10:33:38+09:00`, the command-3 lint correction and the
subsequent focused analyzer correction are complete. The runtime adds only
class/method-local Mago expectations required by its D153-fixed callable
shape. The canonicalizer adds only a static `array<string,mixed>` refinement
after the existing `is_array()` guard; the Accepted `AuditIngressEnvelope`
constructor already enforces the exact string-key logical-record shape. No
callable, dependency, control flow, failure mapping, runtime behavior, or
public API changed.

```text
f501e3b84ee3ded6935d743c4d722b5404f98e6ca8cc02dd6570a288f88c47ed  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
a9ab51732a598e82bb96710c466dc9d85fe5f958e5e5c9fb289ec18f1f83858a  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
```

Focused commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php -> exit 0
docker compose run --rm app mago lint --reporting-format=short -> exit 0 (No issues found; 186 baseline issues filtered)
docker compose run --rm app mago lint --verify-baseline -> exit 0 (baseline current)
docker compose run --rm app mago analyze --minimum-report-level=error src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php -> exit 0
docker compose run --rm app mago analyze --minimum-report-level=error --reporting-format=count src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php -> exit 0 (0 errors)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Architecture/AuditRelayArchitectureTest.php -> exit 0 (38 tests / 11541 assertions / 0 warnings)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0
```

The forbidden management-ID scan remains zero and status remains exact25.
Because Product bytes changed, the earlier Iteration/reviews and stopped Final
attempt below are Historical. Replacement Iteration, fresh reviews/refreeze,
and Complete Final Gate from command 1 remain Not Run.

### Historical: Complete Final Gate Command 3 Blocker

At `2026-09-04T10:21:58+09:00`, the Complete Final Gate started from the
freshly reviewed exact25
`b8384a4608808a4ec30cd4ad24c800fc839cac2f3d37d6fcec399867b66c355d`.
Command 1 full format and command 2 Composer strict validation exited `0`.
Command 3 stopped the Gate:

```text
docker compose run --rm app mago format --check src tests -> exit 0
docker compose run --rm app composer validate --strict -> exit 0
docker compose run --rm app mago lint --reporting-format=short -> exit 1
```

The lint result is four errors and two help messages, all in the two new Relay
classes: `AuditOutboxRelayRuntime` has class cyclomatic-complexity, kan-defect,
and too-many-methods errors plus two fixed private boolean-flag parameters;
`AuditIngressCanonicalizer` has a class cyclomatic-complexity error. D153 fixes
the private callable graph, so the bounded correction is code-local Mago
expectations in those existing exact23 source files, without callable,
control-flow, behavior, or public API change. Commands 4 through 10 were not
run. The prior Iteration and review evidence becomes Historical after any
Product edit; correction verification, replacement Iteration, and fresh
reviews are required before the Complete Final Gate restarts at command 1.

## Replacement Iteration Summary

At `2026-09-04T10:38:02+09:00`, the complete replacement P23-003C4 Iteration
Gate restarted from command 1 and is Green on corrected Product/Test exact23
`0780592d2d4eaefe1c503266e3532356e0d94ff9ceff6613116d97c65997e66d`.
Every command recorded below exited `0` without a further Product/Test change.

The exact D153 seven-type relay/recovery graph, Application composition,
Architecture/package guards, direct fixtures, and real PostgreSQL evidence are
implemented. Replacement Iteration, all three independent reviews, and the
Complete Final Gate are Green on the unchanged Product/Test freeze.

## Changed Files

The current worker tree changes exactly the Task's ordered Product/Test
exact23 plus this Report and `develop/STATE.md`. No other path is present in
status, and no immutable authority, migration, Stable generic Outbox,
Community Board, public source, route, command, or Documentation path changed.

## Decisions and Assumptions

- Claim, canonical append, and acknowledgement are three separately committed
  Store-owned roots on the same C3 Connection.
- Recovery and relay are bounded one-row calls; C4 adds no loop, command,
  scheduler, worker, route, registration, or current runtime caller.
- Retry, poison, settlement precedence, append-before-ack convergence, and
  poison FIFO release follow D153 without changing Accepted C1/C2 Stores.
- Stable `1.2.0` remains current, BlackOps `1.3` remains unreleased, package
  required inventory becomes exact75, and public Documentation remains
  unchanged.
- The package export is deliberately deferred until Acceptance and a clean
  committed candidate because its Git archive side cannot prove untracked C4
  files.

## Commands and Results

The ending freeze is:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=41abbfe891f226fd1756fc6e7905e23f762604a5
staged=0
status=exact25
exact23=0780592d2d4eaefe1c503266e3532356e0d94ff9ceff6613116d97c65997e66d
preManagementExact25=b8642d5a7fc16c944f9771ff8665ee44192b559f1dc35e47056cd9a51e4ebf16
immutable12=e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4
```

The exact23/exact25/immutable12 counts, unique paths, existence, ordered
aggregates, individual package hashes, Accepted C3 ancestry, and four
add/remove/duplicate/wrong-hash negatives passed. The package script hashes
remain `9f983932f02761456133a534bd3ff95334e8311a51646fbcbcf3e37132960f1f`
and `9ff12e3e2f921fba27165c112ddd839fd6bdc1c50926d30dd689a69410c1bd63`.

Literal quality commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditRelayOutcome.php src/Internal/Audit/Relay/AuditRelayFailure.php src/Internal/Audit/Relay/AuditRelayException.php src/Internal/Audit/Relay/AuditRelayPersistence.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Application/ApplicationAuditRuntime.php src/Internal/Application/ApplicationAuditRuntimeComposer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Internal/Audit/Relay/PostgreSqlAuditOutboxRelayTest.php tests/Architecture/AuditRelayArchitectureTest.php tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php tests/Architecture/AuditOutboxStateArchitectureTest.php tests/Architecture/AuditOutboxStorageArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditStoreArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Internal/Audit/Relay/PostgreSqlAuditOutboxRelayTest.php tests/Architecture/AuditRelayArchitectureTest.php tests/Architecture/AuditOutboxStateArchitectureTest.php tests/Architecture/AuditOutboxStorageArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditStoreArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php tests/Architecture/PublicApiArchitectureTest.php tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php tests/Internal/Audit/Storage/PostgreSqlAuditRecordStoreTest.php tests/Internal/Audit/Protection/AuditProtectedBodyCodecTest.php -> exit 0 (256 tests / 1788282 assertions / 0 warnings)
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs -> exit 0
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs -> exit 0
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs -> exit 0 (mutations=15 / mutationKills=2 / inventorySha=629c262d1d8fe6ccea4260be981568b579b04ae0d5e48d0dc9967d0526e22343)
node tests/Fixtures/Audit/observed-audit-record-golden.mjs -> exit 0
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered -> exit 0 (1036 analyzed / violations=0 / skipped=0 / uncovered=0 / allowed=6341 / warnings=0 / errors=0)
bash tests/Consumer/version-baseline.sh -> exit 0 (published=1.2.0 / historical=1.1.0)
bash tests/Consumer/framework-package-required-inventory.sh -> exit 0 (75 ordered paths / 29 rejected mutations = 15 inventory + 14 call-contract)
bash -n tests/Consumer/version-baseline.sh tests/Consumer/framework-package-required-inventory.sh tests/Consumer/framework-package-export.sh -> exit 0
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Outbox/OutboxRelayRuntimeTest.php tests/Internal/Application/ApplicationOutboxRelayConfigurationTest.php tests/Internal/Console/OutboxRelayCommandsTest.php tests/Internal/Scheduler/OutboxRelayMaintenanceTaskTest.php -> exit 0 (10 tests / 69 assertions)
docker compose run --rm app php -r 'require "vendor/autoload.php"; $cases=\BlackOps\StorageProtection\StoragePurpose::cases(); if (count($cases) !== 9) { fwrite(STDERR, "unexpected StoragePurpose count\n"); exit(1); } printf("StoragePurpose cases=%d\n", count($cases));' -> exit 0 (StoragePurpose cases=9)
git diff --exit-code -- docs/guide docs/website -> exit 0
git diff --check -> exit 0
git diff --cached --quiet -> exit 0
```

The release/static guard also exited `0`: Framework migrations are `13`,
Community Board migrations are `5`, total migrations are `18`, and the README
and clean-install count expressions are each exact1. Stable generic Outbox
runtime/configuration/run/daemon/scheduler source and Guide/Website bytes are
unchanged. Audit C4 source reference inventories are runtime/canonicalizer/
adapter `3/3/2`, the composer inventory is exact1, and Audit-specific public
source, route, CLI, scheduler, worker, maintenance, and runtime activation
remain exact zero. The forbidden management-ID scan has zero matches in PHP
comments, Documentation status/diff is clean, `git diff --check` passes, and
the index remains empty.

The Batch sections below are Historical implementation checkpoints; this
section is the current Iteration evidence. Fresh independent General,
Transaction, and Documentation reviews, the unchanged post-review refreeze,
Complete Final Gate, Acceptance, candidate commit, and clean same-SHA package
export are Green as recorded above and below. Push, release, deploy, browser
verification, generated Documentation artifacts, and public activation remain
Not Verified and were not performed.

## Post-Acceptance Evidence

```text
git commit -m "feat(audit): add outbox relay and recovery runtime"
git rev-parse HEAD
git status --short --untracked-files=all
bash tests/Consumer/framework-package-export.sh
```

The accepted exact25 candidate committed exactly the Task paths and no
unrelated path. Its initial full SHA was
`e7176759bdd7f851b51f4a5f84e0027312e5c9c0`; the tree was clean and staged0.
The package command exited `0` with
`Created: Framework Git and Composer package export contract passed.` This
Report/STATE completion checkpoint is management-only, is folded into the same
local commit by one amend, and the package command is rerun from the final
amended SHA before handoff. The final full SHA is recorded in the Orchestrator
handoff. No push was performed.

## Acceptance Criteria

- [x] exact seven-type C4 graph and exact23 Product/Test scope
- [x] protected-ingress canonical reconstruction and binding checks
- [x] three separately committed transaction phases and finite failure matrix
- [x] crash, recovery, FIFO, convergence, capacity, key-role, and no-leak evidence
- [x] exact75 package, PublicApi, Deptrac, Stable/release, and nonactivation guards
- [x] Iteration Gate Green on the frozen Product exact23
- [x] fresh General, Transaction, and Documentation implementation reviews
- [x] unchanged post-review refreeze and Complete Final Gate
- [x] P23-003C4 and parent P23-003C Acceptance
- [x] candidate commit and clean same-SHA package export

## Remaining Issues

No C4 or parent C issue remains. P23-003D through P23-003G and the remaining
BlackOps 1.3 Audit roadmap stay downstream; C4 does not activate or release
them. Stable `1.2.0` remains current and C remains unreleased.

## Suggested Next Action

Begin P23-003D preflight from the clean final C commit. Do not push without
separate user authorization.

## Historical: Batch D Checkpoint — Real PostgreSQL Relay and Recovery Evidence

At `2026-09-04T09:03:58+09:00`, the bounded real-PostgreSQL relay and
recovery evidence is complete for Orchestrator review. The new test file is
the only Batch D Product/Test addition; its final SHA-256 is
`7a3fc4a9e996c616a628a9b48baed4c4381db9fe863286979749a14782ff83b2`.
The accepted exact22 and exact23 bytes remain preserved at
`52f4543ef567b83684c8d43d569352c810f77b3e3263515dcedf8850626613a7` and
`20f43c10ef41ce75861f8c93bfb22e1e4882ad31e614dcae2284ba00b90d92ee`.

The real fixtures cover independently committed claim, canonical append, and
ack transactions for system and tenant records; crash windows before append
and after append; final-attempt recovery poison with FIFO release;
ack-commit response loss; stale token/version CAS; and a paused relay whose
committed append is followed by recovery, stale old ack, and fresh
`RelayedExisting` convergence. They also cover same-partition races,
cross-partition progress, historical/role-separated key and ciphertext
retention, no-leak surfaces, and bounded child/connection/resource cleanup.
The capacity fixture records the measured logical maximum of `16983` bytes,
evidence JCS lengths `8192` and `8193`, maps the latter to
`IntegrityInvalid`, and proves all PostgreSQL rows, states, sequences, hashes,
and protected bodies remain unchanged after rejection.

Literal final commands and results:

```text
docker compose run --rm app mago format --check tests/Internal/Audit/Relay/PostgreSqlAuditOutboxRelayTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/PostgreSqlAuditOutboxRelayTest.php -> exit 0 (12 tests / 1104 assertions / 0 warnings)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
git status --short --untracked-files=all -> exit 0 (exact25 paths)
```

All prior Batch evidence is historical/unchanged. Iteration, Complete Final,
Acceptance, candidate commit, and package export are Not Run/Not Verified.
No Production, Application, package, export, stage, commit, push, or network
action occurred.

## Historical: Batch C3 Checkpoint — Exact75 Package Boundary

At `2026-09-04T07:30:37+09:00`, the bounded C3 package slice is complete for
Orchestrator review. Only the two package scripts and this Report/STATE pair
moved. The required-inventory expected list and the export loop both contain
the exact D153-ordered seven paths immediately after the existing C3
orchestration entries and before the C3 key adapter:

```text
src/Internal/Audit/Relay/AuditRelayOutcome.php
src/Internal/Audit/Relay/AuditRelayFailure.php
src/Internal/Audit/Relay/AuditRelayException.php
src/Internal/Audit/Relay/AuditRelayPersistence.php
src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
```

The package inventory is exact75 in both lists. The required-inventory oracle
retains one validation function, one ordered export loop, exactly two
top-level Git/Composer validation calls, bounded temporary cleanup, and
candidate-independent locally-repinned rejection. Its valid mutation fixtures
reject missing, added, reordered, duplicated, new-C4-entry omission,
call-removal, wrappers, early return, alias/shopt resolution override,
function redefinition, here-doc/quoted/non-executed decoys, and a locally
repinned candidate before runtime/SHA. The new omission fixture removes the
actual C4 `AuditRelayOutcome.php` entry. It rejects `29` mutations: `15`
inventory mutations and `14` call-contract mutations.

Changed C3 files and final hashes:

```text
9f983932f02761456133a534bd3ff95334e8311a51646fbcbcf3e37132960f1f  tests/Consumer/framework-package-required-inventory.sh
9ff12e3e2f921fba27165c112ddd839fd6bdc1c50926d30dd689a69410c1bd63  tests/Consumer/framework-package-export.sh
```

Literal C3 commands and results:

```text
bash tests/Consumer/framework-package-required-inventory.sh -> exit 0 (exact75 ordered paths; 14 call-contract mutations; 29 total mutations)
bash tests/Consumer/version-baseline.sh -> exit 0 (published=1.2.0 historical=1.1.0)
bash -n tests/Consumer/version-baseline.sh tests/Consumer/framework-package-required-inventory.sh tests/Consumer/framework-package-export.sh -> exit 0
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

`tests/Consumer/framework-package-export.sh` was not executed, as required
for an uncommitted worker tree. No package archive, network, stage, commit,
push, Production, Application, Relay, Architecture, or unrelated file action
occurred. The accepted C2 Production correction remains byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`; the C2
Architecture aggregate remains
`cfac4a5a874e18e0729b651e6b383c87dffc35686895f651886c431e17cdfcac`, with all
six individual C2 Architecture hashes unchanged in the checkpoint below.
Batch D, real PostgreSQL, the Iteration Gate, Complete Final, Acceptance,
candidate commit, and clean same-SHA package export remain Not Verified.

## Historical: Batch C2 Checkpoint — Relay Architecture Oracle

At `2026-09-04T07:20:03+09:00`, the C2 Architecture slice is complete for
Orchestrator review. The accepted `AuditOutboxRelayRuntime.php` correction
remains byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`. The
new `AuditRelayArchitectureTest.php` owns a hard-coded authority map for the
exact seven Relay files, declarations, enum cases/properties, callable
signatures, dependencies, constructors, direct calls/receivers, Application
composition graph, activation, class-string, and forbidden-capability rules.
It includes valid `TOKEN_PARSE` mutation fixtures for extra callable/property,
candidate-self-baseline, shared whole-path bypass, constructor `self`, direct
literal-concat class strings, target-aware callable-array/ReflectionMethod/
parenthesized indirection, validator caller removal/extra/receiver mutation,
and DBAL/clock/UUID/nonce/key capability additions. The actual `allSourceFiles()`
scan gates Relay dynamic calls and target-aware indirect calls across `src`;
generic unrelated dynamic code is not globally rejected. The five shared
Architecture maps now contain only the exact C4 path/target/kind/count deltas,
including `validateLogical` and `new self(...)` constructor resolution.

Changed C2 files:

```text
tests/Architecture/AuditRelayArchitectureTest.php
tests/Architecture/AuditOutboxStateArchitectureTest.php
tests/Architecture/AuditOutboxStorageArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditStoreArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
```

Literal C2 commands and results:

```text
docker compose run --rm app mago format --check src tests -> exit 0 (all files already formatted)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Architecture/AuditRelayArchitectureTest.php -> exit 0 (6 tests / 2,767 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Architecture/AuditRelayArchitectureTest.php tests/Architecture/AuditOutboxStateArchitectureTest.php tests/Architecture/AuditOutboxStorageArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditStoreArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php -> exit 0 (50 tests / 1,772,610 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (44 tests / 10,188 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (36 tests / 9,026 assertions / 0 PHPUnit warnings)
docker compose run --rm app mago lint --reporting-format count tests/Architecture/AuditRelayArchitectureTest.php -> exit 0 (0 errors / 132 warnings / 19 help)
docker compose run --rm app mago lint --minimum-report-level error --reporting-format count tests/Architecture/AuditRelayArchitectureTest.php tests/Architecture/AuditOutboxStateArchitectureTest.php tests/Architecture/AuditOutboxStorageArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditStoreArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php -> exit 1 (70 errors in the existing shared Architecture files; the new C2 file has 0 errors; no baseline was regenerated)
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

Current C2 hashes are recorded immediately below. The accepted Relay
Production correction remains unchanged; the C2 edits are Architecture tests
only. Batch D and the Iteration Gate remain Not Verified. No stage, commit,
push, network, package export, real PostgreSQL fixture, activation, or public
Documentation change occurred.

```text
3a0a25cec645d5669705dc627d1970e5595dd816641e96bffdcb8962390696fb  tests/Architecture/AuditRelayArchitectureTest.php
06db1df7141ea10109ddec65c5c33878a3b8a7e0c5640cf287272f9773316f31  tests/Architecture/AuditOutboxStateArchitectureTest.php
385fc104f44d49d478fba63b6cc0f544e16966afca90e5cb954638942abdf75d  tests/Architecture/AuditOutboxStorageArchitectureTest.php
70fe9abb763d1275767684f6ab1c9a20a7986b34ac4040af95c502987a8b7f03  tests/Architecture/AuditRecordArchitectureTest.php
aeb6b99e1bf5991da3d4b4e2165bea1063654c697c61d06de04292406fd4a669  tests/Architecture/AuditStoreArchitectureTest.php
78c86a9e4f2946581fc60eb1536837466642927b7b7b201be3cc03048bb884f1  tests/Architecture/AuditDeliveryArchitectureTest.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33d6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
```

The ordered C2 Architecture/Relay hash aggregate is
`cfac4a5a874e18e0729b651e6b383c87dffc35686895f651886c431e17cdfcac`.

## Historical: Batch C1 Checkpoint — Application Relay Composition and Nonactivation

At `2026-09-04T05:59:18+09:00`, the accepted
`AuditOutboxRelayRuntime.php` correction remained byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`. This
slice changed only the two Application composition sources and their two
Application tests. Relay Production sources and Relay tests remain unchanged.

`ApplicationAuditRuntime` now carries exactly one private
`AuditOutboxRelayRuntime`, accepts it as the final `fromComposition()` argument,
and exposes `relay(): AuditOutboxRelayRuntime`; all prior members, getters,
parameter types, order, and `SensitiveParameter` coverage remain fixed by
reflection tests. The composer constructs exactly one
`AuditIngressCanonicalizer`, one `PostgreSqlAuditRelayPersistence`, and one
`AuditOutboxRelayRuntime` after the existing Stores, reusing the existing
`$body`, `$validator`, `$recordStore`, `$outboxStore`, `$operations->connection`,
and Outbox configuration identities.

The composition test observes the relay's Connection/configuration, adapter
Store identities, canonicalizer codec/validator identities, one-time provider
and database-factory resolution, exact constructor source counts, and zero
external composer or Audit relay activation references. No current runtime
activation, Relay behavior, Architecture/package work, or real PostgreSQL
fixture was added.

Literal C1 commands and results:

```text
docker compose run --rm app mago format src/Internal/Application/ApplicationAuditRuntime.php src/Internal/Application/ApplicationAuditRuntimeComposer.php tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php -> exit 0 (all formatted)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php -> exit 0 (8 tests / 1,162 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (44 tests / 10,188 assertions / 0 PHPUnit warnings)
docker compose run --rm app mago format --check src tests -> exit 0 (all files already formatted)
docker compose run --rm app mago lint --reporting-format count src/Internal/Application/ApplicationAuditRuntime.php src/Internal/Application/ApplicationAuditRuntimeComposer.php tests/Internal/Application/ApplicationAuditRuntimeTest.php tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php -> exit 0 (0 errors / 52 warnings / 5 help)
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

Current C1 hashes are:

```text
54ded77b05566926bde9610dbc559ff1f8e47da306d88c375cc4da9cc251ec7a  src/Internal/Application/ApplicationAuditRuntime.php
0b13ed33af979e342e63857d67f123cd9f6bc0072f8ae44f8abf97e687e8ea3f  src/Internal/Application/ApplicationAuditRuntimeComposer.php
4f95a88f4a133fe6b47735eb99b7877bbf6060084be854027a98306d366d6c76  tests/Internal/Application/ApplicationAuditRuntimeTest.php
8fe9866f6914b5670611456eb2d6e2218299f2eb89bb8116040c0ec01cf98c05  tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php
48a54a86a61a973fe3f427d6eb4b88ff99c614ecca0c42f5eb92c567d8ab6a2d  tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
```

The ordered fourteen-file Product/Test aggregate is
`03c3c3ee887f11858b54afae31da0ed359fca14c623d62d6dbdb307ecc84ab02`.
Batch C2/D, Architecture/package checks, real PostgreSQL fixtures, remaining
relay/recovery work, and the Iteration Gate remain Not Verified. No stage,
commit, push, network, package export, activation, or public Documentation
change occurred.

## Historical: Batch B2b2b2 Checkpoint — Connection Drift and Returned-State Evidence

At `2026-09-04T05:50:15+09:00`, the accepted
`AuditOutboxRelayRuntime.php` correction remained byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`. This
slice changed only the existing relay runtime test/fake/helper; Production
code and the B2b2a/B2b2b1 semantics remain unchanged.

The executable B2b2b2 cases cover all `relayOne` Connection entry guards
(auto-commit false, active nesting level 1, inactive level 1, level 2, and
each Connection API throwing), post-claim drift before malformed-claim
interpretation, pre-append drift after actual canonicalization, post-append
drift before malformed-result inspection, and pre-settlement drift after valid
committed-result inspection. Retry, poison, and relay each also cover
post-settlement drift before returned-state inspection, plus independent
wrong-valid-state and constructorless-corrupt-state settlement responses that
contract to C4 `storage_unavailable`.

Retry uses actual protection failure at attempt 1, poison uses actual
validation failure, and relay uses a matching committed append. The tests
assert exact Connection/persistence event order, fences, key/decode/append
reachability, zero later or fallback calls, settlement cardinality, and
non-exposure of actual raw key, protected ciphertext, key ID/material, token,
content hash, and canaries across exception/result surfaces.

Literal B2b2b2 commands and results:

```text
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php --filter 'testRelayEntryConnectionFailuresStopBeforeClaim|testRelayPostClaimConnectionDriftPrecedesMalformedClaimInterpretation|testRelayPreAppendConnectionDriftStopsAfterActualCanonicalization|testRelayPostAppendConnectionDriftPrecedesMalformedResultInspection|testRelayPreSettlementConnectionDriftFollowsValidCommittedInspection|testRelayPostSettlementConnectionDriftPrecedesReturnedStateInspection|testRelaySuccessfulSettlementReturnedStateFailuresContractStorageUnavailable' -> exit 0 (7 tests / 1,523 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (24 tests / 8,485 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (36 tests / 9,026 assertions / 0 PHPUnit warnings)
docker compose run --rm app mago format --check src tests -> exit 0 (all files already formatted)
docker compose run --rm app mago lint --reporting-format count tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (0 errors / 77 warnings / 48 help)
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

Current test SHA-256 is
`48a54a86a61a973fe3f427d6eb4b88ff99c614ecca0c42f5eb92c567d8ab6a2d`;
the accepted runtime SHA remains
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`; the
ordered ten-file Product/Test aggregate is
`6e3dae4504cf8926f058a22ef36b02edb19606e7c8808db1c1e567bb2c3f6aa3`.

Batch C/D, Application composition, Architecture/package checks, real
PostgreSQL fixtures, successful-return work beyond this slice, and the
Iteration Gate remain Not Verified. No stage, commit, push, network, package
export, activation, or public Documentation change occurred.

## Historical: Batch B2b2b1 Checkpoint — Settlement Failure Precedence Evidence

At `2026-09-04T05:37:15+09:00`, the accepted
`AuditOutboxRelayRuntime.php` correction remained byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`. This
slice changed only `tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php`
and its existing failure fake; B2b2a2 retry-attempt retention and all
Production behavior remain unchanged.

The new executable matrix runs `retry`, `poison`, and `relay` settlement for
each actual Outbox failure (`transition_conflict`, `resource_unavailable`,
`integrity_invalid`, `idempotency_conflict`, and `storage_unavailable`) plus a
unique unexpected throwable: 18 cases total. Retry uses an actual protection
sender failure at attempt 1, poison uses an actual decrypted validation sender
failure, and relay uses a matching committed append. Transition conflict
returns `stale`; resource failure throws C4 `resource_unavailable`; the other
typed failures and unexpected throwable throw C4 `storage_unavailable`, each
superseding the initiating sender result.

Every case asserts exact claim/decode/append/settlement order, the
pre-settlement Connection check and absence of a post-throw check, exact fence
arguments, one settlement with no fallback or later call, actual key/decode
and append reachability, and no sensitive raw key, ciphertext, key ID/material,
token, content hash, or unique unexpected canary on exception/result surfaces.

Literal B2b2b1 commands and results:

```text
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php --filter testSettlementFailureMappingSupersedesEachSenderPath -> exit 0 (1 test / 2,643 assertions / 0 PHPUnit warnings)
docker compose run --rm app mago format --check src tests -> exit 0 (all files already formatted)
docker compose run --rm app mago lint --reporting-format count tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (0 errors / 59 warnings / 28 help)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (17 tests / 6,962 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (29 tests / 7,503 assertions / 0 PHPUnit warnings)
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

Current test SHA-256 is
`509bba7063a29de599c1b0d89655044c258f45759ab839dfbd5141a788f6f731`;
the accepted runtime SHA remains
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`; the
ordered ten-file Product/Test aggregate is
`358cc6a3788296bc7844af78b58395eed7e5d9554a08ea2c38a7d1bc7876078c`.

Successful-return mismatch/connection-drift work, Batch C/D, Application
composition, Architecture/package checks, real PostgreSQL fixtures, and the
Iteration Gate remain Not Verified. No stage, commit, push, network, package
export, activation, or public Documentation change occurred.

## Historical: Batch B2b2a2 Checkpoint — Sender Failure and Successful Settlement Evidence

At `2026-09-04T05:26:44+09:00`, the accepted
`AuditOutboxRelayRuntime.php` correction remained byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`, and
only `tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php` was extended.
This bounded slice proves sender failures settle successfully; settlement-failure
mapping and all broader B2b2, Batch C/D, and Iteration Gate work remain outside
the slice.

The actual canonicalizer protection matrix mutates the authenticated ciphertext
tag, directly observes `AuditProtectionException` after one Outbox key lookup,
and then runs the runtime with the same actual canonicalizer. Attempt `1` of
`maxAttempts = 2` returns `retried` with a successful `retry` settlement whose
state remains `pending` at attempt `1`; attempt `2` returns `poisoned` with a
successful `poison` settlement at attempt `2`. Both cases prove key ID,
purpose, material, and decode/open reachability, one settlement, zero append,
and exact Connection/persistence ordering.

The validation matrix decrypts a valid durable system ingress and then uses a
separate authority-owned `AuditRecordValidator` backed by an empty immutable
Catalog lookup. The direct canonicalizer call observes actual
`AuditValidationException::SemanticInvalid`, and each runtime attempt (`1` and
`2`) returns `poisoned` after exactly one poison settlement with zero append.

The append matrix sends every actual `AuditStoreFailure` through
`appendCanonical()`: `resource_unavailable` and `storage_unavailable` retry at
attempt `1` and poison at attempt `2`; `idempotency_conflict`,
`segment_conflict`, and `integrity_invalid` poison at both attempts. Each
`AuditStoreException` is captured as the exact object thrown by the fake after
the actual canonicalizer completed, with one successful settlement, exact fence
arguments, returned state/attempt, and no later calls.

The committed-result matrix supplies actual valid `AuditStoredRecord` values
with each of `recordId`, `partitionId`, `tenantBinding`, and `contentHash`
mutated independently; each is rejected before relay and poisons once. It also
reaches the real `AuditAppendResult::record()`,
`AuditStoredRecord::toArray()`, and `AuditAppendResult::inserted()` inspection
failures through constructorless malformed values, proving the same poison
path. All retry/poison cases assert exact Connection checkpoints, persistence
call order, one settlement/no second settlement, fence record ID/tenant/
partition/version/token, resulting state and attempt, actual key/decode
observations, and absence of the raw idempotency key, original/corrupt
protected ciphertext, key ID/material, fencing token, content hash, and caught
throwable from debug, export, JSON, serialization, and trace surfaces.
The five exception-surface exports use a local error-handler-backed
`safeVarExport()` helper so circular-reference warnings remain suppressed
without changing the no-leak assertions.

Literal B2b2a2 commands and results:

```text
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php --filter 'testActualProtectionFailureSettlesRetryBelowMaxAndPoisonAtMax|testActualValidationFailurePoisonSettlesAtBothAttempts|testEveryAppendStoreFailureUsesTheExactRetryOrPoisonSettlement|testCommittedResultBindingAndInspectionFailuresPoisonExactlyOnce' -> exit 0 (4 tests / 2,289 assertions / 0 PHPUnit warnings)
docker compose run --rm app mago format --check src tests -> exit 0 (all files already formatted)
docker compose run --rm app mago lint --reporting-format count tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (0 errors / 54 warnings / 20 help; the former 12 targeted errors are resolved)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (16 tests / 4,319 assertions / 0 PHPUnit warnings)
docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (28 tests / 4,860 assertions / 0 PHPUnit warnings)
git diff --check -> exit 0
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

The ordered ten-file Product/Test individual SHA-256 values are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33d6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
455eaf09aae4d0f76d24ba14b89abcdf89bbf4d0e12fd951c13bc0726364c6ff  tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
```

The ordered `sha256sum`-output aggregate is
`50f4e82ea02d502487eaa9e318c25d033762d5dd1acce66769708094ecb8291c`.
Immutable exact12 aggregate remains
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`;
package required-inventory/export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` /
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.

No Production correction was needed. Remaining B2b2 settlement-failure work,
Batch C/D, Application composition, Architecture/package checks, real
PostgreSQL fixtures, and the Iteration Gate remain Not Verified. No stage,
commit, push, network, package export, activation, or public Documentation
change occurred.

## Historical: Batch B2b2a1 Checkpoint — Claim and Append Rejection Evidence

At `2026-09-04T04:42:32+09:00`, the accepted
`AuditOutboxRelayRuntime.php` correction remained byte-identical at
`eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2`, and
only `tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php` was extended.
This bounded slice covers claim interpretation and the append exception seam;
remaining B2b2 settlement and full relay failure evidence is not included.

The claim matrix proves that `AuditOutboxLookupResult::notFound()` returns
`idle` after exactly one successful claim and post-claim Connection check,
with no key decode or later persistence call. Every
`AuditOutboxStoreFailure` maps once: `transition_conflict` returns `stale`,
`resource_unavailable` throws C4 `resource_unavailable`, and
`integrity_invalid`, `idempotency_conflict`, and `storage_unavailable` throw
C4 `storage_unavailable`. An unexpected `claim-canary` throwable also maps to
fixed `storage_unavailable`; thrown lookup paths perform no post-failure
Connection check, append, retry, poison, or relay.

The corrupt-claim matrix has fifteen separate actual-value mutations:
wrong state; missing and corrupt returned record; attempt `0` and
`maxAttempts + 1`; version `0` and `MAX_SAFE_INTEGER + 1`; null and malformed
fencing token; missing expiry; non-null `availableAt`; and malformed record ID,
partition, content hash, and tenant. Each returns C4 `storage_unavailable`
after the post-claim state check, then performs zero canonicalizer key opens,
append calls, or settlement calls. The exception is checked for absence of
the fixture’s actual raw idempotency key, protected ciphertext, fencing token,
and content hash across `print_r`, `var_export`, JSON, serialization, and both
trace surfaces.

The append seam uses the actual canonicalizer and a valid claimed system
ingress. After one Outbox-purpose key open and capture of the canonical
logical object, `appendCanonical()` is made to throw each of
`AuditProtectionException`, `AuditValidationException`, and an unexpected
`append-canary` throwable. All three map to C4 `storage_unavailable`, call
append exactly once, perform no retry/poison/relay settlement and no
post-append state check, and preserve the no-leak contract. The actual key ID
and material, raw idempotency key, protected ciphertext, fencing token, and
content hash are checked on every exception surface; the unexpected canary is
also checked directly.

Literal B2b2a1 commands and results:

```text
docker compose run --rm app php vendor/bin/phpunit --filter testRelayClaimMapsEveryOutboxFailureAndUnexpectedWithoutLaterCall tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (1 test / 171 assertions)
docker compose run --rm app php vendor/bin/phpunit --filter testRelayRejectsEveryCorruptClaimBeforeCanonicalizerAndSettlement tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (1 test / 840 assertions)
docker compose run --rm app php vendor/bin/phpunit --filter testRelayMapsAppendProtectionValidationAndUnexpectedToStorageWithoutSettlement tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (1 test / 216 assertions)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 1 initially (runtime test needed formatting)
docker compose run --rm app mago format tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (one file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (12 tests / 1,970 assertions)
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (24 tests / 2,511 assertions)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' -> exit 0
```

The ordered ten-file Product/Test individual SHA-256 values are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
eca4223b11a957726de907cbd207709b977dd22b9165a64822446088710eade2  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
183ac3cc914ec4c6ea633d5f159e419de3295cd95270c15f66aa1ec2733b6bf2  tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
```

The ordered `sha256sum`-output aggregate is
`37f7de71476debda1768ee5e6b220e8665683679fd19279496d8dce22c2a4c5e`.
Immutable exact12 aggregate remains
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`;
package required-inventory/export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` /
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.

The only source/test path changed in this slice is the existing authorized
runtime test. Batch B2b2 settlement branches not covered here, Batch C/D,
Application composition, Architecture/package checks, and the Iteration Gate
remain Not Verified. No stage, commit, push, network, package export,
activation, or public Documentation change occurred.

## Historical: Batch B2b1 Checkpoint — Positive Relay Evidence

At `2026-09-04T04:12:53+09:00`, the accepted recovery evidence was
preserved and the existing runtime test gained only the positive relay
matrix. The result-canary remains embedded in a corrupt returned lookup
record whose state is actually interpreted before the fixed C4 contract;
the caught exception surfaces continue to assert that the canary and all
other source canaries are absent.

The positive matrix uses the accepted actual canonicalizer and one shared
`ArrayObject` event sink. It covers system and tenant ingress, each with
`AuditAppendResult::inserted()` true and false. The four outcomes are
`relayed_inserted` and `relayed_existing` with the exact expected
system/tenant combinations. The observed order is six complete
Connection checkpoints with `claim`, one Outbox `key()` decode/open,
`append`, and `relay` in their mandated positions:

```text
connection:auto, connection:active, connection:level, claim,
connection:auto, connection:active, connection:level,
outbox-key:decode/open,
connection:auto, connection:active, connection:level, append,
connection:auto, connection:active, connection:level,
connection:auto, connection:active, connection:level, relay,
connection:auto, connection:active, connection:level
```

The authority-owned persistence fake records exactly one claim, append,
and relay, and zero recovery, retry, or poison calls. It captures the
canonical logical object passed directly to append, exact append
inserted-state, exact relay fence values (record ID, tenant reference,
partition ID, version `2`, and fencing token bytes), and the direct relayed
record. The valid append result is built with
`AuditStoredRecord::fromArray()` from the claimed protected-ingress
record ID, partition, tenant, and content hash; those four committed
stored fields are asserted byte-for-byte equal. The key source records one
Outbox-purpose open of `audit-outbox-key` and one active-key call used only
while constructing the fixture; no runtime active-key call occurs.
Logical, outcome, event, and fence surfaces are checked for absence of
the raw idempotency key and protected ciphertext.

No Production correction was needed. Relay failure/settlement precedence,
mutation resistance beyond recovery, composition/Architecture/package
checks, real PostgreSQL fixtures, and the Iteration Gate remain Not
Verified. No stage, commit, push, network, package export, activation, or
public Documentation change occurred.

Literal B2b1 commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 1 (runtime test needed formatting)
docker compose run --rm app mago format tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (one file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (8 tests / 733 assertions)
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (20 tests / 1274 assertions)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

The ordered ten-file Product/Test individual SHA-256 values are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
05fa13bac84fee45539b2d600e826e10e8c2ea603948cf0a213d98cc963101f7  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
4a185b5fdcd318243f248b4835df4a3d6c7efad5e6b4975b2dbe281d284dc699  tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
```

The ordered `sha256sum`-output aggregate is
`d2f6ed749220447595104b71f467684400996bca67dcb5a6782f7d262e613a54`.
Immutable exact12 individual values and aggregate remain unchanged at the
dispatch values, including
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`.
Package required-inventory/export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` /
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.

## Historical: Batch B2a Checkpoint — Recovery Runtime Evidence

At `2026-09-04T03:53:33+09:00`, recovery-only direct evidence was added in
`AuditOutboxRelayRuntimeTest.php`. No Production correction was required:
the accepted B1 runtime passed the new recovery matrix unchanged. The prior
Batch A Catalog cleanup remained a no-op because the accepted canonicalizer
test contains one Catalog assignment, not a duplicate.

The authority-owned recovery fake records every persistence method. Green
cases cover idle, recovered pending, recovery poisoned, transition stale,
resource, integrity, idempotency, storage, and unexpected recovery failures.
Each successful recovery performs exactly one `recoverNextExpired()` call and
then only the success post-state check; every path asserts zero claim, decode,
append, retry, relay, or poison calls. Entry state cases cover disabled
auto-commit, active level 1, inactive nesting levels 1 and 2, and each of the
three Connection state APIs throwing. A successful Store return whose
post-state drifts is rejected before `isFound()`/record/state interpretation.
Malformed `isFound()`, missing returned record, and an invalid returned
`leased` state each contract to fixed `storage_unavailable`. C4 exception
assertions cover exact failure/code/message, null previous, and absence of
the unique persistence/Connection/result canaries from debug, var_export,
JSON, serialization, and both trace surfaces. The source oracle asserts the
final readonly type, exact public surface, one recovery delegation, and no
runtime transaction, SQL, Clock, UUID, nonce, key, sleep, or random access.

Literal B2a commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php -> exit 0 (7 tests / 527 assertions)
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (19 tests / 1068 assertions)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

The ordered ten-file Product/Test individual SHA-256 values are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
05fa13bac84fee45539b2d600e826e10e8c2ea603948cf0a213d98cc963101f7  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
b4497c89c06317e91324de14c7a74af23ae3dfbcd94ad54cb77dc5bc91c24b01  tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
```

The ordered `sha256sum`-output aggregate is
`bd11d6adb0442098b35fe5a28a87824a59b3ea50bb9a3ead7b599e3eae71d6d8`.
Immutable exact12 individual values and aggregate remain unchanged at the
dispatch values, including
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`.
Package required-inventory/export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` /
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.

Relay B2b call-order and failure/settlement evidence, real PostgreSQL
crash/FIFO/concurrency/capacity fixtures, Application composition,
Architecture/package checks, and the Iteration Gate remain Not Verified.
No stage, commit, push, network, package export, activation, or public
Documentation change occurred.

## Historical: Batch B1 Correction Checkpoint — Runtime Contract Roots

At `2026-09-04T03:29:19+09:00`, the three independent Production corrections
were applied only to `AuditOutboxRelayRuntime.php`. Every applicable private
row/logical/tenant/identifier/hash/token/exception/Store-result parameter now
carries `#[\SensitiveParameter]`, including lookup results, claim arrays,
append results, settlement records, and typed failures. The accepted Batch A
canonicalizer test still contains exactly one Catalog assignment; there was no
duplicate byte to remove.

Successful relay claim interpretation now occurs inside `claimDetails()` after
the post-claim Connection check. That contraction includes `isFound()` and
returns null only for a not-found result (`idle`); a throwing or malformed
successful lookup is contracted to fixed C4 `storage_unavailable` without any
settlement or catch-path state recheck. The captured claim version now must be
within the exact positive SAFE-integer range through
`9_007_199_254_740_991`, before decode or settlement.

No other Production path changed. B2 direct runtime tests, composition,
Architecture/package work, real PostgreSQL fixtures, and the Iteration Gate
remain not started. Runtime behavior and mutation resistance are still Not
Verified beyond the accepted Batch A regression.

Literal correction commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 1 (one file needed formatting)
docker compose run --rm app mago format src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php -> exit 0 (one file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (12 tests / 541 assertions)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

The corrected runtime SHA is
`05fa13bac84fee45539b2d600e826e10e8c2ea603948cf0a213d98cc963101f7`; the
ordered nine-file Product/Test aggregate is
`bc5c8abe8b4db54106d68649ee569e46cbebee9979bd541c011470be29199078`.
Immutable exact12 aggregate and package required-inventory/export hashes
remain unchanged. The final `git diff --check` and staged0 commands both
exited `0`.

## Historical: Batch B1 Checkpoint — AuditOutboxRelayRuntime (superseded)

At `2026-09-04T03:25:33+09:00`, Batch B1 added only
`src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php`. The requested
duplicate Catalog assignment was already absent from the accepted
`AuditIngressCanonicalizerTest.php` (one assignment remains), so no accepted
Batch A test byte was changed for that cleanup.

The runtime has the exact four injected constructor dependencies and exact
`recoverOne()`／`relayOne()` public surface. Its private helper decomposition
is `assertConnectionState()`, `claimDetails()`, `recoveryOutcome()`,
`committedAppendInserted()`, `settleSenderFailure()`, `isRetryable()`,
`settleRetry()`, `settlePoison()`, `settleRelay()`,
`assertSettlementState()`, `lookupFailure()`, `settlementFailure()`,
`tenant()`, `isUuidV7()`, `isPartition()`, and `isHash()`.

The implemented mapping is finite and literal: recovery／claim
`transition_conflict` returns `stale`, Outbox `resource_unavailable` maps to
C4 `resource_unavailable`, and every other Outbox or unexpected lookup
failure maps to value-free C4 `storage_unavailable`; claim not-found is
`idle`, while recovered pending／poisoned states are
`recovered_pending`／`recovery_poisoned`. Protection failures and append
resource／storage failures retry below max and poison at max; validation and
all other typed append failures poison immediately. A successful retry or
poison is post-state checked and returns `retried`／`poisoned`. Settlement
transition conflict returns `stale`, settlement resource failure maps to
`resource_unavailable`, and every other settlement or unexpected failure
maps to `storage_unavailable`; no second settlement or fallback is attempted.
Successful canonical append binding mismatches and committed-result
inspection failures poison once, while a successful append never falls back
to retry or poison before relay acknowledgement. Inserted true／false maps to
`relayed_inserted`／`relayed_existing` after a relayed post-state check.

Each persistence success is immediately followed by a connection state check
before result interpretation. The runtime rejects disabled auto-commit,
active transactions, and nonzero nesting before the first call or before each
later persistence phase, and it performs no transaction-control, SQL, Clock,
UUID, nonce, key, loop, or configuration-resolution operation. Persistence
exceptions are mapped without a catch-path post-state recheck; unexpected
canonicalizer failures leave the lease untouched. All C4 exceptions have the
fixed message, null previous, and no original throwable.

Batch B1 direct runtime tests have intentionally not been added or run yet;
the complete recovery／relay／failure／settlement／call-order and no-leak
matrix is the next bounded B2 step. Therefore runtime behavior and its
mutation resistance are Not Verified at this checkpoint. Batch C composition,
Architecture/package checks, Batch D real PostgreSQL fixtures, and the
Iteration Gate remain not started.

Literal B1 commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 1 (one file needed formatting)
docker compose run --rm app mago format src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php -> exit 0 (one file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (12 tests / 541 assertions)
```

The final ordered nine-file Product/Test individual hashes are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
f495b5f65b7c756c7a12fb0b840edd5f5ee17ddc042b53b56de8e5fa68e918a3  src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
```

The ordered nine-file aggregate is
`4ca5d0d775ce33476800b9e4b8dc30536edb6de7cdb29055b833d343bbd152d1`.
The accepted Batch A eight-file aggregate remains
`14a24e08b30eb7f9748b97c62f49db9a7c418b046519c94c23dbe0bd7a3470c7`.
Immutable exact12 aggregate and package required-inventory／export hashes
remain unchanged.

## Historical: Batch A Closure Correction 2 — P2-2 Canonicalizer Evidence Final

At `2026-09-04T03:13:31+09:00`, the independent P2-2 evidence roots were
closed within the existing authorized canonicalizer test (with no Batch B or
PostgreSQL fixture work). The historical-key fixture now writes with the
historical Outbox key ID/material `audit-outbox-key-history`/32 `h` bytes and
reads with a materially different active Outbox key ID/material
`audit-outbox-key-current`/32 `n` bytes. The reader made zero `activeKey()`
calls and exactly one `key()` call; the captured request was the historical ID
`audit-outbox-key-history`, purpose `AuditOutboxRecord`, and the returned
material was exactly the historical 32 `h` bytes. Canonicalization remained
positive and durable, the protected ciphertext was byte-identical, and the
Record and Outbox roles remained distinct.

The authority-owned canonicalizer source oracle now requires exactly one each
of the captured ingress, envelope, Outbox unprotect, binding guard, Candidate,
logical validator, and Canonical construction operations in that order. It
also requires the exact fail-closed `hasBindings()` branch and logical-array
guard, and rejects any `contentHash()` recomputation or raw
`idempotencyKey` access. A deletion mutant removing the binding fail-closed
branch is applied to an independent source string and is killed by the oracle
because the required call and branch violations are reported.

The non-durable policy, logical-shape, Catalog, and evidence mutations are
truthfully labeled as protected-body codec-layer failures: the envelope codec
and, where applicable, its nested record-codec validator reject them during
`unprotectOutbox()` before the canonicalizer's direct Candidate/validator
path. The positive path and source oracle separately prove that direct
Candidate -> `validateLogical()` -> Canonical construction is single and
ordered. The remaining direct negatives are isolated and fail with the fixed
value-free `protection_failed` exception with null previous; raw idempotency
keys are absent from returned values and exception debug, serialization
attempt, JSON, `var_export()`, and trace surfaces. Real PostgreSQL
crash/concurrency observations remain explicitly deferred to Batch D.

Literal commands and results for this correction:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 1 (one file needed formatting)
docker compose run --rm app mago format src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (one file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (12 tests / 541 assertions)
git diff --check -> exit 0
git diff --cached --quiet -> exit 0 (staged0)
```

The final ordered eight-file Batch A Product/Test individual hashes are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
e25b45593b352d10ca7f4531ae310b509cfd300473127563d9a9cf7c42702ee4  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
```

The ordered aggregate is
`14a24e08b30eb7f9748b97c62f49db9a7c418b046519c94c23dbe0bd7a3470c7`.
Immutable exact12 and package hashes remain unchanged; Batch B and the
Iteration Gate remain paused for Orchestrator review.

## Historical: Batch A Correction Checkpoint — P2-2 Canonicalizer Final (superseded)

At `2026-09-04T03:01:54+09:00`, P2-2 was closed within the authorized
`AuditIngressCanonicalizerTest.php` and the already-authorized unused-import
cleanup in `AuditIngressCanonicalizer.php`; no Batch B or PostgreSQL fixture
work started. The accepted system and tenant ingress fixtures both
canonicalize successfully. An instrumented fixture key source records that
the canonicalizer opens exactly one Outbox key, with no Record-purpose key
resolution; the historical key case succeeds with exactly one open of
`audit-outbox-key-history`, preserves the protected ciphertext, and proves the
Record/Outbox key IDs are distinct.

The direct matrix is isolated and Green: the source oracle finds one ordered
`protectedIngress` -> `toArray` -> `unprotectOutbox` -> envelope capture ->
Candidate -> `validateLogical` -> Canonical path with no raw idempotency-key
call; five individual outer binding substitutions (ID, partition, tenant,
ordinal, content hash); non-durable policy; logical-shape, Catalog, and
evidence failures; malformed BOPD; unavailable key; key-ID mismatch; and
ciphertext/AAD swaps. Every failure is the fixed `protection_failed` result
with null previous. Returned logical/row surfaces and exception debug,
serialization attempt, JSON, `var_export()`, and trace surfaces contain no raw
idempotency key. Real PostgreSQL crash/concurrency observations remain the
explicitly deferred Batch D fixture boundary.

Literal commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/AuditIngressCanonicalizer.php tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (12 tests / 541 assertions)
```

The corrected ordered eight-file Batch A individual SHA-256 values and
aggregate are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
c78a1341de7d8647c73fcdba49322910dbbfdc74a10c7d3efff78d5d8299095a  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
```

The ordered `sha256sum`-output aggregate is
`df8150464b466ca339d0707fa2e2d98ad44ea6820fe3e03f1a5375e4510d9489`.
Immutable exact12 aggregate remains
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4` and
package required-inventory/export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` /
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.
`git diff --check` is Green and staged0 remains Green. Batch B and the
Iteration Gate remain paused for Orchestrator review.

## Historical: Batch A Correction Checkpoint — P2-1 Adapter Evidence Final

At `2026-09-04T02:55:59+09:00`, the P2-1 evidence correction was reconciled.
The mutation array now visibly contains eight syntactically plausible,
authority-owned cases, including a poison argument reorder; the adapter test
reports `8/8` independent mutation kills. The canary-bearing
`AuditRelayException::__unserialize()` failure is now inspected directly via
its caught exception, debug, serialization, JSON, `var_export()`, and trace
surfaces, with the unique `lease-token` canary absent and previous null.

Literal scoped commands and results:

```text
docker compose run --rm app mago format --check src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (all files already formatted)
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (4 tests / 252 assertions)
```

The current ordered eight-file Batch A Product/Test aggregate is
`28a84bf1e1c420effd3a7dc5e107ce605d1568eb2c9617bb5c235d2efd36df88`; the
adapter test SHA is
`c9c5b7c332dd4b39ff1dc6a88c225d54fa4666f3f5fa6f815b74495a62722c81`.
Immutable exact12 aggregate and package hashes remain unchanged. This final
P2-1 checkpoint supersedes the earlier 02:43 report label, which claimed 8/8
while its then-visible mutation list had seven entries. Canonicalizer P2-2,
Batch B, and the Iteration Gate remain paused.

## Historical: Batch A Correction Checkpoint — P2-1 Adapter Evidence

At `2026-09-04T02:43:54+09:00`, the requested P2-1 correction was completed
without changing the adapter Production body. The direct adapter test now uses
an authority-owned six-method expected return/body map, exact public return and
parameter signatures, nullability, and `SensitiveParameter` attributes for both
the seam and the PostgreSQL adapter. Its independent mutation set rejects
wrong lookup target, one-call lookup wrapper, append result/inserted-flag
replacement, retry argument reorder, relay argument substitution, caught
exception translation, and transaction control: `8/8` mutation kills. The
constructorless accepted Store invocation checks observe the Store failure
types, fixed `storage_unavailable` code, null previous, and absence of C4
exception translation. Because both accepted PostgreSQL Store classes are
`final` and their constructors require the production graph, a test-double
capture of a successful result object or a preselected Store throwable is not
available in Batch A; exact direct-return bodies and the independent mutation
oracle are the non-vacuous identity evidence, with that boundary retained for
the Report.

Scoped commands and results:

```text
docker compose run --rm app mago format src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (one test file formatted)
docker compose run --rm app mago format --check src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0
docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php -> exit 0 (4 tests / 244 assertions)
```

The first `mago format --check` command used the correct adapter path
`src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php`; no path outside
the eight Batch A Product/Test files and the worker Report/STATE moved. Current
Batch A ordered Product/Test aggregate is
`0b1bf5c5d1daf9910ecc724ada875e4b95612473cb7f445a675565b2d7578730`.
The changed adapter-test SHA is
`f401247ac1ceae1603df0d0f37e38d58cb0785b2a59f12019339026c824faccf`; removing
the unused canonicalizer import changed its SHA to
`94a6a209b037a9ac380567ac925212af1257b63a27f57a23fcffe5be4062db77`.
Immutable exact12 aggregate remains
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`, package
required-inventory and export hashes remain
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` and
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`, and
`git diff --check` plus staged0 remain Green. P2-2 canonicalizer correction is
next; Batch B and the Iteration Gate remain paused.

## Historical: Worker Start Checkpoint

At `2026-09-04T02:12:24+09:00`, the configured Production Worker started from
the acknowledged clean freeze on branch
`agent/p23-001-blackops-1-3-feasibility` at HEAD
`41abbfe891f226fd1756fc6e7905e23f762604a5`. The worktree is clean and staged0;
C3 `b7b352a8e4c86a45822351c108772367515654bd` is an ancestor and the
C3-to-HEAD Product/Test/docs/migration delta is zero. Fresh General／Architecture／package,
Transaction／PostgreSQL, and Documentation／Release preflight lanes are Green
with `P1/P2/P3=0/0/0`.

Immutable exact12 individual SHA-256 values and ordered aggregate
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4` match the
dispatch. Package required-inventory and export script hashes match
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` and
`2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.
Product/Test work was unstarted at this checkpoint; Batch A is the first
bounded implementation batch. No stage, commit, push, network, package export,
runtime activation, or public Documentation change is permitted.

## Historical: Batch A Checkpoint

Completed at `2026-09-04T02:24:56+09:00`. Batch A is complete and ready for
Orchestrator review. It added only the six Relay source types and the two
authorized direct tests listed below; Batch B runtime behavior has not started.

Product/Test files changed in this batch, in Task order, are:

```text
src/Internal/Audit/Relay/AuditRelayOutcome.php
src/Internal/Audit/Relay/AuditRelayFailure.php
src/Internal/Audit/Relay/AuditRelayException.php
src/Internal/Audit/Relay/AuditRelayPersistence.php
src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
```

The exact Batch A individual SHA-256 values and ordered aggregate are:

```text
9e6c0a3257a16612af04c8eb1d75b190c418a6a8eeac3e990b63ca6f7919c187  src/Internal/Audit/Relay/AuditRelayOutcome.php
e496d5747920caf6c170a1aa14650a1376740fb13ef82b4c8fc030f20904a815  src/Internal/Audit/Relay/AuditRelayFailure.php
ac9fd0a00510b7454199f681554b3d2a62f7c07e3aa3f47e91f2484df7c88385  src/Internal/Audit/Relay/AuditRelayException.php
f70df7580658021f26f5a1fa616df912eae7bceca2a4b129db79dd4827048d2f  src/Internal/Audit/Relay/AuditRelayPersistence.php
09d33de6fe33c4656bcb536bee90c445c32d16224ea5acfe44c65c82d6788c4f  src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
43ca25de49dde23500a83f4a69f101519de6d9be2dc17bb2abb69318070c7e27  src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
6b089362d46ee77b1b332569a1b464b362108b670e5bafbf23685ac8091bd441  tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
6e6ce60466c70fdbb6a23760eaf0ac525084e5731cb3dbc2b98165e818f4d04d  tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
```

The ordered Batch A aggregate is
`f59c4a3027659868d81802432acec98117d7d11bbf93ac8df7f35e7d66f6f754`.
The immutable exact12 aggregate was rechecked as
`e4666918bd9c7324df59179b5427cf851723ccd9d986180e58b1d7cf2d3c87b4`; all
dispatch individual values remain unchanged. Package script hashes remain
required-inventory
`cf0f45c60c2452bb469f7a6e24603b3cfd1c34ed0c265fd925d1e2a3512daeec` and
export `2ed38aab4a5b22431b976d3c38dc1c47daba8c5af24460d45f207c11dc26c732`.

The scoped formatter check exited `0` for the eight Batch A files. The focused
PHPUnit command
`docker compose run --rm app php vendor/bin/phpunit --stop-on-failure tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php`
exited `0` with `4 tests / 35 assertions`. `git diff --check` exited `0`, the
index remains staged0, and the only current paths are the eight Batch A files
plus this Report and `develop/STATE.md`. The first sandbox Docker invocation
was denied by the Docker socket permission boundary; the approved rerun passed.

Batch A proves the closed outcome/failure values and value-free exception,
exact persistence interface shape and one-to-one adapter call map, and system
protected-ingress reconstruction with outer-binding rejection and no raw
idempotency-key retention. It does not yet prove recover/relay order, failure
settlement, PostgreSQL crash/concurrency behavior, Application composition,
Architecture mutations, package exact75, or runtime activation. Batch B is the
next bounded action after Orchestrator review. The Iteration Gate, Complete
Final Gate, package export, commit, stage, push, network, release, deploy,
publication, and public Documentation changes remain Not Run/Not permitted.

## Historical: Initial Authority Summary

P23-003C1, P23-003C2, and P23-003C3 are Accepted.
The reviewed C3 candidate is committed at
`b7b352a8e4c86a45822351c108772367515654bd`, and its clean same-SHA
Git／Composer package export is Green.
No push was performed.

The first C4 DB and Documentation preflight returned authority-only Changes
Requested before Product work.
It identified an undefined bridge between the protected Outbox row and the
canonical Store, missing transaction ownership and sender／settlement failure
rules, undefined final-attempt append-before-ack semantics, contradictory
poison FIFO wording, stale C3 current handoffs, and a missing C4 release
ledger.

D153 and the C4 Task now close those roots.
They define an exact seven-type Internal relay graph, a direct-test persistence
adapter, one protected-ingress canonicalizer, bounded `recoverOne()` and
`relayOne()` entries, three separate Store-owned transactions, a complete
failure matrix, conservative final-attempt poison semantics, poison FIFO
release, exact75 package inventory, and zero current activation.

The first corrected Transaction／PostgreSQL preflight is Green. The parallel
Documentation／Release preflight found one current-truth P1 before Product
work: the immutable C1／C2 Reports still exposed pre-C3 Remaining／Next Action
handoffs as current. Their current-facing closeouts now state C1／C2／C3
Accepted and C4 current; prior checkpoints remain under Historical headings.

No Product or Test file has changed.
The resulting exact13 management packet is committed at the current
Orchestrator HEAD. The next step is a clean exact immutable refreeze followed
by fresh General and Documentation closure plus a bounded Transaction freeze
confirmation.

## Historical: Initial Authority Changed Files

This authority correction changes exactly thirteen management, specification,
Decision, Task, and Report files:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
- `develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md`
- `develop/orchestration/tasks/P23-003C4-audit-outbox-relay-and-recovery-runtime.md`
- `develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- this Report
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

No Product, Test, package script, migration, public Documentation, Community
Board, generated artifact, or release file has changed.

## Historical: Initial Authority Decisions and Assumptions

- C4 exposes bounded Internal `recoverOne()` and `relayOne()` seams, not an
  unbounded loop, daemon, scheduler, worker, command, route, or registration.
- Claim, canonical append, and relay acknowledgement remain three separately
  committed Store-owned roots.
- A dedicated Internal persistence interface and PostgreSQL adapter make every
  failure branch directly testable without changing Accepted C1／C2 Store APIs.
- The claimed protected ingress is deliberately opened again after the claim
  transaction commits, because C2 authenticates before CAS but returns only the
  protected record.
- Canonical `resource_unavailable` and `storage_unavailable`, plus protection
  failure, are retryable below max; idempotency, segment, integrity, validation,
  and committed-result mismatch poison immediately.
- An unexpected throwable leaves the lease untouched and contracts to fixed
  `storage_unavailable`.
- A final-attempt recovered poison may already have a committed canonical row;
  no C1／C2 reconciliation API is added.
- Both terminal states acknowledge the current ordinal and release the next.
  Earlier persistent-partition poison wording is superseded.
- The Stable generic Outbox runtime and `outbox:relay:*` commands remain
  byte-identical and are not Audit relay activation.
- Stable `1.2.0` remains current; BlackOps `1.3` remains unreleased.

## Historical: Initial Authority Commands and Results

### Authority research and initial preflight

- `git status --short`, `git branch --show-current`, and `git rev-parse HEAD`
  confirmed the clean accepted C3 starting point on branch
  `agent/p23-001-blackops-1-3-feasibility` at
  `b7b352a8e4c86a45822351c108772367515654bd`, staged0.
- Read-only source and authority inspection confirmed the Accepted Outbox
  transition API, canonical durable append API, protection decode seam,
  configuration identity, package exact68 baseline, Framework migration13,
  Community total18, and zero C3 runtime caller.
- Fresh Transaction／PostgreSQL preflight returned authority Changes Requested
  with two P1 candidates and one P2 candidate.
- Fresh Documentation／Release preflight returned authority Changes Requested
  with two P1 candidates and one P2 candidate.
- `git diff --check`, initial exact11 status, Product／Test delta0, Files Allowed
  exact23, worker exact25, and immutable exact12 static guards passed.
- `git commit -m "docs(audit): define C4 relay runtime boundary"` exited 0;
  the truth-only checkpoint and C1／C2 current-handoff correction were folded
  into that same management commit.
- The first corrected Transaction／PostgreSQL preflight returned Green
  `P1=0／P2=0／P3=0` on the original clean immutable freeze.
- The first corrected Documentation／Release preflight returned Changes
  Requested `P1=1／P2=0／P3=0` solely for stale current-facing C1／C2 Report
  handoffs. Product work remained stopped and those handoffs were corrected.
- No test, build, Docker, package export, browser, network, stage, commit, push,
  release, or deploy command was run during that read-only preflight.

### Not Run

- corrected fresh preflight
- configured Luna implementation
- focused checks and Iteration Gate
- implementation reviews and unchanged refreeze
- Complete Final Gate
- Acceptance and candidate commit
- clean same-SHA package export
- push, release, deploy, publication, generated artifact, browser, and CI

Generated artifacts and browser behavior are Not Verified.

## Historical: Initial Authority Acceptance Criteria

- [x] Accepted C3 commit and clean same-SHA package baseline identified
- [x] initial C4 DB and Documentation preflight completed before Product work
- [x] relay bridge and exact Connection／Store identity defined
- [x] sender and settlement failure matrix defined
- [x] final-attempt append-before-ack meaning defined
- [x] poison FIFO meaning aligned with Accepted C1／C2
- [x] bounded nonactivation and Stable generic Outbox separation defined
- [x] exact23 Product／Test, exact25 worker, immutable exact12, and package
  exact75 targets drafted
- [x] management packet final static review
- [x] management-only commit and clean immutable freeze
- [x] first corrected Transaction／PostgreSQL preflight Green
- [x] first corrected Documentation／Release preflight stopped on stale C1／C2
  current handoffs
- [x] stale C1／C2 handoffs corrected before Product work
- [ ] fresh three-lane preflight Green
- [ ] configured Luna exact25 implementation
- [ ] Iteration Gate Green
- [ ] fresh implementation reviews and unchanged refreeze
- [ ] Complete Final Gate Green
- [ ] P23-003C4 and parent P23-003C Acceptance
- [ ] candidate commit and clean same-SHA package export

## Historical: Initial Authority Remaining Issues

No Product implementation is authorized yet.
The corrected C1／C2 handoff bytes require a new immutable exact12 freeze and
fresh closure review before worker dispatch.

Within the larger BlackOps 1.3 Audit roadmap, P23-003D through P23-003G,
P23-004, P23-005, P23-006, release, runtime activation, generated artifacts,
and browser verification remain downstream.

## Historical: Initial Authority Suggested Next Action

From the corrected committed management packet, obtain fresh General and
Documentation／Release closure plus a bounded Transaction freeze confirmation
before dispatching the configured Luna worker.
