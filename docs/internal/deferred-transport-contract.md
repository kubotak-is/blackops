# Deferred Transport Contract

Deferred Transport Contractは、HTTP Process、Worker Runtime、Infrastructure Adapterの境界で使うPublic Contractである。

## Strategy

`Deferred` は論理Execution Strategyを表す。Transportの種類は表さない。PostgreSQL、SQS、その他のAdapter選択はRuntime構成で行う。

## Message

`DeferredOperationMessage` は、Process境界へ渡すためにCodec済みのOperation情報を保持する。

```text
operationId
operationType
schemaVersion
encodedPayload
encodedContext
availableAt
```

PHP Object Serializationへ依存しない。PayloadとContextは、別途定義されるCanonical Codecで文字列へ変換済みであることを前提にする。

## Acknowledgement

`DeferredAcknowledgement` はDurable保存が完了し、Frameworkが後続実行の責任を引き受けたことを表す。

```text
operationId
acceptedAt
```

Handler実行完了やOutcome確定は表さない。HTTP AdapterはこれをHTTP 202 Responseへ変換できる。

## Claim

`ClaimRequest` はWorkerがClaimを試みる基準時刻を持つ。Lease Owner、Lease期限、Fencing Tokenなどの排他制御MetadataはTransport内部の責務であり、業務Handlerへ渡すExecution Contextには含めない。

`OperationClaim` はClaim済みMessageと不透明なClaim Tokenを持つ。Claim TokenはTransportがHeartbeat、Acknowledge、Release時に検証するための値であり、業務Handlerへ渡さない。

## Ports

Transport Portは責務ごとに分離する。

```text
OperationSender        enqueue
OperationReceiver      claim
ClaimHeartbeat         heartbeat
ClaimSettlement        acknowledge / release
ExecutionTransport     all ports
```

HTTP Processは`OperationSender`だけへ依存できる。Worker RuntimeはReceiver、Heartbeat、Settlementへ依存する。PostgreSQLなどの総合Adapterは`ExecutionTransport`を実装できる。

## In-Memory Test Adapter

[`InMemoryExecutionTransport`](in-memory-execution-transport.md) は全Transport Portを一つにまとめたUnit Test用Adapterである。PSR Clockと明示Lease Durationを受け取り、`availableAt`、一件Claim、決定的Sort、Lease、Heartbeat、Fencing、Acknowledge、ReleaseをDatabaseなしで検証できる。

Stateは一つのPHP Object内だけに保持される。Durable Storage、Process間共有、並行Worker排他、Canonical Journal、Outcome、Attempt Lifecycleを提供しないため、Production Runtimeへ登録しない。

## PostgreSQL Sender

PostgreSQL Senderは`DeferredOperationMessage`を`operations` tableへ保存し、保存成功時に`DeferredAcknowledgement`を返す。

SenderはDoctrine DBAL `Connection` を受け取る。これにより、後続のDeferred受付OrchestratorはOperation State保存とCanonical Journal記録を同じConnection / Transactionへ載せられる。

保存する主な情報は次のとおり。

```text
operation_id
operation_type
schema_version
encoded_payload
encoded_context
content_type
encoding
key_id
state
state_version
next_sequence
available_at
accepted_at
attempt_number
current_attempt_id
current_attempt_started_at
lease_owner
lease_expires_at
fencing_token
created_at
updated_at
```

PayloadとContextは不透明な`bytea`として保存する。Transportは内部構造を検索しない。初期Stateは`accepted`、初期Versionは`1`、初期Sequenceは`1`とする。

PostgreSQL Senderは低レベルTransportであり、Canonical Journalは生成しない。Deferred受付の上位OrchestratorがOperation State保存とCanonical Journal記録を同一Transactionへ統合する。

## Deferred Acceptance Orchestrator

Internal Deferred Acceptance Orchestratorは、同じDBAL Connection / Transaction内で次をCommitする。

```text
operations row insert
operation.received journal record
operation.accepted journal record
operations.next_sequence update
```

受付時のJournal Sequenceは`1`と`2`を使う。受付完了後のOperation Stateは`accepted`で、次にWorkerが予約するSequenceは`3`になる。

このOrchestratorはHandlerを実行しない。Deferred Dispatcher、Worker Claimは後続Runtime層で扱う。

## HTTP Deferred Acceptance

HTTP AdapterはRouteを持つDeferred Operationを受け付け、Operation CodecでPayloadとExecutionContextをMessage化し、Deferred Acceptance Orchestratorへ渡す。

受付成功時はHandler完了を待たずにHTTP 202を返す。既定JSON Responseは次を含む。

```json
{
  "status": "accepted",
  "operationId": "...",
  "acceptedAt": "2026-07-10T00:00:01.123456Z"
}
```

HTTP層はDeferred受付用のPortへ依存し、具体的なRegistry、Codec、PostgreSQL Transaction構成はInternal実装へ閉じる。

## PostgreSQL Worker Claim

PostgreSQL Receiverは、EligibleなOperationを短いTransaction内で1件Claimする。

Claim対象は次の条件を満たすOperationである。

```text
state IN ('accepted', 'retry_scheduled')
available_at <= claimedAt
```

Receiverは`FOR UPDATE SKIP LOCKED`で行Lockを取得し、同じTransactionでStateを`running`へ更新する。併せてLease Owner、Lease期限、Fencing Token、State Versionを更新し、Codec済みMessageと不透明なClaim Tokenを持つ`OperationClaim`を返す。

Lease OwnerとLease DurationはPostgreSQL Receiverの構成値であり、ExecutionContextや業務Handlerへ公開しない。Handler実行中にDatabase Transactionは保持しない。

## Deferred Worker Runtime

Internal Worker RuntimeはClaim済みMessageをOperationValueとExecutionContextへDecodeし、Operation MetadataからOperation Definitionを復元する。

Attempt開始Boundaryでは、同じDBAL Transaction内で次をCommitする。

```text
attempt_number update
next_sequence update
state_version update
current_attempt_id update
current_attempt_started_at update
attempt.started journal record
```

`current_attempt_id` と `current_attempt_started_at` は、Handler実行中にWorker Processが落ちた場合でも、失効したAttemptを後から確定的に閉じるための復元情報である。Handler呼び出し前にAttempt Contextを生成し、Journalへ`attempt.started`を保存する前にOperations Rowへ現在Attempt情報を保存する。

その後、Handler実行中はDatabase Transactionを保持しない。

Handlerが成功した場合、Result反映BoundaryではFencing Tokenを検証し、同じDBAL Transaction内で次をCommitする。

```text
state = completed
next_sequence update
state_version update
current attempt clear
attempt.succeeded journal record
operation.completed journal record
```

Handlerが業務Rejectを返した場合は、同じBoundaryでStateを`rejected`へ更新し、`operation.rejected` Journalを保存する。

Handler例外が発生した場合、Worker Runtimeは例外を捕捉し、Failure BoundaryでFencing Tokenを検証して`attempt.failed` Journalを保存する。Operation Stateは一度`supervising`へ進め、Lease情報と現在Attempt情報は解除する。例外は記録後に再throwする。

その後、Supervision Policyの判断に基づいて、同じTransaction内で`attempt.retry_scheduled`、`operation.failed`、または`operation.dead_lettered`を保存する。Retry予定時はStateを`retry_scheduled`へ遷移させ、`available_at`を次回実行予定時刻へ更新する。Retry不能または上限到達時はStateを`failed`へ遷移させる。Dead Letter時はStateを`dead_lettered`へ遷移させ、Dead Letters Tableへ調査用Recordを保存する。

`attempt.failed` Dataは、例外型、例外Message、現時点のRetryable判定を保持する。

WorkerはHandler実行中にHeartbeatを送り、Running OperationのLease期限を延長できる。HeartbeatはClaim Token内のOperation IDとFencing Tokenを検証し、Running State以外または古いFencing Tokenを拒否する。

## Claim Settlement

Claim Settlementは低レベルTransport Portであり、Lifecycle Journal Eventを発行しない。Worker Runtimeの成功、Reject、Failure、Retry、Dead Letterの確定はLifecycle StoreがJournal込みで処理する。

`acknowledge()` はClaim Token内のOperation IDとFencing Tokenを検証し、OperationがTerminal Stateであり、Lease情報と現在Attempt情報が解除済みであることを確認する。Stateを変更しない。

`release()` はAttempt開始前のRunning Claimだけを`accepted`へ戻し、`available_at`を引数の時刻へ更新する。Lease情報は解除する。現在Attempt情報が保存済みのRunning OperationはHandler実行開始後とみなし、`release()`を拒否する。

## Lease Expired Recovery

Lease Expired Recoveryは、Lease期限を過ぎたRunning Operationを短いTransactionで1件予約する。対象は次の条件を満たすOperationである。

```text
state = running
lease_expires_at <= expiredAt
current_attempt_id IS NOT NULL
current_attempt_started_at IS NOT NULL
```

予約時にOperation Stateを`supervising`へ進め、Lease情報と現在Attempt情報を解除する。Recoveryは保存済みの現在Attempt情報からAttempt Contextを復元し、`lease_expired`の`attempt.failed`をJournalへ保存する。

`lease_expired`はFramework内部のRetryableな構造化Errorとして扱う。Recovery後は通常のFailure Boundaryと同じくSupervision Policyへ渡し、Policyの判断に基づいてRetry、Fail、Dead Letterのいずれかへ遷移させる。

## Current Scope

現在の低レベルTransport PortはEnqueue、Claim、Heartbeat、Acknowledge、Releaseを提供する。InMemory AdapterはUnit Test用のQueue Semanticsだけを提供し、PostgreSQL側のLifecycle StoreはAttempt、Journal、Outcome、Recoveryを担う。

現在も次を低レベルTransport自身では実装しない。

- Deferred Dispatcher
- Attempt開始前Crashの自動復旧
