# P22-003B Release Architecture and Export Closure Report

Status: Commit Approved — Post-Commit Export Pending

## Summary

D141／D142に従い、P22-003Aで露出したDeptrac graph debtとFramework archive exclusionを解消した。Architecture waiver、generic Public／Transport -> Internal permission、外部Publicationは行わない。

## Changed Files

- `deptrac.yaml`
- `tests/Consumer/version-baseline.sh`
- `.gitattributes`, `composer.json`
- `src/Core/Exception/ConfigurationFailure.php`, `src/Application/ApplicationBootstrapException.php`
- Internal CLI catches and focused regression tests
- Core API／runtime dependency／release plan documentation and website reader contract
- Specification 16 and D142 task/report/state synchronization for the five bounded facade implementation collectors
- Framework package export／version guards

## Decisions and Assumptions

- D142 Option Bに従い、Public facadeを維持して5つのInternal implementationをnarrow collector化し、generic `Application/Auth/Http -> Internal`を削除する。
- Public／Library dependencyは明示Layerへ追加する。
- Transportが利用するInternal facilityはnarrow collectorへ分離し、catch-all Internalを許可しない。
- Internal CLIのconfiguration classificationはCore markerで維持し、Internal -> Application cycleを作らない。
- Tracked Mago baselineはRepository／CIに残し、Git／Composer published archiveだけから除外する。

## Dependency Inventory

- Baseline: 152 violations／59 uncovered after 857 files.
- Explicit `Identifier`, `Idempotency`, and `Outbox` layers depend on `Core`; `Dotenv` and `Nyholm` are explicit Library collectors.
- `Telemetry` edges are limited to the propagation and safe-correlation contracts. `Transport` has no catch-all `Internal` access.
- `InternalTelemetry`, `InternalStorageProtection`, and exact `DeferredIntegrity` (`DeferredOperationContextValidator`) collectors are excluded from catch-all `Internal` and have narrow rules.
- D142 Option B adds non-overlapping `InternalApplication`, `InternalAuth`, `InternalHttp`, `InternalIdempotency`, and `InternalSapiRuntime` collectors. Public edges are limited to `Application -> InternalApplication`, `Auth -> InternalAuth`, and `Http -> InternalHttp, InternalIdempotency, InternalSapiRuntime`; implementation rules are explicit and source-measured.
- SCC evidence from the current ruleset is exactly two non-trivial SCCs: `Core / Idempotency / Telemetry`, and `Application / Auth / Http / Internal / InternalApplication / InternalAuth / InternalHttp / InternalIdempotency`. The second is the sole D142-accepted facade/internal SCC from D064/D069/D111/D114 composition; `InternalSapiRuntime` is one-way and outside it. Bounded means no direct Public facade -> catch-all `Internal` permission; only the five listed collectors are facade entry points.

## Bounded Layer Evidence

- Deptrac 4.7.1 with `--no-cache`: 857/857 files, 0 violations, 0 skipped, 0 uncovered, 4,848 allowed, 0 warnings/errors.
- Core `ConfigurationFailure` is a no-detail public marker. `ApplicationBootstrapException` remains a public `RuntimeException` subtype implementing it; Internal CLI catches the marker and emits safe configuration classification.

## Package Export Evidence

- `.gitattributes` and Composer `archive.exclude` now contain the same `/mago-lint-baseline.toml` entry; Consumer and version guards assert it.
- Pre-commit root/exclusion checks pass; exact regular-file inventory is intentionally pending because Git archive HEAD omits the untracked public `src/Core/Exception/ConfigurationFailure.php`.

## Commands and Results

- PASS: Deptrac 4.7.1 full graph with `--no-cache` (857/857; 0/0/0; 4,848 allowed).
- PASS: focused PHPUnit (21 tests, 81 assertions).
- PASS: Composer strict root and Quickstart validation.
- PASS: Mago format check, lint, strict baseline verification, analyze (71 advisory warnings/help).
- PASS: `bash -n tests/Consumer/version-baseline.sh`; version baseline guard.
- PASS: archive root/exclusion contract; exact inventory check is expected to fail pre-commit on missing `src/Core/Exception/ConfigurationFailure.php` and is mandatory immediately after the candidate Commit.
- PASS: `git diff --check`, no Deptrac skip/uncovered waiver, and PHP management-ID scope guard.
- PASS: D142 section-aware collector/ruleset guard in `tests/Consumer/version-baseline.sh` (stable=1.1.0, candidate=1.2.0).
- PASS: SCC guard computes exactly the two documented non-trivial SCC sets from the ruleset using POSIX shell/awk only.
- PASS: Independent Documentation Reviewer returned P1=0／P2=0／P3=0 and permitted the replacement candidate commit, conditional on immediate post-commit exact Framework package export PASS.
- PASS after transient flake: full PHPUnit initially observed one Outbox heartbeat failure (2,317 tests／9,443 assertions); the filtered test then passed with 4 assertions, the complete file passed 4 tests／32 assertions, and the final full suite passed 2,317 tests／9,444 assertions with exit 0. No assertion or runtime source was changed for the flake.
- PASS: Website `pnpm test` 79 tests, `pnpm run check` 0 errors/warnings/hints, `pnpm run build` 42 generated pages, and `pnpm run site:check` 41 reader pages. The first sandboxed build reached static generation and hit Blume/Astro font server `listen EPERM`; the permitted host rerun completed successfully.

## Acceptance Criteria

- [x] Deptrac full graph passes with zero violations and uncovered dependencies.
- [x] Pre-commit Framework Git／Composer root and exclusion contract passes with synchronized archive roots.
- [ ] Post-commit Framework Git／Composer exact regular-file inventory matches, including public `src/Core/Exception/ConfigurationFailure.php`.
- [x] Source／quality／focused and full PHPUnit／documentation website contracts pass; complete P22-003 candidate gate restart remains with Orchestrator after Commit.
- [x] Independent documentation review permits replacement candidate commit.

## Remaining Issues

The D142 Option B worker correction is implemented, measured, and independently reviewed with P1=0／P2=0／P3=0. Exact Git/Composer regular-file inventory is an expected pre-commit failure because the new public file is untracked; root/exclusion checks pass. Replacement candidate commit, post-commit exact export rerun, and complete P22-003 gate restart remain pending.

## Suggested Next Action

Commit the reviewed correction, immediately require the exact Framework package export to pass from that committed source, then restart the complete P22-003 gate from the replacement candidate SHA.
