# P23-003D5R1: Storage Rotation Transaction Outcomes

Status: Accepted (local) — final evidence Green; BlackOps 1.3 unreleased

## Goal

Make native storage rotation preserve committed progress/terminal state when
commit throws, as the smallest complete prerequisite for D5's immutable phase
events. This child does not complete the rotation adapter or parent1.3.

## Scope and Authority

- Specifications99 (Key Rotation/CLI),105/106 (D migration),109 (verification),
  [118](../../spec/118-storage-rotation-transaction-outcomes.md); D154 and
  [D168](../../decisions/168-storage-rotation-transaction-outcomes.md).
- Two error boundaries only: row commit propagation and single normal finalization.
  Native begin/finish cleanup, SQL/CAS, tenant binding, schema, hooks, C3/Catalog,
  CLI source and ordinary composition are outside scope.
- No stage/commit/push/tag/export/deploy. D5P/D5H and mixed Documentation stay preserved.
  No baselines, thresholds, dependencies, configuration or memory-limit changes.

Exact worker-owned files:

1. `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php`
2. `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php`
3. `tests/Transport/PostgreSql/PostgreSqlStorageProtectionRotationTransactionOutcomeTest.php` (new)

Root owns this Task/Report, spec118/D168, the bounded D154 dependency note,
spec index, TODO, STATE and generated state archives. If another Product file is
needed, stop and return the reason before expanding scope.

Baseline `/tmp/blackops-d5r-20260929/baseline.json` contains3303 Git-visible files,
233 preexisting dirty entries, HEAD8106c3556248dca1f9719b9d55cf1f647255a422 and index
patch SHA. Its SHA256 is6eba96ba7b533574bcc13c4c03126ebabc1424c29e187ef5fa825b7faf358118.
`resume-preservation.json` proves D5P11/1699 input equality and original16 Reel
handoff/index preservation. Published Reel main a419f375 is the delivery authority;
the primary stale handoff is frozen, not selected for publication or staging.
Future branch integration must combine published Reel and older docs deliberately.

## Team and Ownership

| Owner | Work | Verification/resource |
| --- | --- | --- |
| Root Orchestrator | Authority, management, integration, Acceptance | All serial PHP/Docker/DB/cache/consumer gates; only STATE writer |
| Astra/max explorer | Completed read-only native root/source survey | No Product edits, no DB/tests |
| Astra/max independent reviewer | Authority then transaction/security implementation/evidence review | Read-only; no shared DB/test execution |
| Luna/max worker | Exact three Product/Test files after authority Green | Local read/diff only; Root runs shared gates |

Profiles follow `.codex`; metadata absence alone is not a blocker. Root alone owns
new schema `blackops_rotation_outcome`; test teardown restores faults/Connections
and drops it. Existing rotation/matrix schemas use their existing teardown in
serial tests. Consumer script owns its temporary project/schema and cleanup.

## Acceptance Criteria

- [x] Authority review Green before implementation; exact scope/dependency recorded.
- [x] Row commit exceptions never become failed-row results, later-row execution or
  finalization; active cleanup is attempted and earlier commits remain intact.
- [x] Normal finalization runs once even on SQL failure or commit-then-throw;
  remaining-scan failure retains bounded best-effort failure finalization.
- [x] Real begin/row/terminal commit-then-throw, before-effect failure, precommit
  advancement/finish failure, cleanup/recovery and explicit resume are proved.
- [x] Existing plan/success/CAS/nine-purpose/kill-resume behavior passes. Native
  DBAL storage/driver faults use redacted storage_error/exit1; explicit IAE category
  fixtures preserve the existing input_error/exit2 branch without promising
  all-Throwable redaction or classifying unknown commit persistence as an input fault.
- [x] Frozen full PHPUnit512M, required static/architecture and consumer gates pass;
  independent final transaction/security review has no open finding.
- [x] D5P, other prior changes, docs/artifacts, release authority, index and HEAD
  stay preserved outside exact scope; Release Impact and remaining work recorded.

## Verification Plan

Change class: native SQL/transaction/security error ordering, so real DB fault
tests, frozen full and independent specialist review are required. Test fixtures
must prove persisted data through another Connection; mocks alone are insufficient.

Evidence root `/tmp/blackops-d5r-20260929`. Before/after manifests include exact
candidate, all src/tests/migrations, consumer app, Composer lock/autoload/runtime,
PHPUnit/Mago/Deptrac/Compose/Docker settings. Record tool/image IDs, commands,
times, exit, logs and JUnit; separate management hashes from runtime evidence.
No concurrent shared PostgreSQL/cache mutation. PHPUnit uses repository512M.

| Phase | Literal command (Root) | Re-run trigger |
| --- | --- | --- |
| Iteration | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings tests/Transport/PostgreSql/PostgreSqlStorageProtectionRotationTransactionOutcomeTest.php` | Product/fixture change or failed evidence |
| Iteration | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --filter 'PostgreSqlStorageProtection(RotationTest\|PurposeMatrixTest)'` | Native compatibility concern; not repeated after identical full |
| Final | `docker compose run --rm --no-deps -T app mago format --check src tests` | Changed PHP |
| Final | `docker compose run --rm --no-deps -T app mago lint` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago lint --verify-baseline` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count` | Changed analysis inputs |
| Final | `docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered` | Changed source graph |
| Final | `docker compose run --rm -T app php vendor/bin/phpunit --display-warnings --log-junit .phpunit.cache/d5r1-full-junit.xml` | Frozen runtime/fixture/config change or failed full |
| Final | `bash tests/Consumer/storage-protection-rotation.sh` | Changed runtime/consumer inputs or failed journey |
| Final | `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+\|D[0-9]{3}\|P[0-9]+-[0-9]+\|TODO\.md:[0-9]+' src tests --glob '*.php'` | Changed PHP |
| Final | `git diff --check` and `node develop/orchestration/state.mjs --check` | Scope/management changes |

Full subsumes same-condition focused tests. No package inventory edit/export is
required: no new Product type, package path or packaging contract. No Website
build/browser repetition for an unchanged public source/artifact tree.

## Release Documentation Impact

Authority tuple remains Experimental Stable1.2.1 (Framework direct1c72fced / source
4efee09f; Skeleton direct7a47e344 / source87a02538), roadmap1.3.0 unreleased.
Existing capability `protected-storage` is affected in development only; no new
public capability/API/command/configuration. CLI unknown-outcome branch selection
changes as spec118 explicitly states; ordinary schemas/options/exits stay exact.
The existing IAE input_error branch remains; no all-Throwable error normalization.
Public guide/route/old-version/historical allowlist and Source/Search/LLM artifacts
are frozen by hash. No new public positive/negative fixture is appropriate before
release; existing public Stable fixtures are preserved. Internal architecture
guidance for the future adapter is spec118 now; user-facing operational/migration
guidance belongs to1.3 integration/P23-005 before release.
same-SHA CI, package export, Documentation delivery and Production deploy are not
run or claimed by this local child and remain parent release work.

## Failure and Completion

Stop on scope expansion, altered prior input, unsafe DB cleanup or baseline
relaxation. Preserve logs/process IDs and resume the failed command and necessary
suffix, not the whole successful prefix. Root reports exact remaining gates.

Report: [P23-003D5R1](../reports/P23-003D5R1-storage-rotation-transaction-outcomes.md).
After local Acceptance, define D5R2's typed phase hook, trusted tenant/interruption
identity, success meaning and C3 same-root writer under new most-specific authority.
Parent1.3 still requires other D5 children/integration, E/F/G and post-F control,
hardening, documentation/local owner preview, same-SHA release gates and delivery.
