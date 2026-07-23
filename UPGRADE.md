# Upgrade Guide

BlackOpsはExperimentalです。1.xのMinor Release間でも破壊的変更を行う場合があり、Backward Compatibilityは保証しません。変更を適用する前にApplication SourceとDatabaseをBackupし、検証環境でUpgradeしてください。

## 1.0.0から1.1.0

### 1. Composer Constraintを更新する

Applicationの`composer.json`でFramework Constraintを更新し、Lock Fileを再生成します。

```json
{
  "require": {
    "blackops/framework": "^1.1"
  }
}
```

```bash
composer update blackops/framework --with-all-dependencies
```

FrameworkはApplication所有のEntrypoint、生成済みOperation、Migration、Configurationを自動更新しません。

### 1.1.1 Preview: Runtime Boundary and Application Dependencies

Repository `main`のSkeleton／Community Boardへ移行する場合、`bootstrap/app.php`を次の形へ更新し、Applicationの直接ImportがないRuntime Packageを`composer.json`から削除してLockを再生成します。

```php
return Application::configure(dirname(__DIR__))
    ->withEnvironmentFile()
    ->withConfiguration()
    ->create();
```

`public/index.php`は`SapiRuntime::run($application)`、Workerは`SapiRuntime::runWorker($application)`を呼びます。`vlucas/phpdotenv`、`nyholm/psr7`、`nyholm/psr7-server`、`laminas/laminas-httphandlerrunner`、`symfony/uid`は標準RuntimeのFramework-owned Dependencyです。ApplicationがDBAL／Migrationsを実Importする場合は、それらをDirect Dependencyとして残してください。外部LoaderやCustom PSR-15 Adapterを選ぶApplicationは、利用Packageを明示的に再追加します。

### 2. Project CLIをRoot Entrypointへ置き換える

Application Rootで次をそのまま実行し、Skeleton `1.1.0`と同じEntrypointを新規作成します。旧`bin/blackops`は`dirname(__DIR__)`をApplication Rootとして使う実装のため、単純な`mv bin/blackops blackops`ではPath解決が壊れます。

```bash
install -m 0755 /dev/stdin blackops <<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

use BlackOps\Application\Application;

require __DIR__ . '/vendor/autoload.php';

/** @var Application $application */
$application = require __DIR__ . '/bootstrap/app.php';

exit($application->console()->run());
PHP

php blackops list
```

`php blackops list`が成功した後、旧Entrypointを削除し、Deploy Script、Compose、Process Manager、CIから`php blackops`を呼び出してください。

```bash
rm bin/blackops
```

Framework UpdateだけではApplication所有のEntrypoint PathやSourceは変わりません。この手順ではSkeleton `1.1.0`の完全版を使い、`__DIR__`がApplication Rootを指す状態を明示的に作ります。

### 3. Project CLI Command名を置換する

| 1.0.0 | 1.1.0 |
| --- | --- |
| `blackops:build:compile` | `build:compile` |
| `blackops:operation:list` | `operation:list` |
| `blackops:database:status` | `database:status` |
| `blackops:database:migrate` | `database:migrate` |
| `blackops:worker:run` | `worker:run` |
| `blackops:retention:plan` | `retention:plan` |
| `blackops:retention:purge` | `retention:purge` |
| `blackops:scheduler:run` | `scheduler:run` |
| `blackops:scheduler:daemon` | `scheduler:daemon` |

旧`blackops:*`名はAliasとして残りません。Application Command名としては利用できます。

### 4. HTTP Runtimeを選択する

Skeleton `1.1.0`はFrankenPHP Worker ModeをDefaultにします。Skeletonの`Caddyfile`、`public/worker.php`、`Dockerfile.frankenphp`、`compose.yaml`をApplicationへ手動でMergeし、Long-running Processで安全な構成にしてください。

- Request、Actor、Tenant等のRequest固有StateをService Propertyやstaticへ保持しない
- `FRANKENPHP_MAX_REQUESTS`を環境に合わせて設定する。SkeletonのDefaultは`1000`
- Classic Fallbackを使う場合は`Caddyfile.classic`と`CLASSIC_HTTP_PORT`をMergeし、`classic-mode` Profileを明示する

Worker Modeへ移行しない場合も、既存Classic RuntimeはApplication側で継続できます。ただしSkeleton `1.1.0`のDefaultとは異なります。

### 5. HTTP ClientのError処理を更新する

- malformed JSONとNon-object JSONは`status=error`と`code`を含むHTTP 400になる
- Missing Field、型Binding、Value Validationは`operationId`、`category`、`code`、`violations`を含むHTTP 422になる
- 正常なInline 200、Deferred受付202、Operation IDのContractは変わらない

Client Testで400／422を明示的に検証してください。

### 6. BuildとMigrationを実行する

Framework Migration Schemaは1.0.0から変更していません。1.1.0ではApplication Migration Runtimeを追加しているため、Application固有Migrationがある場合はFramework Migrationの後に同じFlowで実行されます。

```bash
php blackops operation:list
php blackops build:compile
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
```

### 7. Applicationを検証する

Inline、Deferred受付、Worker Retry、Outcome、JournalのSensitive Mask、Validation 422を検証します。新規OperationやMigrationはGeneratorで作成できますが、既存Sourceは書き換えられません。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
php blackops make:migration CreateOrdersTable
```
