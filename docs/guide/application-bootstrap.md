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

`withConfiguration()`は既定で`<application-root>/config`を読みます。認識するFileは`app.php`、`database.php`、`operations.php`、`execution.php`、`journal.php`、`middleware.php`、`retention.php`の7つです。存在しない既定Directoryは空Configとして扱い、未知Fileは読みません。

Configは呼出時に一度だけ読み、`create()`後のFile変更を自動反映しません。各設定のShapeは[Configuration Reference](configuration.md)を参照してください。

## Operation、Service、Command

`config/operations.php`のDiscovery RootはBuild時にOperationを探索します。通常のApplication OperationをProviderへ列挙する必要はありません。PackageやApplication外Sourceを登録する場合だけ`providers`を使います。

Typed Self-handled OperationはBuildでHandler Serviceとして自動登録されます。Repository Interface等のApplication固有DependencyをBindingする場合は、Service Providerを`config/app.php`の`services`へ登録します。Builderの`withOperations()`、`withServices()`、`withCommands()`から明示登録を追加することもできます。

Application Maintenance CommandはSymfonyの`#[AsCommand]`を付け、`config/app.php`の`command_discovery`へSource Rootを指定します。`build:compile`がCommandを探索してCompiled Containerへ登録するため、Required Constructor DependencyをService Providerから注入できます。`commands`と`withCommands()`はPackage Command、Instance、明示Override／追加用として維持されます。

QuickstartではOperationをProviderへ列挙せず、Repository Interface、Transactional Command、After Commit Serviceだけを登録します。Default DBAL `Connection`はFrameworkがRuntimeでSynthetic Serviceとして注入するため、ApplicationがCredential付きConnectionをProviderで作る必要はありません。

```php
<?php

declare(strict_types=1);

namespace App;

use App\Feature\Order\CreateOrder\CreateOrderCommand;
use App\Feature\Order\DoctrineOrderRepository;
use App\Feature\Order\OrderRepository;
use App\Feature\Order\RecordOrderCommit;
use App\UserInterface\Http\SampleTokenAuthenticator;
use App\UserInterface\Console\SampleConsoleActorProvider;
use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(HttpAuthenticator::class, SampleTokenAuthenticator::class);
        $services->autowire(ConsoleActorProvider::class, SampleConsoleActorProvider::class);
        $services->autowire(OrderRepository::class, DoctrineOrderRepository::class);
        $services->autowire(CreateOrderCommand::class);
        $services->autowire(RecordOrderCommit::class);
    }
}
```

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

`list`はCommand Manifestの名前／説明／Alias／Hiddenだけを読み、Command Constructor、Compiled Container、Database、PCNTL、Retention Serviceを構成しません。Command固有の`help`または実行時だけCompiled ContainerからCommandを解決します。Command ManifestがMissing／Invalid／StaleでもFramework Commandは残るため、`php blackops build:compile`で復旧できます。

`#[ConsoleCommand]`で公開したOperationも同じManifestから登録します。認可主体が必要なApplicationは`ConsoleActorProvider`をService ProviderでBindingしてください。未Bindingまたは`actor() === null`ではOrigin／Authorization Actorを持たず、`#[Authorize]`付きOperationは既存Policyで拒否されます。FrameworkはExecution Actorを`console-runtime`に固定し、CLI OptionからActor IDやCredentialを受け取りません。

Application ObjectやContainerをOperation、Value、Domain Serviceへ渡しません。業務DependencyはConstructor Injectionします。

## Session AuthenticationをOpt-in登録する

Framework同梱のSession Coreは、`SessionServiceProvider`を追加したApplicationだけで有効になります。BearerとCookieは同時に解釈せず、Applicationが一方を選びます。

```php
use App\Identity\ApplicationSessionIdentityProvider;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionServiceProvider;

return [
    'services' => [
        SessionServiceProvider::bearer(
            ApplicationSessionIdentityProvider::class,
            new SessionConfiguration(ttlSeconds: 28_800, touchIntervalSeconds: 300),
        ),
    ],
];
```

CookieをCredential Sourceにする場合は`SessionServiceProvider::cookie(ApplicationSessionIdentityProvider::class, 'application_session')`を使います。Cookieの発行、`Secure`／`HttpOnly`／`SameSite`、Path／Domain、CSRFはApplicationが所有します。どちらも`AuthenticationMiddleware`のGlobal登録は別途必要です。

`SessionManager`はLogin／Logout Application ServiceへConstructor Injectionします。`issue()`が返すRaw Tokenは発行直後にBearer Responseまたは安全なCookieへ変換し、Operation Value、Outcome、Journal、Logへ渡さないでください。`rotate()`は旧Sessionの失効とSuccessorの発行を一つのTransactionで行い、`revoke()`はnull／Malformed／Unknown／Expired／Revokedでも安全に完了します。

Session CoreはDefault 8時間のAbsolute TTLとDefault 5分間隔の`last_used_at`更新を持ちます。`cleanup($cutoff)`はCutoffより古いExpired／Revoked Rowだけを削除します。SchemaはRuntimeが自動作成しません。`resources/stubs/auth-session-migration.php.stub`をApplication Migration Historyへ取り込み、Migrationを明示実行してください。`make:auth`による一度だけのPublishは後続のGenerator対応で追加します。
