# D069: Skeleton HTTP Entrypoint Adapters

Status: Proposed

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



[/ANSWER]

## Decision

[DECISION]

回答待ち。

[/DECISION]

## Consequences

[CONSEQUENCES]

回答後に確定する。

[/CONSEQUENCES]

## References

- [Installed Application Layout and Bootstrap](../spec/43-installed-application-layout-and-bootstrap.md)
- [Public Application Bootstrap API](../spec/44-public-application-bootstrap-api.md)
- [Public HTTP Runtime Configuration](../spec/47-public-http-runtime-configuration.md)
- [vlucas/phpdotenv](https://packagist.org/packages/vlucas/phpdotenv)
- [nyholm/psr7-server](https://packagist.org/packages/nyholm/psr7-server)
- [laminas/laminas-httphandlerrunner](https://packagist.org/packages/laminas/laminas-httphandlerrunner)
