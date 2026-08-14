# P22-003A Release Quality Tooling Blocker Resolution Report

Status: Review Pending

## Summary

P22-003のstrict quality blockerに対し、D140のtracked Mago baselineとDeptrac 4.7.1 exact updateを実装した。Magoの新規Issue検出とbaseline同期検査は成功したが、Deptracは857 file解析後に既存Rulesetのviolations／uncoveredを報告し、Framework package exportはbaselineのarchive exclusion契約不足で失敗した。いずれもProduction PHP Behaviorを変更せず、範囲外修正を行わずにBlockerとして残す。StatusはReview Pendingであり、Commitしていない。

## Changed Files

- `composer.json`, `composer.lock` (exact Deptrac 4.7.1 minimal update; lockはDeptrac packageとcontent hashだけ)
- `mago.toml`, `mago-lint-baseline.toml` (Mago 1.42.0 strict generated baseline; stale `.bkp`は削除)
- `.github/workflows/ci.yml` (normal／verify lintとVersion Guard)
- `tests/Consumer/version-baseline.sh` (Mago／Deptrac／baseline path／strict variant guard)
- `docs/internal/runtime-dependencies.md`
- D140/spec/index/Release Plan/TODO/STATE and P22-003／P22-003A Task／Report synchronization

## Decisions and Assumptions

- Magoの既存DebtはRule無効化ではなくMago生成strict baselineへ固定する。
- Deptracは公式4.7.1へexact／minimal updateし、vendor patchやArchitecture waiverを使わない。
- Tooling／CI Metadata変更後のCommitは`08ad61f`をsupersedeし、P22-003 Full Gateを新SHAから再実行する。
- Deptracの152 violations／59 uncoveredは4.7.1で初めてPHP 8.5 graph解析が完走したことで観測できた既存Ruleset debtである。TaskはRuleset／Architecture／Production PHP変更を禁止しているため、4.7.1固有の新規違反とは判定せず、raw outputを証跡としてOrchestratorへ返す。
- Package exportを通すにはroot baselineを`.gitattributes`の`export-ignore`とComposer `archive.exclude`へ同期する必要があるが、`.gitattributes`とpackage export Consumerは本Taskの変更許可File外であるため変更しない。

## Composer Update Evidence

- `composer update deptrac/deptrac --with-all-dependencies --minimal-changes --no-interaction --no-scripts`: PASS, 0 installs／1 update／0 removals; `deptrac/deptrac` 4.6.2 => 4.7.1 only.
- `composer show deptrac/deptrac --locked`: PASS, 4.7.1 (`de3303ae…`). `composer show carthage-software/mago --locked`: PASS, 1.42.0.
- `composer.lock` diff contains only content hash and the Deptrac package metadata/version; no other package version changed.

## Mago Baseline Evidence

- Pre-implementation broad lint: 186 issues = 14 errors／105 warnings／45 notes／22 help.
- Mago 1.42.0 normal lint reports that it filters the observed 186 existing findings using `mago-lint-baseline.toml` and exits 0. The generated file contains 176 serialized issue records; Mago's own filtered count remains the acceptance count. `mago lint --verify-baseline` reports `Baseline is up to date` and exits 0. Baseline declares exactly one `variant = "strict"`; no rule disable or hand-written waiver was added.
- Diagnostic `mago lint --ignore-baseline --minimum-report-level error --reporting-format count` returns `error: 14` and exit 1, preserving the existing-debt signal.

## Deptrac Evidence

- Current 4.6.2 stops at 0/857 on the vendor dynamic `instanceof` expression; 4.7.1 removes that parser stop.
- Deptrac 4.7.1 raw output `/tmp/p22a-deptrac.out` reaches `857/857` files, then exits 1 with: `Violations 152`, `Skipped violations 0`, `Uncovered 59`, `Allowed 4172`, `Warnings 0`, `Errors 0`.
- Representative output includes `BlackOps\\Execution\\Dispatcher` -> `BlackOps\\Telemetry\\TelemetryContext`, `BlackOps\\Http\\OperationRequestHandler` -> `BlackOps\\Telemetry\\TelemetryContext`, and `BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation*` -> `BlackOps\\Internal\\StorageProtection` dependencies. No Deptrac config／Ruleset or source was changed. This is a newly observable existing graph result, not accepted as a passing architecture gate.

## Commands and Results

- PASS: `docker compose run --rm app composer validate --strict`.
- PASS: `docker compose run --rm app composer validate --strict examples/quickstart/composer.json`.
- PASS: locked package checks: Deptrac 4.7.1, Mago 1.42.0.
- PASS: `docker compose run --rm app mago format --check src tests examples`.
- PASS: `docker compose run --rm app mago lint` (186 baseline issues filtered).
- PASS: `docker compose run --rm app mago lint --verify-baseline`.
- PASS: `docker compose run --rm app mago analyze` (exit 0; 71 advisory warning/help findings).
- FAIL / BLOCKER: `docker compose run --rm app vendor/bin/deptrac` (857/857; 152 violations, 59 uncovered, 4,172 allowed, 0 skipped/warnings/errors; exit 1).
- PASS: `docker compose run --rm app vendor/bin/phpunit --colors=never` (2,315 tests, 9,435 assertions; 1 deprecation, 2 PHPUnit deprecations, 13 notices).
- FAIL / BLOCKER: `bash tests/Consumer/framework-package-export.sh` (`mago-lint-baseline.toml` unexpected archive root; requires Task-out-of-scope `.gitattributes` and archive exclusion updates).
- PASS: `bash -n tests/Consumer/version-baseline.sh`; `bash tests/Consumer/version-baseline.sh` (`stable=1.1.0 candidate=1.2.0`).
- PASS: no PHP diff under `src`／`tests`; no management IDs in PHP under `src`／`tests`／`examples`; `git diff --check`.

## Acceptance Criteria

- [x] Mago strict baseline, normal lint, and synchronization verification pass.
- [ ] Deptrac 4.7.1 full graph passes (blocked by 152 violations／59 uncovered after 857/857 analysis).
- [ ] Composer／PHPUnit／version／repository guards pass; package export remains blocked by archive contract for the new baseline file.
- [x] Internal Documentation、Decision、Specification 16／103、TODO、STATE、P22-003 Task／Report are synchronized for review.
- [ ] Documentation Reviewer P1=0／P2=0 and Orchestrator replacement-candidate commit approval.

## Remaining Issues

Orchestrator must decide whether to authorize a follow-up bounded change for Deptrac Ruleset debt and archive exclusion metadata, or split those blockers into a new Task. Until then, P22-003A remains Review Pending; no worker Commit was created.

## Suggested Next Action

Review this report and raw `/tmp/p22a-deptrac.out` evidence. If the two blockers are authorized in scope, create a follow-up Task; otherwise accept the Mago/Composer portion as a partial correction and keep P22-003 Final Fixed Candidate withheld.
