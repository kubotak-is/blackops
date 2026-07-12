# D069: Skeleton HTTP Entrypoint Adapters

Status: Decided

## Context

P7-005では `examples/quickstart/public/index.php` を、Install直後と同じ独立Composer ProjectのHTTP Entrypointとして配置する。

D064はApplication CodeとSkeleton Bootstrapから `BlackOps\Internal` Importを排除すると決定した。P7-003でPublic `Application::http()` はPSR-15 Handlerを返せるようになったが、PHP SuperglobalからPSR-7 Server Requestを生成し、PSR-7 ResponseをSAPIへEmitする既存AdapterはFramework Internalにある。

SkeletonのEntrypointがInternal AdapterをImportするとFramework Update時の互換性Contractを破る。一方、このAdapterをFramework Public APIへ昇格すると、FrameworkがSAPI／Server Runtimeを公開契約として所有することになる。

DotenvはD064によりSkeleton所有と確定済みである。2026-07-12時点で、候補の `vlucas/phpdotenv`、`nyholm/psr7`、`nyholm/psr7-server`、`laminas/laminas-httphandlerrunner` はPHP 8.5を許容するComposer Requirementを持つ。

## Question 1: HTTP Request Creation and Response Emission Ownership

`public/index.php` のSuperglobal変換とResponse Emitを誰が所有するか。

### Options

- A: Skeletonが標準PSR Packageを直接Requireし、`nyholm/psr7-server` でRequestを作成し、`laminas/laminas-httphandlerrunner` でResponseをEmitする
- B: FrameworkへPublic SAPI Adapter／Front Controller APIを追加し、SkeletonはFramework APIだけを呼ぶ
- C: 外部Packageを追加せず、Skeletonの `public/index.php` にSuperglobal変換とHeader／Body Emitを独自実装する

### Recommendation

Aを推奨する。

SkeletonはServer Entrypointを所有しつつ、Application Runtime CompositionはFrameworkのPublic `Application::http()` に委ねられる。Framework Internalを公開せず、SAPI変換の独自実装やHeader検証の重複も避けられる。

Skeletonの直接Dependencyは次を基本とする。

```text
vlucas/phpdotenv                  ^5.6
nyholm/psr7                      ^1.8
nyholm/psr7-server               ^1.1
laminas/laminas-httphandlerrunner ^2.13
```

`public/index.php` はPSR-17 Factory、Server Request Creator、SAPI Emitterだけを構成し、Handler、Container、Database、Artifactを組み立てない。

[ANSWER]

A
一つだけlaminasパッケージに依存するのが気になる。laminasしかないの？Symfonyは？

[/ANSWER]

## Question 2: Response Emitter Implementation

Question 1の回答Aにより、HTTP Entrypoint AdapterはSkeleton所有とする。Response Emitの実装Packageをどれにするか。

### Option A: Direct PSR-7 Emit with Laminas

```text
nyholm/psr7-server
laminas/laminas-httphandlerrunner
```

SuperglobalからPSR-7 Requestを生成し、Frameworkが返したPSR-7 Responseを変換せずSAPIへEmitする。Laminas PackageはEmitter境界だけで使用し、Application／Feature CodeへLaminas型を持ち込まない。

### Option B: Symfony HttpFoundation Bridge

```text
symfony/http-foundation
symfony/psr-http-message-bridge
```

`Request::createFromGlobals()` をPSR-7 Requestへ変換し、Frameworkが返したPSR-7 ResponseをHttpFoundation Responseへ戻して `send()` する。Frameworkが既に利用するSymfony Ecosystemへ統一できるが、RequestとResponseの両方に変換層が入る。

### Option C: Alternative Direct Emitter

`zaphyr-org/http-emitter` 等の小規模なPSR-7 Emitterを採用する。Laminas依存は避けられるが、導入実績とFramework Ecosystemとの整合性はA／Bより小さい。

### Recommendation

Aを推奨する。

Laminasしか選択肢がないわけではなく、Symfony Bridgeでも実装できる。ただしSymfonyの公式PSR-7 ComponentはHttpFoundationとの相互変換Bridgeであり、PSR-7 Responseの直接Emitterではない。BlackOpsのHTTP BoundaryはPSR-7／PSR-15であるため、Responseを別Modelへ変換せずEmitするAの方が境界が単純で、Body StreamもPSR-7のまま扱える。

Laminasへの依存は `public/index.php` のSAPI Emitだけに閉じる。将来Emitterを交換してもApplication Bootstrap、Feature、Handler、Framework Public APIには影響しない。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. HTTP Request CreationとResponse EmitはSkeleton Entrypointが所有する。
2. FrameworkのInternal FrankenPHP／SAPI AdapterをSkeletonからImportしない。
3. Framework SAPI AdapterをPublic APIへ昇格しない。
4. Skeletonは `nyholm/psr7` と `nyholm/psr7-server` を直接Requireし、SuperglobalからPSR-7 Server Requestを生成する。
5. Skeletonは `laminas/laminas-httphandlerrunner` を直接Requireし、Frameworkが返したPSR-7 Responseを別Modelへ変換せずSAPIへEmitする。
6. Laminas型の参照は `public/index.php` のSAPI Emit境界だけに限定する。
7. Application Bootstrap、Feature、Operation、Handler、ConfigへLaminas型を持ち込まない。
8. DotenvはD064どおり `vlucas/phpdotenv` をSkeletonが直接Requireして所有する。
9. `public/index.php` はRequest Creator、Emitter、`Application::http()` の接続だけを行い、Container、Database、Artifact、Runtime Serviceを手動構成しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- SkeletonはHTTP Entrypoint用にNyholm、Laminas、Dotenvの直接Dependencyを持つ。
- PSR-7 Request／ResponseをHttpFoundation等の別Modelへ変換せず、FrameworkのPSR境界を維持できる。
- Laminas依存はProcess EntrypointのAdapter Detailであり、Application Featureの設計やFramework Public APIをLaminasへ固定しない。
- Emitterを将来交換する場合は `public/index.php` とComposer Dependencyの変更で完結する。
- Framework Internal AdapterをSkeletonが参照せず、Framework Update時のInternal変更からInstalled Applicationを分離できる。

[/CONSEQUENCES]

## References

- [Installed Application Layout and Bootstrap](../spec/43-installed-application-layout-and-bootstrap.md)
- [Public Application Bootstrap API](../spec/44-public-application-bootstrap-api.md)
- [Public HTTP Runtime Configuration](../spec/47-public-http-runtime-configuration.md)
- [vlucas/phpdotenv](https://packagist.org/packages/vlucas/phpdotenv)
- [nyholm/psr7-server](https://packagist.org/packages/nyholm/psr7-server)
- [laminas/laminas-httphandlerrunner](https://packagist.org/packages/laminas/laminas-httphandlerrunner)
- [Symfony PSR-7 Bridge](https://symfony.com/doc/current/components/psr7.html)
- [symfony/psr-http-message-bridge](https://packagist.org/packages/symfony/psr-http-message-bridge)
- [symfony/http-foundation](https://packagist.org/packages/symfony/http-foundation)
