# Application Bootstrap

Installed Applicationは、Application Rootを起点にPublic Builderで起動設定を組み立てる。

```php
use BlackOps\Application\Application;

$application = Application::configure(dirname(__DIR__))
    ->withEnvironment()
    ->withConfiguration()
    ->withOperations([$operationProvider])
    ->withServices([$serviceProvider])
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

未知のFileは読み込まない。読み込みは `withConfiguration()` の時点で一度だけ行われ、`create()` 後にFile変更を自動反映しない。

`operations.php` はProviderのList、または `providers` Keyを持つ配列を返せる。`app.php` の `services` と `commands` からService ProviderとApplication Commandを登録できる。Builderで明示した登録はConfig由来の登録の後へ追加される。

Providerは対応するPublic ContractのInstanceまたは引数なしで生成できるClass Name、CommandはSymfony Console CommandのInstanceまたは引数なしで生成できるClass Nameを指定する。同一Classは一度だけ登録され、異なるCommandが同じCommand Nameを使う場合は起動エラーになる。

## Current Boundary

この段階の `Application` は検証済みConfiguration Snapshotを保持するBootstrap Foundationである。HTTP RuntimeとConsole Runtimeの構成はまだPublic APIではなく、`http()`、`console()`、Container Getterは提供しない。
