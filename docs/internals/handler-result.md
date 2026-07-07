# Handler and Result

`OperationHandler<TValue, TOutcome>` は、一つの `handle()` MethodでOperationEnvelopeを受け取り、OperationResultを返す。

OperationResultは成功、予期された業務拒否、システム障害を区別する。成功と業務拒否はResultで表し、システム障害は例外として実行境界へ伝播する。

## Completed

`OperationResult::completed($outcome)` は具体的なOutcomeを持つ成功を生成する。返却値のない成功では `completed()` を使用し、内部値としてEmptyOutcomeを保持する。

## Rejected

`OperationResult::rejected($reason)` は予期された業務上の拒否を生成する。

RejectionReasonはCategoryと安定したCodeだけを保持する。自由文や任意detailsを持たせず、利用者向けMessageとTransport表現はResponderが生成する。

CategoryはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleを提供する。

## Query

Frameworkは `isCompleted()` と `isRejected()` で状態を判定する。`outcome()` と `rejectionReason()` は対応する状態だけで呼び出し、状態が一致しない場合はLogicExceptionとなる。
