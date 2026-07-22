# 現在の提供状況（Current Status）

BlackOpsのLatest StableはFramework／Skeleton `1.1.0`です。このWebsite Sourceは`main` Document Channelであり、未ReleaseのPhase 12〜17 Surfaceも明示して説明します。WebsiteはLocal／CI Buildだけで、現在は公開していません。Stableとの差を次表で確認してください。

BlackOps固有のOperation、Claim、Journal、Outcome等は[用語集](glossary.md)で確認できます。

BlackOpsはExperimentalです。1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。ApplicationはDatabase Credential、Deployment、Process Supervision、Authentication／Authorization、Access Control、Encryption、Retention Policy、Operational Monitoringを所有します。

## Stableとmain

| Capability | Stable 1.1.0 | main Document |
| --- | --- | --- |
| Typed Self-handled Operation／Native Outcome | Available | Available |
| Inline HTTP／Deferred HTTP／Worker Retry | Available | Available |
| Lifecycle Journal／Sensitive Projection | Available | Available |
| Typed Outcome Retrieval／Retention | Available | Available |
| Composer Skeleton | Available | Available |
| Project CLI Entrypoint | Project Root `blackops` | Project Root `blackops` |
| `make:operation`／`make:migration` | Available | Available |
| Application Migration Runtime | Available | Available |
| 7 Value Validation Attribute／422 Lifecycle | Available | Available |
| FrankenPHP Worker Mode | Default Runtime | Default Runtime |
| Global PSR-15 Middleware Config | Not available | Available |
| Authentication／Durable ActorContext | Not available | Available |
| `#[Authorize]` Inline／Deferred再認可 | Not available | Available |
| Named DBAL Connection／Default Connection DI | Not available | Available |
| `#[Transactional]` Operation／Service | Not available | Available |
| Nested Required／`#[AfterCommit]` | Not available | Available |
| Long-running Connection Health Check／Reconnect | Not available | Available |
| Operation ID Diagnostics Human／JSON CLI | Not available | Available |
| Development Local Diagnostics Viewer | Not available | Available |
| Configurable Application／Framework JSONL Correlation | Not available | Available |
| Frontend Contract Manifest／Operation Object生成 | Not available | Available（Experimental） |
| `.url()`／`.toRequest()`／Typed `.fetch()` | Not available | Available（Experimental） |
| `frontend:generate`／`frontend:check` | Not available | Available（Experimental） |
| Deferred Status Query／`GET /operations/{operationId}` | Not available | Available（Experimental） |
| Generated `.status()`／finite `.wait()` | Not available | Available（Experimental） |
| Typed `Environment`／Configuration Closure | Not available | Available（Experimental） |
| Generated Bound `createBlackOpsClient()` | Not available | Available（Experimental） |
| Application `#[AsCommand]` Discovery／DI | Not available | Available（Experimental） |
| Operation `#[ConsoleCommand]` Adapter | Not available | Available（Experimental） |
| Opt-in Session Core／`make:auth` | Not available | Available（Experimental） |
| Database Seeder／`database:seed`／`make:seeder` | Not available | Available（Experimental） |
| BlackOps Board Full-stack Reference Application | Not available | Available（Experimental、Local／CI only） |

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
- HTTP Operationから生成するFramework-neutral TypeScript ESM Operation Object
- Readonly Metadata、`.url()`、`.toRequest()`、Typed `.fetch()`とFrontend Drift Check
- 認可前Subject Projection、Unknown／Deny 404、認可済みExpired 410を持つPublic Status Query／HTTP Resource
- 7 Stateを一回取得するGenerated `.status()`と、Abort／Deadline必須の有限`.wait()`
- 起動時に一回評価するTyped `Environment`／Configuration Closure
- Server Fetch、Base URL、Header、CredentialをRequest単位で固定するGenerated Bound Client
- Symfony `#[AsCommand]`のBuild-time Discovery／Constructor DIと`#[ConsoleCommand]` Operation Adapter
- Opaque Token、Hash保存、TTL、Touch、Rotation、Revocation、Cleanupを持つOpt-in Session Coreと`make:auth`
- Build-time Discovery、Compiled Container DI、明示順の子Seederを持つDatabase Seederと`database:seed`／`make:seeder`
- Application-owned Identity、Ephemeral Auth Operation、SvelteKit BFF、Post／Comment、Deferred Digest、Real Browser E2Eを統合した[BlackOps Board](community-board.md)

BlackOps BoardはRepository `main`だけのExperimental Local Reference Applicationです。Stable `1.1.0` Skeletonには含まれず、公開Hostも提供していません。Documentation Websiteと同様にSource、Local／CI Build、Consumer Evidenceだけを維持し、External Publication／Deployは将来の明示Taskへ分離しています。

## Known Constraints

- Session Coreは提供するが、User／Password／Registration Policy、Cookie／CSRF、JWT／OAuth／API Key、Actor Repository、Permission StoreはApplication責務
- Production Status Authorization Policy、Tenant Model、Role／Permission Repositoryは提供しない
- 無限Wait、任意Backoff／Jitter、Global Generated Client、Cache／Offline Queueは提供しない
- Transactional Outbox Relayは提供しない
- Canonical Journal／Transport PayloadのEncryption Adapterは提供しない
- Remote OpenTelemetry、CloudWatch、SQS、Kafka、SQLite、MySQL Adapterは提供しない
- Observer Replay CLI、Admin UI、Scheduled Operation Strategyは提供しない
- Array／Nested ObjectのHTTP Binding、宣言的DB照合、Cross-field Attribute、Custom Callbackは提供しない。`Count` Validatorは実装済みだが現行HTTP BinderからArrayを渡せない
- Production CertificationやExperimental Public API Contractを超える互換性保証は提供しない。1.x Minor間のBackward Compatibilityも保証しない
- DiagnosticsのPublic PHP Query API、Remote Viewer、OpenTelemetry／Metric／Collectorは提供しない

これらの不在はApplication側のSecurity／Operations設計が不要であることを意味しません。Stableと`main`の差を確認し、Deployment前に必要なAdapterと運用責務を明示してください。
