# P22-002 Stable 1.2 Release Documentation Report

Status: Accepted

## Documentation Reviewer Correction Pass

Applied the final P1/P2 findings: corrected Stable-versus-candidate claims in CHANGELOG, removed the duplicate Unreleased trailer, documented Stable Quickstart direct dependency ownership, reclassified Reader interfaces as Infrastructure SPI while retaining aggregate Store PublicApi boundaries, added EphemeralOutcome credential non-persistence and proxy/Ray boundaries, separated database DDL guards from Application key/tenant policy preflight, added the executable Stable-to-candidate source/Provider merge matrix, and strengthened semantic guards and the actual-tag Consumer's post-update build/operation-list checks. Final Documentation Review passed with P1=0／P2=0／P3=0 and permits acceptance.

Bounded follow-up correction clarified that Reader types/methods remain in aggregate Store implementation boundaries (only direct PublicApi designation/marker is removed) and that the local source diff command runs from a Framework Repository Root or via explicit `git -C` path. The Orchestrator deferred the Consumer rerun until the worker stopped, then completed the exact run recorded below.

The Orchestrator then reproduced a compatibility-lane failure after Composer update: Stable `config/app.php` has no `frontend_manifest`, so candidate `build:compile` failed with `Application configuration key "app.build.frontend_manifest" must be a non-empty absolute path.` The Consumer and UPGRADE now apply/document only this minimal absolute `dirname(__DIR__) . '/var/build/frontend.php'` config mutation after source-hash checks; `command_manifest` remains optional fallback. The subsequent exact `bash tests/Consumer/framework-update-generators.sh` rerun passed from start to cleanup with source-state invariant and `Framework update generator smoke passed.`

The final runtime correction documents that Compatibility-first is build/list-only: Candidate HTTP/Worker composition unconditionally resolves `StorageKeyProvider` and must not be run with Stable `providers=[]`. The Opt-in lane now includes Service Provider binding plus `config/app.php` `services` registration, canonical Quickstart `SampleStorageKeyProvider` source placement, disposable untracked `.env` injection with restrictive permissions and cleanup, and explicit P22-003 shared Database migration/setup with DDL guard evidence, Provider-present HTTP/Worker Positive, and Provider-missing HTTP/Worker safe Negative lanes. Consumer temporary checkout reuse is not claimed.

The final secret/config correction handles both Stable and current `.env.example` shapes: Stable has no `BLACKOPS_STORAGE_KEY` line while current has an empty line. The runbook now filters any existing key line and appends exactly one generated value with `printf`, avoiding delimiter issues and never printing the secret. The complete `app/ApplicationServiceProvider.php` source now includes PHP strict types, `App` namespace, imports, and binding; `config/app.php` registers that class.

The cleanup correction now removes the disposable `.env` before attempting Compose shutdown and suppresses a best-effort `docker compose down` failure, so `set -e` cannot leave the generated key file behind. The semantic guard rejects the previous cleanup ordering and requires the fail-closed ordering.

The runtime probe correction keeps Step 3's `EXIT` trap alive across Steps 4-7 in one Disposable Application Root shell, waits for the Worker service and HTTP endpoint with bounded retries, and asserts Worker `running`, HTTP `200`, `application/json`, and exact `/welcome` JSON `{"message":"Welcome to BlackOps"}`. The probe does not claim Operation compatibility; P22-003 owns the shared Database migration/setup and Provider-present／missing HTTP／Worker lanes.

## Summary

Audited the immutable annotated Stable `1.1.0` tag against committed `main` (`1.1.0-249-ga8243bd`) and synchronized the candidate release notes, upgrade runbook, public Releases page, website guards, and Stable-to-candidate generator Consumer. No tag, push, release, Packagist, Skeleton publication, or deployment was performed.

## Audit Base and Candidate

- `git cat-file -t refs/tags/1.1.0`: `tag` (annotated).
- Peeled Stable commit: `e3df5576c7216cfe8bd9e10e12ee6795f7674088`.
- Candidate HEAD: `a8243bd`; `git rev-list --count 1.1.0..HEAD`: `249`.
- PublicApi source-marker inventory: 119 at `1.1.0`, 215 at HEAD. A separate all-PHP fixture-inclusive grep is 124→220 files; it is not the PublicApi count.
- PostgreSQL Framework migrations: 2 at Stable, 11 at HEAD (9 added).

## Release Surface Inventory

- Public additions are grouped as PSR-15 authentication/authorization and actor context; named DBAL and transaction/AfterCommit contracts; status/outcome/diagnostics; environment/SAPI/UUID/seeder; idempotency, outbox, replay and deferred child dispatch; tenant propagation and protected storage/key rotation; JSONL/OTel/health; frontend generation; scheduling, console adapters and framework proxy artifacts.
- Additive signature changes requiring migration review include `Dispatcher::dispatch` actor/idempotency/tenant/telemetry options, `ExecutionContext` correlation/actor/tenant data, `OperationResult` and deferred message metadata, retention query context, and JSONL envelope fields (`kind`, `schemaVersion`, `attempt`, `telemetry`).
- `CanonicalJournalReader` and `OutcomeReader` are no longer PublicApi; authorized default-deny OperationData queries are the replacement.
- `EphemeralOutcome` is a one-response credential boundary with no Journal/Outcome/Status/Artifact persistence. Transactional adoption uses the Framework-owned Proxy Profile; Ray is absent from both Stable and Candidate Composer packages, so there is no Stable package-removal migration.
- Runtime Composer additions are `ext-sodium`, `vlucas/phpdotenv`, and `open-telemetry/api`; development telemetry SDK/exporter and HTTP adapter remain development dependencies. Stable Quickstart `vlucas/phpdotenv`／`nyholm/psr7` are Framework Runtime dependencies in the candidate; `nyholm/psr7-server`／`laminas/laminas-httphandlerrunner` are removed because the candidate Quickstart no longer imports them. Application-owned direct imports remain the Application's responsibility.
- Framework-owned commands now include build/operation/migration status and migrate, frontend, retention, scheduler, worker, auth, seeder, and operation adapters. Project-root `blackops` remains the Application-facing entrypoint.
- Migration order is Framework PostgreSQL migrations first, then Application migrations. The nine added migrations are listed in `UPGRADE.md`; five are explicitly irreversible (`20260724000000`, `20260803000000`, `20260808000000`, `20260808010000`, `20260808100000`) and the other four have reversible `down()` paths with data-loss/reconstruction risk.
- Stable `1.1.0` install CTA, historical changelog, and 1.0→1.1 procedure remain unchanged. Upgrade documentation now separates a compatibility-first lane that preserves Composer/source hashes, applies only the minimal Application-owned `frontend_manifest` mutation, and proves candidate `build:compile`／`operation:list` without claiming HTTP／Worker compatibility, from an opt-in Candidate-Skeleton lane where only required providers/config are manually aligned. Candidate surfaces are experimental and unpublished.

## Breaking and Migration Matrix

| Area | Stable 1.1.0 | Candidate 1.2.0 | Migration boundary |
| --- | --- | --- | --- |
| Runtime/bootstrap | legacy application bootstrap | explicit environment/SAPI runtime and application-owned entrypoint | manually merge bootstrap/config; update Composer from local/VCS candidate |
| Public data reads | reader-oriented access | authorized default-deny OperationData queries | replace direct reader calls and provide tenant/actor/purpose |
| JSONL | prior envelope | versioned `kind`/`schemaVersion`/attempt/telemetry envelope | update consumers and retain masked fields only |
| Storage | no protected-storage migration | BOPD envelope, key provider and rotation checkpoints | backup database and keys; fail closed on plaintext/non-empty legacy schema |
| Database | 2 Framework migrations | 11 Framework migrations | Framework first, Application second; no fabricated migration history |
| Generated artifacts | Stable generators | candidate stubs, manifest, frontend and proxy artifacts | regenerate and review Application-owned source hash invariants |

## CHANGELOG / UPGRADE Coverage

`CHANGELOG.md` now has complete Unreleased Added, Changed, Removed, Fixed and Known Limitations sections, explicitly distinguishes the nine migrations and five irreversible boundaries, records reader/PublicApi and JSONL changes, and does not claim publication. `UPGRADE.md` preserves the historical 1.0→1.1 section byte-for-byte and adds an ordered 1.1→candidate runbook for backup, local Composer source, bootstrap/config, security/storage, migrations, generated artifacts, runtime verification, and rollback/publication boundaries.

## Actual Framework Update Consumer Evidence

`tests/Consumer/framework-update-generators.sh` now clones the repository, checks out the actual annotated `1.1.0` tag, verifies its peeled commit, archives the Stable quickstart, creates a temporary local `1.2.0` tag at committed HEAD, and updates only `blackops/framework`. It asserts Application direct requirements and pre-existing lock versions remain stable while expected framework runtime dependencies are added, checks the complete stable Application-owned inventory, verifies old/new generator output and vendor stubs, then applies only the documented `frontend_manifest` config migration before candidate `build:compile` and `operation:list` (including `welcome.show`). An initial nonexistent `package.json` inventory failure and the subsequent missing-`frontend_manifest` build failure were reproduced; the exact final rerun passed through cleanup and source-state invariant.

## Documentation and Website Coverage

- Releases guide now links canonical root CHANGELOG/UPGRADE files and the actual-tag Consumer, while preserving Stable `1.1.0` install language and unpublished-candidate warning.
- Website content-map description, guide tests, content/site guards, and internal smoke documentation are synchronized. Roadmap Deferred Ecosystem now excludes implemented Documentation Website publication and Scheduled Operation; only custom domain/version selector and future Batch/Saga remain deferred.
- Website `pnpm test` passed (77 tests), 42-page `check`/static build/site check and artifact scan passed. Content, diagrams, Blume validation, and type checks passed.

## Changed Files

`CHANGELOG.md`, `UPGRADE.md`, `docs/guide/mvp-status.md`, `docs/guide/core-api.md`, `docs/internal/installed-application-status.md`, `docs/website/content-map.mjs`, `docs/website/scripts/check-site.mjs`, `docs/website/tests/guide-code.test.mjs`, `docs/website/tests/reader-experience.test.mjs`, `tests/Consumer/framework-update-generators.sh`, `tests/Consumer/version-baseline.sh`, `develop/spec/60-post-phase-10-roadmap.md`, `develop/spec/103-stable-1-2-release-plan.md`, `develop/TODO.md`, `develop/STATE.md`, and the P22-002 Task／Report.

## Decisions and Assumptions

- Candidate means committed HEAD, not uncommitted documentation edits; the temporary Consumer tag is local only.
- Dependency evidence distinguishes Application direct requirements from framework transitive additions rather than requiring every lock package to remain byte-identical.
- No production-code or out-of-scope test changes were made.

## Commands and Results

- PASS: annotated tag/type/peeled commit and 249-commit count checks.
- PASS: `bash -n tests/Consumer/framework-update-generators.sh tests/Consumer/version-baseline.sh`.
- PASS: Composer strict root and quickstart validation, version-baseline guard, actual Stable-to-candidate Consumer after inventory and minimal `frontend_manifest` corrections, Website test (77), 42-page check/build/site check, artifact scan, management-ID scan, and diff check.
- PASS (correction pass): `bash -n tests/Consumer/framework-update-generators.sh tests/Consumer/version-baseline.sh`; `bash tests/Consumer/version-baseline.sh`; Website test (77), including the PublicApi Reader/SPI semantic guard.
- PASS (bounded follow-up): focused `bash -n`, version-baseline guard, Website 77 tests, and `git diff --check`.
- PASS (latest correction): version-baseline guard covers cleanup ordering, common Database migration/setup plus HTTP／Worker lane wording, one-shell trap scope, bounded runtime retries, and HTTP status/content-type/body assertions.
- PASS (latest Orchestrator rerun): exact `bash tests/Consumer/framework-update-generators.sh` completed with cleanup, source-state invariant, and `Framework update generator smoke passed.`
- Mago exact `format --check src tests examples` traversed ignored third-party `examples/community-board/vendor` and `node_modules` (835 Hack/third-party files) and failed; an equivalent clean committed clone with only tool-owned vendor mounted passed. No PHP source was changed for this environment issue.
- PASS: `git diff --check` at checkpoint.

## Acceptance Criteria

Documentation, audit, actual-tag Consumer source, version guards, roadmap correction, and report/state synchronization are accepted. Required release-documentation gates passed except the exact broad Mago invocation's ignored third-party traversal; its clean-clone equivalent passed. Independent Documentation Reviewer final findings are P1=0／P2=0／P3=0 and acceptance is permitted.

## Remaining Issues

1. P22-003 fixed-SHA Full Gate must add shared Database migration/setup with DDL guard evidence, Provider-present HTTP/Worker Positive, and Provider-missing HTTP/Worker safe Negative lanes; Compatibility Consumer evidence does not claim HTTP/Worker compatibility.

## P22-003 Inputs and Suggested Next Action

Use peeled Stable commit `e3df5576c7216cfe8bd9e10e12ee6795f7674088`, audit candidate `a8243bd`, the migration table, and the actual-tag Consumer as inputs. After committing the accepted P22-002 documentation, P22-003 must fix the resulting committed HEAD and execute the full local／CI gate plus the shared Database and Provider-present／missing HTTP／Worker lanes before any separately authorized publication work.
