# PHP Runtime and Dependency Injection

## PHP Runtime

初期バージョンはPHP 8.5以上を要求する。

MVPおよび公式Reference EnvironmentはFrankenPHPを前提にする。

Core、Operation、HTTP、Journal、TransportのContractはFrankenPHP固有APIへ直接依存しない。Framework境界はPSR-7、PSR-15、PSR-17、PSR-11を維持し、FrankenPHP固有のBootstrap、Worker設定、Server設定はRuntime CompositionまたはAdapter層で扱う。

PHP-FPM、RoadRunner、Swoole等は将来のCompatibility TargetまたはAdapter候補とし、MVPの主要検証対象にはしない。

## Container Contract

FWがRuntimeで依存するDI Container ContractはPSR-11 `ContainerInterface` とする。

標準実装にはSymfony DependencyInjection Component 7.4 LTSを採用する。

PSR-11 Containerを利用するのはFWのComposition Root、Handler Resolverなどの解決境界に限定する。Handler、Domain Service、Operation EnvelopeへContainerを渡さず、GlobalなContainer Helperを推奨しない。

## Dependency Injection

Constructor Autowiringを既定とする。

```php
final class CreateOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ClockInterface $clock,
    ) {
    }
}
```

Interface実装、Scalar、Factory、複数候補など曖昧な依存はConfigで明示する。Property InjectionおよびMethod Injectionは標準の推奨方法としない。

## Container Compile

- 開発環境ではContainerを動的構築できる
- 本番環境とCIではOperation Manifestと同じBuild工程でContainer Compileと検証を必須とする
- 本番Runtimeは生成済みPHP Containerを利用する

Runtimeで生成するDatabase ConnectionとDatabaseManagerはSynthetic ServiceとしてBuild時Containerへ定義する。Credentialや解決済みConnection Parameterを生成済みPHP Containerへ保存しない。HTTP／Worker／ConsoleのComposition RootがAccepted Configuration SnapshotからRuntime Instanceを生成し、Compiled Containerへ設定する。

## Database Dependency Injection

ApplicationはPublic `BlackOps\Database\DatabaseManager`をConstructor Injectionし、DefaultまたはNamed Doctrine DBAL Connectionを取得できる。

```php
final readonly class AnalyticsRepository
{
    public function __construct(
        private BlackOps\Database\DatabaseManager $databases,
    ) {}
}
```

Default Connectionだけを使うRepositoryは`Doctrine\DBAL\Connection`を直接Constructor Injectionできる。Named ConnectionはDatabaseManagerから明示的に選択する。Container、Global Helper、Static FacadeをRepositoryへ渡さない。

## Build-time Method Interception

`#[Transactional]`と`#[AfterCommit]`はRay.AopによるBuild時Method Interceptionを使用する。Ray.Diへ移行せず、Symfony DIのService Definitionを正本とする。

- ProxyはApplicationの`build:compile`で生成する
- Production RuntimeでSource ScanまたはTemporary Proxy生成を行わない
- DI Containerから解決されたPublic MethodだけをInterceptする
- Direct `new`、Static／Private MethodはInterceptしない
- 対象ClassとMethodは非`final`を必須とする
- `readonly` Classは許可する
- Attributeを無効な対象へ付けた場合はBuild Errorにする

## Package Service Provider

Composer PackageはService Providerを公開し、Build時にContainer Definitionを登録できる。

Operation ProviderはOperation DefinitionをManifestへ登録し、Service ProviderはHandlerやInfrastructure AdapterのService定義を登録する。一つのPackageが両方を提供できる。

## Long-running Worker

Workerで再利用されるServiceは原則Statelessとする。

Operation固有状態はOperation EnvelopeまたはMethod Localへ保持する。DB接続など再利用ResourceはWorker Lifecycle HookによってHealth CheckおよびResetできるようにする。

FrankenPHPを含むLong-running Processでは、Operation Scope、Logger Scope、Observer Buffer、Database ConnectionをOperation境界またはProcess Lifecycleで明示的にreset / flush / health-checkできる設計を必須とする。

Named Connectionは未使用なら生成しない。生成済みConnectionはRequest／Attempt開始時にHealth Checkし、正常終了時はActive Transactionがないことを検証して再利用する。Throwable、Rollback失敗、Transaction Leak、Health Check失敗では該当ConnectionをCloseし、次回利用時に再接続する。Deferred WorkerのHeartbeat専用ConnectionはApplication Serviceへ公開しない。
