# P13-001: Database Configuration and DI Foundation

Status: Ready

## Goal

D096とPhase 13 Delivery Planに従い、Canonical Named Database Configurationを導入し、ApplicationのRepository／ServiceがPublic `DatabaseManager`またはDefault Doctrine DBAL ConnectionをCompiled ContainerからConstructor Injectionできる基盤を実装する。

## In Scope

- `default`／`connections`／`framework.connection`／`framework.schema`の読込と検証
- 既存単一`connection`／`schema`形式の互換正規化
- Public `BlackOps\Database\DatabaseManager` Contract
- Internal Doctrine DBAL DatabaseManager実装
- Connectionの名前別Lazy生成、同一名のInstance再利用、Unknown Name拒否
- Framework Store ConnectionをConfigured Nameから解決
- Default `Doctrine\DBAL\Connection`とDatabaseManagerのSynthetic Service登録
- HTTP／Deferred Worker RuntimeでCompiled ContainerへRuntime Database Serviceを設定
- Migration／Retention／Framework Storeを同じConfiguration ModelとManagerへ移行
- Quickstart `config/database.php`のCanonical形式への更新
- Public API／Configuration Guideの最小同期
- Unit／Integration／Consumer回帰Test

## Out of Scope

- `ray/aop` Dependency
- `#[Transactional]`／`#[AfterCommit]`
- Proxy生成またはMethod Interception
- Transaction Scope、Nested／Rollback-only、Manual Transaction Guard
- Operation Terminal Journal／OutcomeとのTransaction統合
- Named Connection全体のRequest／Attempt Health CheckとReconnect
- ORM、Repository基底Class、Query Builder Wrapper
- Transactional Outbox
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `deptrac.yaml`
- `src/Database/**`
- `src/Internal/Database/**`
- `src/Internal/Application/ApplicationDatabaseConfiguration.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationRetentionRuntime.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Runtime/ProductionRuntimeArtifacts.php`
- `src/Internal/Runtime/ProductionRuntimeArtifactLoader.php`
- `tests/Database/**`
- `tests/Internal/Database/**`
- `tests/Internal/Application/ApplicationDatabaseConfigurationTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/ApplicationConsoleKernelTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/PublicApiArchitectureGuard.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `examples/quickstart/config/database.php`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/internal/bootstrap.md`
- `develop/orchestration/reports/P13-001-database-configuration-and-di-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportのBlockerへ記録する。

## Implementation Constraints

- `BlackOps\Database\DatabaseManager`は`#[PublicApi]`を持つInterfaceとし、`connection(?string $name = null): Doctrine\DBAL\Connection`だけを公開する
- Default Connection名はtrim後の非空String、Connection Mapは一件以上のString Key Mapとする
- Named Connection ParameterはString Key Mapとし、値をSecret非露出でDoctrine DBALへ渡す
- `framework.connection`は存在するNamed Connectionを参照し、`framework.schema`は安全なPostgreSQL Identifierとする
- Legacy形式は内部で一つのNamed Connectionへ正規化し、既存Quickstart／Stable 1.1 Runtimeを壊さない
- DatabaseManagerはConnectionを未使用時に生成せず、同じNameへ同じInstanceを返す
- Unknown Connection Error、Config Error、Container ErrorへDSN、Password、User、Host等の値を含めない
- Framework StoreとApplication Defaultが同じConnection Nameなら同一Connection Instanceを共有する
- Build ArtifactはSynthetic Service Definitionだけを含み、Credential、Connection Parameter、Live Connectionを含めない
- Runtime Service設定にはSymfony DI内部型を使ってよいが、Public APIはPSR-11／DatabaseManager／DBAL Connection以外へ拡張しない
- Build CommandはDatabaseへ接続せずにContainer Artifactを生成できる
- HTTPとDeferred WorkerはHandler／Policyを初回解決する前にRuntime Database ServiceをContainerへ設定する
- MigrationとRetentionは`framework.connection`を使用する
- Connection Health Check／Close／Reconnectの一般化はP13-005へ残し、このTaskで既存Framework Connection Lifecycleを弱めない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Canonical Named形式がDefault、Connection Map、Framework Connection、Schemaへ正規化される
- [ ] Legacy形式が一つのDefault／Framework Connectionとして互換動作する
- [ ] 不正Name、空Map、Unknown Default／Framework参照、不正Parameter Key／SchemaをSecret非露出で拒否する
- [ ] DatabaseManagerがDefault／Named ConnectionをLazy生成・再利用し、Unknown Nameを拒否する
- [ ] Default DBAL ConnectionとDatabaseManagerをApplication ServiceへConstructor Injectionできる
- [ ] Provider登録済みServiceを上書きせず、Database Runtime Serviceの不正な再定義をBuildで拒否する
- [ ] Build ArtifactにCredential／Connection Parameterが含まれず、Build時にDatabaseへ接続しない
- [ ] HTTPとDeferred WorkerがFramework StoreとApplication DIへ正しいConnection Instanceを使う
- [ ] Migration／Retentionが`framework.connection`を使用する
- [ ] Quickstart ConfigとGuideがCanonical形式、Legacy互換、Default／Named DIを説明する
- [ ] Target／Full Quality Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src/Database src/Internal/Database src/Internal/Application src/Internal/Console/ApplicationBuildCompileCommand.php src/Internal/DependencyInjection/RuntimeContainerCompiler.php src/Internal/Runtime tests/Database tests/Internal/Database tests/Internal/Application tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php
docker compose run --rm app vendor/bin/phpunit tests/Database tests/Internal/Database tests/Internal/Application/ApplicationDatabaseConfigurationTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/PublicApiArchitectureTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Directoryが実装前に存在しない場合、対応Commandの該当Pathだけを省略し、Reportへ明記する。

## Expected Report

`develop/orchestration/reports/P13-001-database-configuration-and-di-foundation.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
