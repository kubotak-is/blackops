# Execution Context

BlackOps Phase 1 の Inline Vertical Slice で利用する ExecutionContext、AttemptContext、Internal ExecutionContextFactory の実装を記録する。確定仕様の正本は `develop/spec/19-execution-context-api.md`、`develop/spec/01-core-model.md`、`develop/spec/31-deferred-claim-and-attempt.md`、判断経緯は `develop/decisions/050-execution-context-public-api.md` である。本文書はFramework実装者向けにPublic APIと不変条件を整理する。

## AttemptContext

`BlackOps\Core\AttemptContext` は Attempt 固有の不変Metadataとし、`#[PublicApi] final readonly class` とする（Spec 19、Spec 31、D050）。

```php
namespace BlackOps\Core;

#[PublicApi]
final readonly class AttemptContext
{
    public function __construct(
        AttemptId $id,
        int $number,
        \DateTimeImmutable $startedAt,
    );

    public function id(): AttemptId;
    public function number(): int;
    public function startedAt(): \DateTimeImmutable;
}
```

不変条件：
- ConstructorはPublic APIとし、すべての生成経路（利用者／Internal Factory双方）で同一の検証を受ける。
- `number` は1以上の整数とし、1未満の場合は `\InvalidArgumentException` を投げる。例外Messageへ入力値そのものを含めない。
- `startedAt` はConstructorでUTCへ正規化する。既にUTCの時刻は再変換せずそのまま保持する。
- Attempt未開始状態では `ExecutionContext::attempt()` が `null` を返し、Attempt開始後はAttemptContextが必ず揃う。

## ExecutionContext

`BlackOps\Core\ExecutionContext` は Operationの伝播と追跡に必要な不変Metadataとし、`#[PublicApi] final readonly class` とする（Spec 19、D050）。

```php
namespace BlackOps\Core;

#[PublicApi]
final readonly class ExecutionContext
{
    public function __construct(
        OperationId $operationId,
        \DateTimeImmutable $receivedAt,
        CorrelationId $correlationId,
        ?CausationId $causationId = null,
        ?AttemptContext $attempt = null,
        ?\DateTimeImmutable $deadline = null,
    );

    public function operationId(): OperationId;
    public function receivedAt(): \DateTimeImmutable;
    public function correlationId(): CorrelationId;
    public function causationId(): ?CausationId;
    public function attempt(): ?AttemptContext;
    public function deadline(): ?\DateTimeImmutable;
}
```

不変条件：
- ConstructorはPublic APIであり、利用者とInternal Factory双方で不正状態を作らない。
- `receivedAt` と `deadline`（非null時）はConstructorでUTCへ正規化する。
- 公開 `with...()` Methodは提供しない。生成後の改変は不可で、状態遷移は新ExecutionContextをInternal Factoryが構築することで表現する（Spec 19、D050）。
- P1-002のScopeでは Actor、Tenant、Idempotency Key、Context Extension は未実装とする。これらは後続TaskでOptional Getterとして後方互換な拡張で追加する（D050）。

## Internal ExecutionContextFactory

`BlackOps\Internal\ExecutionContext\ExecutionContextFactory` は Root受信、Attempt開始、子Operation Context生成を集約するInternal Factoryとする（Spec 19、D050）。

```php
namespace BlackOps\Internal\ExecutionContext;

final readonly class ExecutionContextFactory
{
    public function __construct(
        IdentifierFactory $identifiers,
        \Psr\Clock\ClockInterface $clock,
    );

    public function receive(?\DateTimeImmutable $deadline = null): ExecutionContext;
    public function startAttempt(ExecutionContext $context, int $attemptNumber): ExecutionContext;
    public function createChild(ExecutionContext $parent, ?\DateTimeImmutable $deadline = null): ExecutionContext;
}
```

- `IdentifierFactory` と PSR-20 `ClockInterface` を注入し、現在時刻の取得とID発行を内部Portへ抽象化する。Framework内部で現在時刻を直接生成しない（Spec 21）。
- Reflection、Closure binding、非公開Property書換えは使用しない。すべての遷移は既存ContextからGetterで値を読み、新ExecutionContextをPublic Constructorで構築する。

### `receive()`

- 新しいOperation IDを `IdentifierFactory::newOperationId()` で発行する。
- Root Correlation IDは `CorrelationId::fromString($operationId->toString())` でRoot Operation IDと同じUUID値から初期化する。
- 受付時刻は注入Clockの `now()` を使う。
- Causation ID、Attemptは `null`、Deadlineは引数（既定 `null`）をそのままConstructorへ渡す。

### `startAttempt()`

- 新しいAttempt ID、注入Clockの `now()` を開始時刻とする `AttemptContext` を生成し、それを含む新ExecutionContextを構築する。
- Attempt番号は1以上を要求し、`AttemptContext` のConstructorが1未満を `\InvalidArgumentException` で拒否するため、Factory側で重複検証しない。
- Deadline到達後のAttempt開始は `\LogicException` で拒否する。到達判定は `$clock->now() >= $context->deadline()` とし、Deadline時刻ちょうども到達とみなす。
- DeadlineがないContextではAttempt開始を常に許可する。
- Operation ID、Correlation ID、Causation ID、受付時刻、Deadlineは元Contextからそのまま伝播する。

### `createChild()`

- 新しいOperation IDを発行する。
- Causation IDは `CausationId::fromString($parent->operationId()->toString())` で親Operation IDと同じUUID値にする。
- Correlation IDは親のものをそのまま伝播する。
- 受付時刻は注入Clockの `now()` を使う。
- Attemptは `null` とする（Spec 19、Task Scope）。
- 子Deadlineは親Deadlineより後の時刻にできず、引数省略時は親Deadlineを継承する。
  - `$childDeadline === null` → 親Deadlineを継承。
  - 親Deadlineが `null` のとき任意の子Deadlineを受け入れる（親に制約がないため）。
  - 親Deadlineが非nullで `$childDeadline > $parentDeadline` のとき `\InvalidArgumentException` で拒否する。等価は許可する。
- DeadlineのUTC正規化はExecutionContextのConstructorが行うため、Factory側では行わない。

## Namespaceと依存方向

- Core Layer（`BlackOps\Core\AttemptContext`、`BlackOps\Core\ExecutionContext`）は外部Namespaceへ依存しない（Spec 16）。`BlackOps\Core\Identifier\*`、`BlackOps\Core\Attribute\PublicApi`、`BlackOps\Core\AttemptContext` はすべてCore内で完結する。
- Internal Layer（`BlackOps\Internal\ExecutionContext\ExecutionContextFactory`）は `Core` と `Library`（`Psr\Clock`）へ依存する。IdentifierFactoryは既存のInternal Layer型で依存方向を保つ。
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない。`ExecutionContextFactory` はConstructor、戻り値ともCore型と外部Library型のみで完結する。
- Symfony UID型は公開APIへ露出しない。

## Internal Execution Scope Provider

`BlackOps\Internal\Execution\ExecutionScopeProvider` はOperation実行中のcurrent envelopeを保持するInternal serviceである。

Scopeはstackとして管理する。nested executionではchild scopeを一時的にcurrentにし、child終了後はparent scopeを復元する。callbackが例外を投げた場合でも `finally` でscopeを閉じる。

Inline DispatcherはHandler実行境界でscopeを開始し、Handlerから戻るか例外で抜けた時点でscopeを終了する。これにより、後続のLogger decoratorはHandler実行中だけcurrent Operation contextを参照でき、Operation外のLogはcurrentなしとして扱える。

## 品質検査

P1-002のAcceptance Criteriaに基づき次の検査を実施し、すべて成功した。結果の詳細は `develop/orchestration/reports/P1-002-execution-context.md` に記録する。

- `composer validate --strict`
- `mago lint`
- `mago analyze`
- `vendor/bin/phpunit`
- `vendor/bin/deptrac`
