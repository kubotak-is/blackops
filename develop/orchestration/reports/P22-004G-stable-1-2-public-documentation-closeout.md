# P22-004G Stable 1.2 Public Documentation Closeout Report

Status: Accepted
Updated At: 2026-08-16T03:07:23+09:00

## Summary

Public and internal documentation was synchronized to the live Experimental Stable `1.2.0` Framework／Skeleton release. Normal and `--no-scripts` install guidance now uses published `1.2.0`; CHANGELOG／UPGRADE, Guide, Website Source, Internal Status, version baseline, TODO, Specification 103, and P22-004 management state describe the immutable publication and Remote smoke evidence. The confirmed bind-mount ownership limitation is recorded separately from the otherwise successful smoke.

## Changed Files

- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `examples/quickstart/README.md`
- `docs/guide/installation.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-status.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/observability.md`
- `docs/internal/installed-application-status.md`
- `docs/website/pages/index.astro`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `tests/Consumer/version-baseline.sh`
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/STATE.md`

No Production PHP, Consumer runtime behavior, Workflow, Tag, Release, Packagist, Website production, or external state was changed; only permitted documentation/static guards and Website assertions were updated.

## Decisions and Assumptions

- Framework tag direct object `00e8c5875047a3c47acbebfe57f75b0e581d18b9` peels to immutable source `3332fd1dd0738fc7e79750facd93d49a59054ecf`.
- Skeleton tag direct object `fedcfda5f39caf320ad67196e8ced459176cedb1` peels to immutable split／main `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- Manual Recovery `31889808876`／job `95024306339`, GitHub Release publication `2026-08-15T16:21:09Z`, and Remote smoke checked `2026-08-16T01:47:00+09:00` are immutable evidence inputs; no publication rerun was performed.
- Remote smoke successes are normal／`--no-scripts` install, exact `1.2.0` locks, Project Root CLI, compile, 12 migrations, HTTP, Worker retry→Completed, sensitive redaction, and cleanup.
- Confirmed limitation: HTTP creates root-owned `var/log/journal.jsonl` on the bind mount; later non-root `operation:inspect` returns `diagnostics.storage_failed`, while root comparison succeeds with masked data. This is an ownership limitation, not an overall Remote smoke failure. Source correction／`1.2.1` publication is out of scope.
- Historical `1.0.0`／`1.1.0` records remain unchanged; Website Source checks are local only.
- Upgrade Step 2 only records `composer require --no-update`; the actual Framework update occurs after Stable migration in Step 5. Runtime bootstrap files are copied from a disposable public Skeleton `1.2.0` create-project, using a validated `mktemp -d` parent whose cleanup remains part of the single `.env`／Compose EXIT trap on both success and failure.
- Documentation Review found that `umask 077` does not tighten the mode of an existing `.env`, the `--no-scripts` path did not unambiguously execute the mandatory key step, First Operation retained current-tense Stable 1.1 boundaries, and the Quickstart README fragment did not exist. These P1=3／P2=1 findings were corrected within the authorized files: both public Guide key blocks now chmod／verify exact 600 before writing, normal and `--no-scripts` lanes converge to the same explicit key step, First Operation is current 1.2.0, and the README links the generated `stable-120-authentication-and-deferred-journey` fragment.
- Documentation re-review resolved all prior content findings and returned P1=0／P2=1. The remaining guard-only gap is that the Website test checks the README fragment literal without asserting the generated artifact `id`, while Quickstart no-scripts／key-block existence is not protected by an ordering guard or negative fixture.
- The authorized P2 guard correction now requires exactly one generated Quickstart `id="stable-120-authentication-and-deferred-journey"` and rejects the retired id; Website unit tests assert positive normal／no-scripts setup convergence and throw for README target drift, an exact current-heading drift, or a no-scripts block moved after the shared key step. version-baseline requires these guard contracts, including the exact heading helper and negative fixture.

## Commands and Results

- PASS: `bash -n tests/Consumer/version-baseline.sh`
- PASS: `bash tests/Consumer/version-baseline.sh` — `Version baseline guard passed: published=1.2.0 historical=1.1.0`
- PASS: `docker compose run --rm app mago format --check src tests` (Docker permission required escalated retry; format clean)
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`
- PASS: `mise exec -- pnpm --dir docs/website test` — 80/80 tests passed after the Documentation Review correction.
- PASS: `mise exec -- pnpm --dir docs/website run check`
- PASS: `mise exec -- pnpm --dir docs/website run build` — static artifact, navigation, accessibility, version notice, and search checks passed.
- PASS: focused correction rerun — `UPGRADE.md` single EXIT cleanup retains `.env`／Compose cleanup and conditionally removes only the non-empty `mktemp` parent; no Skeleton-only trap or `trap - EXIT` remains. Website and version-baseline guards require this contract, fail-closed key subshells, and authenticated-header test naming.
- PASS: `git diff --check`
- PASS: `docker compose run --rm app mago format --check src tests` — `INFO All files are already formatted.`
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`
- PASS: exact allowed-file scope review — `git diff --name-only` and untracked-file listing contain only the Task Files Allowed set.
- NOT RUN: long Consumer runtime journeys; bounded correction is covered by the new static Website／version-baseline guards as authorized by the Task, with no external or Docker journey mutation.
- PASS: Orchestrator independently reran `version-baseline.sh`, Website 80／80 test, Website check／42-page build／41-page site check, Mago format, PHP management-ID guard, focused stale-current-claim search, exact 22-path allowed-file scope review, and diff check. Public Authentication／Storage Key semantics, Upgrade ordering, single cleanup trap, immutable refs／metadata, and separate `operation:inspect` limitation agree.
- PASS: Orchestrator dynamic key-mode probe copied `.env.example` to a disposable file at mode `0644`, ran the documented fail-closed block without printing the key, and confirmed exact `0600` before and after insertion plus a decoded length of 32 bytes; the disposable path was removed.
- PASS: Generated Website artifact contains the exact `id="stable-120-authentication-and-deferred-journey"` fragment targeted by the Quickstart README.
- PASS: `mise exec -- pnpm --dir docs/website test` — 82/82 tests, including generated-fragment, exact-heading, and negative convergence fixtures.
- PASS: Heading correction rerun — `assertQuickstartConvergence` requires the exact line `### Stable 1.2.0 Authentication and Deferred Journey`; a `(legacy)` heading fixture fails closed via `assert.throws`.
- PASS: `mise exec -- pnpm --dir docs/website run check`
- PASS: `mise exec -- pnpm --dir docs/website run build` — `check-site.mjs` requires exactly one generated Quickstart target id and rejects the retired id.
- PASS: `bash -n tests/Consumer/version-baseline.sh && bash tests/Consumer/version-baseline.sh`
- PASS: `docker compose run --rm app mago format --check src tests` — `INFO All files are already formatted.` (Docker permission required escalated retry.)
- PASS: PHP management-ID guard and `git diff --check`
- PASS: exact 22-path scope review — tracked and untracked changed paths remain inside Files Allowed.
- NOT RUN: long Consumer runtime journeys; this guard-only Task explicitly authorizes static Website／version-baseline evidence instead.

## Acceptance Criteria

- [x] Public normal／`--no-scripts` install commands use `1.2.0`.
- [x] Public Install／Quickstart chmod and verify existing `.env` to exact `0600` before writing a Local-only 32-byte Base64 `BLACKOPS_STORAGE_KEY`, cover normal／`--no-scripts` paths, and document Local／Production provider ownership.
- [x] Latest Experimental Stable, Release, Packagist, and Skeleton status match the live publication.
- [x] CHANGELOG／UPGRADE describe published `1.2.0`, irreversible migrations, Backup, and rollback boundaries.
- [x] Version baseline requires current public claims and rejects stale pre-publication claims.
- [x] Remote smoke successes and the `operation:inspect` ownership limitation are separated with evidence.
- [x] Required documentation Website unit／build gates and repository guards prove the generated fragment plus Installation／Quickstart no-scripts convergence with negative fixtures.
- [x] Independent Orchestrator and Documentation Review confirm P1=0／P2=0（final P1=0／P2=0／P3=0）.
- [x] P22-004／Phase 22 final acceptance is recorded after those reviews.

## Remaining Issues

- P22-004G and Phase 22 acceptance work is complete. No publication or Website deployment work remains in this Task.
- The root-owned journal bind-mount behavior remains a known post-1.2.0 runtime/environment limitation; fixing it requires a separately authorized Source task.

## Suggested Next Action

Commit the exact reviewed 22-path diff on a dedicated branch, open a PR, require Green CI／Documentation delivery, merge, and fetch a clean `main`. Then verify immutable public refs and package metadata remain unchanged. Do not redeploy Website documentation.
