# P22-003 Stable 1.2 Release Candidate Gate Report

Status: In Progress — Runtime Consumer and CI wiring pass the exact pre-commit baseline; Gate Asset commit, fixed-SHA gate, and Orchestrator acceptance remain pending.

## Summary

Implemented the bounded Stable-to-candidate Runtime Consumer and its GitHub Actions job. The Consumer starts from the actual annotated Stable `1.1.0` quickstart in a fresh disposable checkout, installs and migrates Stable's two Framework migrations, creates a local annotated `1.2.0` tag at the committed candidate source, updates only `blackops/framework`, applies the documented Provider/config/environment merge plus the exact three runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`), proves the additional nine migrations and DDL guards, and defines Provider-present HTTP/Worker and Provider-missing HTTP/Worker safe-negative lanes. No production source, external tag, branch, release, Packagist, or deployment state was changed.

## Candidate Establishment and Fixed SHA Evidence

- Baseline commit: `61142d254861ffe13985679c338f592a46151af5`.
- Candidate source is cloned from the repository's committed `HEAD`; uncommitted Task/STATE/Report files are not mounted as candidate source.
- The Consumer verifies annotated Stable tag type and peeled commit, archives only `1.1.0:examples/quickstart`, then creates an annotated local `1.2.0` tag at the committed candidate SHA.
- Final Fixed Candidate remains pending the Gate Asset review Commit. No external Tag/Push was performed.

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
- `tests/Consumer/version-baseline.sh`
- `.github/workflows/ci.yml`
- `CHANGELOG.md`
- `UPGRADE.md`
- `docs/guide/installation.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/website/tests/guide-code.test.mjs`
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

## Commands and Results

- PASS: `bash -n tests/Consumer/framework-update-runtime.sh tests/Consumer/version-baseline.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` (`stable=1.1.0 candidate=1.2.0`).
- PASS: `git diff --check`.
- PASS: exact escalated pre-commit `bash tests/Consumer/framework-update-runtime.sh` (exit 0; Stable `e3df5576c7216cfe8bd9e10e12ee6795f7674088`, candidate `61142d254861ffe13985679c338f592a46151af5`, migrations 11, Provider-present Worker-mode HTTP/Worker, Provider-missing Classic HTTP/Worker safe-negative, cleanup/source invariant).
- Correction: the Orchestrator reproduced an immediate post-install assertion failure because Composer's `show --format=json` shape did not provide the expected `"version"` field. Both checks now read the resolved `blackops/framework` version from `composer.lock`, matching the existing generator Consumer contract. Negative redaction also includes captured HTTP headers/body.
- Correction: two Docker runs stopped at the Stable post-migration count assertion. The Consumer now uses a bounded `assert_migration_status` helper for Stable, candidate-before-migrate, and candidate-after-migrate stages; only applied/pending/version status is printed on mismatch, with no environment or secret output. Docker was not rerun for this focused correction.
- Correction: the Orchestrator reproduced the Stable status mismatch as the known current-schema metadata defect. The Consumer now never reruns Stable migration or requires Stable CLI counts; it directly verifies the two exact Stable metadata rows, six baseline tables, and baseline constraints, then lets Candidate recognize `applied: 2`/`pending: 9`. CHANGELOG, UPGRADE, Specification 103, version guard, and STATE now document the safe order and prohibit metadata edits. Docker was not rerun for this focused correction.
- Correction: a fourth exact Docker run passed Stable catalog checks and candidate Composer `1.2.0`, then stopped at the config merge's opaque `exit(1/2)`. The merge now uses bounded stderr labels, verifies the unique HTTP/frontend markers, inserts services before the uniquely matched final root closure, and checks read/write results without overwriting unrelated config. Docker was not rerun for this focused correction.
- Correction: a fifth exact Docker run reached the hardened merge but exposed a PHP parse error from a single-quoted `preg_replace` pattern inside the shell single-quoted `php -r` program. The final-root regex now uses a correctly escaped PHP double-quoted pattern with regex delimiters. Docker was not rerun for this focused correction.
- Correction: a sixth exact Docker run exposed the same shell quoting boundary in the marker strings: literal PHP single quotes were stripped before execution. All config-marker and provider-service-removal `php -r` programs now construct those literals with `chr(39)`, and the version guard checks that boundary. Docker was not rerun for this focused correction.
- Correction: a seventh exact Docker run passed Stable metadata, candidate update/migrations/build, and Worker checks but received `422` from Header-free `/welcome`. The actual annotated Stable `1.1.0` `WelcomeValue` requires `X-Sample-Token` as a sensitive Value input even though Stable is authorization-anonymous and has no `#[Authorize]`. The Consumer now sends the required header on both positive and provider-missing HTTP lanes; UPGRADE, Installation, Runtime Bootstrap, MVP Sample, and website regression expectations distinguish Stable Value binding from Preview Sample Authentication/Authorization. Docker was not rerun for this focused correction.
- PASS: `pnpm --dir docs/website test` (78 tests).
- Correction: an eighth exact Docker run passed Stable/candidate migrations, build, Worker, and Provider-present Worker-mode HTTP `200`, then showed Provider-missing Worker-mode HTTP exits during boot before accepting requests. The Consumer now mounts the Framework clone into `http-classic`, uses its `${http_port}+1` port for the Provider-missing safe `500` HTTP lane, stops the correct Classic profile, retains the Worker CLI/process non-zero assertion, and redacts Classic HTTP logs. Task and Specification 103 now record this Runtime contract. Docker was not rerun for this focused correction.
- Correction: a ninth exact Docker run reached the Provider-missing Classic lane, where the first readiness request reset the connection and the script exited without identifying the silent assertion. The Consumer now reports only fixed non-sensitive stage labels for HTTP/Worker preflight, Classic readiness/status/content/body, Worker CLI/process, redaction, and source invariants via `fail_stage`; it never prints headers, bodies, logs, environment, or keys. Docker was not rerun for this focused correction.
- Correction: a tenth exact Docker run showed the bounded Classic readiness loop accepted a transient non-`000` response before the expected safe `500`. The loop now retries for all 30 attempts until exactly `500`, then retains the fixed `provider-missing-classic-http-readiness` label on exhaustion. Docker was not rerun for this focused correction.
- Correction: an eleventh exact Docker run passed migrations, build, Worker, and Provider-present evidence but failed at Provider-missing HTTP because the Stable app still used its `1.1.0` bootstrap/runtime entrypoints. The Consumer now copies only candidate `bootstrap/app.php`, `public/index.php`, and `public/worker.php`, verifies byte equality at initial/positive/negative checkpoints, and excludes exactly those paths from generic source hashes. Docker was not rerun for this focused correction.
- Correction: Documentation review synchronized UPGRADE's executable order with the passing Consumer: fresh Stable pre-status 0/2 and one-time migrate, read-only metadata/baseline DDL checks, no Stable post-migrate status, then Framework-only Candidate update/strict validation, status 2/9, dry-run/migrate, final 11/0. The matrix now names only the three runtime bootstrap files and explicitly leaves blackops/Caddyfile/Compose unchanged; the Runtime Consumer reference and lane boundaries are executable and guarded.
- NOT RUN: full fixed-SHA PHP/Consumer/Website/Package gate and Remote GitHub Actions evidence; the Runtime Consumer pre-commit baseline passed, and the Gate Asset must now be committed before the full gate restarts.

## Acceptance Criteria

- [x] Runtime Consumer and CI wiring implement the P22-002 common migration/DDL and Provider-present/missing lane contract.
- [x] Runtime Consumer executes successfully with cleanup/source/Docker invariants at the pre-commit baseline.
- [ ] Final Fixed Candidate SHA is committed and recorded.
- [ ] Full local and Remote GitHub Actions gates pass at the same fixed SHA.
- [x] Report, Specification 103, TODO, and STATE are synchronized for the current Gate Asset; fixed-SHA, full-gate, and Remote CI criteria remain pending below.

## Remaining Issues

1. Gate Asset Commit and Orchestrator review are pending; the passing baseline SHA is not yet a committed fixed candidate.
2. Full fixed-SHA PHP, Consumer, Website, Package, Publication dry-run, and Remote CI evidence remain pending.

## Suggested Next Action

Review the passing pre-commit evidence, Commit the accepted Gate Asset to establish the fixed SHA, then restart the complete P22-003 gate from that committed SHA.
