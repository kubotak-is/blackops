# ApplicationをBootstrapする

Installed ApplicationはApplication Rootを起点にPublic `Application` BuilderからHTTPとConsoleの共通Configuration Snapshotを作ります。

```php
use BlackOps\Application\Application;

$application = Application::configure(dirname(__DIR__))
    ->withEnvironment($environment)
    ->withConfiguration()
    ->create();
```

`configure()`は存在するApplication Rootを絶対Pathへ正規化します。不正なRoot、Environment、Config、Registrationは`ApplicationBootstrapException`で拒否されます。

## EnvironmentとConfiguration

`withEnvironment()`へ文字列Key／Valueの配列を渡します。引数を省略した場合は呼出時のProcess Environmentを一度だけCaptureします。FrameworkはDotenvを提供せず、Skeletonが`.env`とProcess Environmentを解決します。

`withConfiguration()`は既定で`<application-root>/config`を読みます。認識するFileは`app.php`、`database.php`、`operations.php`、`execution.php`、`journal.php`、`retention.php`の6つです。存在しない既定Directoryは空Configとして扱い、未知Fileは読みません。

Configは呼出時に一度だけ読み、`create()`後のFile変更を自動反映しません。各設定のShapeは[Configuration Reference](configuration.md)を参照してください。

## OperationとService

`config/operations.php`のDiscovery RootはBuild時にOperationを探索します。通常のApplication OperationをProviderへ列挙する必要はありません。PackageやApplication外Sourceを登録する場合だけ`providers`を使います。

Typed Self-handled OperationはBuildでHandler Serviceとして自動登録されます。Repository Interface等のApplication固有DependencyをBindingする場合は、Service Providerを`config/app.php`の`services`へ登録します。Builderの`withOperations()`、`withServices()`、`withCommands()`から明示登録を追加することもできます。

## HTTP Process

```php
$handler = $application->http();
$response = $handler->handle($serverRequest);
```

`http()`は初回呼出時にCompile済みArtifactとDatabase設定を検証し、PSR-15 Handlerを遅延構成します。同じApplicationから繰り返し取得すると同じHandler Instanceを返します。

HTTP構成はSource Discovery、Artifact Compile、Database Migration、DDLを行いません。Artifact不足、Format不正、Build ID不一致時はFallbackせず失敗します。

## Console Process

Project Rootの`blackops`は同じApplicationからPublic Console Kernelを起動します。

```php
use BlackOps\Application\Application;

require __DIR__ . '/vendor/autoload.php';

/** @var Application $application */
$application = require __DIR__ . '/bootstrap/app.php';

exit($application->console()->run());
```

`list`と`help`ではDatabase、Artifact、PCNTL、Retention Serviceを構成しません。対象Commandの実行時だけ必要なRuntimeを遅延構成します。

Application ObjectやContainerをOperation、Value、Domain Serviceへ渡しません。業務DependencyはConstructor Injectionします。
