# Releases

BlackOpsのStableはFramework／Skeleton `1.1.0`です。Repository `main`の未Release Surfaceもこのドキュメントで明示します。Documentation WebsiteはCloudflare Pages（`https://blackops-php.pages.dev`）へ公開しています。Stableとの差を次表で確認してください。

BlackOps固有のOperation、Claim、Journal、Outcome等は[Glossary](glossary.md)で確認できます。

BlackOpsはExperimentalです。1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。ApplicationはDatabase Credential、Deployment、Process Supervision、Authentication／Authorization、Access Control、Encryption、Retention Policy、Operational Monitoringを所有します。

## Stableとmain

| Capability | Stable 1.1.0 | main Document |
| --- | --- | --- |
| Typed Self-handled Operation／Native Outcome | 利用可 | 利用可 |
| Inline HTTP／Deferred HTTP／Worker Retry | 利用可 | 利用可 |
| Lifecycle Journal／Sensitive Projection | 利用可 | 利用可 |
| Typed Outcome Retrieval／Retention | 利用可 | 利用可 |
| Composer Skeleton | 利用可 | 利用可 |
| BlackOps CLI Entrypoint | Project Root `blackops` | Project Root `blackops` |
| `make:operation`／`make:migration` | 利用可 | 利用可 |
| Application Migration Runtime | 利用可 | 利用可 |
| 7 Value Validation Attribute／422 Lifecycle | 利用可 | 利用可 |
| FrankenPHP Worker Mode | 既定Runtime | 既定Runtime |
| Global PSR-15 Middleware Config | 未提供 | 利用可 |
| Authentication／Durable ActorContext | 未提供 | 利用可 |
| `#[Authorize]` Inline／Deferred再認可 | 未提供 | 利用可 |
| Named DBAL Connection／Default Connection DI | 未提供 | 利用可 |
| `#[Transactional]` Operation／Service | 未提供 | 利用可 |
| Nested Required／`#[AfterCommit]` | 未提供 | 利用可 |
| Long-running Connection Health Check／Reconnect | 未提供 | 利用可 |
| OperationalHealthQuery／明示Health Route・CLI Adapter | 未提供 | 利用可（試験的、Application登録） |
| Operation ID Diagnostics Human／JSON CLI | 未提供 | 利用可 |
| Development Local Diagnostics Viewer | 未提供 | 利用可 |
| Configurable Application／Framework JSONL Correlation | 未提供 | 利用可 |
| OpenTelemetry API-only Trace／Metric Provider Composition | 未提供 | 利用可（試験的、Application SDK／Exporter） |
| Frontend Contract Manifest／Operation Object生成 | 未提供 | 利用可（試験的） |
| `.url()`／`.toRequest()`／Typed `.fetch()` | 未提供 | 利用可（試験的） |
| `frontend:generate`／`frontend:check` | 未提供 | 利用可（試験的） |
| Deferred Status Query／`GET /operations/{operationId}` | 未提供 | 利用可（試験的） |
| Generated `.status()`／finite `.wait()` | 未提供 | 利用可（試験的） |
| Typed `Environment`／Configuration Closure | 未提供 | 利用可（試験的） |
| Generated Bound `createBlackOpsClient()` | 未提供 | 利用可（試験的） |
| Application `#[AsCommand]` Discovery／DI | 未提供 | 利用可（試験的） |
| Operation `#[ConsoleCommand]` Adapter | 未提供 | 利用可（試験的） |
| Opt-in Session Core／`make:auth` | 未提供 | 利用可（試験的） |
| Database Seeder／`database:seed`／`make:seeder` | 未提供 | 利用可（試験的） |
| BlackOps Board Full-stack Reference Application | 未提供 | 利用可（試験的、Local／CI only） |
| Optional Idempotency Key／Duplicate Replay | 未提供 | 利用可（試験的、Actor-scoped） |
| Transactional Outbox Relay／Retry／Fencing／Dead Letter | 未提供 | 利用可（試験的、at-least-once） |
| Canonical Observer Replay／Checkpoint／Resume | 未提供 | 利用可（試験的、Canonical read-only） |
| TenantRef／Entry Tenant Propagation | 未提供 | 利用可（試験的、HTTP／Console／Scheduled／Dispatch） |
| Default-deny Journal／Outcome Data Query | 未提供 | 利用可（試験的、Tenant／Actor／Purpose必須） |
| BOPD v1 Protected Storage／StorageKeyProvider | 未提供 | 利用可（試験的、XChaCha20-Poly1305） |
| `storage:protection:plan`／`rotate`／Resume | 未提供 | 利用可（試験的、Bounded／CAS／Audit） |
| Scheduled Application Operation／`ScheduledBy`／one-shot CLI | 未提供 | 利用可（試験的、`operation:schedule:run`） |

Stable Applicationを作る場合はVersionを明示します。

```bash
composer create-project blackops/skeleton my-app 1.1.0
```

## Available Runtime Surface

- PHP 8.5、PSR-7／15／17 HTTP Boundary、FrankenPHP Reference Runtime
- Typed Operation、OperationValue、Native Outcome／Void、業務拒否Exception
- Inline LifecycleとJSON Response
- PostgreSQL Deferred受付、Claim、Retry、Heartbeat、Fencing、Crash Recovery、Dead Letter
- Operation IDで取得するTyped Outcome
- Canonical JournalとSensitive Observed Projection
- Versioned Operation／HTTP ManifestとCompiled Symfony DI Container
- BlackOps所有の7 Value Validation AttributeとSymfony Validator Backend
- Global PSR-15 HTTP Middleware、Authentication Contract、Durable ActorContext
- `#[Authorize]`とInline／Deferred Worker再認可
- Named DBAL Connection、Default `Connection`／`DatabaseManager` Constructor Injection
- Operation／Container管理Serviceの`#[Transactional]`、Nested Required、Rollback-only
- `#[AfterCommit]` Queue、Failure Reporter、HTTP／Deferred Connection Lifecycle
- Doctrine PostgreSQL Migration
- Payload、Journal、Outcome、Dead LetterのRetention、Hold、Purge Audit、Scheduler
- Operation IDからLifecycle／Attempt／Outcome Availabilityを読むSafe Human／JSON Diagnostics
- 既定無効・Loopback限定・Token必須・Read-onlyのDevelopment Local Viewer
- Process起動時に一度解決するApplication／Framework JSONL LoggingとOperation／Attempt／Correlation ID相関
- OpenTelemetry API-only Provider Composition、W3C Context、固定Metric、OperationalHealth Query（Application-owned）
- HTTP Operationから生成するFramework-neutral TypeScript ESM Operation Object
- Readonly Metadata、`.url()`、`.toRequest()`、Typed `.fetch()`とFrontend Drift Check
- 認可前Subject Projection、Unknown／Deny 404、認可済みExpired 410を持つPublic Status Query／HTTP Resource
- 7 Stateを一回取得するGenerated `.status()`と、Abort／Deadline必須の有限`.wait()`
- 起動時に一回評価するTyped `Environment`／Configuration Closure
- Server Fetch、Base URL、Header、CredentialをRequest単位で固定するGenerated Bound Client
- Symfony `#[AsCommand]`のBuild-time Discovery／Constructor DIと`#[ConsoleCommand]` Operation Adapter
- Opaque Token、Hash保存、TTL、Touch、Rotation、Revocation、Cleanupを持つOpt-in Session Coreと`make:auth`
- Build-time Discovery、Compiled Container DI、明示順の子Seederを持つDatabase Seederと`database:seed`／`make:seeder`
- Application-owned Identity、Ephemeral Auth Operation、SvelteKit BFF、Post／Comment、Deferred Digest、Real Browser E2Eを統合した[BlackOps Board Reference Application](community-board.md)

BlackOps BoardはRepository `main`だけの試験的Local Reference Applicationです。Stable `1.1.0` Skeletonには含まれず、公開Hostも提供していません。BoardはSource、Local／CI Build、利用者向け検証記録だけを維持し、外部公開／デプロイの対象外です。Documentation Websiteの公開先は上記Cloudflare Pagesです。

## Known Constraints

- Session Coreは提供するが、User／Password／Registration Policy、Cookie／CSRF、JWT／OAuth／API Key、Actor Repository、Permission StoreはApplication責務
- Production Status Authorization Policy、Tenant Model、Role／Permission Repositoryは提供しない
- 無限Wait、任意Backoff／Jitter、Global Generated Client、Cache／Offline Queueは提供しない
- Transactional Outboxは同一Named Connectionへの原子登録、有限Relay、Retry／Backoff、Lease／Fencing、Dead Letter再開を提供する（at-least-once。外部配送のExactly Onceは提供しない）
- Canonical Journal、Deferred Payload／Context、Outcome、Outbox、Dead Letter、Idempotencyの復元可能FieldはBOPD v1 Envelopeで保護する。Key Material、KMS Vendor操作、Replica／Backup上の旧Key確認、旧Key削除は提供しない
- Remote OpenTelemetry Backend、CloudWatch、SQS、Kafka、SQLite、MySQL Adapterは提供しない。Local Docker CollectorはApplication／Consumerが明示的に起動する検証手順だけを提供する
- Observer Replay CLIはCanonical Journalを変更せず、現在のSensitive Projectionを再適用する有限Batch／Checkpoint／Resume／Audit操作として提供する。Admin UIは提供しない
- Array／Nested ObjectのHTTP Binding、宣言的DB照合、Cross-field Attribute、Custom Callbackは提供しない。`Count` Validatorは実装済みだが現行HTTP BinderからArrayを渡せない
- Production CertificationやExperimental Public API Contractを超える互換性保証は提供しない。1.x Minor間のBackward Compatibilityも保証しない
- DiagnosticsのPublic PHP Query APIとRemote Viewerは提供しない。OpenTelemetry／Metric／Healthの本番Backend、Dashboard、Collector自動起動も提供しない
- Application Schedule Daemon、Supervisor／Kubernetes／systemd Manifest Generator、Schedule-specific Retentionは提供しない。one-shot CLIは[Scheduled Operation](scheduled-operation.md)の手順で外部Supervisorから起動する

これらの不在はApplication側のSecurity／Operations設計が不要であることを意味しません。Stableと`main`の差を確認し、Deployment前に必要なAdapterと運用責務を明示してください。
