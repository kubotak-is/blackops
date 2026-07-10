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
attempt.started journal record
```

その後、Handler実行中はDatabase Transactionを保持しない。

Handlerが成功した場合、Result反映BoundaryではFencing Tokenを検証し、同じDBAL Transaction内で次をCommitする。

```text
state = completed
next_sequence update
state_version update
attempt.succeeded journal record
operation.completed journal record
```

Handlerが業務Rejectを返した場合は、同じBoundaryでStateを`rejected`へ更新し、`operation.rejected` Journalを保存する。

Handler例外、Retry、Dead Letter、Heartbeat、Claim Settlementは後続Phaseの責務として残す。

## Current Scope

現在のDeferred Transport実装では、まだ次を実装しない。

- Deferred Dispatcher
- Heartbeat / Settlement
- Retry、Crash Recovery、Dead Letter
