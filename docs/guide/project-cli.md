# Project CLI Reference（Command一覧）

Project Rootの`blackops`はApplication所有の薄いEntrypointです。Framework Packageが提供するCommandを、ApplicationのConfiguration Snapshotから起動します。EntrypointへCommand実装をCopyしないため、`composer update blackops/framework`後は同じ入口から更新済みCommandを利用できます。

```bash
php blackops list
php blackops help build:compile
```

`list`／`help`はDatabase接続、Migration Scan、Artifact Load、PCNTL、Retention Runtimeを要求しません。

## BuildとDiscovery

```text
operation:list
build:compile
```

Operation ListとBuildだけが`config/operations.php`のSource Rootを探索します。BuildはOperation Manifest、HTTP Manifest、Frontend Contract Manifest、DI Containerを同じBuild IDで生成します。TypeScript Source Treeは変更しません。

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
```

この2 CommandはExperimental Stable `1.1.0`で利用できます。詳細は[Project Generators](project-generators.md)を参照してください。生成済みApplication SourceはFramework Updateで自動変更されません。

`1.0.0`の`bin/blackops`と`blackops:*` Project Commandは互換対象ではありません。`1.1.0`への移行ではProject Root `blackops`とPrefixなしCommandへ更新してください。
