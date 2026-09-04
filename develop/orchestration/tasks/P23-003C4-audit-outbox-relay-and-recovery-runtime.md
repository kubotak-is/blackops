# P23-003C4: Audit Outbox Relay and Recovery Runtime

Status: Accepted — final candidate commit and clean same-SHA package export Green

Updated At: 2026-09-04T13:57:41+09:00

## Current Checkpoint

P23-003C1 through P23-003C4 and parent P23-003C are Accepted.
The final C candidate is committed at
`d5ab3476181058c5fb87b7c2c9476b6db76fef2e`; its clean same-SHA
Git／Composer package export is Green. No push was performed.

D153 and this Task remain the Accepted C4 authority and evidence contract.
Fresh General／Transaction／Documentation reviews and the Complete Final Gate
are Green. Full PHPUnit is `3,170 tests / 1,806,825 assertions`; Deptrac is
`1,036 analyzed / 0 violations / 0 skipped / 0 uncovered / 6,341 allowed /
0 warnings / 0 errors`; package inventory is exact75 with `29` rejected
mutations; migration `13 + 5 = 18`, Stable generic Outbox, public-source,
nonactivation, and exact-freeze guards are Green.

C4 adds a bounded Internal `recoverOne()`／`relayOne()` runtime over the exact
C3 Connection, configuration, protection, validator, canonical Store, and
Outbox Store graph.
It does not add or register a loop, daemon, scheduler, worker, command, route,
Container service, or current Application caller.

Stable `1.2.0` remains current. BlackOps `1.3` and all Audit Trail behavior
remain unreleased. P23-003D1 is the next bounded Task.

## Goal

Implement D153 so that:

- one expired lease can be recovered or one pending Outbox row can be claimed,
  decoded, appended to the canonical Store, and acknowledged;
- claim, canonical append, and acknowledgement are three separate
  Store-owned root transactions on the exact same Framework Connection;
- retries use the same protected logical record, `recordId`, and
  `contentHash`, so duplicate canonical append converges to the same stored
  sequence and hash;
- every sender, transition, settlement, crash, final-attempt, and transaction
  failure has one finite value-free outcome or exception;
- `poisoned` acknowledges the current ordinal and releases the next ordinal,
  while explicitly not proving canonical absence;
- the complete graph is directly and on real PostgreSQL testable but has zero
  current runtime activation.

## Model and Workflow

- Orchestrator／research／review:
  repository-configured `gpt-5.6-sol`／`max`.
- Production Worker:
  repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer:
  repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- Product work starts only after a clean management-packet commit and fresh
  General／Transaction／Documentation preflight are separately Green.
- The worker changes only exact Files Allowed, updates this Report／STATE, and
  does not stage, commit, push, publish, release, or deploy.
- Any Product／Test change invalidates all earlier Iteration／Final evidence.
- The Orchestrator alone reviews, accepts, commits, and runs the clean
  same-SHA package export.

Worker Process model metadata absence alone is not a blocker.
Explicit model／effort rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. Accepted C3 commit
   `b7b352a8e4c86a45822351c108772367515654bd` is an ancestor of the clean
   Orchestrator-supplied C4 packet HEAD.
3. The packet HEAD is clean and staged0 before worker dispatch.
4. P23-003C1／C2／C3, parent P23-003B, and their same-SHA package evidence
   remain Accepted.
5. The twelve immutable authority files and their ordered aggregate match the
   Orchestrator dispatch exactly.
6. Stable `1.2.0` remains current, Framework migration count remains `13`,
   Community Board total remains `18`, and BlackOps `1.3` remains unreleased.
7. Accepted package required inventory remains exact68 before C4.

Any drift stops Product work.
Do not repair a prerequisite inside worker scope.

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
14. `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
15. `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
16. `develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md`
17. Accepted C1／C2／C3 Reports
18. current relay, Outbox, record, protection, Application composition,
    Architecture, package, transaction, DBAL, and Stable generic Outbox source
    named here

D153 is the most-specific C4 authority.
D150 remains the parent serial split.
If authority and current code conflict, stop and record a Blocker; do not
resolve it only in Product code.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.outbox_relay.v1
publicSourceInventory=[]
publicRouteInventory=[]
publicCliInventory=[]
frameworkMigrationCount=13
communityMigrationCount=18
packageRequiredBefore=68
packageRequiredAfter=75
```

C4 adds only seven Internal package-visible types.
No Stable `1.2` Guide／Website claim, public API, route, command, example,
migration, Community Board behavior, generated artifact, release, or deploy
changes.
The existing Stable generic `outbox:relay:*` command and runtime remain
present and byte-identical, but are not C4 activation.

## In Scope

### 1. Exact C4 values and persistence seam

Implement the exact seven D153 types.

`AuditRelayOutcome` is exact eight and `AuditRelayFailure` exact three.
`AuditRelayException` has the fixed message, null previous, exact code, and
value-free representation surfaces.

`AuditRelayPersistence` is the exact direct-test seam.
`PostgreSqlAuditRelayPersistence` delegates one-to-one to the existing C2
Outbox Store and B2 canonical Store without translation, retry, transaction
control, or new resource access.
It has one Production construction in the C3 composer and no other
implementation or caller.

### 2. Protected ingress to canonical record

Implement `AuditIngressCanonicalizer` exactly as D153 §4.

It opens the claimed protected ingress once, verifies all outer bindings,
constructs exactly one `AuditLogicalRecordCandidate`, invokes the Accepted
validator once, and constructs exactly one `CanonicalAuditLogicalRecord`.
No raw idempotency key escapes or is retained.

The canonicalizer is the only new Production constructor authority for the
Candidate and Canonical types.
Every existing A2／B1／B2 caller and constructor map remains exact.

### 3. Bounded recovery

Implement `AuditOutboxRelayRuntime::recoverOne()`.

It requires auto-commit enabled, inactive level0 on the injected exact
Connection before the Store call and after a successful Store return.
It delegates once to `recoverNextExpired()` and returns only `idle`,
`recovered_pending`, `recovery_poisoned`, or `stale`, or throws one fixed C4
failure according to D153.

It performs no claim, decode, append, retry, acknowledgement, Clock, UUID,
sleep, loop, or transaction-control call.

### 4. Bounded relay

Implement `AuditOutboxRelayRuntime::relayOne()` in the D153 exact order.

The three persistent phases are:

1. Outbox claim commits;
2. canonical durable append commits;
3. Outbox relay acknowledgement commits.

The Connection is inactive level0 before each phase and after every successful
Store return.
Active level1 is rejected at the runtime boundary even though each individual
Accepted Store can participate in level1 for its own producer use case.

The committed canonical result must match the claimed row's ID, partition,
tenant, and content hash before acknowledgement.
Inserted and existing idempotent results produce distinct value-free outcomes.

### 5. Retry, poison, and settlement precedence

Implement the exact D153 §8 matrix.

Canonical `resource_unavailable` and `storage_unavailable`, plus protection
failure before append, are retryable below max and poison at max.
Idempotency conflict, segment conflict, integrity failure, logical validation
failure, and committed-result mismatch poison immediately.
Unexpected throwables leave the lease untouched and contract to
`storage_unavailable`.

For retry, poison, or relay settlement:

- transition conflict returns `stale`;
- resource failure throws C4 `resource_unavailable`;
- every other failure throws C4 `storage_unavailable`;
- settlement failure supersedes the initiating sender failure;
- no second settlement is attempted.

### 6. Crash and FIFO semantics

Prove claim-before-append, append-before-ack, ack-response-loss, and final
attempt states.
The same ID and content duplicate must retain one canonical row, sequence,
record hash, and protected bytes.

At final-attempt expiry, C2 may poison without querying the canonical Store.
The row may therefore already exist canonically.
Do not add reconciliation or claim-and-decode APIs to Accepted C1／C2.

Both terminal Outbox states acknowledge the current ordinal and release the
next ordinal.
Poison is an operational result, not a persistent partition halt.

### 7. Application composition

Extend only the exact C3 Application runtime and composer seams named by D153.

`ApplicationAuditRuntime` gains one relay member, one final
`fromComposition()` argument, and `relay(): AuditOutboxRelayRuntime`.
Every prior getter and identity remains unchanged.

The composer constructs the canonicalizer, persistence adapter, and runtime
once from the exact existing Connection, configuration, codec, validator, and
Store instances.
It performs no additional provider, database, Clock, UUID, or configuration
resolution.

### 8. Architecture and nonactivation

Add an exact C4 Architecture oracle and update only the existing shared Audit
oracles required by the new authorized graph.

Fix exact namespace inventory, type kind／modifiers／parent／implementations,
public and private callable signatures, properties, defaults, nullability,
SensitiveParameter attributes, dependency and constructor graphs, direct call
counts, and caller maps.

Reject alias, group-use, FQCN, same-namespace, literal／one-hop／two-hop class
strings, heredoc／nowdoc, dynamic／reflection／parenthesized invocation,
outside caller, extra file／type／implementation／dependency, and forbidden
capability mutations across all `src`.
Keep ordinary comments, strings, short names, and unconsumed decoys as positive
controls.

The oracle must use an authority-owned expected map, not a baseline derived
only from current candidate declarations.
Shared A2／B1／C1／C2／C3 tests may add exact C4 exceptions but must not exempt a
whole C4 path from reference and caller checks.

Prove exact zero current Application／Container／Console／HTTP／worker／scheduler／
maintenance／route callsite and exact zero Audit-specific CLI command.
Prove the Stable generic Outbox runtime, run／daemon commands, scheduler task,
and Guide claim remain positively present and unchanged.

### 9. Package inventory

Add the seven new Internal types in D153 order immediately after the existing
C3 orchestration entries and before the C3 key adapter in both package lists.
The required inventory becomes exact75.

Preserve the one exact validation function, one ordered expected list, one
ordered export loop, exact two top-level Git／Composer validation calls,
executable semantics, bounded temp cleanup, and candidate-independent repinned
mutation rejection.

Add／update mutations for missing, added, reordered, duplicated, new C4 entry
omission, call removal, wrapper, early return, alias, shopt, function
redefinition, here-doc decoy, and locally repinned candidate.

## Out of Scope

- unbounded loop, batch, sleep, heartbeat, signal, daemon, scheduler, worker,
  maintenance task, CLI, HTTP, route, or Container registration
- Stable generic Outbox runtime, sender, telemetry, scheduler, commands,
  configuration, migrations, tests, or public Documentation changes
- C1／C2 Store or state-machine Production changes
- B1／B2 canonical Store, codec, protection, schema, or migration Production
  changes
- C3 delivery, transaction, protected-read, key adapter, public provider, or
  configuration Production changes
- final-attempt reconciliation, new Outbox state／column／reason, persistent
  partition halt, or dead-letter API
- external transport, cross-database delivery, distributed transaction, or
  exactly-once claim
- P23-003D operation/authentication/authorization/control adapters
- P23-003E event producer registration and current runtime activation
- P23-003F checkpoint／anchor and P23-003G query／export／verify
- public API, migration, public docs, release note, route, command, Community
  Board behavior, generated artifact, browser verification, release, or deploy
- stage, commit, push, or package export from the worker tree

## Files Allowed

Production／Test is exact23 in this order:

```text
src/Internal/Audit/Relay/AuditRelayOutcome.php
src/Internal/Audit/Relay/AuditRelayFailure.php
src/Internal/Audit/Relay/AuditRelayException.php
src/Internal/Audit/Relay/AuditRelayPersistence.php
src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php
src/Internal/Audit/Relay/AuditIngressCanonicalizer.php
src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php
src/Internal/Application/ApplicationAuditRuntime.php
src/Internal/Application/ApplicationAuditRuntimeComposer.php
tests/Internal/Audit/Relay/AuditIngressCanonicalizerTest.php
tests/Internal/Audit/Relay/PostgreSqlAuditRelayPersistenceTest.php
tests/Internal/Audit/Relay/AuditOutboxRelayRuntimeTest.php
tests/Internal/Audit/Relay/PostgreSqlAuditOutboxRelayTest.php
tests/Architecture/AuditRelayArchitectureTest.php
tests/Internal/Application/ApplicationAuditRuntimeTest.php
tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php
tests/Architecture/AuditOutboxStateArchitectureTest.php
tests/Architecture/AuditOutboxStorageArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditStoreArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/framework-package-export.sh
```

Worker-managed exact2:

```text
develop/orchestration/reports/P23-003C4-audit-outbox-relay-and-recovery-runtime.md
develop/STATE.md
```

Worker scope is exact25.
No other path may move.

Orchestrator-only immutable exact12:

```text
develop/TODO.md
develop/decisions/150-audit-outbox-state-and-delivery-split.md
develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md
develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md
develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md
develop/orchestration/tasks/P23-003C4-audit-outbox-relay-and-recovery-runtime.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/spec/README.md
develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md
develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md
develop/orchestration/reports/P23-003C3-audit-delivery-orchestration-and-key-composition.md
```

The worker must not change an immutable path.

## Exact Failure Precedence

For `recoverOne()`:

1. Connection auto-commit／active／nesting state;
2. recovery transition／resource／other Store failure;
3. successful post-Store Connection state;
4. successful returned-record state matrix.

For `relayOne()`:

1. Connection auto-commit／active／nesting state;
2. claim transition／resource／other Store failure;
3. successful post-claim Connection state;
4. claimed-record state and binding;
5. protection／logical validation;
6. pre-append Connection state;
7. canonical append failure;
8. successful post-append Connection state;
9. canonical committed-result inspection and binding;
10. pre-settlement Connection state;
11. relay／retry／poison settlement failure;
12. successful post-settlement Connection state.

A successful Store return is post-checked before its result is interpreted.
A thrown Store failure is never masked by a catch-path state check.
A successful claim with untrusted fence coordinates contracts to
`storage_unavailable` without settlement. A failure while inspecting the
committed canonical result is non-retryable and poisons the claimed row.

## Required Fixtures

### Values, adapter, and canonicalizer

- exact enum values, exception message／previous／code, type modifiers,
  interfaces, public／private callables, properties, attributes, and no-leak
  surfaces;
- persistence adapter exact-one delegation, same result identity, same
  exception identity, argument identity／bytes, and zero translation／resource／
  transaction calls;
- system and tenant protected ingress to exact Canonical record;
- one codec open, Candidate construction, validation, Canonical construction,
  and no idempotency-key exposure;
- wrong outer ID／partition／tenant／ordinal／content hash, non-durable policy,
  logical shape／Catalog／evidence failure, malformed BOPD, key unavailable,
  key-ID mismatch, and ciphertext／AAD swap;
- historical Outbox key success and record／Outbox key-role separation.

### Recovery

- idle, recovered pending, recovery poisoned, and stale outcomes;
- expiry minus one microsecond, equality, and plus one microsecond;
- attempt below max and at max;
- resource, storage, integrity, unexpected, and returned-state mismatch;
- exact one Store call, one C2 Clock capture, and zero claim／decode／append／
  settlement after recovery;
- poison terminal state releases the next ordinal.

### Relay and failure matrix

- exact order `claim commit -> decode -> canonical append commit -> ack commit`;
- system and tenant, inserted true and existing false, and committed ID／
  partition／tenant／content-hash equality;
- every `AuditStoreFailure`, protection failure, validation failure,
  committed-result mismatch, and unexpected throwable;
- attempt 1 and max for each retryable class;
- retry／poison／relay transition, resource, storage, integrity, and unexpected
  settlement failure precedence with no second settlement;
- stale token／version, lease expiry during append, relay-versus-recovery, and
  no fallback after successful append;
- Connection auto-commit false, active level1, inactive nonzero, level2,
  wrong composer Connection identity, and post-claim／post-append／post-ack
  drift, each with exact later-call zero matrix;
- no runtime begin／commit／rollback／savepoint／SQL／Clock／UUID／nonce／key
  resolution.

### Crash, capacity, and PostgreSQL

- crash after claim before append;
- crash after append before ack, followed by existing canonical append and
  eventual relay with one canonical sequence／hash／ciphertext;
- final-attempt crash followed by recovery poison while preserving any
  already-committed canonical row;
- ack committed then response lost;
- two Connections racing the same partition head;
- ordinal 2 before 1 rejected, same-partition serialization, cross-partition
  progress, and poison releasing the next ordinal;
- actual maximum logical record relay and cap+1 rejection without truncation;
- real PostgreSQL setup includes both Accepted Outbox and canonical schemas and
  observes all three transactions separately;
- every real fixture has bounded wait／cleanup and direct persisted row, state,
  sequence, hash, token, version, and transaction assertions.

### Application, Architecture, package, and release

- one exact Connection, configuration, codec, validator, canonical Store,
  Outbox Store, canonicalizer, persistence adapter, and relay runtime identity;
- every prior C3 Application runtime member and provider resolution count
  unchanged;
- exact seven C4 type inventory and exact caller／constructor／dependency maps;
- all mutation families in D153 §12, including extra implementation,
  candidate-self-baseline, and whole-path shared-test bypass negatives;
- exact75 order in required-inventory and export loop, exact-two top-level
  calls, and all package mutations including locally repinned candidates;
- PublicApi exact zero for C4, Deptrac `0/0/0`, Stable `1.2.0`, migration13,
  Community18, `StoragePurpose::cases()` exact9, no public source／route／CLI／
  docs delta, and no forbidden management IDs in PHP comments;
- Stable generic Outbox runtime／commands／scheduler／Guide positive and
  byte-identical; Audit-specific activation exact zero.

## Iteration Gate

Run once after all exact23 Product／Test changes and Report／STATE updates are
complete.
Stop on first failure.

1. Freeze branch, HEAD, staged0, status exact25, exact23 ordered SHA aggregate,
   immutable exact12 individual hashes／aggregate, Accepted prerequisite
   hashes, and package-script hashes.
2. Prove exact23 and exact25 counts, unique paths, file existence, and
   add／remove／duplicate／wrong-hash negatives.
3. Run scoped `mago format --check` for every changed PHP source and test.
4. Run focused PHPUnit for all four new Relay tests, all six affected Audit
   Architecture tests, both Application tests, PublicApi Architecture, and
   the accepted C2／B2 direct fixtures needed for regression.
5. Run all existing Audit Node golden scripts.
6. Run Deptrac with `--report-uncovered --fail-on-uncovered` and require
   violations／skipped／uncovered／warnings／errors all zero.
7. Run `version-baseline.sh`, required-inventory exact75, Bash syntax, and all
   package mutation oracles.
8. Prove migration13／Community18, `StoragePurpose` exact9, Stable generic
   Outbox positives, zero Audit-specific activation／public source／route／CLI／
   docs delta, Guide／Website no-diff/status, forbidden-ID scan,
   `git diff --check`, staged0, and post-gate exact freezes.

Record exact command lines, exits, test／assertion counts, Deptrac counters,
mutation counts, individual and aggregate hashes, and Not Verified boundaries
in the Report.

Do not run `framework-package-export.sh` from the uncommitted worker tree.
Its Git side archives HEAD and cannot prove newly uncommitted C4 files.

## Complete Final Gate

After Iteration Green, fresh independent General／Transaction／Documentation
implementation reviews, and an unchanged exact25 refreeze, restart from
command 1 and stop on first failure:

1. full `mago format --check src tests`;
2. `composer validate --strict`;
3. full Mago lint and baseline verification;
4. full Mago analyzer at error level and count format;
5. full PHPUnit;
6. Deptrac `--report-uncovered --fail-on-uncovered`;
7. all Audit Node goldens;
8. version baseline and package exact75 required-inventory oracle;
9. Community Board clean-install only if the unchanged current journey is
   available without changing scope;
10. shell syntax, Stable generic Outbox positives, docs directory／no-diff,
    Audit-specific nonactivation, forbidden-ID, `git diff --check`, staged0,
    exact25, exact23, immutable exact12, prerequisite, and package post-guards.

No evidence may be reused after a Product／Test change.

## Acceptance Criteria

- D153 exact seven-type graph and exact23 Product／Test scope are complete.
- Claim, append, and ack are three separately committed Store-owned roots on
  the exact C3 Connection.
- The protected ingress is reauthenticated and reconstructed as one canonical
  logical record with exact outer binding.
- Same ID／content duplicate converges to one canonical sequence／hash and can
  be acknowledged after append-before-ack recovery.
- Every failure and settlement branch follows the exact finite matrix.
- Final-attempt poison semantics and poison FIFO release are proven.
- The runtime rejects ambient transaction participation and controls no
  transaction itself.
- Direct, composed, mutation-resistant, and real PostgreSQL evidence is Green.
- Exact75 package, PublicApi, Deptrac, Stable／release, migration, generic
  Outbox preservation, docs, and nonactivation guards are Green.
- Iteration and Complete Final Gate are Green on one unchanged freeze.
- Fresh General／Transaction／Documentation reviews are separately Green.
- Report and STATE truthfully separate current, Historical, Not Run, and Not
  Verified evidence.

## Post-Acceptance

After Orchestrator Acceptance only:

1. accept P23-003C4 and then parent P23-003C;
2. commit the reviewed exact candidate without unrelated paths;
3. confirm clean tree／staged0 and exact commit SHA;
4. restart and run `tests/Consumer/framework-package-export.sh` from that exact
   committed SHA;
5. record Git／Composer same-inventory and autoload proof;
6. do not push unless the user separately authorizes it.

D154 splits P23-003D into bounded children. P23-003D1 is the next Task and
adds only the Framework operational Catalog／trusted lookup lineage; it is not
a producer adapter. Runtime activation remains deferred to its explicit
future integration owner and release boundary.

## Blocker Rule

Stop and update the Report／STATE without broadening scope when:

- authority conflicts with an Accepted API, state machine, failure taxonomy,
  callable graph, or package inventory;
- an exact file, fixture, package path, or required guard is missing;
- a fix needs an unlisted Product／Test／management file;
- Connection identity, transaction separation, failure classification,
  final-attempt meaning, FIFO progress, or nonactivation is not
  implementation-unique;
- branch／HEAD／index／status／hash freeze drifts;
- the configured worker model／effort is explicitly rejected, unavailable, or
  falls back;
- any focused or complete gate fails.
