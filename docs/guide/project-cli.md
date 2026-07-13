# Project CLI Reference（Command一覧）

Project Rootの`blackops`はApplication所有の薄いEntrypointです。Framework Packageが提供するCommandを、ApplicationのConfiguration Snapshotから起動します。EntrypointへCommand実装をCopyしないため、`composer update blackops/framework`後は同じ入口から更新済みCommandを利用できます。

```bash
php blackops list
php blackops help blackops:build:compile
```

`list`／`help`はDatabase接続、Migration Scan、Artifact Load、PCNTL、Retention Runtimeを要求しません。

## BuildとDiscovery

```text
blackops:operation:list
blackops:build:compile
```

Operation ListとBuildだけが`config/operations.php`のSource Rootを探索します。BuildはOperation Manifest、HTTP Manifest、DI Containerを同じBuild IDで生成します。

## Database

```text
blackops:database:status
blackops:database:migrate
```

Status、Dry-run、MigrateはFramework MigrationとApplication Migrationを一つの明示Deployment Flowで扱います。

## Execution

```text
blackops:worker:run
```

Deferred Workerは対象Command実行時にだけDatabase、Transport、Lifecycle、Heartbeatを構成します。

## RetentionとScheduler

```text
blackops:retention:plan
blackops:retention:purge
blackops:scheduler:run
blackops:scheduler:daemon
```

PlanとDry-runは変更を行いません。Purgeは`--confirm`を要求し、Schedulerも明示Commandでのみ開始します。

## Generator Channel

```text
make:operation
make:migration
```

この2 Commandは`main`に実装済みですがLatest Stable `1.0.0`には含まれません。次回Stable Releaseへ含まれるまでは、Stable Projectで利用可能とみなさないでください。詳細は[Project Generators](project-generators.md)を参照してください。

Latest Stable `1.0.0`はEntrypointを`bin` Directory内に配置します。このPageのProject Root Command、Generator、Application Migration Runtimeは`main`の未Release Surfaceです。生成済みApplication SourceはFramework Updateで自動変更されません。
