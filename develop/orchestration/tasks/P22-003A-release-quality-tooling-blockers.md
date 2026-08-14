# P22-003A: Release Quality Tooling Blocker Resolution

Status: Review Pending

## Goal

P22-003で再現したbroad Mago lintの既存186 Issue／14 ErrorとDeptrac 4.6.2のPHP 8.5 vendor parse blockerを、Production PHP Behaviorを変更せず、追跡可能なstrict Mago baselineとDeptrac 4.7.1限定更新で解消する。Review／Commit後のSHAをreplacement Final Fixed CandidateとしてP22-003 Full Gateへ返す。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/decisions/022-namespace-dependencies.md`
- `develop/decisions/140-release-quality-tooling-baseline.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`

## Diagnostic Baseline

- Source Commit: `46fea30312b68df8897d1ab0b9b39e2519b918b0`
- Release Source Parent: `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5`
- Mago: `1.42.0`; broad lint 186 issues = 14 errors／105 warnings／45 notes／22 help, 10 auto-fix suggestions
- Mago errors: 9 existing Classes; complexity／kan-defect／too-many-methods 11、excessive-parameter-list 2、no-error-control-operator 1
- Deptrac: exact `4.6.2`; stops at `0/857` before graph analysis in vendor `NikicFileReferenceVisitor.php`
- Official fixed Deptrac: `4.7.1`; parenthesized dynamic `instanceof` target
- Composer dry run with `--with-all-dependencies --minimal-changes`: exactly one lock update, `deptrac/deptrac 4.6.2 => 4.7.1`

## In Scope

- Mago 1.42.0によるcurrent broad lint strict baseline生成
- `mago.toml`へのtracked baseline／strict variant設定
- CIで通常Mago lintとbaseline同期検査を両方実行するwiring
- Version GuardでMago version、baseline path／strict variant、CI同期検査、Deptrac exact versionを固定
- `deptrac/deptrac` exact `4.7.1`へのminimal Composer／Lock更新
- PHP 8.5上のDeptrac full graph result
- Internal Quality Tooling Documentation、Decision／Specification／TODO／Report／STATE同期
- Review／Commit後SHAのreplacement Final Fixed Candidate固定

## Out of Scope

- `src/`、PHP Test、Quickstart／Community Board Runtime、Public API、Database Schemaの変更
- Mago Ruleの全体無効化、Severity downgrade、baselineへの手書きIssue追加
- 9 Classのcomplexity／parameter-count Debt refactor
- Deptrac Layer／Ruleset／allowed dependencyの変更
- Vendor file直接Patch、未追跡PHAR、PHP Version downgrade
- Branch Push、Tag、Skeleton publication、Packagist、GitHub Release、Documentation Deploy

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `mago.toml`
- `mago-lint-baseline.toml`（新規）
- `.github/workflows/ci.yml`
- `tests/Consumer/version-baseline.sh`
- `docs/internal/runtime-dependencies.md`
- `develop/decisions/140-release-quality-tooling-baseline.md`
- `develop/spec/README.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P22-003A-release-quality-tooling-blockers.md`
- `develop/orchestration/reports/P22-003A-release-quality-tooling-blockers.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`

範囲外のProduction／Test変更が必要な場合は実装を広げず、ReportへBlockerとして返す。

## Constraints

- GPT-5.6 Luna High workerがTooling／Workflow／Guard／Documentation実装を行い、Orchestrator Review前にCommitしない
- BaselineはMago 1.42.0のgenerator outputを使用し、秘密値／generated vendor path／Repository外pathを含めない
- `baseline-variant = "strict"`を使い、Issue位置／件数の変化をsilentに吸収しない
- 通常`mago lint`と`mago lint --verify-baseline`の両方を成功させる
- `mago lint --ignore-baseline`の既存DebtはReportへ件数を記録するが、成功扱いまたは新規Issueの隠蔽に使わない
- Composer更新は`deptrac/deptrac`のexact pinだけを`--minimal-changes`で更新し、他Packageの不要なLock updateを行わない
- Deptrac config／Architecture Contractを変更しない
- Replacement Candidate確定後はP22-003 complete gateを最初から再実行する
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Mago strict baselineがcurrent 186 issuesをgenerator outputとして追跡し、Mago 1.42.0で通常lintが成功する
- [ ] `mago lint --verify-baseline`が成功し、baselineの追加／削除／位置変化をCIで検出する
- [ ] CIとVersion Guardがbaseline path／strict variant／同期検査／Mago exact versionを固定する
- [ ] Deptrac exact pin／Lockが`4.7.1`へ限定更新され、他PackageのLock Versionが変わらない
- [ ] PHP 8.5でDeptracが全857 fileを解析し、Architecture violation／uncovered／warning／error 0で成功する
- [ ] Root／Quickstart Composer strict、Mago format／lint／baseline verify／analyze、Full PHPUnit、Framework export、Version Guardが成功する
- [ ] Production PHP／PHP Test／Quickstart／Community Board Sourceに差分がない
- [ ] Internal Documentation、Decision index、Specification 16／103、P22-003 Task／Report、TODO、STATEが同期する
- [ ] Documentation ReviewerがP1=0／P2=0を返し、Orchestratorがreplacement candidate commitを許可する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app composer show deptrac/deptrac --locked
docker compose run --rm app composer show carthage-software/mago --locked
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
docker compose run --rm app vendor/bin/phpunit
bash tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
! git diff --name-only -- src tests | rg '\.php$'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

Diagnostic only:

```bash
docker compose run --rm app mago lint --ignore-baseline --minimum-report-level error --reporting-format count
```

The diagnostic command is expected to remain non-zero while the recorded existing debt remains. Its count must not exceed or differ from the generated baseline without Review.

## Expected Report

`develop/orchestration/reports/P22-003A-release-quality-tooling-blockers.md`へSummary、Changed Files、Decisions and Assumptions、Composer Update Evidence、Mago Baseline Evidence、Deptrac Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
