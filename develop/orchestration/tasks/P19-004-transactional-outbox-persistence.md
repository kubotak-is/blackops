# P19-004: Transactional Outbox Persistence

Status: Accepted

## Goal

Deferred child Operationを実行中OperationのFramework管理Transactionへ登録するPublic CapabilityとPostgreSQL Persistenceを実装し、Application MutationとOutbox Rowが同じNamed Connection Instanceで原子的にCommit／Rollbackされる境界を完成する。

## In Scope

- Deferred child Operationを登録するPublic `TransactionalOutbox` Capability
- Outbox Record ID、child Operation ID、登録時刻を返すPublic Registration Result
- Parent ExecutionContextからのCorrelation／Causation／Actor継承
- child Operationからの親Idempotency Key Hash非伝播
- Active Execution ScopeとFramework管理Transactionの参加検証
- 同じ`DatabaseManager`の同じNamed Connection InstanceへのOutbox Insert
- Transaction外、異Connection、所有者不明Manual Transaction、Active Parent ContextなしのFail-fast
- PostgreSQL Outbox Record、Schema Helper、Versioned Migration、Persistence Store
- Relay Claim前の固定`pending` StateとVersion
- Commit／Rollback／Rollback-only／Nested Required／Insert FailureのTransaction Matrix
- Direct Deferred Transportの回帰
- Public API、Architecture、Security、Documentation同期
- Migration追加に伴うFresh Community Board clean-install件数期待値の同期

## Out of Scope

- Relay Claim／Lease／Heartbeat／Fencing／Batch
- Retry／Backoff／Dead Letter／Dead Letter再開
- `outbox:relay:*`または`outbox:dead-letter:*` CLI
- Outbox Recordの送信済み更新
- Terminal Operation Replay／Canonical Observer Replay
- Community Board Application／Frontend／Notification Journey
- Quickstart／SkeletonのOutbox Consumer追加
- External Broker、Email、Push、Exactly Once保証
- Outbox Payload暗号化、External Publication／Deploy

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/03-execution.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/orchestration/tasks/P19-003-http-php-duplicate-lifecycle-retention.md`
- `develop/orchestration/reports/P19-003-http-php-duplicate-lifecycle-retention.md`

## Files Allowed to Change

- `src/Outbox/**`
- `src/Core/Identifier/OutboxRecordId.php`
- `src/Internal/Outbox/**`
- `src/Internal/Identifier/IdentifierFactory.php`
- `src/Internal/ExecutionContext/ExecutionContextFactory.php`
- `src/Internal/Execution/ExecutionScopeProvider.php`
- `src/Internal/Transaction/TransactionRuntime.php`
- `src/Internal/Transaction/TransactionScope.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Application/ApplicationOperationRuntimeComposer.php`
- `src/Internal/Application/ApplicationOperationRuntimeComposition.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationOperationConsoleRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Application/ApplicationCommandContainerResolver.php`
- `src/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `migrations/postgresql/**`
- `tests/Outbox/**`
- `tests/Core/Identifier/OutboxRecordIdTest.php`
- `tests/Core/Identifier/IdentifierTest.php`
- `tests/Internal/Outbox/**`
- `tests/Internal/Identifier/IdentifierFactoryTest.php`
- `tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php`
- `tests/Internal/Transaction/**`
- `tests/Internal/DependencyInjection/**`
- `tests/Internal/Application/**`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `tests/Internal/Migration/**`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Core/Execution/DeferredTransportContractTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/framework-package-export.sh`
- `docs/guide/core-api.md`
- `docs/guide/configuration.md`
- `docs/guide/database-and-transactions.md`
- `docs/guide/execution.md`
- `docs/guide/glossary.md`
- `docs/guide/security.md`
- `docs/internal/**`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P19-004-transactional-outbox-persistence.md`
- `develop/orchestration/reports/P19-004-transactional-outbox-persistence.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Contract

### Public Capability

- `BlackOps\Outbox\TransactionalOutbox`を`#[PublicApi]` Interfaceとして提供する
- `register(Operation $definition, OperationValue $value, ?DateTimeImmutable $availableAt = null, ?ActorRef $executionActor = null): OutboxRegistration`を提供する
- `OutboxRegistration`は固定`OutboxRecordId`、child `OperationId`、UTC登録時刻を公開し、Transport Acceptanceや送信完了を表現しない
- `OutboxRecordId`は他のPublic UUIDv7 Identifierと同じShape／String Contractを持つ
- CapabilityはCompiled Application ContainerへConstructor Injectionでき、Application DomainではなくOperation Coordination／Application Infrastructureから利用する
- Inline／Deferredの親Operationから利用できるが、登録するchild Operation MetadataはDeferred Strategyだけを許可する
- Operation Metadata不一致、Value Type不一致、Inline childはRow作成前にSafe Fail-fastする

### Identity and Context

- 登録ごとに新しいOutbox Record IDとchild Operation IDを一度だけ発行する
- child Correlation IDは親を維持し、Causation IDは親Operation IDから作る
- origin／authorization Actorは親から維持し、Optional execution Actorだけ登録主体として上書きできる
- 親Deadlineより遅いchild Deadlineを作らず、既定は親Deadlineを維持する
- 親Idempotency Key Hashはchild Contextへ伝播しない
- Operation Type、Schema Version、Value Payload、child Context、availableAt、登録時刻、Named Connectionを一つの固定Recordへ保存する
- Active Execution Scopeがない場合は新しいRoot Contextを暗黙生成せずFail-fastする

### Transaction Participation

- OutboxはApplication Database ConfigurationのFramework Named Connection／SchemaへBoundする
- 登録時の最上位論理Transaction Invocationが同じConnection Nameであり、`TransactionRuntime`が所有するActive ScopeのConnection ObjectがOutbox StoreのConnection Objectと同一の場合だけInsertする
- Transaction外、別Named Connection、同名だが別Connection Instance、Manual TransactionだけがActive、所有Scope不明ではFail-fastする
- Fail-fast時はDirect TransportへFallbackせず、Deferred Operation RowやOutbox Rowを作らない
- Nested Required Transactionは外側の同一Scopeへ参加する
- Application MutationとOutbox Insertは最外Commitで同時に残り、Throwable／Rejected／Rollback-only／Commit Failureでは共に残らない
- Outbox Insert Failureは元TransactionをRollbackさせ、SQL、Schema、Table、Connection Parameter、Payload、CredentialをPublic Exception Messageへ出さない
- Public CapabilityはTransactionのbegin／commit／rollbackを所有しない

### PostgreSQL Persistence

- Current Framework Schemaへ専用Outbox Record TableをVersioned Migrationで追加する
- Primary KeyはOutbox Record ID、child Operation IDはUniqueとする
- Claim前Stateは`pending`、State Versionは`1`に固定する
- Operation Type／Connection Nameは空文字を拒否し、Schema Version／State Versionは正数とする
- encoded payload／context、Content Type、Encoding、Optional Key ID、availableAt、recordedAtを保持する
- Raw Credential、Connection Parameter、Throwable Detailを保存しない
- Schema HelperとVersioned MigrationのCurrent Schema、Constraint、Indexを一致させる
- P19-005用のClaim／Lease／Fencing／Retry ColumnまたはRelay Runtimeを先取りしない
- Migration Downは専用Table／Indexだけを安全に除去し、既存Operation／Idempotency／Retention Tableを変更しない

### Compatibility and Security

- Existing `OperationSender`／`PostgreSqlDeferredOperationSender`はDirect Transportとして不変に保つ
- Keyなし／Key付きInline・Deferred入口、Status、Idempotency、Retentionを回帰させない
- Outbox Registrationは親OperationのCanonical Journalへ新しいLifecycle Eventを追加しない
- Public API Signatureへ`BlackOps\Internal`、Doctrine、PostgreSQL型を露出しない
- Public／Log／Diagnostics／ExceptionへEncoded Payload、Actor Credential、SQL、Table、Connection Parameter、Throwable Detailを出さない
- Worker Reuse時にActive Scope、Record、Payload、Connection Ownershipを次Attemptへ残さない
- Community Board Consumer差分はMigration件数期待値だけとし、Application／Frontend／Seedを変更しない

## Constraints

- Production Code実装はGPT-5.6 Luna High workerが行う
- WorkerはCommitしない
- P19-003 Idempotency、HTTP／PHP Duplicate Lifecycle、Retention Contractを弱めない
- Relay／Retry／Dead Letter／Replay／Community Board Product JourneyへScopeを広げない
- Direct TransportをOutbox経由へ置換しない
- 異Connectionや外部APIへ原子的保証を出さない
- Public Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Dependency追加、Credential保存、External Publication／Deployを行わない

## Acceptance Criteria

- [x] Public Capability／Registration／Outbox Record IDがArchitecture GuardとCore API Guideへ同期される
- [x] Active Parent Contextから固定child Identity／Correlation／Causation／Actor／Deadlineが生成される
- [x] 親Idempotency Key Hashがchildへ伝播しない
- [x] 同一Named Connection InstanceのFramework管理Transaction内だけ登録できる
- [x] CommitでMutationとOutbox Rowが一緒に残り、Rollback／Rollback-onlyで両方残らない
- [x] Transaction外／異Connection／別Instance／Manual Transaction／親ContextなしがRowなしでFail-fastする
- [x] PostgreSQL Constraint、Unique Boundary、Claim前State、Schema Helper／Migration Parityが検証される
- [x] Insert FailureがTransactionをRollbackし、Sensitive／SQL Detailを漏らさない
- [x] Direct TransportとP19-003 Idempotency／Retentionが回帰しない
- [x] Fresh Community Board Clean Installが新Migration件数で成功し、Application／Frontend／Seed差分がない
- [x] Public API Architecture、Docs Reader、Deptrac、Full PHPUnitが成功する
- [x] Relay／Retry／Dead Letter／Replay／Community Board Product Journey差分がない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
mise exec -- pnpm --dir docs/website run test
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-004-transactional-outbox-persistence.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Public Capability／Identity Matrix
- Transaction Participation Matrix
- PostgreSQL Persistence／Constraint Matrix
- Direct Transport／Compatibility Evidence
- Sensitive Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
