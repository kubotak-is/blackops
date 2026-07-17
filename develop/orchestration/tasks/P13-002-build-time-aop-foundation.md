# P13-002: Build-time AOP Foundation

Status: Ready

## Goal

D096とPhase 13 Delivery Planに従い、Ray.AopをSymfony DIのBuild工程へ統合し、Public `#[Transactional]`／`#[AfterCommit]` Attributeを持つDI管理Serviceを事前生成Proxyへ置き換える基盤を実装する。Production Runtimeは生成済みContainerとProxyだけを読み、Source Scan、Temporary Proxy生成、Ray.Diを使用しない。

このTaskはMethod InterceptionとBuild-time Validationの土台までを扱う。実際のTransaction Scope、Commit／Rollback、After Commit QueueはP13-003で実装する。

## In Scope

- Runtime Dependency `ray/aop`の追加とLock File更新
- Public `BlackOps\Database\Attribute\Transactional`
- Public `BlackOps\Database\Attribute\AfterCommit`
- Symfony DI Service Definitionを対象とするAOP Metadata収集とBuild-time Validation
- Ray.Aop Compilerによる決定的なProxy Artifact生成
- Proxy Class／FileをSymfony DI Service Definitionへ反映したContainer Dump
- Class-level／Method-level `#[Transactional]`のBinding
- Method-level `#[AfterCommit]`のBinding
- Foundation段階のInterceptorは元Methodを一度だけ実行し、戻り値／Throwableを変更せず伝播する
- Default／Named Connection参照のBuild-time Validation
- Container管理InstanceとDirect `new` Instanceの境界Test
- Build Artifact、Public API、Attribute Reference、Internal Bootstrap Documentationの同期
- Unit／Integration／Consumer回帰Test

## Out of Scope

- Transactionの開始、Commit、Rollback、Nested Required、Rollback-only
- Manual Transaction混在Guard
- After Commit Invocation Queue、Callback実行、Failure Reporter
- Operation ManifestへのTransactional Metadata保存
- Operation成功Terminal Journal／Outcomeとの原子的Commit
- Long-running Connection Health Check／Close／Reconnect
- Quickstartの業務Repository／Transactional Command実例
- ORM、Repository基底Class、Query Builder Wrapper
- Transactional Outbox
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/17-core-api.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `deptrac.yaml`
- `src/Database/Attribute/**`
- `src/Internal/Aop/**`
- `src/Internal/Application/ApplicationBuildConfiguration.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `src/Internal/Runtime/ProductionRuntimeArtifactLoader.php`
- `src/Internal/Runtime/ProductionRuntimeArtifacts.php`
- `tests/Database/Attribute/**`
- `tests/Internal/Aop/**`
- `tests/Internal/Application/ApplicationBuildConfigurationTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/PublicApiArchitectureGuard.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Fixtures/**`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `examples/quickstart/config/app.php`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/internal/bootstrap.md`
- `develop/orchestration/reports/P13-002-build-time-aop-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportのBlockerへ記録する。

## Public Attribute Contract

### Transactional

```php
namespace BlackOps\Database\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[\BlackOps\Core\Attribute\PublicApi]
final readonly class Transactional
{
    public function __construct(public ?string $connection = null) {}
}
```

- `connection: null`はApplicationのDefault Connectionを表す
- Connection Nameを指定する場合はtrim後の非空Stringで、`config/database.php`の`connections`に存在しなければならない
- Class-level Attributeは対象ClassのIntercept可能なPublic Instance Methodへ適用する
- Method-level指定はClass-level指定を上書きできる

### AfterCommit

```php
namespace BlackOps\Database\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
#[\BlackOps\Core\Attribute\PublicApi]
final readonly class AfterCommit
{
}
```

- Public Instance Methodだけへ付与できる
- Return Typeは明示`void`でなければならない
- Static、`final`、Constructor、Destructor、Generator、Reference Return、Reference Parameterは拒否する

## Implementation Constraints

- `ray/aop`はRuntime Dependencyとして互換範囲を固定し、`ray/di`を追加しない
- Ray.Aopの公開Compiler／Binding APIを利用し、Proxy Engineを独自再実装しない
- `Aspect::newInstance()`等のRuntime Temporary Generation APIをProduction経路で使用しない
- Build対象はSymfony `ContainerBuilder`へ登録済みでClassを特定できる非Synthetic Serviceとする
- Provider登録Service、Operation Handler、Authorization Policy、HTTP MiddlewareをAOP検査対象に含める
- AttributeのないServiceはProxyへ置換しない
- Direct `new`で生成したInstanceはInterceptされず、Containerから解決したInstanceだけがProxyであることをTestする
- 対象Classは存在し、Instantiable、非`final`でなければならない。`readonly` Classは許可する
- Intercept対象MethodはPublic、非Static、非`final`のInstance Methodでなければならない
- 無効なAttribute TargetやSignatureを黙って無視せず、対象Class／Methodと理由が分かるSecret非露出のBuild Errorにする
- `Transactional`のNamed ConnectionはAccepted Database Configuration Snapshotに対して検証し、Build時にDatabaseへ接続しない
- Proxy出力DirectoryはContainer Artifact Directory配下の`aop`とし、利用者向けConfig Keyは追加しない
- Proxy File名／Class名は同じSourceと設定から再Compileした場合に安定する決定的な値とする
- Build前にBlackOpsが所有するAOP出力Directoryの古い生成物を安全に除去し、部分生成物を残さない
- Compiled ContainerはProxy Fileを明示的に読み込めるDefinition／Artifactを持ち、Production RuntimeでApplication Sourceを再Scanしない
- Foundation Interceptorは`MethodInvocation::proceed()`を一度だけ呼び、Return／Throwableをそのまま伝播する。Transaction Semanticsを先取りしない
- Proxy ArtifactとContainer ArtifactへDatabase Credential、Connection Parameter、Environment Snapshotを保存しない
- Existing Build Artifact FormatとStable 1.1 Runtimeを不要に壊さない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] `ray/aop`がRuntime Dependencyとして導入され、`ray/di`は導入されていない
- [ ] Public `Transactional`／`AfterCommit` Attributeが正しいTargetとSignatureを持つ
- [ ] Class-level／Method-level TransactionalとMethod-level AfterCommitをBuild時に発見できる
- [ ] Attribute付きDI ServiceがRay.Aopの事前生成Proxyへ置換され、Containerから解決できる
- [ ] AttributeなしServiceは元Classのままである
- [ ] Foundation InterceptorがReturn／Throwable／引数を変更せず元Methodを一度だけ実行する
- [ ] Direct `new`はInterceptされず、Container管理InstanceだけがInterceptされる境界をTestする
- [ ] `final class`、`final method`、非Public／Static Target、不正AfterCommit SignatureをBuildで拒否する
- [ ] Unknown／空Connection NameをCredential非露出でBuild時に拒否し、Default Connectionは受理する
- [ ] `readonly` ServiceのProxy生成とContainer解決が成功する
- [ ] ProxyがContainer Artifact隣接Directoryへ決定的に生成され、古いArtifactが残らない
- [ ] Production RuntimeがTemporary Proxy生成／Source ScanなしでCompiled Containerを起動できる
- [ ] Build ArtifactにCredential／Connection Parameter／Environment Snapshotを含めない
- [ ] GuideがAttributeの用途、制約、Container管理境界、P13-003で有効になる実行Semanticsを正確に説明する
- [ ] Target／Full Quality Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format src/Database/Attribute src/Internal/Aop src/Internal/Application/ApplicationBuildConfiguration.php src/Internal/Console/ApplicationBuildCompileCommand.php src/Internal/DependencyInjection tests/Database/Attribute tests/Internal/Aop tests/Internal/Application/ApplicationBuildConfigurationTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/DependencyInjection tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Architecture
docker compose run --rm app vendor/bin/phpunit tests/Database/Attribute tests/Internal/Aop tests/Internal/Application/ApplicationBuildConfigurationTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/DependencyInjection tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Architecture/PublicApiArchitectureTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'Aspect::newInstance|sys_get_temp_dir|tempnam' src/Internal/Aop src/Internal/DependencyInjection src/Internal/Console/ApplicationBuildCompileCommand.php --glob '*.php'
git diff --check
```

Directoryが実装前に存在しない場合、対応Commandの該当Pathだけを省略し、Reportへ明記する。

## Expected Report

`develop/orchestration/reports/P13-002-build-time-aop-foundation.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
