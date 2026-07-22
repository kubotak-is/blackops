# Handler and Result

## BindingとValidation

FWが扱う入力検証を二段階へ分ける。

| 段階 | 内容 | 担当 |
| --- | --- | --- |
| Binding | 入力形式、必須フィールド、型変換 | Input Adapter / Value Binder |
| Value Validation | 文字数、範囲、形式などのAttribute規則 | OperationValue Validator |

DB上の重複、在庫、利用権限など外部状態との照合は、ユーザーがHandlerまたはDomain層で実装する。

BindingまたはValue Validationの失敗は、Operation IDを持つ `OperationRejected` として記録する。生入力は無条件に記録せず、安全なSnapshotだけを任意で追加できる。

### Declarative Value Validation

OperationValueのConstructor Promotion Propertyへ`BlackOps\Core\Validation\Attribute`のRuleを付与する。

| Attribute | Target value | Rule |
| --- | --- | --- |
| `NotBlank` | string | 空文字と空白だけの文字列を拒否する |
| `Length` | string | 文字数の`min`／`max`を検証する |
| `Range` | int／float | 数値の`min`／`max`を検証する |
| `Email` | string | Email形式を検証する |
| `Regex` | string | 指定Patternへ一致するか検証する |
| `Count` | array | 要素数の`min`／`max`を検証する |
| `Choice` | scalar | 許可した選択肢に含まれるか検証する |

`Range`、`Length`、`Count`は対象の意味を分離する。曖昧な`Min`／`Max` Attributeは提供しない。Nested Object、DB照合、Cross-field Rule、Custom Callbackは初期Scopeへ含めない。

Validatorは全Propertyを検証してViolationを集約する。ViolationはField、Rule、安定Codeだけを持ち、Raw Valueを保持しない。Value Validation FailureはHandler実行前にCategory `validation`、Code `validation.failed`のRejected Resultへ変換する。

## Handler

標準形ではOperation Definition自身が業務Handlerとなる。一つのOperationは一つの `handle()` Methodを持つ。

標準Handlerは型付きOperationValueとOptional `ExecutionContext` をNative Parameterで受け取る。Legacy／Separate Handlerだけが `#[HandledBy]` と読み取り専用Operation Envelopeを使用する。

## Handler Result

標準Typed Self-handled Operationは成功時に具象Outcomeを直接返し、値のない成功では `void` を返す。予期された業務上の拒否はFramework標準 `OperationRejectedException` をthrowする。

```php
public function handle(OrderValue $value): OrderCreated
{
    if (!$this->inventory->isAvailable($value->items)) {
        throw OperationRejectedException::conflict('inventory_unavailable');
    }

    return new OrderCreated($orderId);
}
```

Framework Invocation BoundaryはNative Outcome／Void／`OperationRejectedException` を内部 `OperationResult` へ正規化する。

OutcomeはNative Scalarに加え、`OutcomeData`を実装するReadonly DTOと`#[ListOf]`で宣言したTyped ListをOutput Shapeとして持てる。Structured Shapeの詳細とUnsupported Typeは[Structured Outcome Contract](73-structured-outcome-contract.md)を正本とする。

Raw Session Token等を一度だけHTTPへ返すOperationは、通常Outcomeではなく`EphemeralOutcome`を実装した具象Outcomeを返す。Ephemeral OutcomeもNative Signatureから推論するが、Route付き明示Inlineだけを許可する。Credential Propertyは`#[Sensitive]`を必須とし、実値はJournal、Outcome Store、Status、Consoleへ渡さない。Frameworkは受付／完了Lifecycleだけを安全な空Dataで記録する。

Legacy Self-handled／Separate Handlerは互換Contractとして次を使用する。

- `OperationResult::completed($outcome)`：成功
- `OperationResult::completed()`：返却データのない成功
- `OperationResult::rejected($reason)`：予期された業務上の拒否

```php
public function handle(OperationEnvelope $operation): OperationResult
{
    if (!$this->inventory->isAvailable($operation->value()->items)) {
        return OperationResult::rejected(
            RejectionReason::conflict('inventory_unavailable'),
        );
    }

    return OperationResult::completed(
        new OrderCreated($orderId),
    );
}
```

## システム障害

システム障害はHandlerから例外としてthrowする。FWの実行境界が捕捉し、`AttemptFailed` を記録する。その後の処置はSupervision Policyが判断する。

Frameworkは `OperationRejectedException` だけをRejectedへ変換する。その他のThrowableをRejected Resultとして隠さない。

## 型契約

標準Typed Self-handled OperationではNative Signatureへ次の契約を集約する。

- 第一引数：OperationValue
- Optional第二引数：ExecutionContext
- Return Type：成功Outcome、HTTP Inline専用Ephemeral Outcome、またはvoid

Legacy／Separate Handlerでは `#[Accepts]`、`#[HandledBy]`、`#[Returns]` を維持する。Operation RegistryおよびCIはNative Signature、Attribute、Manifestの整合性を検証する。
