# P23-003D5O1: Observer Replay Transaction Outcomes

Status: Locally Accepted — implementation, frozen full and independent Review complete; BlackOps1.3 unreleased

## Goal and Authority

Prevent ambiguous cursor commits from being converted into fabricated native
replay failures, and preserve native root/lock cleanup before the four immutable
replay phase events are added in D5O2. Follow specifications80/99/105/106/109,
[120](../../spec/120-observer-replay-transaction-outcomes.md), D154 and
[D170](../../decisions/170-observer-replay-transaction-outcomes.md).
Independent authority Review must be Green before Product changes.

## Exact Scope and Ownership

Luna Max owns only:

1. `src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php`
2. `src/Internal/Replay/ObserverReplayRuntime.php`
3. `tests/Transport/PostgreSql/PostgreSqlObserverReplayTransactionOutcomeTest.php` (new)

No schema/migration, selector/binding/writer/CLI, public API, C3/Catalog/adapter,
dependency, baseline/threshold/configuration, package or Documentation edits.
If another file is necessary, return the bounded evidence before expansion.
Root owns this Task/Report, spec120/D170, bounded D154 dependency note,
spec index, current TODO/STATE and its archives. Root also owns006 ledger updates.
Source investigator and transaction/security reviewer are read-only. Worker
does not stage/commit or write STATE. Root handles later separate reviewed commits
under the now-authorized release parent; this child does not itself publish.

Baseline `/tmp/blackops-d5o1-20260929/baseline.json` at22:55:43+09:00 contains3338
Git-visible files,252 prior dirty entries, HEAD8106c355 and unchanged cached patch
(pre-existing intent-to-add index entries are retained),
SHA256 `83501f595fafac42c8329c6d90ee92245b96ac66f8e83a44c8bae6cd8365101a`.
`predecessor-preservation.json` confirms D5R2 frozen17/1707/2219 input equality.
The two original source files and public Source/artifact hashes are retained.
Preserve all previous accepted Product, mixed Documentation and published Reel
authority. This native child cannot silently activate the future adapter.

Root alone runs PHP/Docker/DB/cache and final gates. The new real-DB fixture owns
schema `blackops_replay_outcome`; teardown must disarm faults, close Connections
and remove its schema on failure. Do not mutate existing shared schemas outside
their serial tests. Worker may inspect/diff but runs no shared tests or Docker.

## Acceptance Criteria

- [x] Most-specific authority and exact scope independently Green before Product.
- [x] Four Store mutation methods reject foreign roots before effects, never
  roll back an inactive/foreign transaction and release the newly acquired lock
  on begin failure, including when rollback cleanup fails.
- [x] Runtime keeps true selection/projection/Observer/flush failure persistence,
  but cursor/advance errors produce a bounded failure with no `fail`, normal
  finalization, later-record work or commit-outcome inference.
- [x] Real PostgreSQL before/after-effect faults for all roots prove checkpoint/
  invocation audit persistence, cleanup attempts, prior-root preservation and
  explicit resume with unchanged canonical bytes and at-least-once Record IDs.
- [x] Existing replay/dry-run/CLI/selector/target/projection behavior passes;
  inherited general CLI/driver exception limits are recorded without overclaim.
- [x] Frozen full512M, required static/format/identifier gates and independent
  transaction/security source/evidence Review pass.
- [x] Prior work, HEAD/index, Release Authority and public Source/artifacts are
  preserved; child and parent remaining work are recorded separately.

## Verification Plan

Change class: native SQL/transaction/security ordering. Root uses real PostgreSQL,
fixed source/test/config/dependency manifests, literal argv/environment/elapsed
time/log/exit and fresh JUnit. Full exactly once on a passing frozen candidate,
unless an actual changed input, failure or missing evidence requires rerun.
No same-condition focused tests are repeated after the full.

| Phase | Command | Re-run condition |
| --- | --- | --- |
| Iteration | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings tests/Transport/PostgreSql/PostgreSqlObserverReplayTransactionOutcomeTest.php` | Changed implementation/fixture or failed evidence |
| Iteration | Existing Store/Runtime/CLI replay fixtures if a specific compatibility concern appears | Concern not already covered by full |
| Final | `docker compose run --rm --no-deps -T app mago format --check src tests` | Changed PHP |
| Final | `docker compose run --rm --no-deps -T app mago lint` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago lint --verify-baseline` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered` | Changed source graph |
| Final | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d5o1-full-junit.xml` | Frozen runtime/fixture/config change or failed full |
| Final | `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+\|D[0-9]{3}\|P[0-9]+-[0-9]+\|TODO[.]md:[0-9]+' src tests --glob '*.php'` | Changed PHP |
| Final | `git diff --check` and `node develop/orchestration/state.mjs --check` | Management change |

Full includes all3644 predecessor cases plus the exact new fixture identities.
No dedicated replay Consumer script exists; actual Store/Runtime/Console fixtures
and real-PG regression prove the current public path. Unchanged package paths and
public docs do not require child export or Website repetition. Parent006 retains
all final committed-SHA package/remote Consumer/release requirements.

## Release Documentation Impact

Starting Release Authority tuple is the unchanged1.2.1 tuple in parent006;
1.3.0 remains unreleased. Development `framework-core` replay error behavior is
affected, with no new API/command/configuration/capability ID. Record the native
root admission and unknown-outcome/resume changes in005/006 migration docs.
Public Source/routes/old-version classifications/historical allowlist/Search/LLM
stay byte-identical here. No remote CI/export/deployment claim is made by this child.

## Remaining Work and Next Action

No local child work remains. Root accepts the exact three-file candidate after
frozen3668/7694025 full, required unchanged quality gates, complete case/input
proof, cleanup and independent final evidence Review. The Report records hashes
and precise unknown-outcome/privacy/at-least-once limits. This is local
Acceptance, not clean committed-SHA qualification or publication.
Next issue reviewed D5O2 and F1 Packets against this actual Accepted baseline,
with serialized package ownership. Parent006 remains active through ordinary
D integration, E/F/control/G,004A/005, Owner preview and release/delivery.
