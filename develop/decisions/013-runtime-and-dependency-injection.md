# D013: PHP RuntimeとDependency Injection

Status: Decided

## Context

Operation Registryは不変Metadataを保持し、Handler、Middleware、Policy、Responder、TransportなどのService InstanceはDI Containerから解決する。

DI Containerの選択はPHP最低バージョン、Build時Compile、Package拡張、Workerの長期稼働へ影響する。

## Question 1: PHP最低バージョン

初期バージョンが要求するPHPをどこに置くか。

### Options

- A: PHP 8.3以上
- B: PHP 8.4以上
- C: PHP 8.5以上

### Recommendation

Bを推奨する。

2026年7月時点でPHP 8.4はActive Support中で、Security Supportは2028年12月末まで予定されている。PHP 8.5だけに限定するより導入範囲を確保しつつ、現代的なPHPを前提にできる。

[ANSWER]

新しいFWなので、Cで良い

[/ANSWER]

## Question 2: DI Contract

FWがRuntimeで依存するContainer Contractをどうするか。

### Options

- A: PSR-11 `ContainerInterface` を使用する
- B: FW独自のContainer Interfaceだけを使用する
- C: 特定DIライブラリのContainer Classへ直接依存する

### Recommendation

Aを推奨する。

FWのComposition RootがPSR-11 ContainerからHandler等を解決する。HandlerやDomain ServiceへContainer自体を注入するService Locator利用は禁止する。

[ANSWER]

A

[/ANSWER]

## Question 3: 標準DI実装

FWが標準で採用するDI Containerをどうするか。

### Options

- A: Symfony DependencyInjection Component 7.4 LTSを標準採用する
- B: PHP-DIを標準採用する
- C: 標準実装を持たず、ユーザーにPSR-11実装を必須選択させる
- D: DI Containerを独自実装する

### Recommendation

Aを推奨する。

Symfony DependencyInjectionはPSR-11対応、Container Compile、Compiler Pass、設定検証、最適化されたContainer Dumpを持つ。Symfony 7.4はLTSで、2029年11月までSecurity Fixesが予定されている。

PHP-DIもPSR-11、Autowiring、Compiled Containerを備え、扱いやすい有力候補である。一方、本FWはOperation ManifestとBuild工程を重視するため、Compileと拡張検証が強いSymfony DIとの相性を優先する。

[ANSWER]

A

[/ANSWER]

## Question 4: Autowiring

Serviceの依存解決をどこまで自動化するか。

### Options

- A: Constructor Autowiringを既定とし、Interface、Scalar、FactoryはConfigで明示する
- B: すべてのServiceをConfigへ明示登録する
- C: Property／Method Injectionを含め、可能な限り自動化する

### Recommendation

Aを推奨する。

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

Constructor Injectionへ限定すると依存が明示され、Testもしやすい。Interface実装、Scalar、複数候補など曖昧な依存だけをConfigで解決する。

[ANSWER]

A

[/ANSWER]

## Question 5: Container Compile

環境ごとのContainer構築をどうするか。

### Options

- A: 開発環境は動的構築、本番とCIはCompileおよび検証を必須にする
- B: すべての環境で動的構築する
- C: 開発環境でも手動Compileを必須にする

### Recommendation

Aを推奨する。

Operation Manifestと同じBuild工程でContainerをCompileする。本番では生成済みPHP Containerを読み、動的Reflectionと定義解決を避ける。

[ANSWER]

A

[/ANSWER]

## Question 6: Package Service Provider

Composer PackageがService定義を追加する方法をどうするか。

### Options

- A: PackageがService Providerを公開し、Build時にContainer Definitionを登録する
- B: アプリケーションがPackage内の全Serviceを自動Scanする
- C: PackageのService登録を許可しない

### Recommendation

Aを推奨する。

Operation ProviderはOperation DefinitionをManifestへ登録し、Service ProviderはHandlerやInfrastructure Adapterの生成定義をContainerへ登録する。一つのPackageが両Providerを実装してよい。

[ANSWER]

A

[/ANSWER]

## Question 7: Containerの利用境界

アプリケーションコードからContainerを直接利用できるようにするか。

### Options

- A: FWのComposition RootだけがContainerを利用し、HandlerやDomain ServiceはConstructor Injectionを使う
- B: Operation EnvelopeへContainerを含める
- C: Globalな `container()` Helperを推奨する

### Recommendation

Aを推奨する。

Service Locator化を避け、Handlerの依存を型とConstructorで明示する。FW内部のHandler Resolverなど、解決境界だけがContainerへアクセスする。

[ANSWER]

A

[/ANSWER]

## Question 8: 長期稼働WorkerとService状態

WorkerでService Instanceを再利用する場合の規則をどうするか。

### Options

- A: Serviceは原則Statelessとし、Operation固有状態をInstance Propertyへ保持しない
- B: OperationごとにContainer全体を再構築する
- C: Serviceの状態管理をユーザーへ完全に任せる

### Recommendation

Aを推奨する。

Operation固有情報はOperation EnvelopeまたはMethod Localへ置く。DB接続など再接続が必要なResourceは、Worker Lifecycle HookによってHealth Check／Resetできるようにする。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. 初期バージョンはPHP 8.5以上を要求する。
2. FWがRuntimeで依存するDI Container ContractはPSR-11 `ContainerInterface` とする。
3. 標準DI実装としてSymfony DependencyInjection Component 7.4 LTSを採用する。
4. Constructor Autowiringを既定とする。
5. Interface実装、Scalar、Factory、複数候補など曖昧な依存はConfigで明示する。
6. Property InjectionおよびMethod Injectionを標準の推奨方法としない。
7. 開発環境ではContainerを動的構築できる。
8. 本番環境とCIではOperation Manifestと同じBuild工程でContainer Compileと検証を必須とする。
9. Composer PackageはService Providerを公開し、Build時にContainer Definitionを登録できる。
10. Operation ProviderはOperation DefinitionをManifestへ登録し、Service ProviderはHandlerやInfrastructure AdapterのService定義を登録する。一つのPackageが両方を提供できる。
11. PSR-11 Containerを利用するのはFWのComposition Root、Handler Resolverなどの解決境界に限定する。
12. Handler、Domain Service、Operation EnvelopeへContainerを渡さず、GlobalなContainer Helperを推奨しない。
13. 長期稼働Workerで再利用されるServiceは原則Statelessとする。
14. Operation固有状態はOperation EnvelopeまたはMethod Localへ保持する。
15. DB接続など再利用ResourceはWorker Lifecycle HookによってHealth CheckおよびResetできるようにする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- PHP 8.5の言語機能を後方互換用分岐なしで利用できる。
- PSR-11によりFWのRuntime境界を標準Contractへ寄せられる。
- Symfony DIのContainer Compile、Compiler Pass、定義検証をOperation Manifest Buildと統合できる。
- Handlerの依存がConstructorで明示され、単体Testで置き換えやすくなる。
- PackageはOperationとServiceの登録をBuild時に拡張できる。
- Symfony DI固有のContainer Build Adapter、Service Provider API、Config Loaderを設計する必要がある。
- Worker Lifecycle HookとReset可能ServiceのContractを設計する必要がある。
- PSR-11 ContainerをService Locatorとしてアプリケーションコードから利用しない規約を文書化・静的解析する必要がある。

[/CONSEQUENCES]

## Sources

- [PHP Supported Versions](https://www.php.net/supported-versions.php)
- [PSR-11 Container Interface](https://www.php-fig.org/psr/psr-11/)
- [Symfony DependencyInjection Component](https://symfony.com/doc/current/components/dependency_injection.html)
- [Symfony 7.4 LTS](https://symfony.com/releases/7.4)
- [PHP-DI Performance and Compilation](https://php-di.org/doc/performances.html)
