# P22-003 Stable 1.2 Release Candidate Gate Report

Status: In Progress — Candidate `413d0964cc132d685b228d5b8d697ac6cc4543e6` is superseded after the fixed-SHA gate exposed a Community Board Storage Protection bootstrap gap. The bounded Provider, fresh setup, lock metadata, Ray cleanup, Consumer, specification, and documentation correction passes independent review; a corrected commit, new fixed SHA, complete gate restart, and Orchestrator acceptance remain pending.

## Summary

Implemented the bounded Stable-to-candidate Runtime Consumer and its GitHub Actions job. The Consumer starts from the actual annotated Stable `1.1.0` quickstart in a fresh disposable checkout, installs and migrates Stable's two Framework migrations, creates a local annotated `1.2.0` tag at the committed candidate source, updates only `blackops/framework`, applies the documented Provider/config/environment merge plus the exact three runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`), proves the additional nine migrations and DDL guards, and defines Provider-present HTTP/Worker and Provider-missing HTTP/Worker safe-negative lanes. The existing Auth Generator Fresh, FrankenPHP Worker, and Scheduled Operation Consumers now prepare a fail-closed 32-byte Storage Key before Docker/Composer execution without logging key material. The restarted gate then found that Community Board neither registers the mandatory Application-owned `StorageKeyProvider` nor prepares a Local key, so its runtime could not resolve before the intended pre-migration database failure. The bounded correction now registers the Local/Test provider, generates a fail-closed fresh key, preserves existing `.env` files, synchronizes the path-repository lock metadata, and proves the full 11 Framework + 5 Community Board (16 total) clean-install journey without exposing key material. No external tag, branch, release, Packagist, or deployment state was changed.

## Candidate Establishment and Fixed SHA Evidence

- Baseline commit: `61142d254861ffe13985679c338f592a46151af5`.
- Superseded candidate: `99f723dfc9bcf1e859689c81878839ee37d2ba91` (`test: add stable 1.2 runtime upgrade gate`).
- Superseded candidate: `413d0964cc132d685b228d5b8d697ac6cc4543e6` (`test: prepare storage keys in quickstart consumers`).
- Final Fixed Candidate: pending the reviewed Community Board Storage Protection correction commit.
- Candidate source is cloned from the repository's committed `HEAD`; uncommitted Task/STATE/Report files are not mounted as candidate source.
- The Consumer verifies annotated Stable tag type and peeled commit, archives only `1.1.0:examples/quickstart`, then creates an annotated local `1.2.0` tag at the committed candidate SHA.
- Documentation Reviewer returned P1=0/P2=0/P3=0 and permitted the bounded Gate Asset commit. No external Tag/Push was performed.
- The fixed-SHA full gate found `auth-generator-fresh.sh` copies `.env.example` without populating the now-required `BLACKOPS_STORAGE_KEY`; the same stale setup is present in `frankenphp-worker-mode.sh` and `scheduled-operation.sh`. Per the Task reset rule, `99f723d` is not silently retained as the Final Fixed Candidate.
- Documentation Reviewer returned P1=0/P2=0/P3=0 for the seven-file correction and permitted commit `413d0964cc132d685b228d5b8d697ac6cc4543e6` as the replacement Final Fixed Candidate.
- The restarted gate at `413d096` passed the initial syntax, Composer, package-export, clean-source format/analyze, full PHPUnit rerun, and Auth Generator Fresh stages. Broad Mago lint and Deptrac reproduced the recorded baseline blockers. Community Board clean install stopped at its pre-migration seed-message assertion because the actual safe output was `Database seeding runtime could not be resolved.`, proving the Reference Application lacked the mandatory Provider composition rather than reaching the intended database-before-migration failure. The Task reset rule supersedes `413d096`.

## Runtime Upgrade Consumer Evidence

`tests/Consumer/framework-update-runtime.sh` uses a unique Compose project and random loopback HTTP port. It removes `.env` before Compose shutdown during all EXIT/INT/TERM cleanup paths, uses `umask 077`, never prints generated key material, and checks root Git status and project containers/volumes/networks after cleanup.

The positive lane runs Stable `database:migrate` once, then uses read-only catalog checks for exactly two Stable Framework rows in current-schema `blackops.schema_migrations`, six baseline tables, and baseline constraints. It updates only `blackops/framework` to candidate `1.2.0`, whose status must recognize `applied: 2`/`pending: 9` before migration and finish at `applied: 11`/`pending: 0`, with latest migration `Version20260808100000`, protected-storage columns/rotation tables, and seven BOPD DDL constraints. It then builds candidate artifacts, sends the required `X-Sample-Token: local-example` request header, proves HTTP `200`/`application/json`/exact `{"message":"Welcome to BlackOps"}`, a running Worker service, and a successful one-iteration Worker command.

The candidate config merge is bounded to the unique Stable `http_manifest` marker and the final root `];` closure. It preserves an existing frontend marker, inserts only the candidate `frontend_manifest` and `services` section, checks each replacement count, verifies `file_get_contents`/`file_put_contents` results, and emits only labelled non-sensitive failure diagnostics.

The Consumer applies only the three authorized candidate runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`) from the candidate quickstart. Generic source snapshots exclude exactly those intentional paths, while byte equality is checked before build and after both positive and negative lanes; Caddyfile, Compose, and other Application-owned files remain untouched.

The negative lane removes only the Provider binding after the common migrated database and candidate build. It checks the exact internal safe Provider error without printing it, then exercises `http-classic`／`classic-mode` for generic HTTP `500` JSON and actual Worker CLI startup for non-running/nonzero safe failure. Provider-present remains Worker-mode HTTP／Worker. HTTP/Worker output and Classic HTTP logs are bounded and searched without printing for key, payload, SQL, stack, tenant, or actor leakage.

The exact pre-commit baseline run passed with: `Framework update runtime consumer passed: stable=e3df5576c7216cfe8bd9e10e12ee6795f7674088 candidate=61142d254861ffe13985679c338f592a46151af5 migrations=11 provider-present=http-worker provider-missing=classic-http-worker-safe-negative.` Cleanup completed and the repository source-state invariant held with exit 0.

## GitHub Actions Evidence

`.github/workflows/ci.yml` adds `framework-update-runtime` with `fetch-depth: 0`, container UID/GID setup, a 45-minute timeout, and the Runtime Consumer command. Remote GitHub Actions evidence is not available until a separately authorized branch push; local wiring/static checks are the only current evidence.

## Changed Files

- `tests/Consumer/framework-update-runtime.sh`
- `tests/Consumer/auth-generator-fresh.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/scheduled-operation.sh`
- `tests/Consumer/version-baseline.sh`
- `.github/workflows/ci.yml`
- `CHANGELOG.md`
- `UPGRADE.md`
- `docs/guide/installation.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/website/tests/guide-code.test.mjs`
- `examples/community-board/.env.example`
- `examples/community-board/composer.lock`
- `examples/community-board/app/ApplicationServiceProvider.php`
- `examples/community-board/app/Security/SampleStorageKeyProvider.php`
- `examples/community-board/bin/setup`
- `examples/community-board/README.md`
- `tests/Consumer/community-board-clean-install.sh`
- `docs/guide/community-board.md`
- `docs/internal/bootstrap.md`
- `develop/spec/09-runtime-and-di.md`
- `mago.toml`
- `deptrac.yaml`
- `tests/Internal/Application/ApplicationSeederBuildIntegrationTest.php`
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`

## Decisions and Assumptions

- Stable install and the candidate update use the same fresh Application checkout but separate committed Framework source states; the temporary annotated candidate tag is local to the Consumer clone.
- Provider-missing HTTP may expose only the framework's generic safe `500` JSON. The exact Provider message is asserted through the application bootstrap chain and an in-container Worker-lane preflight without exposing it in output.
- The amended Task Packet explicitly permits the three affected public guides and the guide-code regression test for this Stable Header contract correction; no Production PHP change was required.
- The amended Task Packet also permits the three Manual Merge Matrix runtime bootstrap files; this Consumer copies and verifies only those files and does not broaden the merge to Caddyfile, Compose, or unrelated Application Source.
- Candidate `99f723d` is superseded. The three existing Consumers use the same bounded Storage Key preparation contract: restrictive `.env`, strict 32-byte base64 generation/decoded-length check, exactly one non-empty assignment, mode 600, and key material unset before Docker/Composer commands. No Production Source or other Consumer was changed.
- Candidate `413d096` is also superseded. Specification 99 already requires every protected Application to register `StorageKeyProvider`; correcting the Community Board example and its setup/docs is implementation conformance, not a new Framework/Public API decision. A fresh setup may generate a Local/Test key without printing it, while an existing `.env` remains Application-owned and must not be silently rewritten.
- The first Provider-corrected clean-install rerun still returned the safe resolution failure. Fixed-stage probes proved the key was present with decoded length 32 and that the compiled Provider/operation-data/seeder composition succeeds when root dependencies are available. The actual fresh Community Board install then failed while constructing `ExecutionScopeProvider`: its stale path-repository lock still recorded Framework `dev-main 462cfdb` requirements and omitted current `open-telemetry/api` (and the current `ext-sodium` requirement). The lock-only metadata synchronization resolved that dependency gap without changing `composer.json`.
- The synchronized lock points `blackops/framework` at candidate source `413d0964cc132d685b228d5b8d697ac6cc4543e6`, retains `ext-sodium` and `open-telemetry/api ^1.10`, and includes the resolved OpenTelemetry API/Context and PHP 8.2 polyfill entries. The exact clean-install Consumer then passed Composer, generated artifacts, migration application (`11 Framework + 5 Community Board = 16`), seed, HTTP, Worker, redaction, and cleanup assertions.
- Fresh Community Board setup now creates `.env` with exclusive `fopen(..., 'xb')`, verifies every write/flush/close step, and validates cleanup when a later setup step fails. The Consumer exercises that failure lane with an obstructing `var/build` file before generating one runtime key for all subsequent redaction checks; existing `.env` byte/metadata preservation remains covered separately.
- Current Framework proxy artifacts are documented as the atomic `proxy-profiles/<build-id>-<content-hash>/` common unit and `framework-proxies/<build-id>-<input-hash>/` Framework unit, with manifest/hash/Build ID validation and runtime no-scan/no-fallback behavior. The current spec, Mago includes, and Deptrac Library collectors no longer reference the removed Ray.Aop package; historical Decisions/Reports remain unchanged.

## Commands and Results

- PASS: `bash -n tests/Consumer/framework-update-runtime.sh tests/Consumer/auth-generator-fresh.sh tests/Consumer/frankenphp-worker-mode.sh tests/Consumer/scheduled-operation.sh tests/Consumer/version-baseline.sh`.
- PASS: final `bash -n tests/Consumer/*.sh` after the Community Board failure-lane and setup-hardening changes.
- PASS: `bash tests/Consumer/version-baseline.sh` (`stable=1.1.0 candidate=1.2.0`); the guard captures exact line order for `umask`, `.env` copy, 32-byte Storage Key generation/non-empty and decoded-length checks, `.env` write, assignment/empty-assignment counts, mode 600, key-variable unset, and the first Docker/Composer command for all three corrected Consumers, rejecting missing or duplicate contract steps.
- PASS: `git diff --check`.
- PASS: `bash tests/Consumer/auth-generator-fresh.sh` after the correction; the full Auth generator/register/login/logout/rotation/revocation journey passed and cleanup restored the repository/Docker baseline.
- PASS: `bash tests/Consumer/frankenphp-worker-mode.sh` after the correction; Worker bootstrap, request isolation, database reconnect, restart/memory bounds, Classic fallback, correlated failure boundary, and cleanup passed.
- PASS: `bash tests/Consumer/scheduled-operation.sh` after the correction; scheduled CLI, recovery, concurrency, and cleanup passed.
- PASS: exact escalated pre-commit `bash tests/Consumer/framework-update-runtime.sh` (exit 0; Stable `e3df5576c7216cfe8bd9e10e12ee6795f7674088`, candidate `61142d254861ffe13985679c338f592a46151af5`, migrations 11, Provider-present Worker-mode HTTP/Worker, Provider-missing Classic HTTP/Worker safe-negative, cleanup/source invariant).
- Correction: the Orchestrator reproduced an immediate post-install assertion failure because Composer's `show --format=json` shape did not provide the expected `"version"` field. Both checks now read the resolved `blackops/framework` version from `composer.lock`, matching the existing generator Consumer contract. Negative redaction also includes captured HTTP headers/body.
- Correction: two Docker runs stopped at the Stable post-migration count assertion. The Consumer now uses a bounded `assert_migration_status` helper for Stable, candidate-before-migrate, and candidate-after-migrate stages; only applied/pending/version status is printed on mismatch, with no environment or secret output. Docker was not rerun for this focused correction.
- Correction: the Orchestrator reproduced the Stable status mismatch as the known current-schema metadata defect. The Consumer now never reruns Stable migration or requires Stable CLI counts; it directly verifies the two exact Stable metadata rows, six baseline tables, and baseline constraints, then lets Candidate recognize `applied: 2`/`pending: 9`. CHANGELOG, UPGRADE, Specification 103, version guard, and STATE now document the safe order and prohibit metadata edits. Docker was not rerun for this focused correction.
- Correction: a fourth exact Docker run passed Stable catalog checks and candidate Composer `1.2.0`, then stopped at the config merge's opaque `exit(1/2)`. The merge now uses bounded stderr labels, verifies the unique HTTP/frontend markers, inserts services before the uniquely matched final root closure, and checks read/write results without overwriting unrelated config. Docker was not rerun for this focused correction.
- Correction: a fifth exact Docker run reached the hardened merge but exposed a PHP parse error from a single-quoted `preg_replace` pattern inside the shell single-quoted `php -r` program. The final-root regex now uses a correctly escaped PHP double-quoted pattern with regex delimiters. Docker was not rerun for this focused correction.
- Correction: a sixth exact Docker run exposed the same shell quoting boundary in the marker strings: literal PHP single quotes were stripped before execution. All config-marker and provider-service-removal `php -r` programs now construct those literals with `chr(39)`, and the version guard checks that boundary. Docker was not rerun for this focused correction.
- Correction: a seventh exact Docker run passed Stable metadata, candidate update/migrations/build, and Worker checks but received `422` from Header-free `/welcome`. The actual annotated Stable `1.1.0` `WelcomeValue` requires `X-Sample-Token` as a sensitive Value input even though Stable is authorization-anonymous and has no `#[Authorize]`. The Consumer now sends the required header on both positive and provider-missing HTTP lanes; UPGRADE, Installation, Runtime Bootstrap, MVP Sample, and website regression expectations distinguish Stable Value binding from Preview Sample Authentication/Authorization. Docker was not rerun for this focused correction.
- PASS: `pnpm --dir docs/website test` (79 tests).
- PASS: `docker compose --project-directory examples/community-board --project-name community-board-lock-update -f examples/community-board/compose.yaml run --rm --no-deps app composer update --lock --no-interaction` (path-repository Framework metadata synchronized to `413d096`; no unrelated package update).
- PASS: `docker compose --project-directory examples/community-board --project-name community-board-lock-validate -f examples/community-board/compose.yaml run --rm --no-deps app composer validate --strict` (`./composer.json is valid`).
- PASS: `docker compose --project-directory examples/community-board --project-name community-board-ray-lock-update -f examples/community-board/compose.yaml run --rm --no-deps app composer update --no-install --no-scripts --minimal-changes` (exactly 0 installs, 0 updates, 1 removal: `ray/aop 2.20.0`).
- PASS: `docker compose --project-directory examples/community-board --project-name community-board-ray-lock-validate -f examples/community-board/compose.yaml run --rm --no-deps app composer validate --strict` after Ray removal (`./composer.json is valid`).
- PASS: `docker compose run --rm app mago format --check src tests examples` (all files formatted after the setup hardening).
- PASS: `docker compose run --rm app mago analyze` (71 existing issues: 46 warnings, 25 help; no error exit).
- PASS: focused `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationSeederBuildIntegrationTest.php` (`3 tests`, `22 assertions`).
- BASELINE: `docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` still exits 255 at `0/857` on the known PHP 8.5 parser error in `NikicFileReferenceVisitor.php:106`; the obsolete Ray namespace collector is removed.
- Correction: an eighth exact Docker run passed Stable/candidate migrations, build, Worker, and Provider-present Worker-mode HTTP `200`, then showed Provider-missing Worker-mode HTTP exits during boot before accepting requests. The Consumer now mounts the Framework clone into `http-classic`, uses its `${http_port}+1` port for the Provider-missing safe `500` HTTP lane, stops the correct Classic profile, retains the Worker CLI/process non-zero assertion, and redacts Classic HTTP logs. Task and Specification 103 now record this Runtime contract. Docker was not rerun for this focused correction.
- Correction: a ninth exact Docker run reached the Provider-missing Classic lane, where the first readiness request reset the connection and the script exited without identifying the silent assertion. The Consumer now reports only fixed non-sensitive stage labels for HTTP/Worker preflight, Classic readiness/status/content/body, Worker CLI/process, redaction, and source invariants via `fail_stage`; it never prints headers, bodies, logs, environment, or keys. Docker was not rerun for this focused correction.
- Correction: a tenth exact Docker run showed the bounded Classic readiness loop accepted a transient non-`000` response before the expected safe `500`. The loop now retries for all 30 attempts until exactly `500`, then retains the fixed `provider-missing-classic-http-readiness` label on exhaustion. Docker was not rerun for this focused correction.
- Correction: an eleventh exact Docker run passed migrations, build, Worker, and Provider-present evidence but failed at Provider-missing HTTP because the Stable app still used its `1.1.0` bootstrap/runtime entrypoints. The Consumer now copies only candidate `bootstrap/app.php`, `public/index.php`, and `public/worker.php`, verifies byte equality at initial/positive/negative checkpoints, and excludes exactly those paths from generic source hashes. Docker was not rerun for this focused correction.
- Correction: Documentation review synchronized UPGRADE's executable order with the passing Consumer: fresh Stable pre-status 0/2 and one-time migrate, read-only metadata/baseline DDL checks, no Stable post-migrate status, then Framework-only Candidate update/strict validation, status 2/9, dry-run/migrate, final 11/0. The matrix now names only the three runtime bootstrap files and explicitly leaves blackops/Caddyfile/Compose unchanged; the Runtime Consumer reference and lane boundaries are executable and guarded.
- PASS at `413d096`: `bash -n tests/Consumer/*.sh`; root and Quickstart Composer strict validation; Framework package export; clean-source `mago format --check src tests examples`; `mago analyze` with the existing 71 advisories; Full PHPUnit clean rerun (`2315 tests`, `9434 assertions`) after one isolated known-flaky heartbeat failure passed alone; Auth Generator Fresh Consumer.
- BASELINE at `413d096`: broad `mago lint` reports the existing 186 findings including 14 errors; Deptrac exits 255 at 0/857 because its vendor parser does not parse the PHP 8.5 construct in `NikicFileReferenceVisitor.php:106`. Framework PHP source was unchanged by either candidate correction.
- BLOCKED at `413d096`: `bash tests/Consumer/community-board-clean-install.sh` stopped at the pre-migration seed assertion. A secret-safe diagnostic rerun confirmed status 1 and exact normalized application message `Database seeding runtime could not be resolved.`; database/demo passwords and key material were not printed. Static inspection confirmed no `BLACKOPS_STORAGE_KEY` placeholder, no Community Board Provider class, and no `StorageKeyProvider` binding.
- CORRECTION DIAGNOSTIC: after the Provider/setup implementation, exact clean install still stopped at the same normalized resolution message. An isolated compiled-container probe with current root dependencies passed configuration/build/artifact/logging/database/transaction/storage-provider/operation-data/seeder-runtime. The actual fresh application probe confirmed key present/length 32 and artifact load, then failed at `ExecutionScopeProvider`; lock inspection showed current root requires `open-telemetry/api ^1.10` and `ext-sodium`, while Community Board's locked Framework metadata includes neither and Composer installs no OpenTelemetry API package.
- PASS: after the Provider/setup, lock, exclusive-create, and failure-lane corrections, `bash tests/Consumer/community-board-clean-install.sh` completed the exact fresh install journey with fail-closed incomplete-`.env` cleanup, one fresh runtime key, `11 Framework + 5 Community Board = 16` migrations, generated-contract/build checks, double seeding, login/feed/detail HTTP checks, Worker startup, secret-safe output/log/database/generated-surface redaction, existing `.env` byte/metadata preservation, and cleanup.
- PASS: the same exact Consumer also verified no `ray/aop` lock entry, no `vendor/ray/aop` installation, and no Ray namespace in the installed vendor tree.
- PASS: current-surface scans found no Ray.Aop references in `src`, root Composer metadata/lock, Community Board lock, Mago, Deptrac, `docs/internal/bootstrap.md`, or `develop/spec/09-runtime-and-di.md`; the old `build/aop` sentinel is gone from `src`/`tests`, while Framework-owned internal `aop` generation remains implementation detail under `framework-proxies`.
- PASS: Documentation Reviewer final correction review returned P1=0, P2=0, P3=0 and permitted the bounded correction commit. Long-running Consumer, build, and Browser commands were not rerun by the read-only reviewer; Orchestrator and worker execution evidence above remains the runtime evidence.

## Acceptance Criteria

- [x] Runtime Consumer and CI wiring implement the P22-002 common migration/DDL and Provider-present/missing lane contract.
- [x] Runtime Consumer executes successfully with cleanup/source/Docker invariants at the pre-commit baseline.
- [ ] Final Fixed Candidate SHA is committed and recorded after the Community Board correction.
- [ ] Full local and Remote GitHub Actions gates pass at the same fixed SHA.
- [x] Report, Specification 103, TODO, and STATE are synchronized for the current Gate Asset; fixed-SHA, full-gate, and Remote CI criteria remain pending below.
- [x] Community Board registers an Application-owned `StorageKeyProvider`, fresh setup creates a strict 32-byte Local key with mode 600 and no exposure, and existing `.env` remains unchanged.
- [x] Community Board lock metadata retains current Candidate `open-telemetry/api` / `ext-sodium` requirements and the fresh install resolves Seed/HTTP/Worker dependencies.
- [x] Community Board clean install applies 11 Framework + 5 Application migrations and the README/Consumer assert the same total of 16.
- [x] Community Board lock and installed vendor tree contain no orphan `ray/aop` package, and the Consumer asserts that absence.
- [x] Current spec, Mago, Deptrac, and internal Bootstrap documentation describe the Framework-owned proxy artifact contract without obsolete Ray.Aop dependency or path references.

## Remaining Issues

1. Commit the independently reviewed bounded correction, fix the new candidate SHA, and restart the complete local gate. Evidence collected at superseded candidates `99f723d` and `413d096` is diagnostic only.
2. Remote CI evidence remains pending a separately authorized branch push.

## Suggested Next Action

Commit the independently reviewed bounded correction as a new candidate, restart the complete P22-003 local gate from that SHA, then request separate authorization for the branch push required to collect Remote GitHub Actions evidence.
