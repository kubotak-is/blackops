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

このOrchestratorはHandlerを実行しない。HTTP 202 Response変換、Deferred Dispatcher、Worker Claimは後続Runtime層で扱う。

## Current Scope

現在のDeferred Transport実装では、まだ次を実装しない。

- Deferred Dispatcher
- Worker Runtime
- Retry、Crash Recovery、Dead Letter
- HTTP 202 Response変換
