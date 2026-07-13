# Handler and Result

標準Typed Self-handled Operationは `handle()` で具象Outcomeを直接返し、値のない成功は `void` で表す。Frameworkの共通Invokerが内部OperationResultへ正規化する。

`OperationRejectedException`だけを予期された業務拒否としてRejected Resultへ変換する。Retryable Exceptionとその他のThrowableは変換せず、既存Supervisionへ伝播する。

## Completed

標準形では具象Outcomeを直接returnする。`void` ReturnはFrameworkがEmptyOutcomeへ変換する。`OperationResult::completed()`はLegacy Self-handledとSeparate Handlerの互換APIとして維持する。

## Rejected

標準形では `OperationRejectedException::validation()` 等をthrowする。`OperationResult::rejected($reason)`はLegacy互換APIである。

RejectionReasonはCategoryと安定したCodeだけを保持する。自由文や任意detailsを持たせず、利用者向けMessageとTransport表現はResponderが生成する。

CategoryはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleを提供する。

## Query

Frameworkは `isCompleted()` と `isRejected()` で状態を判定する。`outcome()` と `rejectionReason()` は対応する状態だけで呼び出し、状態が一致しない場合はLogicExceptionとなる。
