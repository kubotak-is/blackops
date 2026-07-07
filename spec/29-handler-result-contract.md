# Handler Result Contract

## OperationResult

Handlerは `OperationResult<TOutcome>` を返す。

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

成功と予期された業務拒否をResultで表し、システム障害は例外としてFramework実行境界へ伝播させる。

## 生成

利用者は公開Static FactoryだけでResultを生成する。

```php
OperationResult::completed($outcome);
OperationResult::completed();
OperationResult::rejected($reason);
```

Constructorと内部表現は公開Contractにしない。Frameworkは公開Query MethodでCompletedとRejectedを判定する。

```php
public function isCompleted(): bool;
public function isRejected(): bool;
public function outcome(): Outcome;
public function rejectionReason(): RejectionReason;
```

状態に合わないAccessorは `\LogicException` を投げる。

## EmptyOutcome

値を返さない成功では `completed()` を許可する。

内部およびWire Schemaでは専用の `EmptyOutcome` として扱い、`null` をOutcomeとして使用しない。

## RejectionReason

拒否は安定したCategoryとCodeで表す。CategoryはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleを扱う。

Codeは小文字英数字を基本とし、`.`、`_`、`-` の区切りを許可する。自由文Messageと任意detailsは保持せず、利用者向け表現はResponderで生成する。

## 置き換え

D023の「Handlerが直接Outcomeを返す」という決定を置き換える。

D023のMarker Interface、単一 `handle()` Method、`#[PublicApi]` の決定は維持する。
