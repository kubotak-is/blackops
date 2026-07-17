# P13-002: Build-time AOP Foundation Report

Status: Accepted

## Summary

- Runtime Dependencyとして`ray/aop ^2.19`を追加し、Lockでは2.20.0を導入した。`ray/di`は導入していない。
- Public `BlackOps\Database\Attribute\Transactional`と`AfterCommit`を追加した。
- Application-aware `build:compile`で登録済みSymfony Service Definitionを検査し、Ray.Aop CompilerでContainer Artifact隣接の`aop/`へProxyを事前生成するようにした。
- Provider Service、Operation Handler、Authorization Policy、HTTP Middlewareを含む非Synthetic Definitionを同じ検査対象にした。
- Class／Method Attribute、Default／Named Connection、`final`／Visibility／Static、AfterCommit Signature、Unsupported Attribute TargetをBuild時に検証するようにした。
- Compiled ContainerへProxy Classとprivate Foundation Interceptor Bindingを反映し、Container Artifact自身が生成Proxy Fileを明示的に読み込むようにした。
- Foundation Interceptorは引数、Return、Throwableを変更せず、元Methodを一度だけ実行する。Transaction／After Commit実行Semanticsは実装していない。
- AOP Artifact DirectoryをBuild前に消去し、Validation／Compile／Dump失敗時も部分生成物を消去するようにした。
- Container管理InstanceだけがProxyとなり、Direct `new`は元Classのままである境界、`readonly` Service、決定的File名、Credential非露出をTestした。

## Changed Files

- Dependency／Quality Configuration:
  - `composer.json`
  - `composer.lock`
  - `deptrac.yaml`
  - `mago.toml`
- Public Attributes:
  - `src/Database/Attribute/Transactional.php`
  - `src/Database/Attribute/AfterCommit.php`
- Build-time AOP:
  - `src/Internal/Aop/**`
  - `src/Internal/Console/ApplicationBuildCompileCommand.php`
  - `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- Tests／Fixtures:
  - `tests/Database/Attribute/**`
  - `tests/Internal/Aop/**`
  - `tests/Fixtures/Aop/**`
  - `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
  - `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- Documentation:
  - `docs/guide/attributes.md`
  - `docs/guide/configuration.md`
  - `docs/guide/core-api.md`
  - `docs/internal/bootstrap.md`
- Orchestration:
  - `develop/orchestration/tasks/P13-002-build-time-aop-foundation.md`
  - `develop/orchestration/reports/P13-002-build-time-aop-foundation.md`
  - `develop/STATE.md`

## Decisions and Assumptions

- Proxy出力Pathは利用者向けConfigを増やさず、`dirname(build.container) . '/aop'`から導出した。
- Ray.Aopの`Bind::bindInterceptors()`と`Compiler::compile()`を使用し、独自Proxy Engineと`Aspect::newInstance()`を使用していない。
- Ray.AopのInterceptor ObjectはSymfony PhpDumperへ直接埋め込めないため、private Serviceとして登録し、Proxyの`_setBindings()`へSymfony `Reference`を渡す方式にした。
- Container dumpへProxyの相対`require_once`を追加し、Production起動時にTemporary Proxy生成またはApplication Source Discoveryを必要としないようにした。
- AttributeなしのBuild-only経路は従来どおりDatabase ConfigなしでCompileできる。`Transactional`を検出した場合だけDatabase Configを必須にし、Named ConnectionをAccepted Snapshotと照合する。
- `mago analyze`が新規Runtime Dependencyの型を解決するため、Orchestrator承認後にTask Packetの許可Fileへ`mago.toml`を追加し、`vendor/ray/aop/src`だけを`source.includes`へ追加した。
- Source／Binding／Artifact Directoryが同じ再CompileではRay.Aopの生成Class／File名が一致することをTestした。

## Commands and Results

```text
docker compose run --rm app composer update ray/aop --with-all-dependencies --no-interaction
Result: ray/aop 2.20.0をLock／Install。ray/diは未導入。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app mago format <P13-002 required paths>
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit <P13-002 target tests> --display-deprecations
Result: OK (58 tests, 315 assertions)。
Task Packet記載の`tests/Internal/Application/ApplicationBuildConfigurationTest.php`はRepositoryに存在しないためTarget Pathから省略した。Application Build Configurationの既存Testは`tests/Internal/Application/ApplicationHttpConfigurationTest.php`にあり、Full Suiteで実行した。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1038 tests, 3535 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1894 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed。

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton通常／no-scripts Create-project smoke passed。

! docker compose run --rm app composer show ray/di --no-interaction
Result: Package ray/di not found。未導入Guard成功。

Public API Count Guard
Result: 131型でdocs/guide/core-api.mdと一致。

Management ID Guard
Runtime Temporary Proxy API Guard
git diff --check
Result: すべて成功。
```

初回Full PHPUnitでは、AOP Connection検証のためDatabase Configを無条件読込したことにより、Databaseを必要としない既存Build-only Fixture 4件が失敗した。AttributeなしBuildではDatabase Configを要求せず、`Transactional`検出時だけ要求する遅延検証へ修正した。該当回帰28 tests／229 assertionsと最終Full Suiteで成功を確認した。

## Acceptance Criteria

- [x] `ray/aop`をRuntime Dependencyとして導入し、`ray/di`を導入していない
- [x] Public `Transactional`／`AfterCommit` Attributeが正しいTargetとSignatureを持つ
- [x] Class-level／Method-level TransactionalとMethod-level AfterCommitをBuild時に発見できる
- [x] Attribute付きDI ServiceをRay.Aopの事前生成Proxyへ置換し、Containerから解決できる
- [x] AttributeなしServiceは元Classのままである
- [x] Foundation InterceptorがReturn／Throwable／引数を変更せず元Methodを一度だけ実行する
- [x] Direct `new`はInterceptせず、Container管理InstanceだけをInterceptする
- [x] `final class`、`final method`、非Public／Static Target、不正AfterCommit SignatureをBuildで拒否する
- [x] Unknown／空Connection NameをCredential非露出でBuild時に拒否し、Default Connectionを受理する
- [x] `readonly` ServiceのProxy生成とContainer解決が成功する
- [x] ProxyをContainer Artifact隣接Directoryへ決定的に生成し、古いArtifactを残さない
- [x] Production ContainerがTemporary Proxy生成／Source Scanなしで生成Proxyを明示読込できる
- [x] Container／Proxy ArtifactへDatabase Credential、Connection Parameter、Environment Snapshotを保存しない
- [x] GuideへAttribute用途、制約、Container管理境界、次Taskで有効になる実行Semanticsを同期した
- [x] Target／Full Quality Commandsが成功した
- [x] Report／STATEを更新し、CommitせずReviewへ返す

## Remaining Issues

- Transaction Begin／Commit／Rollback、Nested Required、Rollback-only、Manual Transaction Guardは未実装であり、次Taskの範囲である。
- After Commit Queue、Commit後Callback実行、Failure Reporterは未実装であり、次Taskの範囲である。
- Operation Transaction LifecycleとTerminal Journal／Outcomeの原子的Commitは後続Taskの範囲である。
- Documentation Websiteは意図的に未公開のままである。

## Suggested Next Action

Orchestratorが差分とTarget／Full Gateを独立Reviewし、Accepted後にP13-003 Generic Transaction and After Commit Scopeへ進む。

## Orchestrator Review

2026-07-18T04:45:47+09:00にOrchestratorがTask許可範囲、公開Attribute、Ray.Aop Binding／Proxy生成、Symfony Definition／Container Dump統合、Artifact Cleanup、Database Connection Name検証、DocumentationをReviewした。Blocking findingはない。

Target PHPUnit 58 tests／315 assertions、Full PHPUnit 1038 tests／3535 assertions、Composer Root／Quickstart Validation、Mago Format／Lint／Analyze、Deptrac 0、Quickstart E2E、Skeleton通常／no-scripts Create-projectを独立再実行して成功した。Public API 131型、`ray/di`非導入、Runtime Temporary Proxy API、Management ID、`git diff --check`の各Guardも成功したため、P13-002をAcceptedとする。
