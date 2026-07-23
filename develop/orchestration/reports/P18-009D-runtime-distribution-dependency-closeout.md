# P18-009D: Runtime Distribution, Dependency Audit, and Closeout Report

## Summary

The installed application examples now use the framework-owned Environment File and SAPI Runtime boundaries. Community Board no longer performs manual Dotenv loading, and Quickstart／Community Board Composer metadata no longer declares runtime packages that the application source does not import. DBAL and Migrations remain direct dependencies where the application imports their APIs. Skeleton, consumer assertions, public documentation, internal runtime guidance, and website reference content were synchronized. P18-009D1 corrected the SAPI `Location` status regression, every distribution／consumer／frontend／browser／website gate passed, and the Runtime Follow-up is ready for Phase 19 handoff.

## Changed Files

- `examples/quickstart/composer.json`, `examples/quickstart/README.md`
- `examples/community-board/bootstrap/app.php`, `examples/community-board/composer.json`, `examples/community-board/composer.lock`, `examples/community-board/README.md`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/skeleton-publication.sh`, `tests/Consumer/community-board-clean-install.sh`
- `README.md`, `CHANGELOG.md`, `UPGRADE.md`
- `docs/guide/application-bootstrap.md`, `docs/guide/configuration.md`, `docs/guide/core-api.md`
- `docs/internal/application-bootstrap.md`, `docs/internal/frankenphp-runtime.md`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/46-composer-skeleton-publication.md`, `develop/spec/49-feature-first-quickstart-application.md`, `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `examples/quickstart/` remains the Skeleton and Distribution source of truth. No generated `composer.lock` is added to that distribution source.
- Framework-owned runtime packages may remain in an application lock transitively when required by `blackops/framework`; they are not application Direct Dependencies. The regenerated Community Board lock removes Laminas HTTP Handler Runner and Nyholm PSR-7 Server, while retaining Framework-transitive Dotenv／Nyholm PSR-7／Symfony UID packages.
- After the corrective P18-009D1 commit, Composer resolver refreshed the Community Board path-repository reference from `b88a1b4` to accepted Framework commit `462cfdb`; install and strict validation passed from the refreshed lock.
- DBAL／Migrations remain explicit in Community Board because repositories, seeders, and migrations import their APIs directly.
- `Application::http()` remains the documented PSR-15 escape hatch. Standard Classic／Worker front controllers call `SapiRuntime`.
- Website and Community Board pnpm commands were run locally with the user store available; no external publication or deployment was performed.

## Final Skeleton／Quickstart／Community Board Shape

| Surface | Final contract |
| --- | --- |
| Bootstrap | `Application::configure(...)->withEnvironmentFile()->withConfiguration()->create()` |
| Classic entrypoint | `SapiRuntime::run($application)` |
| Worker entrypoint | `SapiRuntime::runWorker($application)` |
| Quickstart direct Composer dependencies | PHP and `blackops/framework` only |
| Community Board direct Composer dependencies | PHP, Framework, `doctrine/dbal`, `doctrine/migrations` |
| Framework-owned runtime packages | Dotenv, PSR-7／PSR-17／SAPI adapters, UUIDv7 implementation remain Framework-owned; transitive lock entries are expected |

## Removed Direct Import／Dependency Matrix

| Package | Quickstart | Community Board | Evidence |
| --- | --- | --- | --- |
| `vlucas/phpdotenv` | Removed from `composer.json`; no application import | Removed from `composer.json`; bootstrap import removed | Architecture test, source scan, Composer strict |
| `nyholm/psr7` | Removed from `composer.json`; no application import | Removed from `composer.json`; Framework may retain it transitively | Entrypoint／bootstrap scan, Composer strict |
| `nyholm/psr7-server` | Removed from `composer.json` | Removed from `composer.json` and regenerated lock | Lock resolver and consumer metadata guard |
| `laminas/laminas-httphandlerrunner` | Removed from `composer.json` | Removed from `composer.json` and regenerated lock | Entrypoint scan and lock resolver |
| `symfony/uid` | Not declared | Removed from `composer.json`; Framework may retain it transitively | Identifier adapter scan and Composer metadata guard |
| `doctrine/dbal`／`doctrine/migrations` | Not required by Quickstart source | Preserved as direct dependencies | Repository／Seeder／Migration imports |

## Commands and Results

### Passed short／static commands

- `docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php` — PASS, 13 tests／308 assertions.
- `docker compose run --rm app vendor/bin/phpunit` — PASS after the P18-009D1 correction, 1,727 tests／6,898 assertions.
- `docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests` — PASS.
- `docker compose run --rm app mago lint` — PASS (existing SAPI empty-loop note and UUID compiler else help remain informational).
- `docker compose run --rm app mago analyze` — PASS.
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` — PASS, 0 violations／2,860 allowed.
- `docker compose run --rm app composer validate --strict --working-dir=examples/quickstart` — PASS.
- `docker compose run --rm app composer validate --strict --working-dir=examples/community-board` — PASS.
- `docker compose run --rm app composer validate --strict` — PASS.
- `! rg -n 'Spec(ification)?...|D[0-9]{3}|P[0-9]+-[0-9]+|TODO.md:' src tests examples/quickstart examples/community-board --glob '*.php'` — PASS.
- `bash -n tests/Consumer/skeleton-publication.sh tests/Consumer/skeleton-create-project.sh tests/Consumer/community-board-clean-install.sh` — PASS.
- `git diff --check` — PASS.

### Consumer／Frontend／Website commands

Website gates completed in the Orchestrator environment: frozen pnpm install PASS; the first test run failed 41/42 on the stale 165-type inventory, the second failed 41/42 on the stale test hard-code, and the third passed 42/42 after updating the Guide and test. `pnpm check` PASS (content determinism, Mermaid, Astro 16 files, 0 errors／warnings／hints). `pnpm build` PASS (content generation, diagrams, 32 pages, artifact boundary, site navigation／accessibility／Pagefind 31 pages). Skeleton publication dry-run／workflow, create-project, Framework update, package export, Quickstart setup, and the corrected Quickstart E2E passed. The initial E2E had returned 302 for `POST /reports`; P18-009D1 reordered SAPI emission and the rerun confirmed 202. No external publication or deployment was attempted.

- `bash tests/Consumer/skeleton-publication.sh --dry-run` — PASS.
- `bash tests/Consumer/skeleton-publication-workflow.sh` — PASS.
- `bash tests/Consumer/skeleton-create-project.sh` — PASS for normal and `--no-scripts` setup.
- `bash tests/Consumer/framework-update-generators.sh` — PASS.
- `bash tests/Consumer/framework-package-export.sh` — PASS for Git and Composer export boundaries.
- `bash tests/Consumer/quickstart-setup.sh` — PASS.
- `bash tests/Consumer/quickstart-e2e.sh` — PASS after P18-009D1; real `POST /reports` remained 202.

The remaining isolated Community Board gates were run sequentially without touching the user-owned Root Runtime or volume. All exited 0:

- `bash tests/Consumer/community-board-foundation.sh` — PASS, `Community Board foundation journey passed.`
- `bash tests/Consumer/community-board-identity.sh` — PASS, 49 PHPUnit tests／548 assertions, `Community Board identity journey passed.`
- `bash tests/Consumer/community-board-post-comment.sh` — PASS, 49 PHPUnit tests／548 assertions, `Community Board post and comment journey passed.`
- `bash tests/Consumer/community-board-product-journey.sh` — PASS, `Community Board product journey passed.`
- `bash tests/Consumer/community-board-digest.sh` — PASS, worker claims processed in the `0, 1, 0, 1, 0, 1` sequence, `Community Board digest journey passed.`
- `bash tests/Consumer/community-board-browser.sh` — PASS, Playwright `1 passed (12.5s)`, `Community Board browser journey passed.`
- `bash tests/Consumer/auth-generator-fresh.sh` — PASS, Fresh／Force generation, build, frontend, HTTP auth journey, and sensitive-surface guards.
- `bash tests/Consumer/frankenphp-worker-mode.sh` — PASS, Worker bootstrap／flush／isolation／database failure recovery／reconnect／restart／memory and Classic fallback.
- `bash tests/Consumer/community-board-clean-install.sh` — PASS, Composer dependency guard, Svelte 0 errors／warnings, 43 frontend tests, production build, deterministic seed, and real frontend journey.
- `docker compose run --rm --no-deps app composer update blackops/framework --with-dependencies --no-install --minimal-changes --no-interaction --no-progress` — PASS after P18-009D1, Framework lock reference `b88a1b4 -> 462cfdb`, no unrelated package updates.

The clean-install script intentionally removed shared checkout dependencies and runtime artifacts on exit. The Orchestrator restored `.env`, `vendor`, frontend `node_modules`, build artifacts, generated frontend contracts, and the frontend production build. Existing migrations reported 0 pending. HTTP／worker／frontend were restarted without stopping or recreating PostgreSQL; the existing PostgreSQL remained healthy, HTTP returned healthy, `/welcome` returned the expected JSON, and frontend `/login` returned the expected page.

## Acceptance Criteria

- [x] Skeleton／Quickstart／Community Board bootstrap and Classic／Worker entrypoint use Framework-owned boundaries.
- [x] Unused application Direct Dependencies are removed; DBAL／Migrations remain where directly imported.
- [x] Lock regeneration completed by Composer resolver; Framework-transitive runtime packages are retained correctly.
- [x] Documentation and consumer architecture guards describe the final contract.
- [x] PHP／static short quality gates passed with no Framework `src/**` changes.
- [x] Quickstart／Community Board Existing／Clean／Classic／Worker／Auth／Board／Deferred／Browser and all frontend gates pass; Website and Distribution publication／update／export gates pass.
- [x] TODO／Delivery Plan／STATE show Runtime Follow-up completion and Phase 19 handoff.
- [x] No external publication／deploy; the existing Community Board PostgreSQL and volume were never stopped or recreated. HTTP／worker／frontend were restarted only after clean-install artifact restoration.

## Remaining Issues

No P18-009D blocker remains.

- Framework Root still owns and may transitively install Dotenv／Nyholm PSR-7／Symfony UID; these are intentionally not removed from Framework metadata.
- Initial Website reader-experience gate failed 41/42 because the Core API inventory documented 165 Public API types while the current source exposes 167. The second run failed 41/42 because the test itself still hard-coded 165. `docs/guide/core-api.md` and `docs/website/tests/reader-experience.test.mjs` now document／assert both `BlackOps\\Http\\SapiRuntime` and `BlackOps\\Identifier\\Uuidv7Generator`; the third run passed 42/42.
- Host PHP was unavailable for post-clean restoration, so `bin/setup` was executed through the Community Board app container. The sandboxed pnpm store was unavailable on the first restoration attempt; the approved user-store execution passed immediately. Neither condition is a repository blocker.

## Suggested Next Action

Commit the accepted P18-009D closeout, then create the first Phase 19 Task Packet from D109 and the Roadmap. Worker has made no commit.
