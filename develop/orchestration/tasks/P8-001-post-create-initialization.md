# P8-001: Post-create Initialization

Status: Ready

## Goal

Skeletonへ安全で再実行可能なProject Setup Entrypointを追加し、Composer `post-create-project-cmd` と `--no-scripts` Manual Setupが同じ処理を使って `.env` とLocal生成Directoryだけを準備する。

## In Scope

- Skeleton所有のExecutable PHP Setup Entrypoint
- Composer `post-create-project-cmd` 接続
- `.env.example` から未作成 `.env` だけをCopy
- `var/build`／`var/log` Directoryの冪等作成
- 次に実行する明示Commandの表示
- 既存 `.env`／既存File非上書き
- Network／Docker／Database／Migration／Build Side Effect不在
- Direct実行とComposer Script実行のTest
- `--no-scripts` Manual Setup導線
- Quickstart／Guide／Internals更新

## Out of Scope

- Local split artifact／create-project Smoke
- Framework Version Constraint書換
- Distribution Repository／GitHub Workflow／Push
- Packagist公開
- Secret生成
- Docker Image Build／Database Migration／Artifact Build自動実行

## Relevant Specifications and Decisions

- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/52-phase-8-delivery-plan.md`

## Files Allowed to Change

- `examples/quickstart/bin/setup`
- `examples/quickstart/composer.json`
- `examples/quickstart/README.md`
- `tests/Consumer/**`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/installed-application-status.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/mvp-e2e.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-001-post-create-initialization.md`
- `develop/orchestration/reports/P8-001-post-create-initialization.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Setup Contract

- EntrypointはProject Rootを自身の位置から解決し、Current Working Directoryへ依存しない
- `.env` がない場合だけ `.env.example` をbyte-for-byte Copyする
- `.env` が存在する場合は内容、Permission、Timestampを変更しない
- `var/build` と `var/log` がない場合は再帰的に作成する
- 同名PathがDirectoryでない場合、対象責務だけを示して非0終了する
- Copy／Directory作成に失敗した場合は安全なMessageで非0終了する
- 成功時は実行した準備と、Composer Install、Docker Build、Artifact Build、Migration、HTTP起動の次手順を表示する
- Setup自身はNetwork、Composer Install、Docker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを実行しない
- Composer ScriptとManual Setupは同じEntrypointを呼ぶ

## Acceptance Criteria

- [ ] Composer Metadataに `post-create-project-cmd` が定義される
- [ ] 初回Setupが `.env` とLocal Directoryを準備する
- [ ] 再実行して既存 `.env` を変更しない
- [ ] Project Root以外のWorking Directoryから実行できる
- [ ] Invalid Directory Path／Copy Failureが非0で安全に失敗する
- [ ] Setupが外部ProcessやRuntime Side Effectを実行しない
- [ ] Composer ScriptとDirect Manual Setupの両方が成功する
- [ ] `--no-scripts` Manual SetupがREADMEに記載される
- [ ] Composer、Focused／Full Test、Mago、Deptrac、Guardが成功する
- [ ] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-setup.sh
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

`bin/setup` は次手順を表示するため、Command名の文字列Guardは使わない。Side Effect不在はTestでEntrypointのProcess境界とFile差分を検証する。

## Expected Report

`develop/orchestration/reports/P8-001-post-create-initialization.md` に次を記録する。

- Summary
- First-run／Idempotency Evidence
- Composer Script／Manual Setup Evidence
- Side Effect Boundary Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
