# P22-003 Stable 1.2 Release Candidate Gate Report

Status: In Progress — Final Fixed Candidate `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5` completed every local Consumer, Frontend, Website, package, publication dry-run, and repository guard. Strict Mago lint, Deptrac, and same-SHA Remote CI remain unsatisfied, so Orchestrator acceptance is withheld.

## Summary

Implemented the bounded Stable-to-candidate Runtime Consumer and its GitHub Actions job. The Consumer starts from the actual annotated Stable `1.1.0` quickstart in a fresh disposable checkout, installs and migrates Stable's two Framework migrations, creates a local annotated `1.2.0` tag at the committed candidate source, updates only `blackops/framework`, applies the documented Provider/config/environment merge plus the exact three runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`), proves the additional nine migrations and DDL guards, and defines Provider-present HTTP/Worker and Provider-missing HTTP/Worker safe-negative lanes. The existing Auth Generator Fresh, FrankenPHP Worker, and Scheduled Operation Consumers now prepare a fail-closed 32-byte Storage Key before Docker/Composer execution without logging key material. The restarted gate then found that Community Board neither registers the mandatory Application-owned `StorageKeyProvider` nor prepares a Local key, so its runtime could not resolve before the intended pre-migration database failure. The bounded correction now registers the Local/Test provider, generates a fail-closed fresh key, preserves existing `.env` files, synchronizes the path-repository lock metadata, and proves the full 11 Framework + 5 Community Board (16 total) clean-install journey without exposing key material. No external tag, branch, release, Packagist, or deployment state was changed.

The complete local gate was restarted from committed candidate `08ad61f`. All 23 top-level non-interactive Consumers, Frontend generation/runtime checks, Website tests/check/build, Framework export, Skeleton split/publication dry run, create-project lanes, Composer strict validation, Mago format/analyze, Full PHPUnit, and repository guards passed. Broad Mago lint reproduced the existing 186-issue baseline with 14 errors, and Deptrac remained blocked at 0/857 by its PHP 8.5 vendor parser. The candidate is 50 commits ahead of remote `origin/main` and is not in remote history, so same-SHA GitHub Actions evidence cannot exist before a separately authorized branch push.

## Candidate Establishment and Fixed SHA Evidence

- Baseline commit: `61142d254861ffe13985679c338f592a46151af5`.
- Superseded candidate: `99f723dfc9bcf1e859689c81878839ee37d2ba91` (`test: add stable 1.2 runtime upgrade gate`).
- Superseded candidate: `413d0964cc132d685b228d5b8d697ac6cc4543e6` (`test: prepare storage keys in quickstart consumers`).
- Superseded candidate: `6e009a433ce1c687f2f117d69afb14079668c206` (`fix: harden community board release setup`).
- Superseded candidate: `e4be46f7e883f5247ed94f86c7854e3163a6c7dc` (`test: correct community board digest actor assertion`).
- Final Fixed Candidate: `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5` (`test: prepare scheduled operation runtime directory`).
- Candidate source is cloned from the repository's committed `HEAD`; uncommitted Task/STATE/Report files are not mounted as candidate source.
- The Consumer verifies annotated Stable tag type and peeled commit, archives only `1.1.0:examples/quickstart`, then creates an annotated local `1.2.0` tag at the committed candidate SHA.
- Documentation Reviewer returned P1=0/P2=0/P3=0 and permitted the bounded Gate Asset commit. No external Tag/Push was performed.
- The fixed-SHA full gate found `auth-generator-fresh.sh` copies `.env.example` without populating the now-required `BLACKOPS_STORAGE_KEY`; the same stale setup is present in `frankenphp-worker-mode.sh` and `scheduled-operation.sh`. Per the Task reset rule, `99f723d` is not silently retained as the Final Fixed Candidate.
- Documentation Reviewer returned P1=0/P2=0/P3=0 for the seven-file correction and permitted commit `413d0964cc132d685b228d5b8d697ac6cc4543e6` as the replacement Final Fixed Candidate.
- The restarted gate at `413d096` passed the initial syntax, Composer, package-export, clean-source format/analyze, full PHPUnit rerun, and Auth Generator Fresh stages. Broad Mago lint and Deptrac reproduced the recorded baseline blockers. Community Board clean install stopped at its pre-migration seed-message assertion because the actual safe output was `Database seeding runtime could not be resolved.`, proving the Reference Application lacked the mandatory Provider composition rather than reaching the intended database-before-migration failure. The Task reset rule supersedes `413d096`.
- Documentation Reviewer returned P1=0/P2=0/P3=0 for the 17-file Community Board/lock/current-proxy correction and permitted commit `6e009a433ce1c687f2f117d69afb14079668c206` as the new Final Fixed Candidate. The complete gate restarts from this exact committed source; earlier candidate evidence remains diagnostic only.
- The restarted gate at `6e009a4` passed Community Board Clean Install, Browser, Foundation, Identity, Post/Comment, and Product journeys after reproducing that the browser lane requires the same Composer/frontend preparation used by CI. Digest then failed at line 304: the query reads only the protected journal's denormalized `origin_actor_id`, but the stale assertion still expects the execution worker ID. The preceding Alice origin assertion passes, the event sequence passes, and the worker ID belongs only to the protected encoded actor context. This is a Consumer contract mismatch introduced when direct protected-payload JSON inspection was removed, so `6e009a4` is superseded under the reset rule.
- The bounded Digest correction now asserts exact denormalized `origin_actor_id` continuity for all eight journal sequences. It does not query the protected encoded execution actor, which is unavailable to the direct SQL contract. The exact corrected Digest journey passes; `6e009a4` remains superseded until the correction is independently reviewed and committed.
- Documentation Reviewer returned P1=0/P2=0/P3=0 and permitted the four-file Digest correction commit. The review confirmed that the exact sequence/origin equality is stronger than the removed presence-only checks, matches Specification 99 restricted-clear metadata, does not query protected actor context, and keeps Task/Report/STATE truthful.
- Orchestrator committed the reviewed four-file Digest correction as `e4be46f7e883f5247ed94f86c7854e3163a6c7dc`. The restarted full gate later superseded it when the Scheduled Operation Consumer exposed its missing runtime directory; prior candidate evidence remains diagnostic only.

## Runtime Upgrade Consumer Evidence

`tests/Consumer/framework-update-runtime.sh` uses a unique Compose project and random loopback HTTP port. It removes `.env` before Compose shutdown during all EXIT/INT/TERM cleanup paths, uses `umask 077`, never prints generated key material, and checks root Git status and project containers/volumes/networks after cleanup.

The positive lane runs Stable `database:migrate` once, then uses read-only catalog checks for exactly two Stable Framework rows in current-schema `blackops.schema_migrations`, six baseline tables, and baseline constraints. It updates only `blackops/framework` to candidate `1.2.0`, whose status must recognize `applied: 2`/`pending: 9` before migration and finish at `applied: 11`/`pending: 0`, with latest migration `Version20260808100000`, protected-storage columns/rotation tables, and seven BOPD DDL constraints. It then builds candidate artifacts, sends the required `X-Sample-Token: local-example` request header, proves HTTP `200`/`application/json`/exact `{"message":"Welcome to BlackOps"}`, a running Worker service, and a successful one-iteration Worker command.

The candidate config merge is bounded to the unique Stable `http_manifest` marker and the final root `];` closure. It preserves an existing frontend marker, inserts only the candidate `frontend_manifest` and `services` section, checks each replacement count, verifies `file_get_contents`/`file_put_contents` results, and emits only labelled non-sensitive failure diagnostics.

The Consumer applies only the three authorized candidate runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`) from the candidate quickstart. Generic source snapshots exclude exactly those intentional paths, while byte equality is checked before build and after both positive and negative lanes; Caddyfile, Compose, and other Application-owned files remain untouched.

The negative lane removes only the Provider binding after the common migrated database and candidate build. It checks the exact internal safe Provider error without printing it, then exercises `http-classic`／`classic-mode` for generic HTTP `500` JSON and actual Worker CLI startup for non-running/nonzero safe failure. Provider-present remains Worker-mode HTTP／Worker. HTTP/Worker output and Classic HTTP logs are bounded and searched without printing for key, payload, SQL, stack, tenant, or actor leakage.

The exact fixed-candidate run passed with: `Framework update runtime consumer passed: stable=e3df5576c7216cfe8bd9e10e12ee6795f7674088 candidate=08ad61f8236b3a240c9c9547fbde3b9d765fc6d5 migrations=11 provider-present=http-worker provider-missing=classic-http-worker-safe-negative.` Cleanup completed and the repository source-state invariant held with exit 0.

## Local PHP, Consumer, Frontend, and Website Full Gate Evidence

The complete local gate used committed source `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5`; uncommitted Task／Report／STATE files were not included in the candidate clone or package export.

- PASS: all Consumer shell syntax, root and Quickstart Composer strict validation, Framework package export, and Mago format for `src tests examples`.
- PASS: Mago analyze completed with 71 advisory findings (`46 warnings`, `25 help`) and exit 0.
- PASS: Full PHPUnit completed `2315 tests / 9435 assertions`; the existing dependency deprecation, two PHPUnit deprecations, and thirteen notices did not fail the suite.
- UNSATISFIED: broad Mago lint exited 1 with the existing `186 issues`: `14 errors`, `105 warnings`, `45 notes`, `22 help`, including 10 auto-fix suggestions.
- UNSATISFIED: Deptrac exited 255 at `0/857`; the vendor `NikicFileReferenceVisitor.php:106` parser cannot parse the PHP 8.5 construct. Removing the obsolete Ray namespace collector did not change this vendor blocker.
- PASS: all 23 top-level non-interactive Consumer scripts completed, including all six prepared Community Board journeys, Stable-to-candidate update, Framework proxy removal, Quickstart, Scheduled Operation, both OpenTelemetry lanes including Grafana LGTM, protected-storage rotation, Skeleton, and version baseline.
- PASS: Community Board installed 69 packages without Ray.Aop, applied 11 Framework plus 5 Application migrations, passed `55 tests / 582 assertions`, passed the 46-test frontend suite, and completed Browser, Foundation, Identity, Post/Comment, Product, Digest, HTTP, Worker, redaction, and cleanup evidence.
- PASS: Frontend installed from lock, compiled artifacts, generated 7 files, passed freshness/type/runtime/module-shape checks, found no forbidden runtime values, and cleaned generated state.
- PASS: Website completed 79 tests, checked 41 pages with 0 errors/warnings/hints, built 42 static pages, checked navigation/accessibility/version/search on 41 pages, and passed the public artifact boundary. The known minified chunk-size advisory did not fail the build.
- PASS: CI-equivalent tracked-required-file, generated-state, public-content, credential, management-ID, Quickstart lock/vendor, version, and diff guards. After generated cleanup, only the allowed Task／Report／STATE management changes remained.

## Package Export, Split, and Create-project Evidence

- Framework package export passed from committed source.
- Skeleton `1.2.0` publication dry run from `08ad61f` was deterministic and produced split commit `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce` on repeated split.
- Skeleton publication workflow regression passed new annotated publication, idempotent recovery, divergence rejection, and legacy-only recovery boundaries.
- Normal and `--no-scripts` local create-project lanes both resolved Skeleton／Framework `1.2.0`, preserved the setup boundary, and passed package/source allowlists.

## Release Surface and Known Limitations Review

- CHANGELOG Unreleased describes the unpublished Experimental `1.2.0` candidate, nine post-Stable migrations, current-schema metadata fix, protected-storage irreversibility, Application-owned infrastructure responsibilities, and no Ray package removal migration.
- UPGRADE keeps public Stable `1.1.0` separate from candidate source, requires Backup／Key preparation, proves Stable metadata with read-only catalog checks, orders `2 applied / 9 pending` before candidate migration and `11 / 0` afterward, and limits the verified runtime merge to `bootstrap/app.php`, `public/index.php`, and `public/worker.php`.
- Public Installation／Runtime／Quickstart guidance preserves the Stable anonymous-authorization versus required `X-Sample-Token` Value binding distinction. Website regression tests enforce this executable contract.
- The release surface matches candidate implementation, but publication remains prohibited until the two strict local quality criteria and same-SHA Remote CI are satisfied.

## GitHub Actions Evidence

`.github/workflows/ci.yml` adds `framework-update-runtime` with `fetch-depth: 0`, container UID/GID setup, a 45-minute timeout, and the Runtime Consumer command. Remote GitHub Actions evidence is not available until a separately authorized branch push; local wiring/static checks are the only current evidence.

`origin/main` was refreshed read-only and remains `267ffed9e5270618318649ec8769756c2d791f06`. Candidate `08ad61f` is 50 commits ahead, 0 behind, and is not an ancestor of remote `main`. No Remote CI run is claimed.

## Publication Preflight State

Checked At: `2026-08-13T01:08:50+09:00`

| Surface | Read-only state |
| --- | --- |
| Framework remote `main` | `267ffed9e5270618318649ec8769756c2d791f06`; candidate absent |
| Framework `1.2.0` tag | Direct／peeled ref absent |
| Skeleton remote `main` | `293f880940636669f28ded756a888a8d6ba65f1b` |
| Skeleton `1.2.0` tag | Direct／peeled ref absent |
| GitHub Release `1.2.0` | `release not found` |
| Packagist `blackops/framework` | Stable `1.1.0` and `1.0.0` only; `1.2.0` absent |
| Packagist `blackops/skeleton` | Stable `1.1.0` and `1.0.0` only; `1.2.0` absent |

All external checks were read-only. No branch, tag, release, package, Skeleton repository, or documentation deployment was changed.

## P22-004 Publication Checklist and Recovery

### Preconditions

1. Resolve the existing Mago lint errors and Deptrac PHP 8.5 parser blocker. Any Production／Test／Workflow／Skeleton／Release Metadata change creates a new candidate and requires the complete P22-003 gate to restart.
2. Obtain separate authorization to push the branch, then require candidate source `08ad61f` or its explicitly superseding fixed SHA to exist in remote `main` history and pass same-SHA GitHub Actions.
3. Accept P22-003 only after strict local quality, Remote CI, final Documentation Reviewer, clean working tree, and fixed candidate evidence all agree.
4. Reconfirm that Framework／Skeleton `1.2.0` direct and peeled refs and GitHub Release are absent, and confirm only the `SKELETON_DEPLOY_KEY` secret name without reading its value.
5. Reconfirm the deterministic Skeleton split for the accepted candidate; for the current candidate it is `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.

### Publication Sequence

1. Create an annotated Framework `1.2.0` tag at the accepted fixed candidate; verify object type, message, and peeled commit locally before pushing it.
2. Push only the immutable Framework tag and monitor the tag-triggered Skeleton publication workflow through all quality and cleanup steps.
3. Verify Skeleton `main` fast-forwarded to the fixed split and its annotated `1.2.0` tag peels to the same split.
4. Wait for Packagist Framework／Skeleton `1.2.0`; verify exact source refs, Skeleton `^1.2` Framework constraint, and package type.
5. Create the GitHub Release from the accepted CHANGELOG／UPGRADE content and verify tag, name, draft/prerelease state, and Experimental warnings.
6. Run remote normal and `--no-scripts` create-project, Project Root CLI, documented Quickstart, HTTP／Worker, migration, and redaction smoke using published packages only.
7. Close P22-004 Report／Specification／TODO／STATE. Do not deploy the documentation website unless that deployment is separately authorized and in scope.

### Success Conditions

- Framework `1.2.0` is an immutable annotated tag peeling to the accepted fixed candidate.
- Skeleton `1.2.0` is an immutable annotated tag and its peeled commit and `main` equal the fixed deterministic split.
- Both Packagist packages expose `1.2.0` with exact accepted source refs; Skeleton requires Framework `^1.2`.
- GitHub Release contains the accepted release note and migration／rollback warnings.
- Remote normal／`--no-scripts` install and documented Quickstart pass without local path repositories or unpublished source.
- Existing `1.0.0`／`1.1.0` refs, credential values, and documentation deployment state remain unchanged.

### Recovery Conditions

- Before Framework tag publication, any CI or source mismatch stops publication and returns to a new fixed candidate/full P22-003 gate.
- After Framework tag publication, never move, delete, or reassign it. Recover only downstream steps against the same immutable tag.
- If the tag-triggered Skeleton workflow fails, use its manual recovery for `release_version=1.2.0`; accept only an annotated Skeleton tag peeling to the same deterministic split.
- Treat a divergent Skeleton `main`, different peeled commit, new lightweight `1.2.0` tag, or non-fast-forward update as a blocker; do not auto-rewrite remote refs.
- For Packagist propagation delay, keep tags unchanged and recheck. If GitHub Release creation fails, retry only Release creation after package/tag consistency is intact.
- Every recovery rerun must preserve credential cleanup and must not print the deploy key or other secret material.

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
- `tests/Consumer/community-board-digest.sh`
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

The Digest Consumer's direct SQL query exposes only the journal's denormalized `origin_actor_id`; the execution actor remains inside the protected journal record. The correction therefore checks exact origin continuity across sequences 1 through 8 without weakening the contract to a presence-only check.

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
- DIAGNOSTIC at `6e009a4`: the first direct `community-board-browser.sh` run after Clean Install stopped at its dependency precondition because Clean Install intentionally removes Community Board `vendor` and frontend `node_modules`. `.github/workflows/ci.yml` has an explicit Composer/frontend preparation phase before the focused journeys. After the same configuration, image build, Composer strict/install, and pnpm install steps, Browser passed 2 Playwright tests; Foundation, Identity (`55 tests / 582 assertions` plus frontend `46`), Post/Comment, and Product journeys all passed.
- BLOCKED at `6e009a4`: `CI=true bash tests/Consumer/community-board-digest.sh` passed migrations, PHPUnit (`55 tests / 582 assertions`), frontend check/test/build, HTTP/Worker retry/completion, digest, isolation, and event-sequence checks, then stopped at line 304. `FIRST_ACTORS` selects `origin_actor_id` only, so requiring `community-board-worker-1` contradicts the column contract; the execution actor is stored inside the protected journal record and is intentionally unavailable to this direct SQL assertion. The bounded Consumer assertion correction is delegated to the Luna High worker.

- PASS after the bounded correction: `CI=true bash tests/Consumer/community-board-digest.sh` completed migrations (`16`), PHPUnit (`55 tests / 582 assertions`), frontend check/test/build (`46` Vitest tests), HTTP/Worker retry/completion, tenant isolation, exact journal event sequence, exact origin-actor continuity, and cleanup; it ended with `Community Board digest journey passed.`
- PASS: `bash -n tests/Consumer/*.sh`, `git diff --check`, the PHP management-ID guard, and `docker compose run --rm app mago format --check src tests`.
- PASS: Documentation Reviewer read-only final review returned P1=0, P2=0, P3=0 and permitted the bounded Digest correction commit. Long-running Consumer/build/browser commands were not rerun by the reviewer; worker runtime evidence and Orchestrator static review remain the execution evidence.
- DIAGNOSTIC at `e4be46f`: the first two exact `bash tests/Consumer/scheduled-operation.sh` runs stopped at the initial `operation:schedule:run --json` with safe `configuration_error`. Secret-safe runtime composition inspection identified the cause as the copied Quickstart lacking `var/log`, while `config/journal.php` and `config/logging.php` require that writable parent. Creating only the fixture directory cleared the error and returned evaluated 2 / accepted 2 / failed 0.
- PASS after the scoped correction: `bash tests/Consumer/scheduled-operation.sh` completed the Scheduled Operation CLI, recovery, and concurrency journey. `mkdir -p "${CONSUMER}/var/log"` is now performed immediately after copying the Quickstart; no Production PHP or public API changed.
- PASS: `bash -n tests/Consumer/*.sh`, `git diff --check`, the PHP management-ID guard, and `docker compose run --rm app mago format --check src tests` after the Scheduled Consumer correction.
- PASS: Documentation Reviewer read-only review returned P1=0, P2=0, P3=0 and permitted the four-file Scheduled Consumer correction commit. Orchestrator committed it as `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5`; this exact committed source is the replacement Final Fixed Candidate.
- PASS: Documentation Reviewer final local-gate checkpoint review returned P1=0, P2=0, P3=0 and permitted the five-file management checkpoint commit while explicitly withholding P22-003 Acceptance. The reviewer confirmed the 23-Consumer count, local gate versus strict blocker distinction, 0-behind／50-ahead remote-tracking relation, P22-004 execution/recovery boundaries, and absence of publication authorization. Long-running commands and remote fetch were not repeated by the read-only reviewer.

## Acceptance Criteria

- [x] Runtime Consumer and CI wiring implement the P22-002 common migration/DDL and Provider-present/missing lane contract.
- [x] Runtime Consumer executes successfully with cleanup/source/Docker invariants at fixed candidate `08ad61f`.
- [x] Replacement Final Fixed Candidate SHA is committed and recorded after the Scheduled Operation Consumer correction.
- [ ] Full local strict quality and Remote GitHub Actions gates pass at the same fixed SHA; Mago lint, Deptrac, and Remote CI remain unsatisfied.
- [x] Report, Specification 103, TODO, and STATE are synchronized with the completed local execution and remaining blockers.
- [x] Community Board registers an Application-owned `StorageKeyProvider`, fresh setup creates a strict 32-byte Local key with mode 600 and no exposure, and existing `.env` remains unchanged.
- [x] Community Board lock metadata retains current Candidate `open-telemetry/api` / `ext-sodium` requirements and the fresh install resolves Seed/HTTP/Worker dependencies.
- [x] Community Board clean install applies 11 Framework + 5 Application migrations and the README/Consumer assert the same total of 16.
- [x] Community Board lock and installed vendor tree contain no orphan `ray/aop` package, and the Consumer asserts that absence.
- [x] Current spec, Mago, Deptrac, and internal Bootstrap documentation describe the Framework-owned proxy artifact contract without obsolete Ray.Aop dependency or path references.
- [x] Digest Consumer asserts only denormalized `origin_actor_id` and exact origin continuity for all eight journal records; protected execution actor remains unqueried.
- [x] Scheduled Operation Consumer prepares the Quickstart runtime log directory required by the configured journal and logging paths, then passes CLI, recovery, and concurrency evidence.
- [x] All 23 top-level Consumers, Frontend, Website, Framework export, Skeleton split/publication dry run, normal/no-scripts create-project, and repository guards pass at `08ad61f`.
- [x] CHANGELOG Known Limitations and UPGRADE match the fixed candidate release and migration surface.
- [x] Framework／Skeleton tags, GitHub Release, and Packagist `1.2.0` are confirmed absent read-only.
- [x] P22-004 preconditions, publication sequence, success conditions, and recovery conditions are fixed without authorizing publication.

## Remaining Issues

1. Broad Mago lint must be brought from the existing 186 issues／14 errors to a successful strict result, or the acceptance contract must be changed by an explicit specification decision; no waiver is inferred.
2. Deptrac must run successfully instead of stopping at the PHP 8.5 vendor parser error at 0/857.
3. Candidate `08ad61f` is not in remote `main`; same-SHA GitHub Actions evidence remains pending a separately authorized branch push.
4. Any source correction supersedes `08ad61f` and requires the complete gate to restart from the replacement committed SHA. Evidence collected at older candidates remains diagnostic only.

## Suggested Next Action

Create a bounded follow-up plan for the Mago lint and Deptrac PHP 8.5 compatibility blockers. After a replacement candidate passes the strict local gate, request separate authorization for the branch push required to collect same-SHA Remote GitHub Actions evidence. Do not begin P22-004 publication before P22-003 acceptance.
