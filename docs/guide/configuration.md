# Configuration Reference（設定一覧）

Installed Applicationは責務別のPHP Configを`config/`に置きます。Frameworkは存在する既知Fileだけを読み、各Fileは配列を返す必要があります。

| File | Responsibility |
| --- | --- |
| `app.php` | Build Artifact、Application Service Provider、Application Command |
| `database.php` | Default／Named Doctrine DBAL ConnectionとFramework Store |
| `operations.php` | Build-time Discovery RootとOptional Operation Provider |
| `execution.php` | Worker ID、Lease、Heartbeat、Grace、Supervision |
| `journal.php` | Observed JSONL JournalのPathとDelivery Mode |
| `middleware.php` | Global PSR-15 HTTP Middlewareの登録順 |
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
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['POSTGRES_HOST'],
            'port' => (int) $_ENV['POSTGRES_PORT'],
            'dbname' => $_ENV['POSTGRES_DB'],
            'user' => $_ENV['POSTGRES_USER'],
            'password' => $_ENV['POSTGRES_PASSWORD'],
        ],
        'analytics' => [
            'driver' => 'pdo_pgsql',
            'url' => $_ENV['ANALYTICS_DATABASE_URL'],
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => $_ENV['BLACKOPS_SCHEMA'] ?? 'blackops',
    ],
];
```

`default`と`framework.connection`は`connections`内のNameを参照します。通常のRepositoryはDefault `Doctrine\DBAL\Connection`をConstructor Injectionでき、複数Databaseを選ぶServiceは`BlackOps\Database\DatabaseManager::connection('analytics')`をConstructor Injectionして使用します。ConnectionはNameごとに生成され、同じNameは同じInstanceを再利用します。

`#[Transactional]`のDefaultとNamed ConnectionもこのSnapshotに対してBuild時に検証します。この検証はConnection Nameだけを使い、Databaseへの接続やCredentialのBuild Artifactへの保存を行いません。AOP Proxyは`build.container`と同じDirectoryの`aop/`へ自動生成されるため、利用者向けの追加Config Keyはありません。

After Commit Callbackの失敗通知をApplication監視基盤へ送る場合は、`BlackOps\Database\AfterCommitFailureReporter`をService Providerで登録します。未登録時はFramework Default ReporterがPSR-3／Monolog経由で標準ErrorへService、Method、存在するOperation／Attempt／Correlation／Causation IDだけを記録します。Callback引数、Throwable Message／Trace、Database CredentialはDefault Logへ展開しません。

HTTP、Worker、Migration、Outcome、Retentionは`framework.connection`と安全なPostgreSQL Identifierである`framework.schema`を使用します。Framework StoreとDefaultが同じNameならApplication ServiceとFramework Storeは同じConnection Instanceを共有します。Build ArtifactにはConnection ParameterやCredentialを保存せず、Build CommandもDatabaseへ接続しません。

従来の単一Connection形式も互換Shorthandとして受理し、一つのDefault／Framework Connectionへ正規化します。

```php
return [
    'connection' => ['driver' => 'pdo_pgsql'],
    'schema' => 'blackops',
];
```

## Deferred Worker

```php
return [
    'worker' => [
        'id' => $_ENV['BLACKOPS_WORKER_ID'] ?? 'worker-1',
        'lease_seconds' => 30,
        'heartbeat_seconds' => 10,
        'grace_seconds' => 20,
        'continue_after_handler_failure' => false,
    ],
];
```

`execution.worker.id`はClaimのLease Ownerと、Journalへ記録するWorker System Actorの両方に使います。Actor TypeはFrameworkが`system`へ固定します。同じProcess内のMain ConnectionとHeartbeat ConnectionはWorker IDを共有しますが、DBAL Connection Instanceは分離されます。

Deferred Operationの受付ActorはTransport Contextへ維持されます。WorkerがAttemptを開始すると、origin／authorization Actorは受付時のまま、execution Actorだけが`execution.worker.id`／`system`へ置き換わります。Worker用の別Actor設定はありません。

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

## HTTP Middleware

```php
return [
    'http' => [
        App\UserInterface\Http\Middleware\RequestIdMiddleware::class,
        BlackOps\Http\Authentication\AuthenticationMiddleware::class,
    ],
];
```

`http`はPSR-15 MiddlewareのService IDまたはClass名を、外側から内側の順で並べたListです。同じEntryを複数回登録できません。Frameworkは順序を変更せず、数値Priorityも使用しません。

ClassがService Providerで未登録の場合、Build時にPSR-15 Middlewareであることを検証してAutowired Public Serviceへ登録します。Constructor InterfaceのBindingや具象Instanceが必要なMiddlewareは、`app.php`のService Providerで同じService IDを登録してください。Providerの明示登録が自動登録より優先されます。

File欠落または空Listでは、Operation HTTP Handlerを直接実行します。存在しないService IDやPSR-15でないServiceはBuildまたはHTTP Runtime起動時に安全なErrorとして拒否します。

Quickstartは`ApplicationServiceProvider`でApplication固有AuthenticatorをPublic ContractへBindingし、Framework MiddlewareをGlobal Pipelineへ登録します。

```php
// config/app.php
return [
    'services' => [App\ApplicationServiceProvider::class],
];
```

```php
// config/middleware.php
return [
    'http' => [BlackOps\Http\Authentication\AuthenticationMiddleware::class],
];
```

Quickstartの`SampleTokenAuthenticator`は`SAMPLE_API_TOKEN`をConstructorで一度だけ読み、RequestごとにはEnvironmentを参照しません。未設定、空文字、空白だけの値はRuntime構成Errorとして拒否し、既知のDefault TokenへFallbackしません。Local値は`.env.example`だけで提供します。Production Applicationは認証方式とSecret Sourceを選び、Credentialではなく`ActorRef`だけをFrameworkへ渡します。

BootstrapのLoading Boundaryは[Application Bootstrap](application-bootstrap.md)、実行Commandは[Project CLI](project-cli.md)を参照してください。
