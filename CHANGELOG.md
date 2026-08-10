# Changelog

BlackOpsはExperimentalです。1.xのMinor Release間でも破壊的変更を行う場合があり、Backward Compatibilityは保証しません。移行手順は[UPGRADE.md](UPGRADE.md)を参照してください。

このFileは[Keep a Changelog](https://keepachangelog.com/ja/1.1.0/)の形式に基づきます。

## [Unreleased]

このUnreleased entryは未公開の`1.2.0` Release Candidateを対象とします。公開済み`1.1.0`の記録、Tag、Release、Packagist metadataは変更しません。完全なRelease GateとPublicationは後続Taskです。

### Added

- PSR-15 HTTP Middleware、Authentication／Authorization、ActorContext、`#[Authorize]`、Deferred実行時の再認可を追加した。
- Named Doctrine DBAL Connection、`#[Transactional]`／`#[AfterCommit]`、Transaction Lifecycle、Worker Connection Lifecycleを追加した。
- Operation Diagnostics、Failure／Status／Outcome Query、Local Viewer、Frontend Contract Manifest、Typed Operation Object／Fetchを追加した。
- Session Authentication、Environment／SAPI Runtime、UUIDv7 Generator、Framework-owned Seeder／`database:seed`／`make:seeder`を追加した。
- Idempotency、Transactional Outbox／Dead Letter Retry、Canonical Observer Replay、Canonical `#[Deferred]` child dispatchを追加した。
- Tenant Context、Protected Storage Envelope／Key Rotation、Structured JSONL v1、OpenTelemetry Trace／Metric、Health／Readinessを追加した。
- Scheduled Application Operation（`ScheduledBy`と`operation:schedule:run`）、Console Operation、Framework-owned Transaction Proxy Contractを追加した。

### Changed

- Application Bootstrapは`withEnvironmentFile()`とFramework-owned `SapiRuntime`を明示的に構成し、Application-owned Entrypoint／Config／Generated SourceをFramework Updateで上書きしない境界へ整理した。
- Skeleton SourceはFramework `^1.2`を要求し、Framework root／Telemetry scope／Candidate Consumerは未公開`1.2.0`へ同期した。公開済みStable `1.1.0` install journeyは維持した。
- Framework／Application Migrationは既存Schemaを前提に順序実行する。`Version20260808000000.php`／`Version20260808010000.php`を含むProtected Storage migrationは非空Table／既存Plaintextをfail-closedに拒否し、不可逆UpgradeとしてBackup／Key準備を要求する。
- Journal／Outcome参照、Frontend生成、Scheduler、Seeder、Outbox、ObservabilityをApplication-owned ConfigurationとCompiled Artifactへ統合した。
- `CanonicalJournalReader`／`OutcomeReader`から直接のPublicApi markerを外し、Infrastructure SPIへ再分類した。Readerの型／MethodはPublicApi aggregate `CanonicalJournalStore`／`OutcomeStore`の実装境界には残るが、Application end-user readsは認可済みのDefault-deny OperationData Queryへ移行する。JSONL Envelopeは`kind`／`schemaVersion`／`attempt`／`telemetry`を含む現行Schemaへ更新した。
- `EphemeralOutcome`はCredential等をHTTP Responseへ一度だけ返し、Journal／Outcome／Status／Generated Artifactへ保存しない境界とした。Transactional Serviceを利用するApplicationはFramework-owned Proxy Profileを選択し、Stable `1.1.0`とCandidateのどちらにもRay package removalは存在しない。

### Removed

- `CanonicalJournalReader`／`OutcomeReader`から直接のPublicApi designation／markerを外した。型／MethodはPublicApi aggregate Storeの実装境界へ残し、既定拒否のTenant／Actor／Purpose付きOperationData Queryを利用してInternal SPI ReaderをApplication end-userへ公開しない。
- Stable Quickstartが直接宣言していた`vlucas/phpdotenv`と`nyholm/psr7`はFramework RuntimeのDependencyとして整理し、`nyholm/psr7-server`と`laminas/laminas-httphandlerrunner`はCandidate QuickstartがImportしないためApplication Composerから削除した。Applicationが実際にImportするPackageは引き続きApplicationが宣言する。

### Fixed

- Protected StorageのTenant／Purpose／AAD binding、XChaCha20-Poly1305 envelope、Key Rotation checkpoint、Sensitive／high-cardinality diagnostics guardをfail-closedにした。
- Structured JSONLの`kind`／`schemaVersion`／`attempt`／`telemetry`境界、Trace／Metric scope、Provider Failure isolation、Local Collector／LGTM Consumer cleanupを固定した。
- Framework ProxyのSignature／DI／Lifecycle／AfterCommit ownership、Artifact hash／Build ID drift、Rollback、no-fallbackを検証した。
- Version inventory、Stable／Candidate表示、Actual annotated Stable `1.1.0`→Local `1.2.0` Framework Update Consumerを追加し、Application-owned Source不変を検証する。

### Known Limitations

- `1.2.0`は未公開Experimental Release Candidateであり、Latest Stable、Production Ready、Packagist、Skeleton Distribution、GitHub Releaseではない。1.x Minor間のBackward Compatibilityを保証しない。
- 完全なFull Gate、Release Tag、Remote Publication、Packagist反映、Remote create-project smokeは後続Release Gateで実行する。
- Database／Deployment／Process Supervision／Credential、Session／Tenant Policy、Storage Key Provider、OpenTelemetry SDK／Exporter／Collector、Frontend HostはApplication／Infrastructureの責務である。
- FrameworkはPostgreSQL Reference Transportを提供するが、SQLite／MySQL／SQS／Kafka、Remote Observability Backend、Public Hosted Community Boardを提供しない。
- Stable `1.1.0`後に9つのCandidate PostgreSQL Migrationを追加した。`Version20260724000000.php`、`Version20260803000000.php`、`Version20260808000000.php`、`Version20260808010000.php`、`Version20260808100000.php`の5つは明示的に不可逆であり、残り4つもdownはデータ削除／再構成を伴うためBackupなしのDowngradeを保証しない。
- Frontend Contract、Diagnostics Viewer、Protected Storage、Session Authentication、Transactional Outbox／Replay、Scheduled Application OperationはRepository `main`のExperimental Surfaceであり、Stable `1.1.0`の既存Canonical Journal／Outcome／Maintenance Scheduler契約とは別の追加Surfaceである。

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
