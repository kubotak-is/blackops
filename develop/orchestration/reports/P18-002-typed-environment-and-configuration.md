# P18-002: Typed Environment and Configuration Closure Report

## Summary

Public Readonly `BlackOps\Application\Environment`を追加し、Process／Dotenvから渡された文字列Mapを一度検証したうえで、Configuration Closureから型付きAccessorで参照できるようにした。Configuration Directoryは`withConfiguration()`で即時検証する一方、File読込とClosure評価は`Application::create()`まで遅延する。これにより`withEnvironment()`との呼出順に依存せず、各`create()`で一つのEnvironment Snapshotを全Configuration Fileへ渡す。

QuickstartとCommunity Boardの`app.php`、`database.php`、`execution.php`、`retention.php`を型付きClosureへ移行した。EnvironmentそのものはApplication Snapshot、Compiled Container、Manifestへ保存せず、検証済みConfiguration値だけを既存Registrationへ渡す。外部Publication／DeployとCommitは行っていない。

## Changed Files

- Public／Internal Runtime: `src/Application/Environment.php`、`src/Application/ApplicationBuilder.php`、`src/Internal/Application/ApplicationConfigurationLoader.php`、`src/Internal/Application/ApplicationConfigurationSnapshot.php`
- Tests: `tests/Application/EnvironmentTest.php`、`tests/Application/ApplicationTest.php`、`tests/Internal/Application/ApplicationBuilderTest.php`、`tests/Internal/Application/ApplicationConfigurationLoaderTest.php`、`tests/Internal/Application/ApplicationConfigurationTest.php`、`tests/Internal/Application/ApplicationRegistrationTest.php`、`tests/Internal/Console/ApplicationBuildCompileCommandTest.php`、`tests/Architecture/QuickstartApplicationArchitectureTest.php`
- Installed Consumers: `examples/quickstart/config/{app,database,execution,retention}.php`、`examples/community-board/config/{app,database,execution,retention}.php`、`tests/Consumer/community-board-foundation.sh`
- Documentation／Specification: `docs/guide/configuration.md`、`docs/guide/core-api.md`、`docs/internal/application-bootstrap.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/47-public-http-runtime-configuration.md`
- Orchestration: `develop/orchestration/tasks/P18-002-typed-environment-and-configuration.md`、`develop/orchestration/reports/P18-002-typed-environment-and-configuration.md`、`develop/TODO.md`、`develop/STATE.md`

`ApplicationBuildCompileCommandTest.php`の削除済みSnapshot Constructor引数除去と、Community Board FoundationのobsoleteなQuickstart zero-diff Guardを開始／終了Status Identity Guardへ置き換える変更は、Orchestrator Scope ExtensionとしてTask Packetへ記録した。

## Public Contract and Failure Matrix

`Environment`は`#[PublicApi] final readonly`で、公開MethodをConstructor、`string()`、`optionalString()`、`int()`、`positiveInt()`、`bool()`だけに限定した。Iterator、Raw Array Getter、Dump、Mutation、Global Helperは追加していない。

- Constructor: 空でないString KeyとString Valueだけを受理し、入力Arrayの後続Mutationから独立する。
- `string()`: 未定義時は型付きDefaultを使い、Defaultなしは失敗する。定義済み空文字列を保持する。
- `optionalString()`: 未定義だけを`null`とし、空文字列を保持する。
- `int()`: `0`または`-?[1-9][0-9]*`とPHP整数範囲だけを受理する。符号付き正数、Leading Zero、Whitespace、Decimal、Exponent、`-0`を拒否する。
- `positiveInt()`: `[1-9][0-9]*`と1以上のDefaultだけを受理する。
- `bool()`: Case-insensitive `true`／`false`と`1`／`0`だけを受理する。
- Defaultは未定義時だけ使用し、定義済み不正値へFallbackしない。
- 失敗MessageはVariable名と期待型までに限定し、Raw Valueを含めない。

Configuration FileはArray、または正確に`static fn (Environment $env): array`相当のClosureを受理する。Closure以外のCallable、引数数／型、Optional／Variadic／Reference Parameter、Return Type、Return Value、Closure内Throwable、Accessor Failureは安全な`ApplicationBootstrapException`境界へ閉じ、Raw Environment Valueや内部Throwable Detailを公開しない。

## Configuration Evaluation Order Evidence

`withConfiguration()`はDirectoryを`realpath()`で解決して存在を即時検証するが、`require`とClosure実行は行わない。`create()`は保持した文字列Mapから新しいEnvironmentを一つ作り、そのInstanceを全Configuration Closureへ渡してからRegistrationを構成する。

Testでは次を固定した。

- `withConfiguration()->withEnvironment()`と`withEnvironment()->withConfiguration()`が同じ最終値を得る。
- 同一`create()`内の複数Closureが同じ`Environment` object IDを受け取る。
- 同じBuilderで二回`create()`すると、各回で別Snapshotを一つずつ作る。
- Configuration Fileを登録後に変更すると、`create()`時の内容が評価される。
- Array ConfigurationとClosure Configurationが既存Snapshot／Registration Shapeへ統合される。
- SnapshotからEnvironment Property／Getterを除去し、Compiled Artifact／Manifestへ保存しない。

## Quickstart／Community Board Migration

両Applicationの4 Configuration FileはPublic `Environment` Closureを使用する。Port、Lease、Heartbeat、Grace、Retentionは整数Accessor、Worker Booleanは`bool()`、Password等は`string()`を使い、既存Defaultを維持した。未知Booleanを従来のDefaultへ丸めず、安全なBootstrap Failureに変更した。

`config/*.php`の`$_ENV`、`$_SERVER`、`getenv()` Guardは成功した。Bootstrap側のDotenv／Process Snapshot責務、config外のApplication Source、Quickstartを正本とするSkeleton Publication境界は変更していない。

## Commands and Results

- `docker compose run --rm app mago format --check src tests examples/quickstart examples/community-board/app examples/community-board/tests`: success
- `docker compose run --rm app mago lint`: success
- `docker compose run --rm app mago analyze`: success
- Focused PHPUnit after final static-Closure guard: OK (65 tests, 436 assertions)
- Full PHPUnit: OK (1506 tests, 5977 assertions)
- Deptrac: success、0 violations、0 skipped、0 uncovered、2556 allowed
- Root／Quickstart／Community Board Composer strict validation: valid
- Quickstart Setup／E2E: success
- Skeleton Create-project／Framework Update Generators: success
- Community Board Foundation: success
- Community Board Clean Install: success
- Environment Config Guard、Management ID Guard、`git diff --check`: success

Community Board Foundationの初回実行は、pnpmがnon-TTYで既存Module Purgeを拒否したため停止した。`CI=true`で同じConsumerを再実行すると、Taskの正当なQuickstart差分に対する旧zero-diff Guardで停止した。Orchestrator承認のStatus Identity Guardへ置換後、再実行は成功した。Clean Installも`CI=true`で成功した。

Clean Install検証後、退避した元の`.env`をbyte-identicalに復元し、Composer／pnpm依存、Build、Generated Frontend、SvelteKit Production Buildを再構築した。既存PostgreSQL Volumeは削除／初期化していない。既存Tableに対するMigration再適用は`Doctrine\DBAL\Exception\TableExistsException`となったため行わず、HTTP・Worker・Frontendだけを再作成した。最終的にPostgreSQL healthy、HTTP／Worker／Frontend up、Frontend `/` 200、Backend `/welcome` 200を確認し、退避Fileを削除した。

Frontend Generate／Check／Buildの最初の復元確認は誤って並列実行したため、Check／Buildが生成完了前にmissing generated treeで失敗した。Generate完了後にCheck、Buildの順で再実行し、Fresh CheckとProduction Buildはいずれも成功した。

## Acceptance Criteria

- [x] Public EnvironmentのSignature、Readonly、Public API Markerを固定した。
- [x] Constructor、各Accessor、Default、Canonical Integer、Boolean、Invalid MatrixをTestした。
- [x] Raw Invalid ValueをException／Diagnosticsへ出さない。
- [x] Array／Closure Configurationが同じ既存Snapshot Shapeを作る。
- [x] Builder呼出順非依存、各create一Snapshot、全File同一InstanceをTestした。
- [x] Missing Directory、Invalid Return／Closure、Closure ThrowableをSafe Failureへ閉じた。
- [x] EnvironmentをSnapshot／Compiled Artifact／Manifestへ保存しない。
- [x] Quickstart／Community Boardのconfigから直接Environment参照と手動型変換を除去した。
- [x] Quickstart、Skeleton、Framework Update、Community Board Consumerが回帰しない。
- [x] Mago、PHPUnit、Deptrac、Composer、Management ID、Diff Gateが成功した。
- [x] Documentation Website／Community Boardを外部公開していない。
- [x] WorkerはCommitしていない。

## Remaining Issues

Active Implementation Blockerはない。Global `env()` Helper、FrameworkによるDotenv探索、Environment Service登録、追加Accessor、config外の既存Environment利用はTask PacketどおりScope外である。

## Suggested Next Action

OrchestratorがPublic Contract、Safe Failure、Snapshot非保持、Scope、品質GateをReviewし、Accept後にP18-003 Frontend Bound Client Factoryへ進む。

## Orchestrator Review

Accepted。

- Public Environment、Configuration Closure Signature、Builder評価順、Safe Failure、Snapshot／Artifact非保持を差分Reviewした。
- Orchestrator独立再検証でFocused PHPUnit 65 tests／436 assertions、Mago format、Deptrac 0 violations、Environment／Management ID／diff Guardが成功した。
- `ApplicationBuildCompileCommandTest.php`のInternal Constructor追従とCommunity Board FoundationのStatus Identity Guardは、記録済みScope Extensionだけに限定されている。
- Community Board RuntimeはPostgreSQL healthy、HTTP／Worker／Frontend up、Frontend `/` 200、Backend `/welcome` 200の状態を維持する。
