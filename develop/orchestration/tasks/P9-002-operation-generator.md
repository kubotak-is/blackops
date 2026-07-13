# P9-002: Operation Generator

Status: Accepted

## Goal

Installed Applicationの`bin/blackops`から、Build可能なTyped Self-handled Operation／Value／Outcomeを安全に生成する`make:operation`を提供する。

## In Scope

- `make:operation <Feature>/<Action> --type=<operation.type>`
- Framework Package所有の3つのOperation Stub
- Feature／Action／Operation Type検証
- Application Base Path配下への安全なPath解決
- 3 Fileの事前衝突検証、Temporary Write、Rollback
- Console Kernel／Factory登録とFramework Command名予約
- Unit／Application Console／Build統合Test
- Generator Guide／Internals更新
- Report／STATE更新

## Out of Scope

- Route／HTTP Method／Deferred Option生成
- Interactive Promptと`--force`
- Migration Generator／Application Migration Runtime
- Skeleton所有Stub
- Framework Release／Publication

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/074-typed-self-handled-operation-signature.md`
- `develop/decisions/075-native-outcome-and-rejection-exception.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/080-project-generator-command-contract.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`

## Files Allowed to Change

- `resources/stubs/operation.php.stub`
- `resources/stubs/operation-value.php.stub`
- `resources/stubs/operation-outcome.php.stub`
- `src/Internal/Console/MakeOperationCommand.php`
- `src/Internal/Generator/OperationGenerator.php`
- `src/Internal/Generator/OperationGeneratorInput.php`
- `src/Internal/Generator/ProjectFileWriter.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `tests/Internal/Console/MakeOperationCommandTest.php`
- `tests/Internal/Generator/OperationGeneratorTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/README.md`
- `docs/guide/project-generators.md`
- `docs/internals/README.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/project-generators.md`
- `examples/quickstart/README.md`
- `develop/orchestration/reports/P9-002-operation-generator.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- Userは2026-07-13の「進めて」により、Model／Profileを明示できない現在のWorkerでこのTaskを進めることを承認した
- WorkerはReview前にCommitしない
- `make:operation`はDB、Build、Composer、NetworkへSide Effectを起こさない
- StubはFramework Packageにだけ置き、Quickstartへ複製しない
- `#[Accepts]`、`#[Returns]`、Generic DocBlock、Narrowing Guard、`OperationResult`を生成しない
- 入力不正または衝突時はTargetを一つも変更しない
- ErrorへFramework Stub Absolute Pathを公開しない
- PHP Comment／DocBlockへ管理番号を書かない

## Acceptance Criteria

- [ ] `make:operation Welcome/ShowWelcome --type=welcome.show`が仕様どおり3 Fileを生成する
- [ ] 生成ClassがTyped Self-handled Contractを満たしApplication Buildに成功する
- [ ] Route／Deferred／Context／Legacy Metadataを生成しない
- [ ] 不正Segment／Type／Traversal／追加Segmentを拒否する
- [ ] Targetの一つでも存在する場合は全Target不変で失敗する
- [ ] Write失敗時に今回の部分生成を残さない
- [ ] `list`／`help`がSource ScanやRuntime設定なしで成功する
- [ ] Application Commandが`make:operation`を上書きできない
- [ ] Guide／Internals／Quickstart READMEがCommand Contractと一致する

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Generator tests/Internal/Console/MakeOperationCommandTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'Accepts|Returns|OperationResult|@implements' resources/stubs
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P9-002-operation-generator.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
