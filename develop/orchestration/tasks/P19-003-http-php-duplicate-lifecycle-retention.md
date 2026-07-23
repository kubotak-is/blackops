# P19-003: HTTP and PHP Duplicate Lifecycle and Retention

Status: Accepted

## Goal

P19-002のIdempotency Core Contractを実Operation入口へ接続し、HTTP MutationとPHP Dispatchの重複Lifecycle、PostgreSQL Atomic Store、Crash-safe Recovery、独立Retentionを一つの整合したFramework Contractとして実装する。

## In Scope

- POST／PUT／PATCH／DELETEのOptional `Idempotency-Key` Header
- PHP `Dispatcher::dispatch()`末尾のOptional `IdempotencyKey`
- Binding／Validation／Authentication／Authorization後のScope／Fingerprint／Atomic Claim
- Inline Terminal ResponseとDeferred 202／Operation IDのReplay
- Conflict／In-progress／Expired／Anonymous／Unsupported／Integrity Failure Matrix
- Version付きSafe HTTP SnapshotとPHP Typed Result Replay
- PostgreSQL Idempotency Store、Schema、Versioned Migration、Atomic Claim／Terminalize
- Process Crash後のOperation／Journal Evidenceに基づくSafe Recovery
- Idempotency Recordの独立Retention、Hold、Plan／Purge／Audit統合
- Real HTTP、PHP Dispatch、Concurrent Claim、Worker Reuse、Sensitive Boundary Test
- Public API Guide、HTTP／Retention Guide、Internal Architecture Documentation同期

## Out of Scope

- Transactional Outbox、Relay、Dead Letter Retry、Operation／Observer Replay
- Console Operation入口とDeferred Worker内部入口へのIdempotency Key追加
- Tenant／Anonymous Idempotency
- GET／HEADのIdempotency実行
- Community Board Product Journey、Quickstart／Skeleton変更
- Frontend Generatorの新しいIdempotency Contract
- External Publication／Deploy

## Relevant Specifications

- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`

## Files Allowed to Change

- `src/Execution/Dispatcher.php`
- `src/Core/OperationResult.php`
- `src/Core/Execution/DeferredAcknowledgement.php`
- `src/Http/DeferredOperationAcceptor.php`
- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Core/Retention/**`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationOperationRuntimeComposer.php`
- `src/Internal/Application/ApplicationRetentionCommandFactory.php`
- `src/Internal/Application/ApplicationRetentionConfiguration.php`
- `src/Internal/Application/ApplicationRetentionRuntime.php`
- `src/Internal/Console/RetentionPlanCommand.php`
- `src/Internal/Console/RetentionPurgeCommand.php`
- `src/Internal/Execution/**`
- `src/Internal/Http/**`
- `src/Internal/Idempotency/**`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Retention/**`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlIdempotency*.php`
- `src/Transport/PostgreSql/PostgreSqlRetention*.php`
- `migrations/postgresql/**`
- `tests/Execution/**`
- `tests/Core/OperationResultTest.php`
- `tests/Core/Execution/**`
- `tests/Core/Retention/**`
- `tests/Http/**`
- `tests/Internal/Application/**`
- `tests/Internal/Console/Retention*.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Internal/Execution/**`
- `tests/Internal/Http/**`
- `tests/Internal/Idempotency/**`
- `tests/Internal/Migration/**`
- `tests/Internal/Retention/**`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Transport/PostgreSql/**`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `docs/guide/core-api.md`
- `docs/guide/configuration.md`
- `docs/guide/execution.md`
- `docs/guide/glossary.md`
- `docs/guide/retention.md`
- `docs/guide/security.md`
- `docs/internal/**`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/17-core-api.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P19-003-http-php-duplicate-lifecycle-retention.md`
- `develop/orchestration/reports/P19-003-http-php-duplicate-lifecycle-retention.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportへBlockerとして記載する。

## Contract

### Entry and Ordering

- `Dispatcher::dispatch()`は既存3引数を維持し、末尾へ`?IdempotencyKey $idempotencyKey = null`を追加する
- HTTPはHeader Fieldを生のまま一つだけ受理し、空値、複数Field、Comma結合、Invalid ShapeをOperation ID発行前の400 `invalid_idempotency_key`にする
- Key付きGET／HEADとEphemeral Outcome Routeは400 `idempotency_not_supported`にする
- Key付きAnonymous HTTP／PHP Dispatchは400相当のStable `idempotency_requires_authenticated_actor` Rejectionとし、Recordを参照しない
- Binding／Validation／Authentication／Authorization FailureはIdempotency Recordを作らない
- 重複時もAuthentication／Authorizationを毎回評価し、Credential失効／権限変更でRecordを迂回しない
- Console Operation AdapterとDeferred Worker内部再実行はKeyを受け取らない

### Claim and Duplicate Decision

- ScopeはP19-002のOperation Type ID＋Authorization Actor type／id＋Opaque Key Hashを使う
- FingerprintはP19-002のVersion付きIncremental Codecを使い、Raw／Canonical Valueを保存しない
- 最初のAtomic Claimだけが新しいOperation IDを発行して実行を開始する
- Processing＋Sameは`idempotency_in_progress`、Processing／Terminal＋Differentは`idempotency_conflict`
- Terminal Snapshot／Typed Resultが利用可能なら元のStatus／Outcome／Rejection／Operation IDを再利用する
- Recordが残り必要なOutcome／Snapshotが消失していれば`idempotency_expired`とし再実行しない
- Conflict／In-progress／ExpiredへOriginal Operation ID、Actor、Fingerprint、Resultを含めない

### HTTP and PHP Replay

- HTTP Safe SnapshotはVersion、Status、固定Allowlist Header、Safe Bodyだけを保存する
- AllowlistはFramework生成の`Content-Type`、`Location`、`Retry-After`だけとし、Credential、Cookie、任意Application Header、Throwable Detailを保存しない
- HTTP ReplayはOriginal Responseへ`Idempotency-Replayed: true`と`Cache-Control: private, no-store`を上書きする
- InlineはCompleted、Business／Conflict Rejected、Safe Internal FailureをTerminal化する
- DeferredはDurable Acceptance成功時の202だけをTerminal化し、後続Worker Stateに関係なく同じ202／Operation IDを返す
- PHP Replayは元のTyped Outcome／RejectionとOperation IDを再構成し、HTTP Headerを混在させない
- `OperationResult::completed()`は既存Callを維持した末尾Optional `OperationId`を受け、初回とReplayのCompleted Resultへ同じIDを保持できる
- `OperationResult`と`DeferredAcknowledgement`はHTTP Headerを保持せず、後方互換なReplay状態だけを表現し、HTTP ResponderがReplay Headerへ投影する
- KeyなしHTTP／PHP Lifecycleと既存Status APIは不変にする

### PostgreSQL and Recovery

- Unique BoundaryはVersion付きScope Hashとし、Claimは単一SQL Transaction／Unique Constraintで競合安全にする
- StoreはRaw Key、Actor、Canonical Value、Credential、任意Header、Throwable Detailを永続化しない
- TerminalizeはClaim Operation ID、Processing State、期待Versionが一致する場合だけ成功する
- Unknown Key／Scope／Fingerprint／Snapshot Version、Invalid Row、Storage Failureは一致扱いにせずSafe Internal Failureへ閉じる
- Claim後のCrashでRecordを暗黙削除しない
- Operation／Journal Evidenceから一意にTerminal SnapshotまたはDeferred 202を再構成できる場合だけRecoveryする
- 判断不能なProcessing Recordは`idempotency_in_progress`を維持する
- Schema HelperとVersioned MigrationのCurrent Schemaは一致させる

### Retention

- Idempotency Recordを`TransportPayload`／`Journal`／`Outcome`／`DeadLetter`と独立したRetention Target／Purge Targetに追加する
- `RetentionPolicy`の末尾Optional Periodは既存4引数を壊さず、未指定時はOperation／Outcome関連Policyの最長期間を使う
- Application ConfigはOptional `idempotency_record_days`を受け、未指定時は上記Defaultを使う
- Recordが残る間は期限後もKeyを再利用できず、Purge後だけ新規Claimできる
- Operation単位Legal Holdは対応RecordのPlan／Purgeを停止する
- Dry-run Plan、Confirmed Purge、Scheduler、Auditへ統合する
- AuditはCount／期間／Operation ID／Actor／Policyだけを記録し、Raw Key／Scope／Fingerprintを含めない

## Constraints

- Production Code実装はGPT-5.6 Luna High workerが行う
- WorkerはCommitしない
- P19-002 Public Key／Hash、Scope／Fingerprint Version、Storage Semanticsを弱めない
- Outbox／Relay／ReplayやCommunity BoardへScopeを広げない
- Public Signatureへ`BlackOps\Internal`型を露出しない
- Security Header／Snapshot AllowlistをApplication拡張可能にしない
- PostgreSQL Error／SQL／Table名をSafe Failure Surfaceへ出さない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない

## Acceptance Criteria

- [x] KeyなしHTTP／PHP Dispatchの既存挙動が回帰しない
- [x] Invalid／multiple／unsupported／anonymous KeyがOperation IDとRecordなしで拒否される
- [x] Binding／Validation／Authentication／Authorization後にだけClaimされる
- [x] Same／Conflict／Processing／Inline Terminal／Deferred Terminal／Expired MatrixがHTTPとPHPで固定される
- [x] Replay Header／Cache-ControlとSafe Snapshot Allowlistが固定される
- [x] PostgreSQL Concurrent Claim／Terminalize／Crash Recovery／Integrity Failureが検証される
- [x] Record保持中はKey再利用不可、Purge後だけ再利用可能になる
- [x] Hold／Plan／Purge／Audit／SchedulerがIdempotency Targetを扱う
- [x] Worker ReuseとSensitive BoundaryでRaw Key／Value／Credentialが残らない
- [x] Public API Architecture、Docs Reader、Deptrac、Full PHPUnitが成功する
- [x] Outbox／Relay／Replay／Community Board差分がない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
mise exec -- pnpm --dir docs/website run test
bash tests/Consumer/frankenphp-worker-mode.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-003-http-php-duplicate-lifecycle-retention.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Entry／Ordering Matrix
- HTTP／PHP Duplicate Matrix
- PostgreSQL Claim／Recovery Matrix
- Retention／Hold／Audit Matrix
- Sensitive Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
