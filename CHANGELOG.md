# Changelog

BlackOpsはExperimentalです。1.xのMinor Release間でも破壊的変更を行う場合があり、Backward Compatibilityは保証しません。移行手順は[UPGRADE.md](UPGRADE.md)を参照してください。

このFileは[Keep a Changelog](https://keepachangelog.com/ja/1.1.0/)の形式に基づきます。

## [Unreleased]

### Added

- Public `Seeder`／`SeederRunner`、Build-time Seeder Discovery、Framework-owned `database:seed`／`make:seeder`を追加した。
- Skeletonへ標準`app/Infrastructure/Seed/DatabaseSeeder.php`を追加した。
- Idempotency、Transactional Outbox Relay、Dead Letter再開、Canonical Observer Replayを追加した。

### Changed

- Community Boardの決定論的Fixtureを標準Root SeederとCompiled Container DIから実行するよう変更した。
- Installed Skeleton／Community BoardのBootstrapを`withEnvironmentFile()`へ統一し、Classic／Worker EntrypointをFramework-owned `SapiRuntime`呼出へ統一した。
- Guide、Quickstart説明、内部Reference、Phase StatusをPhase 19の現行Capabilityへ同期した。

### Removed

- Community Boardの専用`app:seed` Symfony CommandとApplicationの`symfony/console`直接Dependencyを削除した。
- Quickstart／Community BoardからFrameworkが所有するDotenv、PSR-7／SAPI、UUIDv7の未使用Direct Dependencyを削除した。DBAL／Migrationsは実Importのため維持する。

## [1.1.0] - 2026-07-16

### Added

- Typed Self-handled OperationのValueを検証する`NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Choice`、`Count` Attributeと、型付き`Violation`を追加した。
- `make:operation`と`make:migration`をBlackOps CLIへ追加した。
- `App\Migrations` NamespaceのApplication MigrationをFramework Migrationと同じCommandで検出・実行できるようにした。
- FrankenPHP Worker Modeと`public/worker.php`をQuickstartのDefault HTTP Runtimeとして追加した。Classic Modeは明示的なFallbackとして利用できる。
- malformed JSONとNon-object JSONに安定した400 Response、Binding／Validation FailureにOperation IDとViolationを含む422 Responseを追加した。
- `RejectionReason::validation()`へOptional Violation一覧と`violations()` Getterを追加した。既存の1引数Callは維持される。

### Changed

- 公式BlackOps CLI Entrypointを`bin/blackops`からProject Rootの`blackops`へ移動した。
- BlackOps CLIの9 Commandから`blackops:` Prefixを削除した。たとえば`blackops:build:compile`は`build:compile`、`blackops:worker:run`は`worker:run`になった。
- QuickstartのDefault HTTP RuntimeをClassic FrankenPHPからWorker Modeへ変更した。
- Skeletonは`blackops/framework: ^1.1`を要求する。
- Validation Backendとして`symfony/validator:^7.4`をRuntime Dependencyへ追加した。

### Removed

- 旧Project Entrypoint `bin/blackops`の互換性を削除した。
- 旧BlackOps CLI名`blackops:build:compile`、`blackops:operation:list`、`blackops:database:status`、`blackops:database:migrate`、`blackops:worker:run`、`blackops:retention:plan`、`blackops:retention:purge`、`blackops:scheduler:run`、`blackops:scheduler:daemon`のAliasとFramework予約を削除した。

### Known Limitations

- Experimental Releaseであり、1.x Minor間のBackward CompatibilityとProduction Readinessを保証しない。
- Authentication／Authorization、Journal／Outcome参照のAccess Control、Canonical Payloadの暗号化はApplicationの責務である。
- Deferred Status／OutcomeのHTTP Endpoint、Generated Client SDK、Transactional Outbox、Observer Replay CLIは提供しない。
- HTTP BinderはArray／Nested Objectを扱わない。`Count`は利用できるが、現行HTTP BinderからArray Valueを構築できない。
- SQLite／MySQL、SQS／Kafka、Remote OpenTelemetry／CloudWatch Adapterは提供しない。

[1.1.0]: https://github.com/kubotak-is/blackops/compare/1.0.0...1.1.0
# Unreleased

- Add canonical observer replay with bounded PostgreSQL selection, checkpoint/resume, and JSONL `recordId` envelopes.
