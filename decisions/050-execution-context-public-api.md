# D050: ExecutionContext Public API

Status: Decided

## Context

D009、D025、D037によりExecutionContextの責務と不変条件は決まっているが、実装に必要なGetterと内部Factoryの正確なSignatureは未確定である。

また、D025ではAttemptContextをAttempt IDと開始時刻で定義した後、D037で1始まりのAttempt番号が追加された。新しいD037を優先して公開APIへ反映する。

Actor、Tenant、Idempotency Key、Context ExtensionはExecutionContextのOptional要素として決定済みだが、それぞれの型、Registry、伝播Policyは独立した設計が必要である。最初のVertical Sliceで未確定APIを同時に固定するか、Core Contextを先行実装するかを決める。

## Question 1: P1-002の実装範囲

### Options

- A: Core Context、Attempt、Deadline、内部Factoryを先行実装し、Actor、Tenant、Idempotency Key、Context Extensionは後続Taskで追加する
- B: Optional要素を含むExecutionContext全体を一括設計・実装する
- C: AttemptContextだけを先行実装する

### Recommendation

Aを推奨する。OperationEnvelopeと最初のInline実行に必要な不変条件を先に確定でき、認証・Multi-tenancy・Extension Registryを拙速にPublic APIへ固定せずに済む。後続のOptional Getter追加は後方互換な拡張として行う。

## Question 2: Public API

### Options

- A: 次のGetterだけを提供し、Constructorと状態変更Methodは公開しない
- B: Public readonly Propertyを提供する
- C: Public Constructorと `with...()` Methodを提供する

### Recommendation

Aを基本方針として推奨する。ただしPHPにはpackage-privateまたはfriend classがなく、別ClassであるInternal Factoryからprivate Constructorを呼べない。ReflectionやClosure bindingによる迂回は採用しない。

```php
final readonly class AttemptContext
{
    public function id(): AttemptId;
    public function number(): int;
    public function startedAt(): \DateTimeImmutable;
}

final readonly class ExecutionContext
{
    public function operationId(): OperationId;
    public function receivedAt(): \DateTimeImmutable;
    public function correlationId(): CorrelationId;
    public function causationId(): ?CausationId;
    public function attempt(): ?AttemptContext;
    public function deadline(): ?\DateTimeImmutable;
}
```

両方へ `#[PublicApi]` を付け、公開 `with...()` Methodは設けない。

生成境界には次の二案がある。

- A1: ConstructorもPHP Public APIとして公開し、全InvariantをConstructorで検証する。Framework本体は常にInternal Factoryを使う
- A2: Publicな `@internal` Named Constructorを設ける。ただし利用者による呼出しをPHPの可視性では禁止できない

A1を推奨する。呼出可能なのに非公開扱いとするA2より、実際のPHP APIとSemVer Contractが一致する。Contextはreadonlyであり、生成後の改変はできない。Frameworkが行うID発行、親子伝播、Attempt遷移は引き続きInternal Factoryだけへ集約する。

## Question 3: 内部Factory

### Options

- A: `receive()`、`startAttempt()`、`createChild()` の目的別Methodを設け、IdentifierFactoryとClockを注入する
- B: 任意Fieldを受ける汎用 `create()` だけを設ける
- C: 各公開Context型のPublic Constructorを直接呼ぶ

### Recommendation

Aを推奨する。

```php
ExecutionContextFactory::receive(?\DateTimeImmutable $deadline = null): ExecutionContext;
ExecutionContextFactory::startAttempt(
    ExecutionContext $context,
    int $attemptNumber,
): ExecutionContext;
ExecutionContextFactory::createChild(
    ExecutionContext $parent,
    ?\DateTimeImmutable $deadline = null,
): ExecutionContext;
```

Factoryは `BlackOps\Internal\ExecutionContext` に置き、`IdentifierFactory` とPSR-20 Clockを注入する。

- `receive()` は新しいOperation IDを発行し、同じUUID値からCorrelation IDを初期化する
- `startAttempt()` はAttempt番号1以上を要求し、新しいAttempt IDとUTC開始時刻を持つ新Contextを返す
- `createChild()` は新しいOperation ID、親のCorrelation ID、親Operation IDと同じUUID値のCausation ID、UTC受付時刻を持ち、Attemptは持たない
- 子Deadlineは親Deadlineより後にできない。引数省略時は親Deadlineを継承する
- Deadline超過時のInternal Factoryは `\LogicException` で拒否する。公開Failure型とLifecycle上の最終状態は後続Taskで確定する

## Decision

[DECISION]

Question 1はA、Question 2はAをPublic Constructorへ修正したA1、Question 3はAを採用する。

P1-002ではCore Context、Attempt、Deadline、内部Factoryを実装する。Actor、Tenant、Idempotency Key、Context Extensionは既存Decisionを維持しつつ、型とPolicyを後続Taskで確定して追加する。

AttemptContextはD037を反映し、Attempt ID、1始まりのAttempt番号、UTC開始時刻を必須とする。

ExecutionContextとAttemptContextは `#[PublicApi] final readonly class` とし、Invariantを検証するPublic ConstructorとGetterだけを持つ。公開 `with...()` Methodは設けない。Framework自身による生成と遷移は内部の目的別Factoryだけが行う。

Public Constructorは次のSignatureとする。

```php
public function AttemptContext::__construct(
    AttemptId $id,
    int $number,
    \DateTimeImmutable $startedAt,
);

public function ExecutionContext::__construct(
    OperationId $operationId,
    \DateTimeImmutable $receivedAt,
    CorrelationId $correlationId,
    ?CausationId $causationId = null,
    ?AttemptContext $attempt = null,
    ?\DateTimeImmutable $deadline = null,
);
```

Attempt番号が1未満の場合は `\InvalidArgumentException` を投げる。すべての時刻はConstructorでUTCへ正規化する。

Internal Factoryでは次の規則を追加する。

- Deadline到達後の `startAttempt()` は `\LogicException` で拒否する。Lifecycle上の最終状態と公開Failure型は後続Taskで決める
- 子Deadlineが親Deadlineより後の場合は `\InvalidArgumentException` で拒否する
- 親にDeadlineがあり子Deadlineを省略した場合は親Deadlineを継承する
- 生成と遷移でReflection、Closure binding、非公開Property書換えを使用しない

[/DECISION]
