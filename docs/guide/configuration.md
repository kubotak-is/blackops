# Configuration Reference（設定一覧）

Installed Applicationは責務別のPHP Configを`config/`に置きます。Frameworkは存在する既知Fileだけを読み、各Fileは配列、または`Environment`を受け取って配列を返すClosureを返します。

| File | Responsibility |
| --- | --- |
| `app.php` | Build Artifact、Application Service Provider、Application Command |
| `database.php` | Default／Named Doctrine DBAL ConnectionとFramework Store |
| `operations.php` | Build-time Discovery RootとOptional Operation Provider |
| `execution.php` | Worker ID、Lease、Heartbeat、Grace、Supervision |
| `journal.php` | Observed JSONL JournalのPathとDelivery Mode |
| `logging.php` | Application／Framework相関LogのJSONL Backend |
| `diagnostics.php` | Local Diagnostics ViewerのEnable GateとLoopback Address |
| `frontend.php` | Generated TypeScript ESMのApplication Root内Output |
| `middleware.php` | Global PSR-15 HTTP Middlewareの登録順 |
| `retention.php` | Payload、Journal、Outcome、Dead Letterの保持期間、Policy、Actor |

## Environment

Frameworkは`.env`を読みません。Skeletonの`bootstrap/app.php`がProcess Environmentを優先してDotenvを読み、解決済み文字列を`Application::configure(...)->withEnvironment($environment)`へ渡します。

SecretをConfig Sourceへ直書きせず、Process Manager、Container Runtime、Deployment PlatformからEnvironmentとして渡してください。Productionは`.env` Fileを必須としません。

ConfigではGlobalな`$_ENV`や`getenv()`を直接読みません。Readonly Snapshotから必要な値だけを型付きで取得します。

```php
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'name' => $env->string('APP_NAME', 'BlackOps App'),
    'port' => $env->positiveInt('APP_PORT', 8080),
    'debug' => $env->bool('APP_DEBUG', false),
    'optional_label' => $env->optionalString('APP_LABEL'),
];
```

`string()`、`int()`、`positiveInt()`、`bool()`は値が未定義でDefaultもない場合に起動を拒否します。整数はCanonicalな10進表現、Booleanは大文字小文字を区別しない`true`／`false`と`1`／`0`だけを受理します。定義済みの不正値をDefaultへ置き換えません。ErrorにはVariable名と期待型だけを含め、Raw Valueを表示しません。

Config Directoryは`withConfiguration()`で検証します。File読込とClosure評価は`create()`まで遅延し、全Closureへ同じ最終Environment Snapshotを一度だけ渡します。したがって`withEnvironment()`と`withConfiguration()`はどちらを先に呼んでも同じ結果です。Environment自体はCompiled ContainerやManifestへ保存されません。

## Operations

```php
return [
    'discovery' => [dirname(__DIR__) . '/app/Feature'],
    'providers' => [],
];
```

Discovery Rootは存在する絶対Directoryです。Application-aware BuildとOperation ListだけがSourceを探索します。Composer PackageやApplication外Sourceを追加する場合だけOperation Providerを使います。

Status参照PolicyはOperation DiscoveryではなくApplication ServiceとしてBindingします。

```php
use App\Security\ApplicationOperationStatusAuthorizer;
use BlackOps\Status\OperationStatusAuthorizer;

$services->autowire(
    OperationStatusAuthorizer::class,
    ApplicationOperationStatusAuthorizer::class,
);
```

Bindingがない場合、Frameworkは常にDenyする実装を使い、`GET /operations/{operationId}`をSafe 404にします。QuickstartのSame-origin実装はLocal Exampleです。ProductionではTenant／Resource PolicyをApplicationが所有します。

## Build Artifact

```php
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'build' => [
        'application_build_id' => $env->string('APP_BUILD_ID', 'local'),
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

Operation Manifest、HTTP Manifest、Frontend Contract Manifest、Containerは同じBuild IDで作成します。Production HTTP／Worker RuntimeはFrontend Contractを読みません。ProductionはBackend Artifact不足、Format不正、Build ID不一致時に起動を拒否し、Source DiscoveryへFallbackしません。

## Frontend Generation

```php
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'output' => dirname(__DIR__) . '/resources/js/blackops',
];
```

`config/frontend.php`はOptionalで、欠落時も上記Pathを使います。OutputはApplication Root配下の絶対Directoryだけを許可し、Application Root自身、Filesystem Root、Repository外Path、Symlinkを拒否します。設定できるのはOutputだけで、Credential、Base URL、Authentication、CSRF Token、Runtime Fetchはここへ保存しません。

`frontend:generate`と`frontend:check`はBuild済みFrontend Contractを読みます。GenerateはNon-marker Directoryを上書きせず、Temporary Treeを検証後にAtomic Replaceします。CheckはRead-onlyで、Fresh 0、Missing／Drift 1、Invalid 2を返します。Generated `resources/js/blackops/`はApplication Sourceではなく、Quickstartでは`.gitignore`対象です。

生成する各HTTP Operation Objectは`.url()`、`.toRequest()`、`.fetch()`に加えて、一回取得の`.status()`と有限待機の`.wait()`を持ちます。Base URL、Credential、Fetch、Abort Signal、DeadlineはConfigへ保存せず呼出単位で渡します。

## Database

```php
return [
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $env->string('POSTGRES_HOST'),
            'port' => $env->positiveInt('POSTGRES_PORT'),
            'dbname' => $env->string('POSTGRES_DB'),
            'user' => $env->string('POSTGRES_USER'),
            'password' => $env->string('POSTGRES_PASSWORD'),
        ],
        'analytics' => [
            'driver' => 'pdo_pgsql',
            'url' => $env->string('ANALYTICS_DATABASE_URL'),
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => $env->string('BLACKOPS_SCHEMA', 'blackops'),
    ],
];
```

`default`と`framework.connection`は`connections`内のNameを参照します。通常のRepositoryはDefault `Doctrine\DBAL\Connection`をConstructor Injectionできます。複数Databaseを選ぶServiceは`BlackOps\Database\DatabaseManager`をConstructor Injectionし、`$databases->connection('analytics')`で明示的に選びます。ConnectionはNameごとに生成され、同じNameは同じInstanceを再利用します。

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
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'worker' => [
        'id' => $env->string('BLACKOPS_WORKER_ID', 'worker-1'),
        'lease_seconds' => $env->positiveInt('WORKER_LEASE_SECONDS', 30),
        'heartbeat_seconds' => $env->positiveInt('WORKER_HEARTBEAT_SECONDS', 10),
        'grace_seconds' => $env->positiveInt('WORKER_GRACE_SECONDS', 20),
        'continue_after_handler_failure' => $env->bool('WORKER_CONTINUE_AFTER_HANDLER_FAILURE', false),
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

## Application Logging

```php
return [
    'backend' => [
        'driver' => 'jsonl',
        'stream' => dirname(__DIR__) . '/var/log/application.jsonl',
        'channel' => 'blackops',
        'minimum_level' => 'info',
    ],
];
```

Canonical Keyは`driver`、`stream`、`channel`、`minimum_level`です。Phase 14のDriverは`jsonl`だけで、Fileがない場合は`php://stderr`／`blackops`／`info`を使います。`stream`は`php://stderr`、`php://stdout`、絶対Local File Pathのみを受け付け、Relative Path、任意PHP Wrapper、Network URIを拒否します。

FrameworkはConfigをHTTP／Worker Process構成時に一度だけ検証し、RequestやLog RecordごとにFileや`$_ENV`を再読込しません。無効なDriver／Stream／Levelは起動時にFail-fastします。起動後のOpen／Write FailureはBest-effortで吸収し、元のOperation、Journal、HTTP Response、Worker Loopを変えません。Directory作成、Permission、Rotation、Disk Capacity、RetentionはApplication／運用の責務です。

## Local Diagnostics Viewer

```php
return [
    'viewer' => [
        'enabled' => true,
        'bind' => '127.0.0.1',
        'port' => 8082,
    ],
];
```

Framework既定は`enabled=false`、`127.0.0.1:8082`です。QuickstartはLocal利用のためだけ`true`にします。Commandの明示実行とEnable Gateの両方が必要で、Non-loopback Bindは設定エラーです。ViewerはCanonical Storeをそのまま表示せず、`operation:inspect`と同じSafe Diagnostics Projectionを使います。

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
