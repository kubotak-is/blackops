# P18-009D2: GitHub Actions Toolchain Correction

Status: Accepted

## Goal

GitHub ActionsのCommunity Board ConsumerがRepository内で使用する`rg`をRunner Imageへ暗黙依存せず、Repository管理の固定Toolchainから利用できるようにする。あわせて、既に手元で補正済みのQuickstart Mago違反を含め、失敗したCI 3 Job相当を再検証してPhase 19開始前の`main`をGreenへ戻せる状態にする。

## In Scope

- `mise.toml`へのripgrep固定Version追加
- 既存`jdx/mise-action`によるCI JobへのToolchain供給確認
- GitHub Actions Run `29906792935`の失敗3 Job相当の再検証
- Report／STATE Checkpoint

## Out of Scope

- Framework Production Code／Public API／Test Contract変更
- Community Board Consumer Scriptの`rg`削除または別Commandへの書き換え
- GitHub Actions Job構成の再設計
- Phase 19 Production Code
- External Publication／Deploy
- Commit／Push／Workflow再実行

## Failure Evidence

- `Mago, PHPUnit, and Deptrac`: `examples/quickstart/app/ApplicationServiceProvider.php`の未整形`use`順序を検出。手元の`b7d8d81`で補正済み
- `Community Board full-stack product journey`: `tests/Consumer/community-board-foundation.sh: line 97: rg: command not found`
- `Community Board clean install and seed`: `tests/Consumer/community-board-clean-install.sh: line 55: rg: command not found`
- 対象Run: `https://github.com/kubotak-is/blackops/actions/runs/29906792935`

## Relevant Configuration

- `mise.toml`
- `.github/workflows/ci.yml`
- `tests/Consumer/community-board-foundation.sh`
- `tests/Consumer/community-board-clean-install.sh`

## Files Allowed to Change

- `mise.toml`
- `.github/workflows/ci.yml`（既存mise Toolchain供給で解決できない場合のみ）
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009D2-github-actions-toolchain-correction.md`
- `develop/orchestration/reports/P18-009D2-github-actions-toolchain-correction.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- ripgrepはRepository管理の固定Versionとする
- Runner Imageにpreinstallされた`rg`へ依存しない
- Consumer ScriptとProduction Codeは変更しない
- 既存`jdx/mise-action@v4`の`install: true`を再利用する

## Acceptance Criteria

- [x] `mise.toml`がripgrepを固定Versionで宣言する
- [x] `mise install`と`mise exec -- rg --version`が成功する
- [x] 既存CIのCommunity Board 2 Jobがmise ToolchainをInstallすることを確認する
- [x] `mago format --check src tests examples`が成功する
- [x] `community-board-foundation.sh`がmise Toolchain上で成功する
- [x] `community-board-clean-install.sh`がmise Toolchain上で成功する
- [x] AGENTS.md必須Mago／管理ID Guardと`git diff --check`が成功する
- [x] Production Code／Test Code／CI Job構成を変更しない
- [x] Worker Commitなし

## Required Commands

```bash
mise install
mise exec -- rg --version
mise exec -- bash tests/Consumer/community-board-foundation.sh
mise exec -- bash tests/Consumer/community-board-clean-install.sh
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P18-009D2-github-actions-toolchain-correction.md`へ次を記録する。

- Summary
- Failure Cause and Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
