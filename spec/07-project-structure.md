# Project Structure

## 基本方針

FWはアプリケーション全体のディレクトリ構造を強制しない。

ConfigでOperation Providerと探索対象を指定する。Manifest Scannerは設定された範囲だけを対象とし、特定のApplication DirectoryをHard Codeしない。

```php
return [
    'discovery' => [
        'paths' => [app_path('Feature')],
    ],
];
```

具体的なConfig APIはManifestとRegistryの設計で決定する。

## Feature単位の配置

Operationに関連するDefinition、Value、Handler、Outcome、Responderは、FeatureとAction単位で近くへ配置することを推奨する。

```text
Feature/
  Order/
    CreateOrder/
      CreateOrder.php
      CreateOrderValue.php
      CreateOrderHandler.php
      OrderCreated.php
      CreateOrderResponder.php
```

これは公式推奨であり、FWの実行要件ではない。

## Operation Entry Point

SkeletonはHTTP／Console／Internal等の入口種別をDirectory名で分類しない。Operationが属するFeatureへ配置し、HTTP Route、Execution Strategy、Provider等のMetadataとApplication Configurationで実行経路を決定する。

公式Skeletonへ `Internal` Directoryを設けない。利用者がApplication都合で独自のLayerやDirectoryを追加することは妨げない。

## CommandとQuery

Command／Queryディレクトリおよびmarker interfaceを公式構造へ導入しない。Operationだけで処理を表現する。

ユーザーがアプリケーション都合で独自ディレクトリを追加することは妨げない。

## Infrastructure

Infrastructureは技術責務ごとに分類することを推奨する。

```text
Infrastructure/
  Persistence/
  ExecutionTransport/
  JournalObserver/
  Authentication/
  Clock/
  IdGenerator/
```

## Config

Configは主要責務ごとに分割する。

```text
config/
  app.php
  operations.php
  execution.php
  journal.php
  middleware.php
  security.php
```

SecretをConfigファイルへ直書きせず、環境変数またはSecret Managerへの参照として扱う。

## Official Skeleton

公式Application Skeletonの完全な構造、Starter Feature、Bootstrap、Local Runtimeは [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md) で定める。`Shared` とFramework名を持つInfrastructure Subdirectoryは公式構造へ含めない。
