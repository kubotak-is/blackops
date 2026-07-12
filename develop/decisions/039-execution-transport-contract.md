# D039: Execution Transport Contract

Status: Decided

## Context

Deferred実行のLease、Claim、Heartbeat、Retry、Fencing規則が確定した。これらをSQLite、SQS等で交換可能にするため、Execution Transportの公開Contractを決める。

Transportは業務Handlerへ露出せず、Framework RuntimeとInfrastructure Adapterの境界として機能する。

## Question 1: Transportへ渡す形式

### Options

- A: Codec済みの `DeferredOperationMessage` を渡す
- B: PHPの `OperationEnvelope` Objectをそのまま渡す
- C: Adapterごとに任意の配列を渡す

### Recommendation

Aを推奨する。

```text
DeferredOperationMessage
  operationId
  operationType
  schemaVersion
  encodedPayload
  encodedContext
  availableAt
```

Process境界でPHP Object Serializationへ依存せず、Adapter間で同じCanonical CodecとVersioningを利用できる。

[ANSWER]

A

[/ANSWER]

## Question 2: Enqueueの結果

### Options

- A: Durable保存成功後に `DeferredAcknowledgement` を返し、失敗時はExceptionを投げる
- B: `bool` を返す
- C: 戻り値を持たず、成功確認をしない

### Recommendation

Aを推奨する。

```text
DeferredAcknowledgement
  operationId
  acceptedAt
```

HTTP Adapterはこれを202 Responseへ変換できる。Acknowledgementは処理完了ではなく、Frameworkが後続実行の責任を引き受けたことを表す。

[ANSWER]

A

[/ANSWER]

## Question 3: Claim単位

### Options

- A: MVPは一回に一件を返す `claim()` とする
- B: 最初からBatch Claimだけを提供する
- C: CallbackへOperationをPushする

### Recommendation

Aを推奨する。

Workerの停止、Heartbeat、Attempt Scope、Fencingを一件単位で実装できる。将来は別のBatch Capabilityを追加できる。

[ANSWER]

A

[/ANSWER]

## Question 4: Claim Contract

次をMVPの基本Contractとするか。

```php
interface ExecutionTransport
{
    public function enqueue(
        DeferredOperationMessage $message,
    ): DeferredAcknowledgement;

    public function claim(ClaimRequest $request): ?OperationClaim;

    public function heartbeat(OperationClaim $claim): OperationClaim;

    public function acknowledge(OperationClaim $claim): void;

    public function release(
        OperationClaim $claim,
        DateTimeImmutable $availableAt,
    ): void;
}
```

`OperationClaim` はMessageとTransport内部で検証する不透明なClaim Tokenを持つ。Handlerへは渡さない。

### Options

- A: このContractを採用する
- B: EnqueueとWorker側Contractを別Interfaceへ分離する
- C: AdapterごとにMethodを自由定義する

### Recommendation

Bを推奨する。

```text
OperationSender        enqueue
OperationReceiver      claim
ClaimHeartbeat         heartbeat
ClaimSettlement        acknowledge / release
ExecutionTransport     上記すべてを束ねる
```

HTTP ProcessはSenderだけ、WorkerはReceiver等だけへ依存できる。SQLite Adapterはすべてを束ねたExecutionTransportを実装できる。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

Execution TransportへはPHP Objectを直接渡さず、Canonical Codecで変換済みの `DeferredOperationMessage` を渡す。MessageはOperation ID、Type、Schema Version、Encoded Payload、Encoded Context、Available Atを持つ。

Durable保存に成功したSenderは `DeferredAcknowledgement` を返し、失敗時は専用Exceptionを投げる。AcknowledgementはOperation IDとAccepted Atを持ち、処理完了ではなく後続実行の責任引受を表す。

MVPのClaimは一回に一件とする。Batch Claimは将来の追加Capabilityとして扱う。

Transport Contractは責務別Interfaceへ分離する。

```text
OperationSender        enqueue
OperationReceiver      claim
ClaimHeartbeat         heartbeat
ClaimSettlement        acknowledge / release
ExecutionTransport     上記すべてを束ねる
```

`OperationClaim` はMessageと不透明なClaim Tokenを持つが、業務Handlerへは渡さない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Process境界でPHP Serializationへ依存しない。
- SQLite、SQS等が同じMessage SchemaとVersioningを利用できる。
- HTTP AdapterはDeferredAcknowledgementをHTTP 202へ変換できる。
- HTTP ProcessはSenderだけ、WorkerはReceiver、Heartbeat、Settlementだけへ依存できる。
- 同期Adapterへ不要なMethod実装を強制せず、Interface Segregationを保てる。
- SQLite Adapter等は全Portを束ねたExecutionTransportを実装できる。
- MessageのSensitive PayloadはTransport Capabilityに応じて安全にEncodeする必要がある。

[/CONSEQUENCES]
