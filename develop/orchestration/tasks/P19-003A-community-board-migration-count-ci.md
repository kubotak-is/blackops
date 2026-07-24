# P19-003A: Community Board Migration Count CI Correction

Status: Accepted

## Goal

P19-003で追加したFramework MigrationをCommunity Board Fresh Install Consumerの固定Migration件数へ同期し、実行内容を広げずにGitHub ActionsのClean Install回帰を解消する。

## Evidence

- Commit `5b565c2`のCI Run `30036046158`
- Failed Job `89304167347`: `Community Board clean install and seed`
- `tests/Consumer/community-board-clean-install.sh:134`が`migrations: 6`を期待する一方、P19-003でFramework Migrationが一件追加された
- 同RunのMago／PHPUnit／Deptrac、Frontend、Documentation Websiteは成功した

## Files Allowed to Change

- `tests/Consumer/community-board-clean-install.sh`
- `develop/orchestration/tasks/P19-003A-community-board-migration-count-ci.md`
- `develop/orchestration/reports/P19-003A-community-board-migration-count-ci.md`
- `develop/STATE.md`

## In Scope

- Fresh Install ConsumerのFramework Migration件数期待値をCurrent Migration集合へ同期する
- ConsumerをFresh状態から再実行する
- Management ID GuardとDiff Guardを実行する

## Out of Scope

- Community Board Application／Frontend／Seed／Product Journeyの変更
- Framework Production Code、Migration、Schema、Retention、Idempotency Contractの変更
- Quickstart／Skeleton／Outbox／Relay／Replayの変更

## Acceptance Criteria

- [x] `community-board-clean-install.sh`がP19-003 Migrationを含むFresh Installで成功する
- [x] Community Board Application／Frontend／Seed差分がない
- [x] Framework Production Code／Migration差分がない
- [x] Management ID Guardと`git diff --check`が成功する

## Required Commands

```bash
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-003A-community-board-migration-count-ci.md`
