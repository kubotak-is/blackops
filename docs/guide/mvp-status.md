# Current Status

BlackOpsのLatest StableはFramework／Skeleton `1.0.0`です。このWebsiteは`main` Branchの最新Documentを公開するため、Stableへまだ含まれない機能を説明する場合があります。

MVP CompleteとProduction Readyは同じ意味ではありません。ApplicationはDatabase Credential、Deployment、Process Supervision、Authentication／Authorization、Access Control、Encryption、Retention Policy、Operational Monitoringを所有します。

## Stableとmain

| Capability | Stable 1.0.0 | main Document |
| --- | --- | --- |
| Typed Self-handled Operation／Native Outcome | Available | Available |
| Inline HTTP／Deferred HTTP／Worker Retry | Available | Available |
| Lifecycle Journal／Sensitive Projection | Available | Available |
| Typed Outcome Retrieval／Retention | Available | Available |
| Composer Skeleton／Project `bin/blackops` | Available | Available |
| `make:operation`／`make:migration` | Not included | Implemented; unreleased |
| Application Migration Runtime | Not included | Implemented; unreleased |

Stable Applicationを作る場合はVersionを明示します。

```bash
composer create-project blackops/skeleton my-app 1.0.0
```

## Available Runtime Surface

- PHP 8.5、PSR-7／15／17 HTTP Boundary、FrankenPHP Reference Runtime
- Typed Operation、OperationValue、Native Outcome／Void、業務拒否Exception
- Inline LifecycleとJSON Response
- PostgreSQL Deferred受付、Claim、Retry、Heartbeat、Fencing、Crash Recovery、Dead Letter
- Operation IDで取得するTyped Outcome
- Canonical JournalとSensitive Observed Projection
- Versioned Operation／HTTP ManifestとCompiled Symfony DI Container
- Doctrine PostgreSQL Migration
- Payload、Journal、Outcome、Dead LetterのRetention、Hold、Purge Audit、Scheduler

## Known Constraints

- Authentication／Authorization実装は提供しない
- Deferred Status／Outcome HTTP EndpointとGenerated Client SDKは提供しない
- Transactional Outbox Relayは提供しない
- Canonical Journal／Transport PayloadのEncryption Adapterは提供しない
- Remote OpenTelemetry、CloudWatch、SQS、Kafka、SQLite、MySQL Adapterは提供しない
- Observer Replay CLI、Admin UI、Scheduled Operation Strategyは提供しない
- Production CertificationやPublic API Contractを超える互換性保証は提供しない

これらの不在はApplication側のSecurity／Operations設計が不要であることを意味しません。Stableと`main`の差を確認し、Deployment前に必要なAdapterと運用責務を明示してください。
