# ExecutionContext API

## ExecutionContext

`ExecutionContext` はFramework管理の `#[PublicApi] final readonly class` とする。

Operation ID、受付時刻、Correlation ID、Causation ID、Actor Context、Deadline等、Operationの伝播と追跡に必要な不変Metadataを保持する。

利用者による継承や独自実装は認めない。アプリケーション固有Metadataは登録済みContext Extensionで扱う。

Tenant、Idempotency Key、Context Extensionの型とPolicyは後続Taskで追加する。

## Public API

```php
public function __construct(
    OperationId $operationId,
    \DateTimeImmutable $receivedAt,
    CorrelationId $correlationId,
    ?CausationId $causationId = null,
    ?AttemptContext $attempt = null,
    ?\DateTimeImmutable $deadline = null,
    ?ActorContext $actorContext = null,
);

public function operationId(): OperationId;
public function receivedAt(): \DateTimeImmutable;
public function correlationId(): CorrelationId;
public function causationId(): ?CausationId;
public function attempt(): ?AttemptContext;
public function deadline(): ?\DateTimeImmutable;
public function actorContext(): ?ActorContext;
```

Actorを持たない既存Call Siteとの後方互換性を保つため、`actorContext`はConstructorの末尾へ追加する。Anonymous HTTP Operationでもexecution Actorを確定できるRuntimeではActorContextを生成し、Framework内部のActor未設定経路だけ`null`を許容する。

ActorContextはorigin、authorization、executionを区別する。Actor ID／Typeだけを保持し、Credential、Role、Permission、Claimを含めない。詳細は[Authentication and HTTP Middleware](06-auth-and-middleware.md)を正本とする。

公開 `with...()` Methodは提供しない。PHPにはpackage-privateまたはfriend classがないためConstructorをPublic APIとし、Framework自身の生成と遷移はInternal Factoryへ集約する。

Constructorは受け取った時刻をUTCへ正規化する。

## AttemptContext

Attempt固有情報はOptionalな `AttemptContext` としてまとめる。

```php
?AttemptContext $attempt
```

`AttemptContext` は `#[PublicApi] final readonly class` とし、次を必須で保持する。

- Attempt ID
- 1始まりのAttempt番号
- Attempt開始時刻

```php
public function __construct(
    AttemptId $id,
    int $number,
    \DateTimeImmutable $startedAt,
);

public function id(): AttemptId;
public function number(): int;
public function startedAt(): \DateTimeImmutable;
```

Attempt番号が1未満の場合は `\InvalidArgumentException` を投げ、開始時刻はUTCへ正規化する。

Attempt開始前は `attempt() === null` とする。Attempt開始後はAttempt ID、Attempt番号、開始時刻が必ず揃う。

Deferred受付時のExecutionContextはAttemptを持たず、WorkerがAttemptを開始するときにAttemptContextを含む新しいExecutionContextを生成する。

## 生成と遷移

ExecutionContextの生成と遷移はFramework内部の目的別Factoryが行う。

```text
ExecutionContextFactory::receive(...)
ExecutionContextFactory::startAttempt(...)
ExecutionContextFactory::createChild(...)
```

Factoryは `BlackOps\Internal\ExecutionContext` に配置し、IdentifierFactoryとPSR-20 Clockを注入する。

- `receive()` は新しいOperation IDを発行し、同じUUID値からRoot Correlation IDを初期化する
- `receive()` はRuntimeが解決したActorContextを受け取れる
- `startAttempt()` は新しいAttempt ID、指定された1始まりのAttempt番号、UTC開始時刻を持つ新Contextを返し、指定された場合はexecution Actorだけを置き換える
- Deadline到達後のAttempt開始は `\LogicException` で拒否する
- `createChild()` は新しいOperation ID、親Correlation ID、親Operation IDを値とするCausation ID、UTC受付時刻を持ち、Attemptを持たない。originとauthorizationを継承し、指定された場合はexecution Actorだけを置き換える
- 子Deadlineは親Deadlineより後にできない。省略時は親Deadlineを継承する

## Codec

Execution Transport用Context Codecは`actors` Objectを追加する。既存Payloadとの互換性のためField欠落を`actorContext === null`としてDecodeする。

```json
{
  "actors": {
    "origin": {"id": "123", "type": "user"},
    "authorization": {"id": "123", "type": "user"},
    "execution": {"id": "http", "type": "system"}
  }
}
```

ActorContextがnullの場合は`actors: null`とする。ActorRefは`id`と`type`以外をEncodeしてはならない。不正型、空文字、余分なCredential系Fieldを含むContextはCodec Errorとして拒否する。

利用者はExecutionContextを読み取るだけとし、Operation ID、Correlation ID等を任意変更する公開 `with...()` Methodは提供しない。
