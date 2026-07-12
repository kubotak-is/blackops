# D052: Handler Result Public API

Status: Decided

## Context

D035によりOperationHandler、OperationResult、EmptyOutcomeの基本形は決まったが、ResultのQuery MethodとRejectionReasonの構造は未確定である。

## Decision

[DECISION]

`OperationHandler<TValue, TOutcome>` は `handle(OperationEnvelope<TValue>): OperationResult<TOutcome>` だけを持つPHP Public API Interfaceとする。

`OperationResult<TOutcome>` はprivate Constructorと `completed($outcome)`、`completed()`、`rejected($reason)` のStatic Factoryを持つ。

Query Methodは `isCompleted()`、`isRejected()`、`outcome()`、`rejectionReason()` とする。状態に合わないAccessorは `\LogicException` を投げる。Completedは常にOutcomeを持ち、引数なしの `completed()` は `EmptyOutcome` を内部値として使用する。

RejectionReasonはValidation、Unauthorized、Forbidden、Not Found、Conflict、Business RuleのCategoryと安定したCodeを持つ。

Codeは小文字英数字を基本とし、`.`、`_`、`-` による区切りを許可する。不正Codeは入力値をMessageへ含めず `\InvalidArgumentException` で拒否する。

自由文Messageと任意detailsはRejectionReasonへ含めない。利用者向け表現はResponderがCategoryとCodeから生成する。

`EmptyOutcome` は状態を持たない `#[PublicApi] final readonly class implements Outcome` とする。

[/DECISION]
