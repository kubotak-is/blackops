# P22-001 Stable 1.2 Version Baseline Report

Status: Accepted

## Summary

Repository `main` now declares an unpublished `1.2.0` Release Candidate while published Latest Stable Framework／Skeleton `1.1.0` remains unchanged. Root Composer metadata, Framework OpenTelemetry scope, Skeleton Source of Truth, candidate Consumer fixtures, active Preview documentation, Decision／Specification／Roadmap／TODO, and the Website release notice are synchronized.

The accepted baseline was committed as `dadb64f`. No tag, push, GitHub Release, Packagist update, Skeleton split publication, or deploy was performed.

## Version Inventory

| Classification | Evidence | Result |
| --- | --- | --- |
| current-candidate | `Dockerfile` root version, `TelemetryTracer`／`TelemetryMetrics` scope, `examples/quickstart/composer.json`, Consumer path mappings, active main Preview docs | `1.2.0` / `^1.2` |
| stable-history | README／Guide／Website Stable install command and CTA, CHANGELOG `1.1.0`, UPGRADE `1.0.0`→`1.1.0`, Stable capability text | Preserved `1.1.0` |
| published-boundary | Existing Stable Tag／Release／Packagist claims | Not modified; no new publication claim |
| third-party | Keep a Changelog URL `1.1.0`, dependency fixtures such as `php-http/guzzle7-adapter:^1.1`, HTTP protocol `1.1` literals, unrelated Website package versions | Unchanged |

The dedicated `tests/Consumer/version-baseline.sh` rejects active `1.1.0` drift and `1.2.0` Latest Stable／published claims while requiring the Stable lane and candidate lane to remain distinct.

## Stable／Candidate Matrix

| Surface | Stable `1.1.0` | Main candidate `1.2.0` |
| --- | --- | --- |
| Framework／Skeleton | Existing immutable Tag／Release／Packagist | Local Source and Consumer fixture only |
| Skeleton constraint | `^1.1` historical artifact | `^1.2` Source of Truth |
| Composer root／Telemetry scope | Published artifact not changed | Root `1.2.0@dev`; Trace／Metric `1.2.0` |
| Install journey | `composer create-project blackops/skeleton my-app 1.1.0` | Local Path Repository with version `1.2.0` |
| Website | Stable CTA remains `1.1.0` | Candidate label and Releases description identify unpublished `1.2.0` |

## Changed Files

- `Dockerfile`, `README.md`, `CHANGELOG.md`, `UPGRADE.md`
- `examples/quickstart/composer.json`, `examples/quickstart/README.md`
- `src/Internal/Telemetry/TelemetryTracer.php`, `src/Internal/Telemetry/TelemetryMetrics.php`
- Candidate Consumer fixtures: `quickstart-e2e.sh`, `auth-generator-fresh.sh`, `scheduled-operation.sh`, `storage-protection-rotation.sh`, `frankenphp-worker-mode.sh`, `skeleton-create-project.sh`, `skeleton-publication.sh`, `skeleton-publication-workflow.sh`, `version-baseline.sh`
- Active internal／guide documentation and Website landing／content map／tests／theme
- `develop/decisions/139-stable-1-2-version-baseline.md`, `develop/spec/103-stable-1-2-release-plan.md`, Spec／Roadmap／README／TODO synchronization
- `develop/STATE.md` and this Report

## Decisions and Assumptions

- D139 and Specification 103 define `1.1.0` as immutable Stable history and `1.2.0` as unpublished main candidate.
- Historical 1.0→1.1 Upgrade steps and Stable onboarding were restored after review; the candidate material is an independent `1.1.0から1.2.0 Preview` section.
- Publication workflow's new-release fixture targets `1.2.0`; `1.0.0` legacy lightweight-tag recovery remains historical evidence. Divergence／lightweight rejection fixtures use later synthetic versions.
- The `1.2.0` Release Note／Migration and all publication gates remain subsequent work.

## Commands and Results

| Command | Result |
| --- | --- |
| `bash tests/Consumer/version-baseline.sh` | PASS |
| `docker compose build app` | PASS; rebuilt `blackops/framework:dev` with root `1.2.0@dev` |
| `docker run --rm blackops/framework:dev php -r 'echo getenv("COMPOSER_ROOT_VERSION"), PHP_EOL;'` | PASS, `1.2.0@dev` |
| `docker compose run --rm app composer validate --strict` | PASS (`composer.json is valid`) |
| `docker compose run --rm app vendor/bin/phpunit tests/Internal/Telemetry` | PASS, 13 tests／96 assertions (existing deprecation/notices) |
| `docker compose run --rm app mago format --check src tests` | PASS |
| `bash -n` required Consumer scripts | PASS |
| `bash tests/Consumer/skeleton-publication-workflow.sh` | PASS |
| `mise exec -- pnpm --dir docs/website run test` | PASS, 77 tests |
| `mise exec -- pnpm --dir docs/website run check` | PASS |
| `mise exec -- pnpm --dir docs/website run build` | PASS, 42 pages (known chunk-size and root-route priority warnings) |
| Management-ID guard | PASS |
| `git diff --check` | PASS |
| `bash tests/Consumer/skeleton-create-project.sh` | PASS after excluding ignored working-tree artifacts from the harness copy |
| `bash tests/Consumer/skeleton-publication.sh --dry-run` | PASS, `version=1.2.0`, `split=working-tree`, after the same copy correction |
| `bash tests/Consumer/quickstart-e2e.sh` | PASS; Framework `1.2.0` lock/install, 13 migrations, build, frontend, HTTP, Deferred, and cleanup |
| `bash tests/Consumer/skeleton-publication.sh 1.2.0 HEAD` | PASS after commit `dadb64f`; source `dadb64fe149663a47f3257f0f8dc9f8c19dc0ab8`, deterministic split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce` |
| Documentation Reviewer final re-review | PASS; P1=0／P2=0／P3=0 (Browser visual review Not Verified because localhost was unreachable from the reviewer environment) |

## Acceptance Criteria

- [x] Decision／Specification separate Latest Stable `1.1.0` and next candidate `1.2.0`.
- [x] Root Composer version, Telemetry Trace／Metric scope, Skeleton constraint, and candidate fixtures synchronize to `1.2.0`.
- [x] Stable install command, CTA, historical records, and published claims remain unchanged.
- [x] README／Guide／Internal docs／Website identify Stable versus unpublished main candidate.
- [x] CHANGELOG Unreleased and independent UPGRADE Preview target `1.2.0` and defer complete Release Note.
- [x] Version inventory guard rejects current-source drift and false publication claims.
- [x] Focused PHPUnit, Composer, Mago format, Consumer workflow, Website, management-ID, and diff checks pass.
- [x] Create-project and publication dry-run Consumer commands pass with Docker API access after the ignored-artifact copy correction.
- [x] Documentation Reviewer final P1=0／P2=0／P3=0 review permits Acceptance; Browser visual review remains explicitly Not Verified.

## Remaining Release Work

Complete the `1.2.0` Release Note／Upgrade, full quality／Consumer gate, and separately authorized Tag／Push／Skeleton publication／Packagist／GitHub Release gate.

## Suggested Next Action

Start the subsequent `1.2.0` Release Note／Upgrade and full release gate as a separate Task Packet. Do not create a Tag, Push, Release, Packagist publication, or deploy without separate authorization.

## Follow-up Finding and Correction

The first Docker-enabled Consumer run reproduced an ignored local `examples/quickstart/node_modules` directory being copied by `cp -a`, causing the create-project harness to fail its generated-artifact assertion before Composer and causing the publication dry-run root allowlist to fail. Both `tests/Consumer/skeleton-create-project.sh` and the `--dry-run` branch of `tests/Consumer/skeleton-publication.sh` now use an explicit tar copy that retains dotfiles and committed Source while excluding `.env`, `composer.lock`, `vendor`, nested `node_modules`, generated frontend output, and generated `var` files; the required `var/*/.gitignore` files are restored.

Orchestrator independently reran and passed `bash tests/Consumer/skeleton-create-project.sh` and `bash tests/Consumer/skeleton-publication.sh --dry-run` (`version=1.2.0`, `split=working-tree`). After acceptance commit `dadb64f`, the exact `bash tests/Consumer/skeleton-publication.sh 1.2.0 HEAD` check also passed against the committed `^1.2` Skeleton constraint and deterministic split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.

## Documentation Reviewer P1 Corrections

The README previously stated that the Documentation Website was not externally hosted. Current evidence is `https://blackops-php.pages.dev` (HTTP 200), so the README now identifies the Cloudflare Pages publication and verified Local／CI build and public-artifact boundary, without claiming that this task published or deployed `1.2.0`.

The internal E2E description previously claimed `git archive HEAD:examples/quickstart` committed-only extraction. It now describes the actual filtered Working Tree tar copy: dotfiles and Source are retained; `.env`, lock／dependency directories, generated frontend, and generated `var` files are excluded; and the two `var` `.gitignore` files are restored.

The Documentation Reviewer re-read the current bytes after the corrections and returned P1=0／P2=0／P3=0. Browser visual／responsive／accessibility review remains Not Verified because the reviewer environment could not reach the Orchestrator's localhost server; Website test／check／build and static artifact guards passed independently.
