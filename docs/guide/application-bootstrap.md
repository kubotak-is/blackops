# Application Bootstrap

Installed Applicationは、Application Rootを起点にPublic Builderで起動設定を組み立てる。

Feature-firstの完全なSource例は `examples/quickstart/` にあり、Application Code、Bootstrap、Config、Entrypointは `BlackOps\Internal` を参照しない。

```php
use BlackOps\Application\Application;

$application = Application::configure(dirname(__DIR__))
    ->withEnvironment()
    ->withConfiguration()
    ->withCommands([$command])
    ->create();
```

`configure()` は既存DirectoryだけをApplication Rootとして受け入れ、絶対Pathへ正規化する。不正な入力、Config、登録は `ApplicationBootstrapException` で拒否される。

## Environment

`withEnvironment()` は呼出時のProcess Environmentを一度だけCaptureする。明示値を使う場合は、文字列Keyと文字列Valueの配列を渡す。

```php
$builder->withEnvironment([
    'APP_ENV' => 'production',
]);
```

Frameworkは `.env` を読まず、Dotenvも提供しない。Process Manager、Container Runtime、Deployment PlatformなどがEnvironmentを用意する。

## Configuration

`withConfiguration()` は既定で `<application-root>/config` を読む。既定Directoryが存在しなければ空のConfigとして扱う。明示Directoryを渡した場合、そのDirectoryは存在しなければならない。

認識するFileは次の5つで、存在するFileは配列を返す必要がある。

- `app.php`
- `database.php`
- `operations.php`
- `execution.php`
- `journal.php`
- `retention.php`

未知のFileは読み込まない。読み込みは `withConfiguration()` の時点で一度だけ行われ、`create()` 後にFile変更を自動反映しない。

`operations.php` は絶対Directoryの `discovery` ListとOptionalな `providers` Listを返す。Application-aware BuildとOperation ListだけがSourceを探索する。PackageやApplication外SourceはProviderで追加できる。`app.php` の `services` と `commands` からService ProviderとApplication Commandを登録でき、Builderで明示した登録はConfig由来の登録の後へ追加される。

Operation自身に `handle(OperationValue): OperationResult` または `handle(OperationValue, ExecutionContext): OperationResult` を定義し、`#[HandledBy]` を省略するTyped Self-handledが標準である。Inlineの `ExecutionContext` はOperation IDを持ちAttemptを持たず、Deferred Workerでは現在のAttemptを持つ。従来の `OperationHandler` によるSelf-handledと、責務を分けるSeparate Handlerの `#[HandledBy]` は互換性のため引き続き利用できる。BuildはHandler Signatureを検証してContainerへ自動登録するため、Service ProviderはRepository Interface、External Client、Factory等のApplication固有Dependencyだけを登録する。

Providerは対応するPublic ContractのInstanceまたは引数なしで生成できるClass Name、CommandはSymfony Console CommandのInstanceまたは引数なしで生成できるClass Nameを指定する。同一Classは一度だけ登録され、異なるCommandが同じCommand Nameを使う場合は起動エラーになる。

## HTTP Runtime

`Application::http()` は初回呼出時にCompile済みArtifactとDatabase設定を検証し、PSR-15 Handlerを遅延構成する。同じApplicationから繰り返し取得した場合は同じHandler Instanceを返す。

```php
$handler = $application->http();
$response = $handler->handle($serverRequest);
```

`config/app.php` の `build` には `operation_manifest`、`http_manifest`、`container` の絶対Path、`container_class`、`container_namespace` を指定する。`config/database.php` には解決済みDoctrine DBAL `connection` Parameter配列と安全なPostgreSQL `schema` Identifierを指定する。Environment Variable名と値の解決はApplication側が所有する。

HTTP構成はArtifact Compile、Source Discovery、Database Migration、DDLを行わない。Deployment StepでBuildとMigrationを明示的に完了させてからProcessを開始する。Artifact不足、Format不正、Build ID不一致ではFallbackせず `ApplicationBootstrapException` で失敗する。

## Console Kernel

`Application::console()` は同じSnapshotからPublic `ConsoleKernel` を遅延構成する。同じApplicationでは同じKernel Instanceを返す。

```php
exit($application->console()->run());
```

KernelはBuild、Operation List、Migration、Worker、Retention、Schedulerの9 Commandを常に表示する。Database、Artifact、PCNTL、Retention設定は対象Commandの実行時まで構成しないため、`list` と `help` は不完全なRuntime環境でも利用できる。Symfony Application、Container、Connection、ConfigのGetterは提供しない。

`journal.php` の `jsonl` SectionでInline Observed Journalを構成できる。`enabled=true` では絶対Path、書込可能な既存Parent Directory、`best_effort` または `required` Deliveryを指定する。FrameworkはDirectoryを作らず、Sensitive Projection後のRecordだけをJSONLへappendする。
