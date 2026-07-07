# PHP Runtime and Dependency Injection

## PHP Runtime

初期バージョンはPHP 8.5以上を要求する。

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

## Package Service Provider

Composer PackageはService Providerを公開し、Build時にContainer Definitionを登録できる。

Operation ProviderはOperation DefinitionをManifestへ登録し、Service ProviderはHandlerやInfrastructure AdapterのService定義を登録する。一つのPackageが両方を提供できる。

## Long-running Worker

Workerで再利用されるServiceは原則Statelessとする。

Operation固有状態はOperation EnvelopeまたはMethod Localへ保持する。DB接続など再利用ResourceはWorker Lifecycle HookによってHealth CheckおよびResetできるようにする。
