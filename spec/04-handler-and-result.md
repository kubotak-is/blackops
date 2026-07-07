# Handler and Result

## BindingとValidation

FWが扱う入力検証を二段階へ分ける。

| 段階 | 内容 | 担当 |
| --- | --- | --- |
| Binding | 入力形式、必須フィールド、型変換 | Input Adapter / Value Binder |
| Value Validation | 文字数、範囲、形式などのAttribute規則 | OperationValue Validator |

DB上の重複、在庫、利用権限など外部状態との照合は、ユーザーがHandlerまたはDomain層で実装する。

BindingまたはValue Validationの失敗は、Operation IDを持つ `OperationRejected` として記録する。生入力は無条件に記録せず、安全なSnapshotだけを任意で追加できる。

## Handler

Operation DefinitionとHandlerは `#[HandledBy(...)]` で関連付ける。一つのOperationは一つの業務Handlerを持つ。

Handlerは、型付きOperationValueとExecutionContextを内包した読み取り専用Operation Envelopeを一つ受け取る。

## OperationResult

Handlerは成功または業務上の拒否をFW標準のOperationResultで返す。

- `OperationResult::completed($outcome)`：成功
- `OperationResult::completed()`：返却データのない成功
- `OperationResult::rejected($reason)`：予期された業務上の拒否

Operation Definitionは `#[Returns(...)]` で成功時のOutcome型を宣言する。

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

予期された業務上の不成立を例外として扱わず、システム障害をRejected Resultとして隠さない。

## 型契約

Operation Definitionには次の契約を集約する。

- `#[Accepts(...)]`：OperationValue
- `#[HandledBy(...)]`：Handler
- `#[Returns(...)]`：成功Outcome

Operation RegistryおよびCIは、これらとHandlerシグネチャの整合性を検証する。
