# Project CLI Reference（Command一覧）

Project Rootの`blackops`はApplication所有の薄いEntrypointです。Framework Packageが提供するCommandを、ApplicationのConfiguration Snapshotから起動します。EntrypointへCommand実装をCopyしないため、`composer update blackops/framework`後は同じ入口から更新済みCommandを利用できます。

```bash
php blackops list
php blackops help build:compile
```

`list`はDatabase接続、Migration Scan、Compiled Container、PCNTL、Retention Runtimeを要求しません。ValidなCommand ManifestがあればApplication CommandのMetadataだけを表示します。Command固有の`help`は、そのCommandのDefinitionを得るためCompiled Containerから一度だけ解決します。

## BuildとDiscovery

```text
operation:list
build:compile
```

Operation ListとBuildだけが`config/operations.php`のOperation Source Rootを探索します。Buildはさらに`config/app.php`の`command_discovery` RootからSymfony `#[AsCommand]`を探索し、Operation Manifest、HTTP Manifest、Frontend Contract Manifest、Command Manifest、DI Containerを同じBuild IDで生成します。TypeScript Source Treeは変更しません。

Command ManifestがMissing／Invalid／Build ID不一致の場合、Application Commandは登録せずFramework Commandだけで起動します。Source ScanへFallbackしないため、壊れたArtifactからも`php blackops build:compile`で復旧できます。

## Operation Command

Operation Classへ`#[ConsoleCommand]`を付けると、`build:compile`がCommand ManifestへCLI契約を固定します。`OperationValue`のpublic constructor-promotedな`string`、`int`、`float`、`bool` PropertyはLong Named Optionになり、例えば`$orderReference`は`--order-reference`になります。

```php
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

#[ConsoleCommand('order:create', 'Create an order.')]
#[OperationType('order.create')]
final readonly class CreateOrder implements Operation
{
    public function handle(CreateOrderValue $value): OrderCreated
    {
        return new OrderCreated($value->reference, 'created');
    }
}

final readonly class CreateOrderValue implements OperationValue
{
    public function __construct(public string $reference) {}
}

final readonly class OrderCreated implements Outcome
{
    public function __construct(
        public string $reference,
        public string $status,
    ) {}
}
```

```bash
php blackops build:compile
php blackops order:create --reference=A-100 --json
```

```json
{"schemaVersion":1,"status":"completed","outcome":{"reference":"A-100","status":"created"}}
```

CommandはHTTPと同じValidation、Authorization、Inline／Deferred Lifecycle、Journal、Transactionを通ります。`--json`は一行JSONをstdoutへ出し、成功／Deferred受付はExit 0、CLI Binding／ValidationはExit 2、その他Rejected／Internal ErrorはExit 1です。位置引数、未知Option、配列／Object／Enum入力、`#[Sensitive]`を含むValueやOutcomeは受け付けません。省略できるのはConstructor Defaultを持つOptionだけです。

Global `list`とOperation Commandの`help`はManifest Metadataだけを使い、Handler、Container、Database、Actor Providerを解決しません。実行時だけArtifactからOperation Runtimeを構成します。

## Frontend

```text
frontend:generate
frontend:check
```

`frontend:generate`は現在のFrontend Contract Artifactから`config/frontend.php`のOutputへFramework-neutral TypeScript ESMを全再生成します。`frontend:check`は生成せず、Expected Treeと既存TreeのPath／Bytes／余剰Fileを比較します。Checkの固定ContractはFresh 0、Missing／Drift 1、Invalid 2です。どちらも`build:compile`を暗黙実行せず、ArtifactのMissing、Stale、Build ID不一致を拒否します。

| Command | Exit | Meaning |
| --- | ---: | --- |
| `frontend:generate` | `0` | Atomicな生成とRead-back検証が完了した |
| `frontend:generate` | `1` | 生成または安全な置換に失敗した |
| `frontend:check` | `0` | Generated TreeがFresh |
| `frontend:check` | `1` | OutputがMissingまたはDrift |
| `frontend:check` | `2` | Config、Artifact、Generated Contract、InspectionがInvalid |

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

Frontend BridgeはRepository `main`のExperimental Surfaceであり、Stable `1.1.0`には含まれません。

## Database

```text
database:status
database:migrate
```

Status、Dry-run、MigrateはFramework MigrationとApplication Migrationを一つの明示Deployment Flowで扱います。

## Execution

```text
worker:run
operation:inspect <operation-id> [--json]
operation:viewer
```

Deferred Workerは対象Command実行時にだけDatabase、Transport、Lifecycle、Heartbeatを構成します。

`operation:inspect`は一つのOperation IDからSafe Diagnosticsを読みます。既定はHuman形式、`--json`は`schemaVersion: 1`のMachine-readable形式です。成功時はDataをstdout、Command自体のErrorをstderrへ出します。

| Exit | Meaning |
| --- | --- |
| `0` | Operationが見つかり、表示できた |
| `2` | UUIDv7ではない、またはIDがない |
| `3` | Missing／Fully purged／Unauthorizedを区別せず`operation.unavailable`とした |
| `4` | Storage／Decode／Integrity Error |

`operation:viewer`はRead-onlyのLocal Viewerを明示起動します。`config/diagnostics.php` のEnable Gateも必要で、起動ごとに一度だけBootstrap URLをstdoutへ出します。既定は無効、Quickstart Localだけ有効です。BindはLoopbackに限定され、GET／HEAD以外は変更処理として受け付けません。

## RetentionとScheduler

```text
retention:plan
retention:purge
scheduler:run
scheduler:daemon
```

PlanとDry-runは変更を行いません。Purgeは`--confirm`を要求し、Schedulerも明示Commandでのみ開始します。

## Generator

```text
make:operation
make:migration
make:auth
```

`make:operation`と`make:migration`はExperimental Stable `1.1.0`で利用できます。`make:auth`はRepository `main`のExperimental Commandで、Application-owned Identity Domain、DBAL Adapter、Ephemeral Register／Login／Logout、Session Migrationを一度だけ生成します。詳細は[Project Generators](project-generators.md)と[Session Authentication Starter](security.md#session-authentication-starter)を参照してください。生成済みApplication SourceはFramework Updateで自動変更されません。

`1.0.0`の`bin/blackops`と`blackops:*` Project Commandは互換対象ではありません。`1.1.0`への移行ではProject Root `blackops`とPrefixなしCommandへ更新してください。
