# P18-008B: Seeder Console and Generator

Status: Planned

## Goal

Framework-owned `database:seed`と`make:seeder`をProject Consoleへ追加する。Seeder実行をSafe Output／Exit Code／Lazy Dependency境界で公開し、Root／Nested Seeder SourceをFramework-owned Stubから安全に生成する。

## In Scope

- Built-in `database:seed` CommandとFramework Console Kernel登録
- Fresh Compiled Container／Root／Locator検証と一回実行
- Success／Unconfigured／Artifact／Resolution／Seeder FailureのSafe Surface
- VerbosityによるApplication Throwable Detail非表示
- Built-in `make:seeder <Name>`、Nested Name、Framework-owned Stub
- Input、Traversal、Symlink、Collision、Atomic Write Safety
- Command予約／Collision、List／Help Lazy Boundary
- Framework Update Consumer
- Specification、Report、STATE同期

## Out of Scope

- Seeder Public API／Build Discovery Contractの変更
- Quickstart／Skeleton／Community Board Source
- `--class`、`--force`、`--pretend`、Interactive Prompt
- Migration／Buildの暗黙実行
- Transaction、Seed Result／Output API、Operation／Journal統合
- 外部Publication／Deploy

## Relevant Specifications

- `develop/decisions/113-database-seeder-contract.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/76-database-seeding.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-008A-seeder-core-and-build-discovery.md`

## Files Allowed to Change

- Seeder Command／Factory登録に必要な`src/Internal/Console/**`、`src/Internal/Application/**`の最小差分
- Seeder Generatorに必要な`src/Internal/Generator/**`
- `resources/stubs/seeder.php.stub`
- 対応する`tests/**`とConsumer Fixture／Script
- `develop/spec/48-public-console-kernel-composition.md`、`develop/spec/55-project-generators-and-application-migrations.md`、`develop/spec/76-database-seeding.md`、`develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-008B-seeder-console-and-generator.md`

`examples/quickstart/**`、`examples/community-board/**`、`docs/guide/**`、`docs/website/**`は変更禁止とする。許可外変更が必要な場合は実装を広げずReportへ記録する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- `list`／`help`でContainer、Database、Seed Sourceを解決しない
- `-vvv`でもApplication Throwable Message／Trace、SQL、Seed Valueを表示しない
- GeneratorはConfigurationを自動編集せず、既存Sourceを上書きしない

## Acceptance Criteria

- [ ] `database:seed`がFramework Built-inとして常時一覧に現れる
- [ ] Fresh ContainerのRootを一度実行し、固定成功Message／Exit 0を返す
- [ ] Unconfigured／Artifact／Resolution／Seeder FailureがSafe Message／Exit 1になる
- [ ] Migration／Build／HTTP／Workerを暗黙実行しない
- [ ] `make:seeder`がRoot／Nested Classを正しいPath／Namespaceへ生成する
- [ ] Invalid Input、Traversal、Symlink、Collision、Write Failureで既存Fileを変更しない
- [ ] Framework Update後もProject Entrypoint不変で新Command／Stubを利用できる
- [ ] Existing Command Discovery／Operation Console／Migration／Generatorが回帰しない
- [ ] Full PHPUnit、Mago、Deptrac、Consumer／Guardが成功する
- [ ] Example／公開Documentation／外部Publication差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P18-008B-seeder-console-and-generator.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Command List／Help／Lazy Dependency Evidence
- Success／Failure／Verbosity／Exit Code Matrix
- Generator Input／Path／Atomic Safety Matrix
- Framework Update Consumer Evidence
- Commandsと実結果、未実行理由、Remaining Issue
