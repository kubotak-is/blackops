# P18-009D3: Community Board Command Discovery Clean-checkout Correction

Status: Accepted

## Goal

Community Boardから最後のApplication-owned Symfony Commandを削除した後も残っている`app.command_discovery`設定を整理し、Fresh Checkout／Clean Installで`build:compile`が存在しないDiscovery Rootを走査して失敗しないようにする。GitHub Actions Run `30004535286`で残ったCommunity Board 2 JobをGreenへ戻せる状態にする。

## In Scope

- `examples/community-board/config/app.php`の不要なCommand Discovery設定削除
- Fresh Checkout相当のCommunity Board Browser／Clean Install再検証
- 必要最小限のRegression TestまたはConsumer Guard
- Report／STATE Checkpoint

## Out of Scope

- FrameworkのCommand Discovery Contract変更
- 存在しないDiscovery Rootを黙って許容するFallback
- Community BoardへのApplication-owned Symfony Command再導入
- Public API／Phase 19 Contract変更
- External Publication／Deploy
- Commit／Push／Workflow再実行

## Failure Evidence

- GitHub Actions Run `30004535286`ではripgrep 15.2.0のInstallとMago／PHPUnit／Deptracが成功した。
- `Community Board full-stack product journey`は`community-board-browser.sh:62`の`build:compile`で`Application command discovery failed.`となった。
- `Community Board clean install and seed`は`community-board-clean-install.sh:119`の`build:compile`で同じ失敗となった。
- `examples/community-board/config/app.php`は`app/Console`をDiscovery Rootとして残しているが、最後のFile `CommunityBoardSeedCommand.php`は`b7d8d81`でFramework-owned Seederへ移行して削除済みである。
- 確定仕様では`app.command_discovery`の欠落または空ListはApplication Commandを自動Discoveryしない。

## Relevant Specification

- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/orchestration/reports/P18-008C-seeder-consumer-adoption-and-closeout.md`
- `develop/orchestration/reports/P18-009D2-github-actions-toolchain-correction.md`

## Files Allowed to Change

- `examples/community-board/config/app.php`
- `tests/Consumer/community-board-browser.sh`
- `tests/Consumer/community-board-clean-install.sh`
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009D3-community-board-command-discovery-clean-checkout.md`
- `develop/orchestration/reports/P18-009D3-community-board-command-discovery-clean-checkout.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Source of Truthどおり、Application CommandがないConsumerは`app.command_discovery`を省略する。
- Frameworkの存在しないRootに対するFail-fast Contractを弱めない。
- Consumer Scriptの本来のJourneyとSecurity Guardを維持する。
- Production／TestのCommentへTask、Spec、Decision管理番号を書かない。

## Acceptance Criteria

- [x] Community Board設定が存在しない`app/Console`をDiscovery Rootとして参照しない
- [x] `build:compile`がFresh Checkout／Clean Install相当で成功する
- [x] `community-board-browser.sh`が成功する
- [x] `community-board-clean-install.sh`が成功する
- [x] Mago Format／Lint／Analyzeと対象PHPUnitが成功する
- [x] AGENTS.md必須Management ID Guardと`git diff --check`が成功する
- [x] Framework Command Discovery Contract／Public API／Phase 19 Contractを変更しない
- [x] Worker Commitなし

## Required Commands

```bash
mise exec -- bash tests/Consumer/community-board-browser.sh
mise exec -- bash tests/Consumer/community-board-clean-install.sh
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationCommandDiscoveryTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P18-009D3-community-board-command-discovery-clean-checkout.md`へ次を記録する。

- Summary
- Failure Cause and Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
