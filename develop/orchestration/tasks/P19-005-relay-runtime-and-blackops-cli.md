# P19-005: Relay Runtime and BlackOps CLI

Status: Orchestrator review passed; consumer verification pending

## Goal

P19-004の固定Outbox Record／child OperationをPostgreSQLから有限BatchでClaimし、Lease／Heartbeat／Fencing／Retry／Dead Letterを通して既存Deferred Transportへat-least-once配送するRuntimeを完成する。一回実行、Daemon、監査付きDead Letter再開をBlackOps CLIへ追加し、Repository内の利用者向け旧CLI呼称を`BlackOps CLI`へ統一する。

## In Scope

- PostgreSQL Outbox Claim／Lease／Heartbeat／Fencing／Settlement Store
- `pending`／`leased`／`retry_scheduled`／`sent`／`dead_lettered` State
- 有限Batch、`available_at`／`next_attempt_at`順序、`FOR UPDATE SKIP LOCKED`
- 同じOutbox Record ID／child Operation ID／encoded messageによるDeferred Transport配送
- Transport Acceptance後／Outbox Sent更新前Crashの同一Identity再配送
- 指数Backoff、最大Attempt、Failure Fingerprint、Batch Isolation
- Lease切れRecovery、Heartbeat、Stale Fencing拒否
- Dead Letter Retry Auditと同じRecordのRetry可能状態への再開
- `outbox:relay:run`／`outbox:relay:daemon`／`outbox:dead-letter:retry`
- Relay Configuration、Runtime Composition、BlackOps CLI登録、Scheduler Task
- PCNTL利用可能時のSIGTERM／SIGINT Graceful Shutdownと利用不可時の明示的Fail-fast
- Schema Helper、Versioned Migration、Fresh Install Migration件数、Package Export同期
- Public Guide、Internal Architecture、Configuration、Security、BlackOps CLI Reference同期
- 完全一致する利用者向け旧CLI呼称の`BlackOps CLI`へのRepository全体統一

## Out of Scope

- Terminal Operation Replay
- Canonical Observer Replay
- Community Board Application／Frontend／Notification Journey
- Transactional Outbox Public APIまたはTransaction Participationの変更
- Direct TransportのOutbox強制
- External Broker、Email、Push、Exactly Once保証
- Outbox Payload暗号化
- `project-cli.md` Filename、`/reference/project-cli/` URL、Decision／Task／Report FilenameのRename
- Canonical Command名への`blackops:` Prefix再導入
- External Publication／Deploy

## Relevant Specifications

- `develop/spec/03-execution.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/orchestration/tasks/P19-004-transactional-outbox-persistence.md`
- `develop/orchestration/reports/P19-004-transactional-outbox-persistence.md`

## Files Allowed to Change

- `src/Internal/Outbox/**`
- `src/Internal/Console/Outbox*.php`
- `src/Internal/Scheduler/**`
- `src/Internal/Application/ApplicationOutbox*.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationRetentionRuntime.php`
- `src/Internal/Application/ApplicationRetentionCommandFactory.php`
- `src/Internal/Application/ApplicationConfiguration*.php`
- `src/Internal/Console/FrameworkCommandNames.php`
- `src/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSender.php`
- `migrations/postgresql/**`
- `tests/Internal/Outbox/**`
- `tests/Internal/Console/Outbox*.php`
- `tests/Internal/Scheduler/**`
- `tests/Internal/Application/**`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `tests/Internal/Migration/**`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/framework-package-export.sh`
- `examples/quickstart/config/execution.php`
- `examples/community-board/config/**`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/**`
- `develop/spec/**`
- `develop/decisions/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/**`
- `develop/orchestration/reports/**`
- `develop/STATE.md`

旧CLI呼称から`BlackOps CLI`への機械的な呼称統一に限り、Task開始時に完全一致を含むTracked Text Fileを変更してよい。Filename、URL、Command Name、過去の事実関係は変更しない。

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Contract

### Outbox State and Schema

- P19-004 Migrationは履歴として変更せず、新しいAdditive MigrationでRelay Column／Constraint／IndexとDead Letter Retry Audit Tableを追加する
- Stateは`pending`、`leased`、`retry_scheduled`、`sent`、`dead_lettered`だけを許可する
- Claim ownershipは非空Relay ID、Lease期限、単調増加Fencing Tokenで表し、ClaimごとにAttempt CountとState Versionを進める
- Retryは`next_attempt_at`までClaim対象外とし、Sent／Dead LetterはClaimしない
- Sent、Dead Letter、Retry Stateに不要なLease owner／期限を残さない
- Failure FingerprintはVersion付きの安全なHashだけを保存し、Throwable Message、SQL、Table、Payload、Credentialを保存しない
- Dead Letter Retry AuditはAudit ID、Outbox Record ID、child Operation ID、Actor、Reason、実行時刻、再開前Attempt Countだけを保持する
- Claim Indexはeligible state／due time／record IDを支え、Schema HelperとMigrationを一致させる
- Migration DownはP19-005 Audit／Index／Column／Constraintだけを除去し、P19-004 Outbox Recordを保持する

### Claim, Heartbeat, and Fencing

- ClaimはTransaction内で有限Batchを`FOR UPDATE SKIP LOCKED`により選び、due時刻、record ID順で安定する
- Claim対象はdueな`pending`／`retry_scheduled`とLease切れ`leased`である
- 同一Rowを並行Relayが同時所有しない
- Heartbeat、Sent、Retry、Dead Letter SettlementはRecord ID、Relay ID、Fencing Token、`leased` Stateの全一致を要求する
- Stale Claimは最新Stateを更新せず、安全な固定Exception／Resultで拒否する
- Lease切れRecoveryは同じRecord／child Operation Identityを維持し、新しいOperationを発行しない

### Delivery and Retry

- Relayは保存済みOutbox Recordから同じchild `DeferredOperationMessage`を復元し、既存`OperationSender`へ渡す
- `PostgreSqlDeferredOperationSender::enqueue()`は同じOperation IDと同一Messageの再配送をDurable Replayとして受理し、保存済みAccepted Atと`replayed=true`を返す
- 同じOperation IDでOperation Type、Schema、Payload、Context、availableAt等が一致しない場合はIntegrity Failureとして上書きしない
- Transport Acceptance後／Sent更新前Crashの再実行は同じchild Operationを重複作成せず、OutboxをSentへ収束できる
- 一件のFailureは同一Batchの他Recordを停止しない
- Failure時は指数BackoffでRetryし、最大Attempt到達時だけDead Letterへ移す
- Failure Fingerprint Versionは`1`、Hash InputはDomain Separator `blackops.outbox.relay.failure.v1`とThrowable Classだけに固定し、Throwable Messageを使わない
- Relayはat-least-onceであり、External Side EffectのExactly Onceを表現しない

### Runtime, Scheduler, and BlackOps CLI

- Configuration Pathは`execution.outbox_relay`とし、`id`は必須非空、`batch_size=50`、`lease_seconds=60`、`heartbeat_seconds=10`、`grace_seconds=20`、`max_attempts=8`、`initial_backoff_seconds=1`、`max_backoff_seconds=300`、`poll_interval_milliseconds=1000`を既定値とする
- HeartbeatはLeaseより短く、初期Backoffは最大Backoff以下とする
- `outbox:relay:run`は既定1 Batch、`--batches=<positive-int>`、または`--until-empty`で空になるまで実行し、両Option同時指定を拒否する。出力はclaimed／sent／retried／dead-lettered／stale件数だけとする
- `outbox:relay:daemon`は`--interval-milliseconds=<positive-int>`、Optional `--iterations=<positive-int>`、SIGTERM／SIGINT Graceful Shutdownを持つ
- `outbox:dead-letter:retry <record-id> --actor=<actor> --reason=<reason>`はDead Letterだけを監査付きで再開し、同じRecord／child Operation IDとAttempt Countを維持する
- BlackOps CLI CommandはFramework予約名へ追加し、Application CommandとのCollisionを既存規則でFail-fastする
- SchedulerはRelayを有限Work Unitとして登録し、Retention FailureとRelay FailureをTask単位で分離する
- CLI／Log／ResultへRaw Payload、Context、Credential、SQL、Connection Parameter、Throwable Detailを出さない

### BlackOps CLI Terminology

- 利用者向け正式呼称は`BlackOps CLI`とする
- 公式形式は`php blackops <command>`を維持する
- Framework CommandはPrefixなしCanonical Nameを維持し、旧`blackops:*` Aliasを復活させない
- 旧CLI呼称の完全一致はTracked Sourceから除去する
- `project-cli.md` Filename、`/reference/project-cli/` Slug、履歴File名は互換性のため維持する
- 公開Artifact、README、CHANGELOG、UPGRADE、Guide、Internal Docs、Current Spec／Decision、過去Task／Reportの事実関係を変えず呼称だけ同期する

### Compatibility and Security

- P19-004 Registration、Transaction Participation、Outbox Record／child Operation Identityを維持する
- Existing Direct Transport、Worker、Idempotency、Retention、Schedulerを回帰させない
- Operation Replay／Observer Replay／Community Board Product Journeyを先取りしない
- Worker／Daemon Reuse時にClaim、Record、Payload、Connection、Failureを次Iterationへ残さない
- Public API SignatureへInternal／Doctrine／PostgreSQL型を追加しない
- Production Comment／DocBlockへSpec、Decision、Task、TODO管理番号を書かない

## Required Failure Matrix

| Case | Required Result |
| --- | --- |
| Two relays claim same due records | Record is owned once with distinct monotonic fencing |
| Active lease | Another relay cannot claim |
| Expired lease | Same record／child Operation is reclaimable |
| Stale heartbeat／settlement | No update to current owner state |
| Successful delivery | Sent once logically; lease cleared |
| Crash after transport acceptance | Same child Operation replayed and Outbox converges to Sent |
| Retryable failure below max | Retry scheduled with bounded exponential backoff |
| Failure at max attempt | Dead Letter with safe fingerprint |
| One record fails in batch | Remaining records continue |
| Dead Letter retry | Same identities, audit row, retryable state |
| Retry non-dead record | Safe refusal, no audit or state mutation |
| Message mismatch at transport duplicate | Integrity failure, existing Operation unchanged |
| SIGTERM／SIGINT daemon | Stop new claims and exit after current unit |
| Invalid configuration／CLI option | Fail before claim |

## Acceptance Criteria

- [ ] Claim／Lease／Heartbeat／Fencing／Lease Recoveryが実PostgreSQL競合で検証される
- [ ] Transport Acceptance後Crashを同じRecord／child Operation IDで回復できる
- [ ] Retry／Backoff／最大Attempt／Dead Letter／Batch Isolationが検証される
- [ ] Dead Letter再開が同じIdentityと安全なAuditで検証される
- [ ] Run／Daemon／Scheduler／Graceful Shutdown／Worker Reuseが検証される
- [ ] Schema Helper／Migration parity、Fresh Install、Package Exportが成功する
- [ ] Direct Transport、P19-004 Transactional Outbox、Idempotency、Retentionが回帰しない
- [ ] CLI／Log／Store／ExceptionへSensitive／SQL／Credential Detailが漏れない
- [ ] 旧CLI呼称の完全一致がTracked Sourceから消え、表示名が`BlackOps CLI`へ統一される
- [ ] Filename／URL／Canonical Command Name／旧Alias非予約Contractが維持される
- [ ] Public API Architecture、Docs Website、Deptrac、Full PHPUnitが成功する
- [ ] Operation Replay／Observer Replay／Community Board Product Journey差分がない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Project[ ]CLI' --glob '!vendor/**' --glob '!examples/community-board/vendor/**' --glob '!examples/community-board/frontend/node_modules/**'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-005-relay-runtime-and-blackops-cli.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- State／Claim／Fencing Matrix
- Delivery／Crash／Retry Matrix
- Dead Letter／Audit Matrix
- BlackOps CLI／Scheduler Matrix
- Terminology Compatibility Evidence
- Sensitive Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
