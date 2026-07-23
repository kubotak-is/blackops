# Execution Transport Contract

## DeferredOperationMessage

Execution TransportへPHP Objectを直接渡さず、Canonical Codecで変換済みのMessageを渡す。

```text
DeferredOperationMessage
  operationId
  operationType
  schemaVersion
  encodedPayload
  encodedContext
  availableAt
```

PHP Serializationは使用しない。Sensitive PayloadはTransport Capabilityに応じて安全にEncodeする。

## DeferredAcknowledgement

Durable保存に成功したSenderは次を返す。

```text
DeferredAcknowledgement
  operationId
  acceptedAt
  replayed
```

失敗時は専用Exceptionを投げる。Acknowledgementは処理完了ではなく、Frameworkが後続実行の責任を引き受けたことを表す。

HTTP AdapterはAcknowledgementをHTTP 202 Responseへ変換できる。
Replay時は`replayed`だけを保持し、HTTP Adapterが`Idempotency-Replayed: true`と`Cache-Control: private, no-store`へ投影する。

## Claim

MVPのClaimは一回に一件とする。Batch Claimは将来の追加Capabilityとする。

`OperationClaim` はMessageとTransport内部で検証する不透明なClaim Tokenを持つ。OperationClaimを業務Handlerへ渡してはならない。

## Port

```text
OperationSender        enqueue
OperationReceiver      claim
ClaimHeartbeat         heartbeat
ClaimSettlement        acknowledge / release
ExecutionTransport     上記すべてを束ねる
```

HTTP ProcessはOperationSenderだけに依存できる。WorkerはOperationReceiver、ClaimHeartbeat、ClaimSettlementへ依存する。

SQLite等の総合Adapterはすべてを束ねたExecutionTransportを実装できる。
