# D035: Handler Result Contract

Status: Decided

## Context

D006では、Handlerが成功または予期された業務拒否を `OperationResult` で返すと決定した。

```php
OperationResult::completed($outcome);
OperationResult::rejected($reason);
```

一方、D023では `OperationHandler::handle()` の戻り値を直接 `Outcome` とした。このままでは、在庫不足や業務上の競合等を例外にせず `operation.rejected` として返す経路が型Contractに存在しない。

状態遷移を定義する前に、Handlerの戻り値を一本化する。

## Question 1: Handlerの戻り値

### Options

- A: D006を維持し、`OperationResult<TOutcome>` を返す
- B: D023を維持し、HandlerはOutcomeだけを返す
- C: `Outcome|RejectionReason` のUnion Typeを返す

### Recommendation

Aを推奨する。

成功と業務拒否を型付きResultとして明示し、システム障害だけを例外にできる。

```php
/**
 * @template TValue of OperationValue
 * @template TOutcome of Outcome
 */
interface OperationHandler
{
    /**
     * @param OperationEnvelope<TValue> $operation
     * @return OperationResult<TOutcome>
     */
    public function handle(OperationEnvelope $operation): OperationResult;
}
```

[ANSWER]

A

[/ANSWER]

## Question 2: OperationResultの型

### Options

- A: `Completed<TOutcome>` と `Rejected` のsealedな実装を持つInterfaceにする
- B: Status EnumとNullable Outcome／Reasonを持つ一つのClassにする
- C: `OperationResult::completed()` 等のStatic Factoryだけを公開し、内部表現は非公開にする

### Recommendation

Cを推奨する。

利用者はFactoryでResultを生成し、Frameworkは `isCompleted()`、`isRejected()` 等で判定する。実装表現を公開Contractとして固定せず、不正な「OutcomeもReasonもない」状態をConstructorから作れない。

```php
OperationResult::completed($outcome);
OperationResult::completed();
OperationResult::rejected($reason);
```

[ANSWER]

C

[/ANSWER]

## Question 3: Outcomeなしの成功

### Options

- A: `completed()` を許可し、内部では専用の `EmptyOutcome` として扱う
- B: すべてのOperationに具体的なOutcome Classを要求する
- C: `null` をOutcomeとして扱う

### Recommendation

Aを推奨する。

Wire SchemaとJournalでは常にOutcome Typeを持たせつつ、利用者は値のない成功を簡潔に返せる。`null` に複数の意味を持たせない。

[ANSWER]

A

[/ANSWER]

## Supersedes

このDecisionでAを選んだ場合、D023の「Handlerが直接Outcomeを返す」という部分だけを置き換える。Marker Interfaceと `#[PublicApi]` の決定は維持する。

## Decision

[DECISION]

Handlerは直接Outcomeを返さず、`OperationResult<TOutcome>` を返す。

利用者は公開Static FactoryだけでResultを生成する。

```php
OperationResult::completed($outcome);
OperationResult::completed();
OperationResult::rejected($reason);
```

Constructorと内部表現は公開Contractにせず、不正な状態を生成できないようにする。Frameworkは公開Query MethodによってCompletedとRejectedを判定する。

値を返さない成功では `completed()` を許可し、内部およびWire Schemaでは専用の `EmptyOutcome` として扱う。`null` をOutcomeとして扱わない。

システム障害はOperationResultへ変換せず、例外としてFramework実行境界へ伝播させる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 成功、業務拒否、システム障害を明確に区別できる。
- Handlerの業務拒否を `operation.rejected` へ型安全に変換できる。
- Nullable OutcomeとNullable Reasonの不正な組み合わせを利用者が生成できない。
- 値のない成功もJournalとWire Schemaでは明示的なOutcome Typeを持つ。
- D023の「Handlerが直接Outcomeを返す」という部分を置き換える。
- D023のMarker Interface、単一 `handle()` Method、`#[PublicApi]` は維持する。

[/CONSEQUENCES]
