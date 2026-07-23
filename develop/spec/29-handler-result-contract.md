# Handler Result Contract

## OperationResult

標準Typed Self-handled Operationは具象 `Outcome` を直接返す。値のない成功は `void` とする。

```php
public function handle(CreateOrderValue $value): OrderCreated;
public function handle(RebuildIndexValue $value): void;
```

予期された業務拒否は `OperationRejectedException` をthrowする。Framework Invocation Boundaryが内部 `OperationResult` へ正規化する。その他のThrowableはシステム障害として既存Supervisionへ伝播する。

Legacy Self-handled／Separate Handlerは互換Contractとして `OperationResult<TOutcome>` を返す。

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

Legacy Handlerは成功と予期された業務拒否をResultで表し、システム障害は例外としてFramework実行境界へ伝播させる。

## 生成

Legacy Handler利用者は公開Static FactoryだけでResultを生成する。

```php
OperationResult::completed($outcome);
OperationResult::completed();
OperationResult::completed($outcome, $operationId); // Idempotency Replay correlation
OperationResult::rejected($reason);
```

Constructorと内部表現は公開Contractにしない。Frameworkは公開Query MethodでCompletedとRejectedを判定する。

```php
public function isCompleted(): bool;
public function isRejected(): bool;
public function outcome(): Outcome;
public function rejectionReason(): RejectionReason;
public function operationId(): ?OperationId;
public function isReplayed(): bool;
```

状態に合わないAccessorは `\LogicException` を投げる。

## EmptyOutcome

標準Typed Self-handledでは `void`、Legacy Handlerでは `completed()` を許可する。

内部およびWire Schemaでは専用の `EmptyOutcome` として扱い、`null` をOutcomeとして使用しない。

## RejectionReason

拒否は安定したCategoryとCodeで表す。CategoryはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleを扱う。

Codeは小文字英数字を基本とし、`.`、`_`、`-` の区切りを許可する。自由文Messageと任意detailsは保持せず、利用者向け表現はResponderで生成する。

## Compatibility

D075により標準Typed Self-handledは直接Outcome／Void Returnへ移行した。Legacy Self-handled／Separate HandlerではD035／D052の `OperationResult` Contractを維持する。
