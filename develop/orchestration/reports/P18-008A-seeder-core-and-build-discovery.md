# P18-008A Seeder Core and Build Discovery Report

## Summary

Public `BlackOps\Database\Seeder`／`SeederRunner` Contract、標準／明示Seeder Configuration、Build-time Discovery、Compiled Container Locator、Root Runtimeを実装した。

Application-aware `build:compile`はSeeder Constructorを実行せずに検出し、SeederをPrivate Autowired Serviceへ登録する。Framework内部の`CompiledSeederRuntime`だけをContainerのRuntime解決境界とし、Private `SeederRunner`とSymfony Compiled Service Locatorを通じてRoot／Child Seederを同じContainer Instanceから実行する。

Orchestrator Reviewを受け、Seederが検出されたBuildでは独立Container Copyを事前Compileする。Seeder Constructor Dependencyが未解決ならAOP／Container／Operation／HTTP／Frontend／Commandの既存Artifactを置換する前に停止する。事前Compileは元のContainerを消費しないため、検証成功後の正式Compile／Dumpへ同じ定義を渡せる。

RunnerはArgument順、Nested Composition、Empty、Sequential Repeat、Unknown Class、Invalid Locator Entry、Cycle、Child Exception Stopを固定した。Runtime Source Scan、Runtime Reflection、動的な`new $class()`、Operation／Journal／Outcome、暗黙Transactionは追加していない。

## Changed Files

### Public API

- `src/Database/Seeder.php`
- `src/Database/SeederRunner.php`

### Configuration／Discovery／Runtime

- `src/Internal/Application/ApplicationSeederConfiguration.php`
- `src/Internal/Application/ApplicationSeederDiscovery.php`
- `src/Internal/Application/DiscoveredApplicationSeeders.php`
- `src/Internal/Discovery/SeederSourceDiscovery.php`
- `src/Internal/Seeder/CompiledSeederRunner.php`
- `src/Internal/Seeder/CompiledSeederRuntime.php`
- `src/Internal/Seeder/SeederRuntimeException.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/DependencyInjection/RuntimeContainerPreflightCompiler.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`

### Tests／Fixtures

- `tests/Database/SeederTest.php`
- `tests/Internal/Application/ApplicationSeederBuildIntegrationTest.php`
- `tests/Internal/Application/ApplicationSeederConfigurationTest.php`
- `tests/Internal/Application/ApplicationSeederDiscoveryTest.php`
- `tests/Internal/Application/Fixture/SeederDiscovery/FixtureSeeders.php`
- `tests/Internal/DependencyInjection/SeederContainerCompilerTest.php`
- `tests/Internal/Discovery/SeederSourceDiscoveryTest.php`
- `tests/Internal/Seeder/CompiledSeederRunnerTest.php`

### Specification／Management

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/17-core-api.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P18-008A-seeder-core-and-build-discovery.md`

## Decisions and Assumptions

- PHP Public APIの追加は`Seeder`と`SeederRunner`の2 Interfaceだけとした。Runner実装、Root Runtime、Failure型はInternalである。
- Seeder用Manifestは追加せず、検出済みClass、Locator、Rootの有無を既存Freshness Contract対象のCompiled Containerへ固定した。
- Seeder ClassとRunner AliasはPrivate Serviceとし、後続Console実装が解決するFramework内部`CompiledSeederRuntime`だけをPublic Container Serviceとした。
- Application Service ProviderがSeeder Classを明示定義している場合は既存定義を尊重する。Framework-owned Runner／Runtimeの再定義は拒否する。
- RunnerはApplication ThrowableをPrevious Exceptionとして保持せず、固定Safe Messageへ変換する。Nested Runner由来の固定Failureだけはそのまま伝播する。
- 標準Directoryまたは標準RootのMissingは未構成としてBuildを維持する。明示Discovery／Rootの不正はBuild Failureにする。
- Seederが一件以上検出された場合だけ、Definition／Alias／Parameterを複製した独立Containerを事前Compileする。Service Provider再登録やSeederなしApplicationへの追加Compileは行わない。
- Operation／HTTP／Frontend ManifestはContainer Validationと正式Dumpの成功後に書き込む。未解決Seeder Constructor DependencyのRegressionでは全既存Build ArtifactのSentinel Byteが不変であることを固定した。
- Console／Generator、Quickstart／Skeleton／Community Board、公開Guide／WebsiteはTask Scope外のため変更していない。

## Final Public API and Architecture Boundary

```php
namespace BlackOps\Database;

#[PublicApi]
interface Seeder
{
    public function run(): void;
}

#[PublicApi]
interface SeederRunner
{
    /** @param class-string<Seeder> ...$seeders */
    public function run(string ...$seeders): void;
}
```

`Database -> Core, Library`をNamespace Contractへ明記した。Public Signatureに`BlackOps\Internal`はなく、Full Public API Architecture TestとDeptracが成功した。

## Default／Explicit Configuration Matrix

| Configuration | Build Result | Compiled Root |
| --- | --- | --- |
| 標準Directory Missing | Success | `null`／未構成 |
| 標準Directoryあり、標準Root Missing | Childだけを検出してSuccess | `null`／未構成 |
| 標準Rootが有効なSeeder | Success | 標準Root |
| `root`だけ明示 | 標準Discoveryを使用 | 明示Rootが検出済みなら有効 |
| `discovery`だけ明示 | 標準Rootを使用 | 標準Rootが検出済みなら有効 |
| 明示DiscoveryがEmpty／Missing／Relative／Root外／Symlink Escape | Safe Failure | 既存Containerを生成しない |
| 明示RootがInvalid Class Name／Missing／Non-Seeder／Discovery外 | Safe Failure | 既存Containerを生成しない |
| Seeder Constructor Dependency未解決 | Container Compile Failure | Containerを生成しない |

## Discovery／Compiled Container／Locator Evidence

- Permanent FixtureはRoot／2 Child／Dependency／Abstract Seeder／Non-Seederを同じDiscovery Rootへ配置した。
- Discovery結果はInstantiableな3 Seederだけで、Root／Child Constructor CountはBuild後も0だった。
- Compiled Containerの`has(RootSeeder::class)`は`false`で、Root／ChildがPrivate Serviceであることを確認した。
- `CompiledSeederRuntime::run()`後にRoot Constructor 1回、Child Constructor合計2回、Dependency値を使った決定的Event順を確認した。
- 未解決Constructor Interfaceを持つSeederはContainer Compileで失敗した。
- 未解決Constructor InterfaceによるBuild Failure時、Operation／HTTP／Frontend／Command Manifest、Container、AOP Artifactの6 Sentinelがすべて不変であることを確認した。

## Ordered／Nested／Unknown／Cycle／Failure Matrix

| Case | Result |
| --- | --- |
| Ordered | Runner Argument順で実行 |
| Nested | 同じRunner／LocatorでChildを順次実行 |
| Empty | No-op Success |
| Sequential Repeat | Active Stack cleanup後の再実行を許可 |
| Unknown | Locator Lookup前に固定Safe Failure |
| Invalid Locator Entry | `Seeder`でないServiceを固定Safe Failure |
| Cycle | 同一Seeder Logicの再入前に固定Safe Failure |
| Child Exception | Raw Message／Previousなしの固定Failure、後続Seederは未実行 |
| Missing Root | Compiled Runtimeは未構成、他Application BuildはSuccess |

## Runtime Scan／Reflection Absence Evidence

- Runtime側`src/Internal/Seeder/**`とPublic `src/Database/Seeder*.php`にReflection、Directory Iterator、`glob()`、`scandir()`、動的Constructionはない。
- Source探索と`ReflectionClass`は`ApplicationBuildCompileCommand`から呼ばれる`SeederSourceDiscovery`だけに限定した。
- Compiled Runnerは注入済みPSR-11 Service Locatorの`has()`／`get()`だけを利用する。

## Commands and Results

- Focused Seeder API／Configuration／Discovery／Runner／Build Integration: PASS、15 tests／70 assertions
- Focused unresolved Constructor Dependency: PASS、1 test／1 assertion
- Orchestrator Review Regression／Container Focused: PASS、21 tests／52 assertions
- `docker compose run --rm app vendor/bin/phpunit`: PASS、1,679 tests／6,751 assertions
- `docker compose run --rm app mago format --check src tests`: PASS
- `docker compose run --rm app mago lint`: PASS、No issues
- `docker compose run --rm app mago analyze`: PASS、No issues
- Changed-file `mago lint`: PASS、No issues
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`: PASS、0 violations／2,820 allowed
- Management ID Guard: PASS
- Runtime Scan／Reflection Guard: PASS
- `git diff --check`: PASS
- Worker Commit: 未実行

Task Packetに当初literalで記載された`mago lint src tests`はFAILし、Task外の既存Tests全体から133 errors／1,252 warnings／40 helpを報告した。Repository設定の`[source].paths`を無視してTestsをLint対象へ追加する呼出しであり、変更File限定LintとRepository標準`mago lint`は成功している。

同じく当初literalの`mago analyze src tests`はFAILし、PHPUnit／Test TraitをIncludeしない明示Path解析により既存Tests全体から335 errors等を報告した。Repository標準`mago analyze`は成功し、Production Sourceの解析Errorはない。Task外のTests-wide Mago Baselineは変更していない。Orchestrator ReviewでP18-008A／B／CのRequired CommandをRepository標準`mago lint`／`mago analyze`へ修正した。

## Acceptance Criteria

- [x] Public APIは`Seeder`と`SeederRunner`の2 Interfaceだけを追加した
- [x] Standard／Explicit RootとDiscoveryを検証した
- [x] Constructorを実行せずBuild-time Discoveryした
- [x] 検出済みSeederをCompiled ContainerからConstructor DIで解決した
- [x] RunnerのOrdered／Nested／Empty／Unknown／Cycle／Exception Stopを固定した
- [x] Missing Standard SeederのExisting Application Build／HTTP／Console回帰をFull Suiteで確認した
- [x] Invalid Seeder Constructor Dependencyによる失敗で既存Build Artifactを一つも置換しない
- [x] Runtime Scan／Reflection Fallback／Dynamic Constructionを追加していない
- [x] Full PHPUnit、Repository標準Mago、Deptrac、Public API／Management ID／diff Guardが成功した
- [x] Console／Generator／Example／外部Publication差分なし、Worker Commitなし
- [x] Task PacketのMago CommandをRepository設定と一致させ、標準Lint／Analyzeが成功した

## Remaining Issues

- `database:seed`／`make:seeder`はP18-008B Scopeであり未実装である。
- Quickstart／Skeleton／Community Board移行と公開DocumentationはP18-008C Scopeであり未変更である。
- Active Blockerはない。

## Suggested Next Action

P18-008B Seeder Console and Generatorへ進む。

## Orchestrator Review

- Public APIを`Seeder`／`SeederRunner`の2 Interfaceへ限定していることを確認した。
- Configuration、Build-only Discovery、Private Locator、Root Runtime、Ordered／Nested／Cycle／Safe Failure境界を確認した。
- 初回Reviewで、未解決Seeder DependencyがContainer Compileで失敗する前に一部Manifestを置換する順序不整合を検出した。
- Worker修正後、Seeder検出時だけ独立Container CopyをPreflight Compileし、6種の既存Artifactを維持するRegressionを確認した。
- Orchestrator Focused Testは17 tests／78 assertions、Full PHPUnitは1,679 tests／6,751 assertionsで成功した。
- Orchestrator Mago Format／Lint／Analyze、Deptrac 0 violations／2,820 allowed、Composer Strict、Management ID／Runtime Scan／diff Guardは成功した。
- Task PacketのTests-wide Mago引数はRepository設定と不整合だったため、P18-008A／B／Cを標準Commandへ修正した。
- P18-008AをAcceptedとする。
