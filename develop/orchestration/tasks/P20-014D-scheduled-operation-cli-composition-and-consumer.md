# P20-014D: Scheduled Operation CLI, Composition, and Consumer Evidence

Status: Accepted

## Goal

Accepted P20-014A〜CのScheduled Application OperationをApplication Runtimeへ構成し、一回実行のBlackOps CLI `operation:schedule:run`から実行可能にする。

Compiled Operation Manifest、Application Container、PostgreSQL Schedule State、Inline／Deferred Runtime、既存Deferred Workerを同じApplication Configuration、Connection、Schema、Clockで接続する。Crash後の`claimed` Occurrence再開と複数Process収束を、実Application／PostgreSQLのConsumer Evidenceで固定する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/09-package-and-autoload.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/18-operation-envelope.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/40-project-cli.md`
- `develop/spec/68-application-runtime-bootstrap.md`
- Accepted P20-014A／P20-014B／P20-014C Task、Report、Source
- Current Application Console／Build／Runtime／Worker composition and Consumer conventions

## In Scope

- One-shot BlackOps CLI `operation:schedule:run`
- Human output and versioned `--json` output
- Deterministic scheduled metadata enumeration by Schedule name
- Claimed recovery before new evaluation for each Schedule
- `ScheduleEvaluator` result aggregation and claimed occurrence invocation
- Compiled ContainerからのOperation Definition resolution
- Optional Application-owned `ScheduledActorProvider` resolution
- Authorized Scheduled Operationに対するProvider Build／Bootstrap validation
- Inline／Deferred Scheduled Runtime composition
- Existing Deferred Worker compositionへのOccurrence lifecycle injection
- Same Application Database Connection／Schema／PSR-20 Clock wiring
- Safe per-Schedule failure aggregation without raw Error／Value／Actor／Credential output
- Existing Maintenance `scheduler:run`／`scheduler:daemon`とのCommand／Runtime分離
- Concurrent one-shot process convergence
- Crash after claim／before invocationから同じOccurrence／Operation IDでの再開
- Inline completion、Deferred acceptance、Worker completionのApplication integration evidence
- Framework Command collision／help／application build artifact synchronization
- Consumer script／fixture and package export contract synchronization where required
- Report／STATE／TODO synchronization

## Out of Scope

- Application Schedule Daemon
- Cron／systemd／Kubernetes Manifest generation
- Supervisor Process管理、Timeout、Restart、Alert実装
- New Public Attribute／Schedule Context／Actor Provider API
- New Database Table／Column／Migration
- New Retry／Dead Letter semantics
- Retention purge
- HTTP／ConsoleCommand／Frontend entry semantics変更
- Existing Maintenance Scheduler semantics変更
- Guide、Website、Release Note、Documentation Review
- New Composer Dependency
- Commit、Push、PR、External Deploy

## One-shot Runtime Contract

1. Compiled Manifestから`OperationMetadata::schedule !== null`だけを抽出し、Schedule名昇順で処理する。
2. 各Scheduleで、既存`claimed` Occurrenceを`scheduled_at`／作成順に先に再開する。
3. Recovery後に現在時刻までを一回だけ評価する。
4. 評価で新しい`claimed` Occurrenceが得られた場合だけ、Metadata Strategyに従ってInline実行またはDeferred受理する。
5. Recovery／評価のどちらもOccurrenceに固定済みのOperation IDを使い、新しいIDへ置換しない。
6. 一Scheduleの安全な失敗で他Scheduleの集計を失わない。Connection／Transactionが再利用不能な失敗はRuntime Failureとして安全に終了する。
7. Definition resolutionはCompiled Containerを使う。Self-handled OperationはContainer service、DefinitionとHandlerが別の場合はconstructorlessで安全に構築する。Private state Reflection書換えは行わない。
8. Existing Validator、Authorization、Journal、Deferred Transport、Worker、Outcomeを迂回しない。

## Summary and Exit Contract

Human／JSONは最低限、次のCountを返す。

| Field | Meaning |
| --- | --- |
| `evaluated` | このInvocationで評価対象にしたScheduled Operation数 |
| `accepted` | Inline完了またはDeferred受理まで正常に進んだOccurrence数 |
| `skipped_misfire` | 評価で記録したMisfire skip Occurrence数 |
| `skipped_overlap` | 評価で記録したOverlap skip Occurrence数 |
| `failed` | Validation／Authorization rejection、Invocation failure、Evaluation failureの合計 |

- No Scheduleは全Count `0`、Exit Code `0`。
- `failed === 0`はExit Code `0`。
- Runtime／per-Schedule failureを一件以上集計した場合はExit Code `1`。
- CLI option、Manifest、Build ID、Provider欠落等の安全な入力／設定ErrorはExit Code `2`とし、Schedule Stateを変更しない。
- `--json`成功Shapeは`schemaVersion: 1`、`status: "ok"|"failed"`と上記Countだけを持つ。
- Error出力はstable safe codeだけとし、Exception message、Class、Trace、SQL、Cron全文、Value、Actor ID、Credentialを出さない。
- Human出力も同じCountだけを判読可能に表示し、Maintenance Scheduler wordingを使わない。

## Actor and Build Contract

- Authorized Scheduled Operationが一つでもある場合、Compiled Containerへ`ScheduledActorProvider`が一つ解決可能でなければBuildまたはRuntime BootstrapをSafe Configuration Errorで拒否する。
- Provider未登録を匿名ActorへFallbackしない。
- Providerが明示的に`null`を返す場合は通常Authorization境界で拒否し、`failed`へ集計する。
- ProviderがActor resolution中に失敗した場合はP20-014Cのsafe categoryでOccurrenceを`failed`へ遷移し、Raw ErrorをCLIへ出さない。
- Schedule Attribute／Manifest／DatabaseへActor ID／Credentialを追加しない。
- Providerを要求しないScheduled OperationだけのApplicationでは未登録を許可する。

## Application and Worker Composition Contract

- CLI compositionは`ApplicationOperationRuntimeComposer`と同じCompiled Manifest／Container、Framework Connection、Schema、Clock、Identifier、Journal、Authorization、Transaction、Observation Pipelineを再利用する。
- `InlineDispatcher`、`DeferredAcceptanceOrchestrator`、`ScheduledOperationRuntime`、`ScheduleEvaluator`、`PostgreSqlScheduleStore`、`PostgreSqlScheduledOccurrenceLifecycle`へ同じConnection／Schema／Clockを明示注入する。
- Deferred Senderは既存Framework Schemaを使う。
- Existing `ApplicationWorkerComposer`は同じSchemaの`PostgreSqlScheduledOccurrenceLifecycle`を`DeferredWorkerRuntimeStorage`へ注入し、Scheduled Deferred completion／rejection／failure／retry／dead letterを実Application Workerでも反映する。
- HTTP／ConsoleCommand／Application child OperationのSchedule Contextは`null`のまま。
- `scheduler:run`／`scheduler:daemon`はMaintenance専用のまま変更しない。

## Crash and Concurrency Contract

- Claim確定後、Operation invocation前にProcessが停止したFixtureを作る。
- 次の`operation:schedule:run`は先行`claimed`を検出し、同じSchedule名／scheduled-at／evaluated-at／Operation IDで一回だけ再開する。
- 同じ時刻に二Processが起動しても、同じSchedule slotは一Occurrenceへ収束する。
- Inline Handler、Deferred transport row、Canonical `operation.received`／`operation.accepted`は同一Operation IDについて重複しない。
- 片方がactiveな場合のもう片方はOverlap skipまたは既存State観測へ安全に収束し、新Operation IDを発行しない。
- Deferred Worker実行後、OccurrenceとOperation／Journal／Outcomeが同じterminal resultへ収束する。

## Files Allowed to Change

- `src/Internal/Scheduling/**`
- `src/Internal/Console/**`
- `src/Internal/Application/**`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Application/ConsoleKernel.php`
- `tests/Internal/Scheduling/**`
- `tests/Internal/Console/**`
- `tests/Internal/Application/**`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/**`
- `tests/Fixtures/**`
- `examples/quickstart/config/**`
- `examples/quickstart/app/**`
- `examples/skeleton/config/**`
- `examples/skeleton/app/**`
- `composer.json`
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-014D-scheduled-operation-cli-composition-and-consumer.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Framework Production Codeの実装はLuna High Workerが行う。
- Existing Phase 20 Working Tree差分を保持する。
- Existing Public API signature、Manifest Schema 3、Database Schemaを変更しない。
- New public generic Scheduler abstractionを追加しない。
- Existing Maintenance SchedulerへApplication Scheduleを混ぜない。
- CommandからApplication SourceをDevelopment Scanしない。ProductionはCompiled Artifactを正本とする。
- Build Artifact ID mismatchを無視しない。
- RecoveryをHTTP Idempotency Storeへ流用しない。
- Catch-allで成功Exitへ変換しない。
- JSONへDynamic key、Raw Throwable、FQCN、SQLを出さない。
- Consumer fixtureのClock／Schedule slotは決定的に固定し、Wall Clock依存のflaky testにしない。
- Consumer fixtureのSecret／CredentialをRepositoryへ保存しない。
- Consumer testは作成物をRepositoryへ残さずcleanupする。
- WorkerはReview前にCommitしない。

## Acceptance Criteria

- [x] `operation:schedule:run`がFramework Commandとして登録され、`--json`を提供する
- [x] Human／JSON CountとExit Code 0／1／2がContractどおりである
- [x] Compiled ManifestのScheduled MetadataをSchedule名順に処理する
- [x] Claimed Recoveryを新規評価より先に行い、固定Operation IDを維持する
- [x] Inline／DeferredをMetadata Strategyどおり通常Lifecycleへ接続する
- [x] DefinitionをCompiled Container／安全なconstructorless boundaryから解決する
- [x] Authorized ScheduleのProvider必須をBuild／Bootstrapで検証する
- [x] Provider null／failureを匿名FallbackやRaw Error露出なしで処理する
- [x] Same Connection／Schema／Clock／Journal／Authorization／TransactionでCLI Runtimeを構成する
- [x] Application Worker compositionがScheduled Deferred terminal lifecycleを更新する
- [x] No Schedule、Misfire、Overlap、Validation rejection、Runtime failureを集計する
- [x] Existing Maintenance `scheduler:*`、HTTP、ConsoleCommand、child dispatch contractを変更しない
- [x] Crash recoveryが同じOccurrence／Operation IDで一回だけ再開する
- [x] Two-process concurrencyが一Occurrence／一Operationへ収束する
- [x] Inline completion、Deferred acceptance、Worker terminal stateを実PostgreSQLで検証する
- [x] BlackOps CLI help／collision／build artifact／Consumer fixtureを同期する
- [x] Focused／Full Test、Consumer、Format、Analysis、Deptrac、Architecture Guardを実行する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Scheduling tests/Internal/Console tests/Internal/Application tests/Integration/ApplicationConsoleKernelTest.php
bash tests/Consumer/scheduled-operation.sh
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

CommandがRepository既存問題、Worker no-commit、または実行環境で失敗する場合は、未実行理由、Exact Error、変更Sourceに限定した代替CommandをReportへ記録する。

## Completion Report

`develop/orchestration/reports/P20-014D-scheduled-operation-cli-composition-and-consumer.md`へSummary、Changed Files、Decisions and Assumptions、Command／Exit Matrix、Composition Matrix、Actor／Provider Matrix、Crash／Concurrency Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
