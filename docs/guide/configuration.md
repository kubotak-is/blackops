# Configuration Reference

Installed Applicationは責務別のPHP Configを`config/`に置きます。Frameworkは存在する既知Fileだけを読み、各Fileは配列を返す必要があります。

| File | Responsibility |
| --- | --- |
| `app.php` | Build Artifact、Application Service Provider、Application Command |
| `database.php` | Doctrine DBAL Connection ParameterとFramework Schema |
| `operations.php` | Build-time Discovery RootとOptional Operation Provider |
| `execution.php` | Worker ID、Lease、Heartbeat、Grace、Supervision |
| `journal.php` | Observed JSONL JournalのPathとDelivery Mode |
| `retention.php` | Payload、Journal、Outcome、Dead Letterの保持期間、Policy、Actor |

## Environment

Frameworkは`.env`を読みません。Skeletonの`bootstrap/app.php`がProcess Environmentを優先してDotenvを読み、解決済み文字列を`Application::configure(...)->withEnvironment($environment)`へ渡します。

SecretをConfig Sourceへ直書きせず、Process Manager、Container Runtime、Deployment PlatformからEnvironmentとして渡してください。Productionは`.env` Fileを必須としません。

## Operations

```php
return [
    'discovery' => [dirname(__DIR__) . '/app/Feature'],
    'providers' => [],
];
```

Discovery Rootは存在する絶対Directoryです。Application-aware BuildとOperation ListだけがSourceを探索します。Composer PackageやApplication外Sourceを追加する場合だけOperation Providerを使います。

## Build Artifact

```php
return [
    'build' => [
        'application_build_id' => $_ENV['APP_BUILD_ID'] ?? 'local',
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

Operation Manifest、HTTP Manifest、Containerは同じBuild IDで作成します。ProductionはArtifact不足、Format不正、Build ID不一致時に起動を拒否し、Source DiscoveryへFallbackしません。

## Database

```php
return [
    'connection' => [
        'driver' => 'pdo_pgsql',
        'host' => $_ENV['POSTGRES_HOST'],
        'port' => (int) $_ENV['POSTGRES_PORT'],
        'dbname' => $_ENV['POSTGRES_DB'],
        'user' => $_ENV['POSTGRES_USER'],
        'password' => $_ENV['POSTGRES_PASSWORD'],
    ],
    'schema' => $_ENV['BLACKOPS_SCHEMA'] ?? 'blackops',
];
```

HTTP、Worker、Migration、Outcome、Retentionは同じAccepted Database Configurationを使用します。Schema名は安全な小文字PostgreSQL Identifierに制限されます。

## Observed Journal

```php
return [
    'jsonl' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/var/log/journal.jsonl',
        'delivery' => 'best_effort',
    ],
];
```

`enabled=true`では絶対Path、書込可能な既存Parent Directory、`best_effort`または`required`を指定します。FrameworkはDirectoryを作らず、Sensitive Projection後のRecordだけをJSONLへ追記します。

BootstrapのLoading Boundaryは[Application Bootstrap](application-bootstrap.md)、実行Commandは[Project CLI](project-cli.md)を参照してください。
