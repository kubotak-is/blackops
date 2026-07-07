# Project Structure

## 基本方針

FWはアプリケーション全体のディレクトリ構造を強制しない。

ConfigでHttp、Console、Internalなど入口種別ごとのOperation探索ディレクトリを指定する。Manifest Scannerは設定された範囲だけを対象とする。

```php
return [
    'discovery' => [
        'http' => [
            app_path('UserInterface/Http/Operation'),
        ],
        'console' => [
            app_path('UserInterface/Console/Operation'),
        ],
        'internal' => [
            app_path('Application/Operation/Internal'),
        ],
    ],
];
```

具体的なConfig APIはManifestとRegistryの設計で決定する。

## Feature単位の配置

Operationに関連するDefinition、Value、Handler、Outcome、Responderは、FeatureとAction単位で近くへ配置することを推奨する。

```text
Http/
  Operation/
    Order/
      CreateOrder/
        CreateOrder.php
        CreateOrderValue.php
        CreateOrderHandler.php
        OrderCreated.php
        CreateOrderResponder.php
```

これは公式推奨であり、FWの実行要件ではない。

## Internal Operation

Internal OperationはUserInterfaceではなくApplication層へ置くことを推奨する。

```text
Application/
  Operation/
    Internal/
      Notification/
        SendNotification/
```

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

## 未決定事項

公式Application Skeletonの完全な構造は現時点では決定しない。

Welcome Page、初期Operation、起動方法などの初期体験を設計する段階で改めて決定する。`Shared` ディレクトリは公式構造へ含めない。
