# Handler and Result

標準Typed Self-handled Operationは `handle()` で具象Outcomeを直接返し、値のない成功は `void` で表す。Frameworkの共通Invokerが内部OperationResultへ正規化する。

`OperationRejectedException`だけを予期された業務拒否としてRejected Resultへ変換する。Keyless実行のRetryable Exceptionとその他のThrowableは既存Supervisionへ伝播する。Keyed PHP direct実行ではFailure BoundaryがThrowableを安全なInternal Failure Recordへ閉じて`OperationExecutionFailed`を返し、HTTP Error Boundaryだけが安全な500 Response SnapshotをAttachして返す。同じHTTP KeyのReplayはHandlerを再実行せず、そのSnapshotまたはInternal Failureを再投影する。

## Completed

標準形では具象Outcomeを直接returnする。`void` ReturnはFrameworkがEmptyOutcomeへ変換する。`OperationResult::completed()`はLegacy Self-handledとSeparate Handlerの互換APIとして維持する。Replayは元の`OperationId`を保持し、`asReplayed()`でReplay markerを付ける。

`EphemeralOutcome`もInvokerでは通常のCompleted Resultへ正規化する。Runtime ValidatorはDeclared Classとの完全一致、Structured Shape、JSON Encoding可能性をTransaction Commit前に検査する。失敗はRaw Property値やThrowable Detailを含まない`OperationExecutionFailed`へ閉じ、Transactional Operationの業務更新と成功TerminalをRollbackする。

## Rejected

標準形では `OperationRejectedException::validation()` 等をthrowする。`OperationResult::rejected($reason)`はLegacy互換APIである。Idempotency conflict、in-progress、expiredは安全なRejected Resultとして表現し、元のOperation IDやResultを新しいCallerへ公開しない。

RejectionReasonはCategoryと安定したCodeだけを保持する。自由文や任意detailsを持たせず、利用者向けMessageとTransport表現はResponderが生成する。

CategoryはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleを提供する。

## Query

Frameworkは `isCompleted()` と `isRejected()` で状態を判定する。`outcome()` と `rejectionReason()` は対応する状態だけで呼び出し、状態が一致しない場合はLogicExceptionとなる。

`operationId()` はReplay時の元Operation IDを返し、初回のKeyless結果ではnullの場合がある。`isReplayed()` はDuplicate Replayだけでtrueになり、HTTP HeaderはResultへ混在させずResponderが投影する。
