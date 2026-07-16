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

Operation ListとBuildだけが`config/operations.php`のSource Rootを探索します。BuildはOperation Manifest、HTTP Manifest、DI Containerを同じBuild IDで生成します。

## Database

```text
database:status
database:migrate
```

Status、Dry-run、MigrateはFramework MigrationとApplication Migrationを一つの明示Deployment Flowで扱います。

## Execution

```text
worker:run
```

Deferred Workerは対象Command実行時にだけDatabase、Transport、Lifecycle、Heartbeatを構成します。

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
