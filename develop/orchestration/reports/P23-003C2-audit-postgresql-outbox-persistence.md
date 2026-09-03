# P23-003C2 Report: Audit PostgreSQL Outbox Persistence

Status: Accepted — candidate committed; clean same-SHA package export Green

Updated At: 2026-09-04T02:02:26+09:00

## Summary

The reviewed candidate is committed at
`a92bef70fdb843a4dd2b8b34243fc892b43939cc`, and the clean exact-SHA
Git／Composer package export is Green. No push was performed.

The unchanged final pre-commit freeze was on branch
`agent/p23-001-blackops-1-3-feasibility` at HEAD
`a64a24631a0d7c7462f85c68c73df5bb7f4dc633`, with staged0 and exact30 status.
Product／Test exact28 remains byte-identical at ordered aggregate
`94ef7d6b73d2552116cb31c7fce34dbd44469bceb220bbe573ef35372e3f67cc`, and
Orchestrator-only immutable exact7 remains byte-identical at aggregate
`8c6a009823864a310094dc00b4c1b614b54bb63228793d601fcc04e8e5c3e2c7`.
Fresh General／DB／Documentation management closure reviews are Green with
`P1=0／P2=0／P3=0`. The Orchestrator accepts this exact candidate.

After the pre scope／immutable／package guards passed, Complete Final Gate was
formally restarted from command 1 on this unchanged freeze and is fully Green:
format, strict Composer validation, Mago lint, lint baseline, analyzer exit 0
with `0 errors／343 warnings／196 help messages`, PHPUnit `3,041 tests／58,402
assertions` with warnings `1`, deprecations `1`, PHPUnit deprecations `2`, and
notices `13`, Deptrac `0 violations／0 skipped／0 uncovered／6,170 allowed／0
warnings／0 errors`, four Node goldens, version baseline, Community Board
clean-install including migration `18`, svelte-check `0 errors／0 warnings`,
Vitest `8 files／46 tests`, frontend build and HTTP journey, shell syntax,
required package inventory `54` with call-contract mutations `14` and total
mutations `27`, documentation directories and diff, forbidden-ID scan,
`git diff --check`, staged0, and post-gate scope／immutable／package guards.

Two earlier clean-install attempts stopped during Composer download with GitHub
API HTTP/2 `504`; codeload returned `200` and the API later recovered with
`302`. These are historical external transient attempts, not Product failures.
Stable `1.2.0` remains current; BlackOps `1.3` and the Audit capability remain
unreleased. The only public source delta is the Community Board README
migration sentence/count to `18` (`13` Framework + `5` app). `docs/guide` and
Documentation Website source/status/diff remain unchanged with no-diff.
Generated artifacts and browser behavior remain Not Verified. No Application,
Container, CLI, HTTP, runtime, release, or deployment activation was
performed.

P23-003C3 is now Accepted at commit
`b7b352a8e4c86a45822351c108772367515654bd` with clean same-SHA package
export Green. P23-003C4 is the current final serial child of parent P23-003C.

## Changed Files (Task exact30)

The Task exact30 allowed set is the following. This management-only final-gate
sync moves only this Report and `develop/STATE.md`; Product／Test exact28 is
byte-identical. The latest cb5c→94ef correction moved only
`AuditOutboxRecord.php`, `PostgreSqlAuditOutboxStore.php`, this Report, and
`develop/STATE.md`; the other exact26 Product／Test paths remain byte-identical.
Earlier cumulative slices are retained only in Historical Checkpoints.

```text
src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php
src/Internal/Audit/Outbox/AuditOutboxStoreException.php
src/Internal/Audit/Outbox/AuditOutboxRecord.php
src/Internal/Audit/Outbox/AuditOutboxAppendResult.php
src/Internal/Audit/Outbox/AuditOutboxLookupResult.php
src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php
src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
migrations/postgresql/Version20260901000000.php
tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php
tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php
tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php
tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php
tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php
tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php
tests/Architecture/AuditOutboxStorageArchitectureTest.php
tests/Architecture/AuditOutboxStateArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditStoreArchitectureTest.php
tests/Internal/Migration/DatabaseMigrationRunnerTest.php
tests/Internal/Console/DatabaseMigrationCommandTest.php
tests/Integration/ApplicationConsoleKernelTest.php
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/framework-package-export.sh
tests/Consumer/community-board-clean-install.sh
examples/community-board/README.md
develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md
develop/STATE.md
```

## Decisions and Assumptions

- The twelve named lint warning/help diagnostics are intentionally covered by
  narrow, established `@mago-expect` annotations at their smallest owning
  class/method sites. No unrelated rule, severity, configuration, baseline,
  behavior, or API was changed.
- The analyzer correction is PHPDoc-only: adjacent DocBlocks were merged,
  `readState`/`assignedAt` state shapes were made visible to callers, and
  exact validated ingress/envelope shapes were supplied locally. No analyzer
  error was suppressed and no executable behavior was changed.
- Union/intersection sensitivity is the OR of its named arms. PHP-generated
  enum `from`/`tryFrom` parameters remain a structural framework exception
  because their signatures cannot carry these attributes.
- Same-namespace type hints and aliases are semantic dependencies even without
  imports. Class-string variables propagate to a fixed point; ordinary unused
  strings and aliases remain decoys.
- The package exact54/Community18 and canonical-clock roots are preserved from
  the prior exact28 candidate. The export script remains unchanged.
- Complete Final Gate was run from the unchanged exact30 freeze only after the
  pre scope／immutable／package guards were Green; no Product／Test path moved
  during the gate. The two Composer-download `504` attempts are recorded as
  external transient history, while codeload `200` and later API `302`
  recovery confirm that they are not Product failures.
- Fresh General／DB／Documentation management closure reviews are Green with
  `P1=0／P2=0／P3=0`, and Orchestrator Acceptance is complete. The accepted
  candidate was then committed and its clean same-SHA package export passed;
  no push, release, or deployment may be inferred.
- The acd9e4 exact28 Iteration evidence is Historical after this source/test
  annotation correction. Full gates must not reuse it.

## Commands and Results

Current bounded, Iteration Gate, and Complete Final Gate evidence:

- Scoped `mago format --check` for the two corrected Outbox source files:
  exit 0; all files are already formatted.
- Full `docker compose run --rm app mago lint --reporting-format=short`: exit
  0; no issues found after filtering 186 baseline entries.
- Full `docker compose run --rm app mago lint --verify-baseline`: exit 0;
  baseline is up to date.
- Full `docker compose run --rm app mago analyze --minimum-report-level=error`:
  exit 0; the exact eighteen analyzer errors are closed.
- Full `docker compose run --rm app mago analyze --minimum-report-level=error
  --reporting-format=count`: exit 0; zero analyzer errors.
- `AuditOutboxStorageArchitectureTest.php`, Store, and value tests: exit 0,
  `43 tests, 5,722 assertions, 1 warning`.
- `git diff --check`, PHP management-ID scan, exact30 scope, staged0, current
  exact28 manifest, and immutable exact7 checks: exit 0 before handoff.
- Current Orchestrator-run Iteration Gate: exact30 scope plus add/remove and
  immutable individual/wrong-hash negatives PASS; format PASS; PHPUnit `192
  tests, 39,912 assertions, 1 warning`, exit 0; Deptrac violations `0`,
  skipped `0`, uncovered `0`, allowed `6,170`, warnings `0`, errors `0`; Node
  `4` goldens PASS; PostgreSQL oracle `15` mutations and `2` mutation kills;
  version `1.2.0` with historical `1.1.0`; package exact54, call-contract
  `14`, total mutations `27`; documentation, forbidden-ID, diff, and staged
  guards PASS; independent analyzer `0 errors, 343 warnings, 196 help
  messages`.
- Complete Final Gate was formally restarted from command 1 after fresh
  pre-gate scope／immutable／package guards were Green and passed every
  command on the unchanged freeze. Full format, strict Composer validation,
  full Mago lint, and baseline verification passed. Analyzer exited 0 with
  `0 errors／343 warnings／196 help messages`. Full PHPUnit exited 0 with
  `3,041 tests／58,402 assertions`, warnings `1`, deprecations `1`, PHPUnit
  deprecations `2`, and notices `13`. Deptrac exited 0 with
  `0 violations／0 skipped／0 uncovered／6,170 allowed／0 warnings／0 errors`.
  All four Node goldens passed.
- Complete Final Gate version baseline passed with published `1.2.0` and
  historical `1.1.0`. Community Board clean-install passed with migration
  `18`, svelte-check `0 errors／0 warnings`, Vitest `8 files／46 tests`, the
  frontend build, and the HTTP journey. Export-script and required-inventory
  `bash -n` checks passed; required inventory passed `54` ordered paths,
  call-contract mutations `14`, and total mutations `27`. Documentation
  directory and diff checks, forbidden-ID scan, `git diff --check`, staged0,
  and post scope／immutable／package guards all passed.
- Before this formal success, two clean-install attempts stopped during
  Composer download with GitHub API HTTP/2 `504`; codeload was HTTP `200` and
  the API later recovered with HTTP `302`. These attempts are historical
  external transients, not Product failures.
- Fresh General／DB／Documentation management closure reviews are Green with
  `P1=0／P2=0／P3=0`, and Orchestrator Acceptance is complete.
- Candidate commit
  `a92bef70fdb843a4dd2b8b34243fc892b43939cc`: Green. The working tree was
  clean and staged0 afterward.
- `bash tests/Consumer/framework-package-export.sh` on that exact commit:
  Green. Git and Composer archive inventories matched, both archives passed
  required-path／excluded-root／autoload validation, and cleanup completed.

Historical Complete Final Gate partial at the preceding exact30: command 1
full format Green, command 2 strict Composer validation Green, command 3
full Mago lint Green, command 4 baseline verification Green, command 5
analyzer failed with exactly 18 errors, and every later command was Not Run.

Historical acd9e4 Iteration Gate evidence is retained only as reference:
scope／immutable／format Green; PHPUnit `192 tests／39,904 assertions` with
one warning; Deptrac `0／0／0`; Node `4`; version baseline Green; package
inventory `54／14／27`; documentation and diff checks Green. Historical
Complete Final Gate evidence stopped at command 3: command 1 full format
Green, command 2 strict Composer validation Green, command 3 Mago lint failed
with exactly four errors, and every later command was Not Run.

## Current exact28 SHA-256 Manifest

```text
1912b1c937c935c52ae997c37acf31b4916368c09cb87580941ee935b1e712af  src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php
9095e69a2895cd2224cbf6eeae557b38932a199dc80888ddf457d77e3e19940b  src/Internal/Audit/Outbox/AuditOutboxStoreException.php
ebbedb0d1168cd1bc1d2161f2246ed843f6c77e37e18ee85dfffd72c522c0e61  src/Internal/Audit/Outbox/AuditOutboxRecord.php
1734e0ff917285a7187927505e59e9e6ec385d8d98f0c2d8ffe52d2965760428  src/Internal/Audit/Outbox/AuditOutboxAppendResult.php
b7d3483eea168fda7619368c2b9189c8248edb1c1a145472959cee078c76e95f  src/Internal/Audit/Outbox/AuditOutboxLookupResult.php
4f8c558474da85c9d40127e0a6bbd426b6a3e6d552160abe790b97ea3622515f  src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php
4d58a74a9a9b3dacdc125de4736471f80efa615c69bd73ff79a03de33235f0b5  src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php
0021517719ac5a03aa73027c42add918c288f9ce245ec621106e252746240e9a  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php
9fa16ae5bc0c3692a0af103c607532d112470316e50e05b4b3cd7d6158401b50  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php
3179207b350fbcca86ecaa5cdb40bf3883bd763dacea541935be57f8e7f7667d  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
e71a4620f9aef46cf1be9c55ade786df0caef85801e1fc5c120e4bdae9bb50bd  migrations/postgresql/Version20260901000000.php
1ce5a72ceffc5323bc77a3bafe33d2e703fa7883ca5c22c0c75b9b0eed90524d  tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php
f94458a75b1b5f8bbd314f940e30d5779d6bed76c8fedf93b1176ba8f2d2807f  tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php
2b22ef7e65ad6e9d79a3ed1a8045844fe5115f38650ee4c8309180825e8698cf  tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php
73ba45d2b58502fb41bd3074355514e424347eb525cbb1c82673b2d148372075  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php
a237cc320b73d8303ed29b5cb8a036b7f9e6208b4a41e5b5c70b3248fb880ec6  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php
5c7a996a2e75b0cccb764632398666a37b7db8c4039b801aa0fbaddb7f3ce47f  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php
1d4fb240beb25881fbcba3127ad7a6b71a129543e01c7fe5bf8614d8d5c60c1f  tests/Architecture/AuditOutboxStorageArchitectureTest.php
98b2ac6ec8c6b6fba474fa6b39e37a377bbb436f02699a8dd24c7f8df8bea9a7  tests/Architecture/AuditOutboxStateArchitectureTest.php
bc4caf343fc1c8aac0dab14913612583470a01ece5d15604d409b049e6014a5b  tests/Architecture/AuditRecordArchitectureTest.php
2371822188cc737d72a7adbc096b8adb02f07f48920c4b448d2fe7e16445fbbc  tests/Architecture/AuditStoreArchitectureTest.php
ca0c2ef6c9ab003e37ea9d0738f80fa9e354f4357ef01d64e02b82b532759bdd  tests/Internal/Migration/DatabaseMigrationRunnerTest.php
0db87542cc4e57613081599b8c125ae613051d4589738866438b97d8e4b59d40  tests/Internal/Console/DatabaseMigrationCommandTest.php
ce2b726840f0a54330a26da6141b528d4da2aee06394c998a8dbc0b2f13fa488  tests/Integration/ApplicationConsoleKernelTest.php
8077139db9bbcef793632e7a3b0a3a34dea34c300271237b09425a9c57e62462  tests/Consumer/framework-package-required-inventory.sh
875c31a51dac63ad22c81a4403e465daa63d57831e4e4895fd905853ec2f0ea6  tests/Consumer/framework-package-export.sh
c8e6e1310ce09df920eb0f058f793abcaa4784e1752662f315d769ecc9a76cbd  tests/Consumer/community-board-clean-install.sh
fe26f3334ac28c9f40fc251e4e801edd4739aaf2251c7d174d67db2e7b1a8e64  examples/community-board/README.md
```

The current exact28 ordered aggregate is
`94ef7d6b73d2552116cb31c7fce34dbd44469bceb220bbe573ef35372e3f67cc`.

## Acceptance Criteria

- [x] Twelve named lint diagnostics are covered by narrow class/method
  expectations; no unrelated warning/help rule, configuration, severity,
  baseline, or behavior/API change.
- [x] Architecture sensitivity and dependency oracles reject union,
  intersection, same-namespace, two-hop, and arbitrary-name mutations while
  preserving decoys.
- [x] Current scoped format, analyzer, full lint, baseline verification,
  Architecture, focused PHPUnit, and Iteration Gate evidence is Green.
- [x] Exact30 scope, staged0, immutable exact7, exact28 manifest, and diff
  guards are Green.
- [x] Complete Final Gate is Green from the unchanged exact30 freeze.
- [x] Fresh General／DB／Documentation management review and Orchestrator
  Acceptance.
- [x] Candidate commit and same-SHA package export.

## Remaining Issues

- Complete Final Gate and fresh General／DB／Documentation management review are
  Green for this unchanged exact30 freeze. Orchestrator Acceptance is
  complete.
- C2 has no remaining Product, gate, commit, or package-export work.
- Browser, generated artifact, release, deployment, and public Documentation
  delivery remain Not Verified／Not Run. Community Board clean-install is
  Green as recorded above.
- P23-003C3 is Accepted. P23-003C4 and parent P23-003C Acceptance remain.

## Suggested Next Action

Continue P23-003C4 against Accepted C1／C2／C3 and the clean C3
candidate／package evidence. Do not push, release, deploy, or activate public
Documentation without separate authorization.

## Historical Checkpoints

## Historical: Second Bounded Architecture Correction Checkpoint

At `2026-09-01T12:42:08+09:00`, the second bounded correction preserved the
frozen Product／Test and immutable manifests while changing only the affected
Outbox source files, `tests/Architecture/AuditOutboxStorageArchitectureTest.php`,
this Report, and `develop/STATE.md`.

Root 1 is now conservative and independent of private helper names. Every
record／result／row／draft／tenant／partition／idempotency／token／timestamp／
protected-body／DB-scalar data parameter, including result booleans, ordinals,
and raw `Throwable`, is required to carry `SensitiveParameter`. An arbitrary
private scalar／Throwable mutation is rejected; an arbitrary control boolean
remains non-data-bearing by type, while an arbitrary private result-state
boolean mutation and `AppendResult::$inserted`／`LookupResult::$found` are
explicitly classified and annotated as result state.

Root 2 replaces imports-only dependency accounting with token-aware semantic
references covering aliases, grouped imports, inline FQCN／`::class` references,
and variable class-string consumers such as `ReflectionClass`. An added inline
FQCN canary, alias／group fixture, dynamic class-string fixture, and comment／
ordinary-string decoys are non-vacuous and pass the dependency boundary.

The package-resolution/repin and canonical-clock roots remain unchanged and
preserved. Current focused evidence is Green: scoped Mago format check;
Store PHPUnit `29 tests, 900 assertions`; value-type PHPUnit `7 tests, 69
assertions`; Storage Architecture PHPUnit `7 tests, 4,745 assertions` with one
existing warning; and `git diff --check`. No package export, clean install,
full Iteration／Complete Final Gate, stage, commit, push, network, release,
deployment, or public Documentation activation was run.

The current exact30 scope remains `HEAD`
`a64a24631a0d7c7462f85c68c73df5bb7f4dc633`, staged0, with no out-of-scope
drift. Product／Test exact28 ordered aggregate is
`acd9e4dc110475718d81036fc946aeb228e8e2cad983b75aa1e43ecc24b76d7a`, and
immutable exact7 remains byte-identical at
`8c6a009823864a310094dc00b4c1b614b54bb63228793d601fcc04e8e5c3e2c7`.
All earlier Product／Test evidence is Historical after this source/test
correction. Remaining: fresh independent review, Iteration／Complete Final
Gate, Acceptance, candidate commit, and post-Acceptance same-SHA package
export; C3/C4 remain downstream. Next Action: Orchestrator reviews this
unchanged exact30 freeze and runs the final gates.

## Prior Final Bounded DB Correction Checkpoint (superseded)

At `2026-09-01T11:57:35+09:00`, this bounded correction changed only the
three authorized DB Product／Test roots, the required-inventory oracle, this
Report, and `develop/STATE.md`:

- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php`
- `tests/Architecture/AuditOutboxStorageArchitectureTest.php`
- `tests/Consumer/framework-package-required-inventory.sh`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/STATE.md`

Root 1 now maps every Clock／timezone／format／canonical-validation failure
from `canonicalNow()` to the fixed `ResourceUnavailable` failure while
persisted malformed timestamps remain `IntegrityInvalid`. Expanded-year Clock
fixtures cover append, claim, recovery, and one leased settlement path, with
one Clock call, no downstream resource work, owned rollback, unchanged state,
fixed no-previous errors, and canary-free exception surfaces.

Root 2 now uses PHP token parsing for dependency imports and source references,
including aliases, grouped imports, fully-qualified names, `::class` and
class-string consumers while ignoring comments and ordinary string decoys.
Callsite ownership uses token-aware direct `$this` property maps, and the
private data-bearing parameter classifier is independent of helper method names.
Mutation fixtures cover alias／group／FQCN／class-string references, direct-call
substitution, indirect protection aliases, comments, and string decoys.

Root 3 rejects executable `shopt -s expand_aliases` plus
`alias 'validate_package=:'` command-resolution overrides before runtime and
the whole-file SHA check. A candidate mutation repins the local reviewed SHA,
then proves rejection at the call-contract phase with no runtime validation.
The export script was not changed; its reviewed hash remains
`875c31a51dac63ad22c81a4403e465daa63d57831e4e4895fd905853ec2f0ea6`.

Focused evidence is Green: Mago format check for the three changed PHP files;
Store PHPUnit `29 tests, 900 assertions`; Schema PHPUnit `7 tests, 483
assertions`; Storage Architecture PHPUnit `6 tests, 4,725 assertions` with
one existing warning; `bash -n` for the required-inventory script; and the
required-inventory oracle with `54 ordered paths`, `14` call-contract
rejections, and `27` total mutation rejections. `git diff --check` is Green.

The exact30 scope guard remains Green with HEAD
`a64a24631a0d7c7462f85c68c73df5bb7f4dc633`, staged0, no out-of-scope drift,
Product／Test exact28 ordered aggregate
`97d22da21bab7d761e021e96daad626eac504ad24f293dacf74321b129ccca61`, and
immutable exact7 aggregate
`8c6a009823864a310094dc00b4c1b614b54bb63228793d601fcc04e8e5c3e2c7`.
All prior implementation／Iteration／Final evidence for changed Product or
Test bytes is Historical; fresh independent review must restart from this
freeze. Complete Final Gate, package-export execution, clean-install execution,
stage, commit, push, network, release, deployment, and public Documentation
activation were not run.

The current exact28 SHA-256 manifest is:

```text
1912b1c937c935c52ae997c37acf31b4916368c09cb87580941ee935b1e712af  src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php
9095e69a2895cd2224cbf6eeae557b38932a199dc80888ddf457d77e3e19940b  src/Internal/Audit/Outbox/AuditOutboxStoreException.php
a2d9053fd97d697493ed748fc957c5f4ac26456e05bce713f15e62f48d9991e5  src/Internal/Audit/Outbox/AuditOutboxRecord.php
1734e0ff917285a7187927505e59e9e6ec385d8d98f0c2d8ffe52d2965760428  src/Internal/Audit/Outbox/AuditOutboxAppendResult.php
b7d3483eea168fda7619368c2b9189c8248edb1c1a145472959cee078c76e95f  src/Internal/Audit/Outbox/AuditOutboxLookupResult.php
4f8c558474da85c9d40127e0a6bbd426b6a3e6d552160abe790b97ea3622515f  src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php
fdc1945ea4fef98f11fef6e91a741dc7dfd3d678668e52c9b66fc0b21b8e61bf  src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php
18ee6201f5a1e941ac7c522e92d0f0396a92eb4b55354946536bb4acaa66e7cc  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php
9fa16ae5bc0c3692a0af103c607532d112470316e50e05b4b3cd7d6158401b50  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php
392c61ae901b5319b4056482d2018e72d26bca1a82dc6d175541a15b4ef709dd  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
e71a4620f9aef46cf1be9c55ade786df0caef85801e1fc5c120e4bdae9bb50bd  migrations/postgresql/Version20260901000000.php
1ce5a72ceffc5323bc77a3bafe33d2e703fa7883ca5c22c0c75b9b0eed90524d  tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php
f94458a75b1b5f8bbd314f940e30d5779d6bed76c8fedf93b1176ba8f2d2807f  tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php
2b22ef7e65ad6e9d79a3ed1a8045844fe5115f38650ee4c8309180825e8698cf  tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php
73ba45d2b58502fb41bd3074355514e424347eb525cbb1c82673b2d148372075  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php
a237cc320b73d8303ed29b5cb8a036b7f9e6208b4a41e5b5c70b3248fb880ec6  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php
5c7a996a2e75b0cccb764632398666a37b7db8c4039b801aa0fbaddb7f3ce47f  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php
c75af76e6bc780ec30ba2f91c8c0305d5cafa070f76ad99ef93042ba9799028d  tests/Architecture/AuditOutboxStorageArchitectureTest.php
98b2ac6ec8c6b6fba474fa6b39e37a377bbb436f02699a8dd24c7f8df8bea9a7  tests/Architecture/AuditOutboxStateArchitectureTest.php
bc4caf343fc1c8aac0dab14913612583470a01ece5d15604d409b049e6014a5b  tests/Architecture/AuditRecordArchitectureTest.php
2371822188cc737d72a7adbc096b8adb02f07f48920c4b448d2fe7e16445fbbc  tests/Architecture/AuditStoreArchitectureTest.php
ca0c2ef6c9ab003e37ea9d0738f80fa9e354f4357ef01d64e02b82b532759bdd  tests/Internal/Migration/DatabaseMigrationRunnerTest.php
0db87542cc4e57613081599b8c125ae613051d4589738866438b97d8e4b59d40  tests/Internal/Console/DatabaseMigrationCommandTest.php
ce2b726840f0a54330a26da6141b528d4da2aee06394c998a8dbc0b2f13fa488  tests/Integration/ApplicationConsoleKernelTest.php
8077139db9bbcef793632e7a3b0a3a34dea34c300271237b09425a9c57e62462  tests/Consumer/framework-package-required-inventory.sh
875c31a51dac63ad22c81a4403e465daa63d57831e4e4895fd905853ec2f0ea6  tests/Consumer/framework-package-export.sh
c8e6e1310ce09df920eb0f058f793abcaa4784e1752662f315d769ecc9a76cbd  tests/Consumer/community-board-clean-install.sh
fe26f3334ac28c9f40fc251e4e801edd4739aaf2251c7d174d67db2e7b1a8e64  examples/community-board/README.md
```

Remaining: fresh General／DB／Documentation review, the Iteration Gate,
Complete Final Gate, Acceptance, Orchestrator candidate commit, and the
post-Acceptance same-SHA package export. Next Action: Orchestrator reviews this
exact28／exact30 freeze, then runs the final gates from unchanged bytes; C3/C4
remain downstream.

## Historical: Final Bounded Integration／Package／Management Checkpoint

At `2026-09-01T11:11:08+09:00`, this bounded slice changed only the seven
integration／package／Community／README paths listed below, plus this Report
and `develop/STATE.md`. The frozen Product／Test exact21 remains byte-identical
with ordered aggregate
`a4f8c4c60ab9541ed3bca2131334b655769a9bbd83d36b420d0b28d3157d96da`.
The Orchestrator-only immutable exact7 remains byte-identical with ordered
aggregate
`8c6a009823864a310094dc00b4c1b614b54bb63228793d601fcc04e8e5c3e2c7`.

The current Framework migration inventory is exact 13, with first
`Version20260712000000` and last `Version20260901000000`. The runner's
two-Application fixture reports 15 migrations, while the console's
Framework-plus-one-Application fixture reports 14. The current Framework
status／kernel assertions report 13. The Community Board clean-install
oracle and README report the exact total 18 (`13 Framework + 5 Community
Board Application migrations = 18`). Historical Stable 1.2 version fixtures
were not changed.

Both package validation loops now reproduce D151's exact ordered 54-path
manifest, including the six Accepted Delivery paths, ten C2 Outbox paths, the
C2 migration, and all eleven stubs. The runnable required-inventory oracle
passes the live function, complete／missing runtime package probes, 13
call-contract mutations, and 13 inventory mutations (26 total), before the
whole-file hash check. The reviewed export script hash is
`875c31a51dac63ad22c81a4403e465daa63d57831e4e4895fd905853ec2f0ea6`.

Changed files in this slice:

- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-package-required-inventory.sh`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/community-board-clean-install.sh`
- `examples/community-board/README.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/STATE.md`

The ordered exact21 SHA-256 values at both slice start and end are:

```text
1912b1c937c935c52ae997c37acf31b4916368c09cb87580941ee935b1e712af  src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php
9095e69a2895cd2224cbf6eeae557b38932a199dc80888ddf457d77e3e19940b  src/Internal/Audit/Outbox/AuditOutboxStoreException.php
73d890d3e1d5f2229bd0dfc864789e153b74b07df3f89054405d29dc9ddb4477  src/Internal/Audit/Outbox/AuditOutboxRecord.php
010da19c340202e71d7ce5585dafb511e7d182972a2855fad2d94ebee4793c5c  src/Internal/Audit/Outbox/AuditOutboxAppendResult.php
67b22d4fd2278028aebcc4f2ba4daadf2cb450d8086c2468ac5ab25238964bda  src/Internal/Audit/Outbox/AuditOutboxLookupResult.php
4f8c558474da85c9d40127e0a6bbd426b6a3e6d552160abe790b97ea3622515f  src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php
43015daf1cd0e57c5b2996de06229620759edd8bc06f9432a23d5a130ec67b23  src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php
4829d629eaa17c40da0fe123817f5b25ce0400229c60242a0630a001424ad7fd  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php
bfee0dda87cd562d1196a805aa4d446b7ddc39cd6d00755d5f28dc1edec113e7  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php
84e360ca5a21df8916ee5072e38945995d06056d569b7e8d85752b2eaf83ed20  src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
e71a4620f9aef46cf1be9c55ade786df0caef85801e1fc5c120e4bdae9bb50bd  migrations/postgresql/Version20260901000000.php
1ce5a72ceffc5323bc77a3bafe33d2e703fa7883ca5c22c0c75b9b0eed90524d  tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php
f94458a75b1b5f8bbd314f940e30d5779d6bed76c8fedf93b1176ba8f2d2807f  tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php
2b22ef7e65ad6e9d79a3ed1a8045844fe5115f38650ee4c8309180825e8698cf  tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php
73ba45d2b58502fb41bd3074355514e424347eb525cbb1c82673b2d148372075  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php
a237cc320b73d8303ed29b5cb8a036b7f9e6208b4a41e5b5c70b3248fb880ec6  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php
187a20fcf8e603b420b0fd4ba0fce28525c124352076e5512df5d93ed56c833b  tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php
4855941d5bc230925064dd8fa826d4d8fbc4bb988f4c08ce52331acac04b9d25  tests/Architecture/AuditOutboxStorageArchitectureTest.php
98b2ac6ec8c6b6fba474fa6b39e37a377bbb436f02699a8dd24c7f8df8bea9a7  tests/Architecture/AuditOutboxStateArchitectureTest.php
bc4caf343fc1c8aac0dab14913612583470a01ece5d15604d409b049e6014a5b  tests/Architecture/AuditRecordArchitectureTest.php
2371822188cc737d72a7adbc096b8adb02f07f48920c4b448d2fe7e16445fbbc  tests/Architecture/AuditStoreArchitectureTest.php
```

The immutable exact7 start／end values remain:

```text
75a440c6ed6ed74e83dd5e59f3efe7b1aff5fa1a9126c5fa22b3d9e2ed1b5fd5  develop/TODO.md
b16b18e975598a57488b4fe45c98b07f3c61299644b2019325bc8d198bb814c5  develop/decisions/150-audit-outbox-state-and-delivery-split.md
e030930ff201193a643dfe843509cc48f4e9c49304a22e80f87e0defd29f1c25  develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md
d9f3c2f1cb7ee01bdb3fbc82c5c9533cb7a531dec13cbb63d822b60be2ae804b  develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md
a4af3c08f94f824bf7d45af15ecb55a56294901e5099d4e31b61b5282667462a  develop/spec/105-blackops-1-3-capability-contract.md
64fff8cd13ae8735e2daf7354b98bbc42bdfe4af05f01dd2896a9d3f005da16f  develop/spec/106-phase-23-delivery-plan.md
86538e6090e20fb8aa797d32da574388beed029fe603f97e5b0ac8c0bfcd8b07  develop/spec/README.md
```

Focused commands are Green: Mago format check for the three changed PHP test
files; focused PHPUnit with `59 tests, 243 assertions`; `bash -n` for all
three shell scripts; and `bash tests/Consumer/framework-package-required-inventory.sh`
with `54 ordered paths`, `13` call-contract rejections, and `26` total
mutation rejections. The README migration sentence occurs exactly once with
the new 18／13／5／18 values and the old 17-value sentence is absent. The
current status is exact30 allowed paths, the immutable exact7 and Product／Test
exact21 aggregates match at slice end, `git diff --check` is Green, and the
index is staged0.

No Complete Final Gate, Community Board clean-install execution, package
export execution, full repository gate, stage, commit, push, network, release,
deployment, or public Documentation activation was performed. Guide and
Website remain byte-identical.

## Historical: Corrected-scope Review Checkpoint

Fresh General review is Green `P1=0/P2=0/P3=0`. Fresh DB review confirms the
authority／scope correction is Green and returns preserved Product Changes
Requested `P1=3/P2=0/P3=0`: expired recovery calls a CAS with the active-lease
predicate, the Outbox Cc／Cf inventory includes U+1D17B beyond Accepted B2,
and one branched append statement contains both bootstrap and existing-state
CTEs. Fresh Documentation review returns `P1=0/P2=2/P3=0` for the stale
Decision catalog and incomplete current Changed Files／Next Action.

The Documentation roots are corrected in this management freeze. Luna's next
scope is limited to the three DB Product roots, their direct and real
PostgreSQL fixtures, the exact B2 Architecture callsite map, and Report／STATE.
Prior focused Green evidence is Historical for changed Product bytes.
The preserved dirty set remains exactly Task Product／Test items 1–20 at sorted
aggregate
`29c6a9873ed3c0e1f9b6abdd551fde8e3e0d3f751f99a81522d408c81d971a88`;
the index is staged0. The amended management commit SHA and new immutable
exact7／committed exact9 hashes are supplied after commit because the commit
cannot self-record its own identifier or bytes.

## Historical: Bounded Architecture-scope Correction Checkpoint

The first corrected-scope General pass found one residual literal in Required
Fixtures: it still said B2 Architecture was unchanged, contradicting the exact
map exception already fixed by D151, the Architecture section, Files Allowed,
and the gates. That preliminary freeze is superseded. The Task now says B2
Production remains unchanged and B2 Architecture changes only the exact C2
Outbox protection-callsite map while every other guard and mutation negative
remains fixed. All fresh lanes restart from the amended management freeze.

At `2026-09-01T10:16:21+09:00`, the Orchestrator reproduced the compatibility
failure against current Source and authority. D151 requires exactly one direct
C2 Store callsite each for `AuditProtectedBodyCodec::protectOutbox()` and
`unprotectOutbox()`. The unchanged Accepted B2
`AuditStoreArchitectureTest.php` still requires both callsite maps to be empty,
so the frozen contract cannot satisfy its own broader Architecture suite.

The bounded correction adds only `AuditStoreArchitectureTest.php` to C2 Files
Allowed, changes Product／Test exact27→exact28 and worker exact29→exact30, and
authorizes only the two C2 Outbox direct-call map entries. Accepted B2 record
callsites, indirect／dynamic-call rejection, every other B2 guard, and every B2
Production byte remain fixed. The preserved partial implementation is not
discarded or committed. Prior preflight and Architecture evidence is
Historical after this authority change; Product work is paused until fresh
General／DB／Documentation re-preflight is Green.

## Historical: C2 Architecture Focused Checkpoint

At `2026-09-01T10:12:35+09:00`, the bounded architecture action changed only
the three authorized C2 architecture tests:
`tests/Architecture/AuditOutboxStorageArchitectureTest.php` was added, and
`tests/Architecture/AuditOutboxStateArchitectureTest.php` plus
`tests/Architecture/AuditRecordArchitectureTest.php` were updated for the
exact C2 Outbox inventory and call graph. The unchanged B2
`AuditStoreArchitectureTest.php` and public API guards were not modified.

Focused Mago format is PASS. The focused C2 PHPUnit command is PASS at
`24 tests`, `26,412 assertions`, with one existing warning. The requested
six-file PHPUnit command ran `42 tests`, `37,261 assertions`, with one failure:
unchanged `AuditStoreArchitectureTest::testB1ProtectedCallGraphRejectsIndirectCallsAcrossAllSource`
still requires empty `protectOutbox` and `unprotectOutbox` callsite maps, while
the authorized C2 Store has one callsite for each. This is recorded as a
compatibility blocker; the unchanged guard was not edited.

The three architecture file SHA-256 values are:

- `tests/Architecture/AuditOutboxStorageArchitectureTest.php`:
  `4855941d5bc230925064dd8fa826d4d8fbc4bb988f4c08ce52331acac04b9d25`
- `tests/Architecture/AuditOutboxStateArchitectureTest.php`:
  `98b2ac6ec8c6b6fba474fa6b39e37a377bbb436f02699a8dd24c7f8df8bea9a7`
- `tests/Architecture/AuditRecordArchitectureTest.php`:
  `bc4caf343fc1c8aac0dab14913612583470a01ece5d15604d409b049e6014a5b`

Immutable exact7 remains byte-identical with ordered aggregate
`96155a87e12e61783cb15f30e7dc7bacbb9e5a7cbcad6b3380a2e03e72974845` and
staged0 remains. No stage, commit, push, network, deployment, or Complete
Final Gate occurred.

## Historical: PostgreSQL Outbox Store Real Concurrency and Failure Checkpoint

At `2026-09-01T09:51:20+09:00`, the bounded Store evidence batch changed only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` and the
concrete rollback-uncertainty branch in
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`. The test now covers
two-Connection same-key and different-key append races with contiguous
ordinals, idempotent retry, no deadlock leak, same-head claim with one winner,
partition-before-record advisory lock order with unrelated-tenant progress,
caller-owned late count-fence abort, owned rollback, rollback quarantine and
reconnect, retained-boundary state-ahead/state-behind/interior/head-tenant
tampering before resource work, and persisted protected-body/tenant tampering
with before-after equality. Child processes use readiness files, bounded lock
polls, timeout, SIGKILL, reap, and cleanup paths.

The focused Mago format check is PASS for the Store and Store test. The
focused PHPUnit suite is PASS: `25 tests, 783 assertions`. The test SHA-256 is
`d4b131ce0e70ee0e53ccc8997142e2c51445cc8369223ac75a1449fe3d5cc33c`; the
Store SHA-256 is
`d9cd8d4cde6d943904c37390980739575cccd884e75648aa21ffe219f87af1c3`.
The Store delta is limited to constructing the fixed storage failure before a
failed owned rollback is attempted, preventing the active rollback throwable
from entering the public exception's trace while preserving quarantine and
`StorageUnavailable` mapping. The lock-key correction and rollback canary
callback are test-only. An initial sandbox Docker-socket denial was retried
through the approved escalation route; the focused checks then completed.
No other Product or Test path changed; staged0 remains, immutable exact7
remains byte-identical, and no stage, commit, push, network, deployment, or
Complete Final Gate occurred.

## Historical: PostgreSQL Outbox Store Read and Transition Test Checkpoint

At `2026-09-01T09:15:50+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` and the
Store's safe-failure mapping changed in this bounded batch. The test now
covers exact record／partition／tenant find predicates without transaction
control, not-found and tenant-before-key paths, one-Clock due selection and
head recheck, deterministic global FIFO order, same-partition lower-ordinal
blocking without `SKIP LOCKED`, microsecond recovery expiry boundaries,
retry-versus-poison at the attempt cap, relay／poison lease clearing and next
head selection, wrong state／tenant／partition／version／token, CAS affected
`0`／`2`, the six mutable CAS columns, and no-leak exception canaries. The
real PostgreSQL case also round-trips the protected binary record through a
second non-UTC connection and serially relays the first head before claiming
the next ordinal.

Focused Mago format is PASS. Focused PHPUnit is PASS: `18 tests, 612
assertions`. The test SHA-256 is
`0b74ec9dc6aea683a83e79f99cc2cdc4173f26af037a815639d4ac77927b7e98`.
The Store SHA-256 is
`9fa14a3bb647aa24be0f5f9743a3dca2858f5e12a4b2909266586cb2b11badd5`.
The Store delta is limited to `safeFailure()`: it classifies the throwable
before clearing it and constructs the fixed failure without retaining the raw
throwable in public exception trace／inspection surfaces. No other Product or
Test path changed; staged0 remains, immutable exact7 remains byte-identical,
and no stage, commit, push, network, deployment, or Complete Final Gate
occurred.

## Historical: PostgreSQL Outbox Store Test Batch Checkpoint

At `2026-09-01T09:02:28+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` was added in
this bounded batch. It covers the frozen Store construction/input and
transaction boundary, owned and caller-level-one append, system and tenant
ordinal/high-water behavior, authenticated same-key idempotency with zero new
resource work, key/hash/raw-key/Draft/tenant/partition/content/body/ID
mismatches, retained aggregate corruption, generated-ID collision ordering,
capacity, quarantine, count-fence, and no-leak canaries. The test also runs a
real PostgreSQL system/tenant append roundtrip. Focused Mago format and
PHPUnit are PASS: `11 tests, 359 assertions`. The test SHA-256 is
`89208dfc0c3b4ebbfc8f05b8d21858fb23d3b173c32807c3baa10b7cd13c8ecb`.

The test exposed two concrete Store defects, both corrected only in
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`: missing-state
orphan probing and strict PHP-array comparison rejecting a semantically
identical authenticated envelope whose nested key order was canonicalized on
decode. The Store SHA-256 is now
`0fde0fe9a7bd212ac0a6116789e5afa2d990d4623456dd0e08ef820c8b25e4e0`.
No other Product/Test path changed; no stage, commit, push, network,
deployment, or Complete Final Gate occurred.

## Historical: PostgreSQL Outbox Store Batch Checkpoint

At `2026-09-01T08:47:02+09:00`, only
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php` was implemented in
this bounded batch. The Store now exposes the frozen constructor and public
operations, owns the single configuration and transition, enforces the
transaction and lock order, validates the retained aggregate, performs
authenticated idempotent append resolution, uses the count-fenced append
statement, selects FIFO heads, authenticates before C1 transitions, and
applies six-column CAS mutations with safe failure mapping. Focused Mago format
check is PASS. The Store SHA-256 is
`04a5a5a665fd236b5fa2d8811a5a5903dea6b03dac00d74bef63e19980f12669`.
No other Product/Test path changed in this batch; no stage, commit, push,
network, deployment, or Complete Final Gate occurred.

## Historical: PostgreSQL Outbox Schema Test Batch Checkpoint

At `2026-09-01T08:30:23+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php` was added in
this bounded batch. It covers pre-SQL schema-name rejection, exact five
statement order, both table column order/type/null/default metadata, all
named constraints and explicit indexes, unexpected catalog objects, the
Accepted Cc/Cf inventory, U+2028/U+2029 positives, isolated U+200E
constraint negatives, state/UUID/hash/ordinal/attempt/body/version/FK/
uniqueness SQLSTATE mutations with before/after snapshots, and NUL body plus
UTC rendering across a second non-UTC PostgreSQL connection. Focused Mago
format is PASS and focused PHPUnit is PASS: `7 tests, 443 assertions`.
The focused test SHA-256 is
`e0a0a03013d18962e3ccfd0a98f33bcb9356539eff80a4a4d0d6bd40119ef067`.
No stage, commit, push, network, deployment, or Complete Final Gate occurred.

## Historical: Luna Takeover Checkpoint

At `2026-09-01T07:34:52+09:00`, the replacement configured Luna Max worker
verified the mandated branch／HEAD, exact7 immutable manifest and aggregate,
and staged0. The only moving paths are the eight preserved in-scope
`src/Internal/Audit/Outbox` files plus this Report and `develop/STATE.md`.
No immutable or outside path drift was found. The partial Product files are
being audited and completed in Task order; no commit, stage, push, network,
runtime activation, or Complete Final Gate has occurred.

## Historical: Worker Start Checkpoint

At `2026-09-01T07:12:59+09:00`, the configured Luna worker verified the
fail-closed dispatch freeze before Product/Test edits: branch
`agent/p23-001-blackops-1-3-feasibility`, HEAD
`fd7671b87a2e498a8b45d7f863b0e341a5a73f70`, clean worktree, and staged index
`0`. The second-corrected General, PostgreSQL/DB, and Documentation/Release
preflight lanes are separately Green (`0/0/0` each). The exact7 immutable
manifest matches all supplied hashes and ordered aggregate
`96155a87e12e61783cb15f30e7dc7bacbb9e5a7cbcad6b3380a2e03e72974845`.
Starting moving hashes matched Report
`4d2b0820e5f429e124a88dacbaf91b585417afb72f8196f4293227c7359a7b27` and
STATE `88e086ab2c06c7af6cd935045aca19f34ec55a489617c9c708c4213992878884`.
No Product/Test file had changed at this checkpoint; implementation is now
authorized only within exact27 plus this Report and `develop/STATE.md`.

## Historical: Initial Summary

The Orchestrator completed read-only C2 authority／source research from clean
HEAD `4bebcb09d8e67e58c424d0c9543e594708d02872`, drafted D151 plus the exact
P23-003C2 Task Packet, and routed three independent preflight lanes.

Those first lanes are Historical Changes Requested and are not combined:
General／API／Architecture／Package returned `P1=3/P2=2/P3=0`, PostgreSQL／DB
returned `P1=4/P2=0/P3=0`, and Documentation／Release returned
`P1=2/P2=2/P3=1`. D151 and the Task now close the overlapping roots before a
fresh review: one configuration source, complete DDL, retained-row boundary,
generated-ID lock／probe order, one Clock order, exact public callable
authority, exact54 package manifest, correct model profiles, and a runnable
clean-HEAD scope gate.

D151 fixes the exact Internal Outbox namespace, fifteen-field row, two-table
PostgreSQL schema, migration version, protected and full-row hydration,
same-key authenticated idempotency, no-gap ordinal allocation, minimum-head
FIFO selection, C1-backed fenced CAS, transaction participation, failure
precedence, Architecture boundary, package inventory, and unreleased boundary.

The preserved worker partial now contains the bounded C2 Production,
migration, direct tests, and three C2 Architecture tests. Focused Product and
C2 Architecture checks are Green at the checkpoints above. Consumer／README／
runtime／Application／Container／CLI／HTTP／Guide／Website／generated-artifact／
deployment bytes remain unchanged. Product work is paused at the first broader
Architecture compatibility failure; prior Green evidence is not promoted to
an Iteration or Final Gate.

The fresh review of committed packet
`26527296da112e315c6a332d26282f52625bb7a7` is now Historical and remains
separate by lane: General `P1=1/P2=0/P3=0`, PostgreSQL／DB
`P1=1/P2=0/P3=0`, Documentation／Release `P1=0/P2=0/P3=0`. General proved
that the migration 12→13 change also requires the current
`DatabaseMigrationCommandTest`, which was outside exact26. DB proved that
`count／min／max` alone accepts an interior retained row whose tenant pair does
not match the locked state. This second bounded management correction adds
that test to exact27 and fixes the retained aggregate at
`row_count／min／max／tenant_mismatch_count`; no Product byte is changed.

## Historical: Initial Changed Files

Orchestrator management／normative drafting and the preserved Luna partial:

- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

- `src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php`
- `src/Internal/Audit/Outbox/AuditOutboxStoreException.php`
- `src/Internal/Audit/Outbox/AuditOutboxRecord.php`
- `src/Internal/Audit/Outbox/AuditOutboxAppendResult.php`
- `src/Internal/Audit/Outbox/AuditOutboxLookupResult.php`
- `src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`
- `migrations/postgresql/Version20260901000000.php`
- `tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php`
- `tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php`
- `tests/Architecture/AuditOutboxStorageArchitectureTest.php`
- `tests/Architecture/AuditOutboxStateArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`

## Historical: Initial Decisions and Assumptions

- C2 uses a new `BlackOps\Internal\Audit\Outbox` namespace, preserving the
  Accepted C1 Delivery and B2 Storage Production inventories.
- Existing-key resolution occurs before Clock, UUID, active key, nonce,
  encryption, capacity, or mutation.
- One Store-owned `AuditOutboxConfiguration` constructs the only transition,
  is passed into every full-row hydration, and creates initial pending state;
  no hidden second configuration is retained.
- A same-key retry is authenticated by rebuilding the incoming Draft with the
  stored record ID／recordedAt and comparing the exact envelope.
- A new append proves the retained-row boundary, captures one Clock, generates
  one record UUID, takes its record lock, proves store-wide absence, allocates
  one ordinal, protects once, and commits state／row through one count-fenced
  CTE.
- FIFO selects only the minimum pending／leased ordinal in a partition.
  Same-partition `SKIP LOCKED` is forbidden. Poison is acknowledged.
- C2 supports owned root level 0 and joins caller-owned active level 1. C3
  later owns the Application-facing same-root orchestration and key composition.
- C4 later owns the active relay loop; C2 only provides persistent operations.
- Framework migration count will become 13, Community total 18, and required
  package inventory exact54 only after implementation.
- Physical hydrators validate shape only; the Store-owned partition deriver
  binds hydrated tenant to clear／requested partition before decrypt.
- Current migration-command counts move framework-only 12→13 and
  Framework-plus-one-Application 13→14 inside exact27 scope.

## Historical: Initial Commands and Results

Read-only research completed:

- branch／HEAD／worktree inspection: PASS; clean HEAD `4bebcb09...2872`
- current D145／D148／D149／D150 and Specification 105／106 contract scan: PASS
- current C1／B1／B2 source, migration, Architecture, package, and consumer
  inventory scan: PASS
- three bounded Luna read-only research lanes: completed; no edits
- initial independent preflight lanes: Historical Changes Requested with the
  separate counts recorded above; no Product edits or command execution
- clean-commit fresh lanes: Historical General `P1=1/P2=0/P3=0`, DB
  `P1=1/P2=0/P3=0`, Documentation `P1=0/P2=0/P3=0`; read-only, no Product
  edits or command execution
- repository profile check: PASS (`sol/max`, `luna/max`, `sol/xhigh`)
- corrected Task all fenced Bash blocks extracted and parsed by `bash -n`: PASS
- D151 exact ordered package manifest count: PASS (`54`)
- corrected exact-nine worktree path set: PASS
- `git diff --check`: PASS

Not Run or not yet complete for the current corrected candidate:

- repository-wide lint, analysis, PHPUnit, and Deptrac
- complete Iteration and Final Gates
- Community Board clean install
- package export
- generated Documentation／browser verification
- push, release, deploy

## Historical: Initial Acceptance Criteria

- [x] C2 unresolved physical／callable／transaction boundaries identified.
- [x] D151 drafted with one implementation-unique contract.
- [x] Exact P23-003C2 Files Allowed and required fixtures drafted.
- [x] Stable `1.2`／unreleased `1.3` and no-partial-activation boundary retained.
- [x] Initial preflight findings corrected in Decision／Task／management scope.
- [x] First clean-commit re-preflight findings corrected in Decision／Task／
  management scope.
- [x] Corrected nine-file management packet committed by the Orchestrator;
  exact self-commit SHA is supplied after commit in the preflight dispatch.
- [x] Second bounded nine-file management correction is this commit checkpoint;
  its self-commit SHA is likewise supplied only after commit.
- [x] Second-corrected General／DB／Documentation preflight Green separately;
  Historical after the Architecture-scope correction.
- [ ] Corrected-scope fresh General／DB／Documentation preflight Green.
- [ ] Luna implementation and Iteration Gate Green.
- [ ] Complete Final Gate Green.
- [ ] Fresh independent implementation reviews Green.
- [ ] P23-003C2 Acceptance, candidate commit, and clean same-SHA package export.

## Historical: Initial Remaining Issues

- Fresh re-preflight remains pending from the bounded Architecture-scope
  correction freeze.
- Product implementation is preserved but paused; remaining inventory／
  migration-count／package／Community updates and full gates remain pending.
- C3, C4, and parent P23-003C remain pending after C2.
- Generated Documentation artifacts and Browser remain Not Verified and are not
  C2 acceptance surfaces.

## Historical: Initial Suggested Next Action

Dispatch only the three DB Product／fixture corrections and exact Architecture
map to the configured Luna worker. Then refreeze, run focused real-PostgreSQL
and Architecture checks, and obtain fresh DB／General implementation review.
