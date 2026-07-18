# D098: Deferred Acceptance Failure Lifecycle

Status: Decided

## Context

Phase 14の仕様化で、Deferred Operation受付中のAuthorization Policyが予期せずThrowableを投げた場合のLifecycle Gapを確認した。

この時点でOperation IDは発行済みである。しかし現在は受付Transactionがrollbackされ、`operation.received`もTerminal Journalも残らない。HTTP 500へOperation IDを返しても、`operation:inspect`ではUnavailableになる。

Expected Authorization Rejectionは従来どおり`operation.rejected`である。このDecisionが対象とするのはPolicy自体の予期しないThrowableだけである。InlineのHandler／Policy ThrowableはAttempt開始後なので、従来の`attempt.failed -> operation.failed`を使用できる。Deferred受付PolicyはWorker Attempt開始前のため、同じEvent列を使うと実行Attemptが開始したように見える。

BlackOpsは「No operation stays in the dark」を設計原則とし、Operation ID発行後のOperationをJournalから追跡できることを目指す。一方、現行State Machineは`received -> operation.failed`を許可していない。

## Question 1: Failure Lifecycle Before an Attempt

Operation ID発行後、Attempt開始前の予期しないFramework／Application Throwableをどう記録するか。

### Options

- A: `received -> operation.failed`を正式なTerminal遷移として追加する。受付Transactionをrollbackした後、別TransactionでReceivedとOperation Failedを記録し、HTTP 500とFramework Logに同じOperation IDを返す。Attemptは作らない
- B: State Machineを変更しない。HTTP 500とFramework LogにOperation IDは返すが、Journalは残さず、`operation:inspect`はUnavailableとなることを例外として許容する

### Recommendation

Aを推奨する。

Operation成立後のFailureはAttemptの有無に関わらずJournalへ残し、HTTP、Log、Diagnosticsを同じOperation IDで接続できる。Attemptを架空に作らないため、Worker実行が始まったような誤解も避けられる。

BはState Machineの変更が小さい代わりに、利用者がHTTP 500で得たOperation IDを診断できず、Phase 14の目的と設計原則に反する。

[ANSWER]

A

[/ANSWER]

## Impact of Option A

- Lifecycle State MachineにAttemptなしの`received -> operation.failed`を追加する。
- Failure Journalはrollbackされた受付Transactionと分離して記録する。
- `operation.failed`はSafe Failure Type／ClassificationをObserved Projectionへ出し、例外MessageをCLI、Viewer、HTTP、Logへ出さない。
- Diagnostics AggregateはAttemptsが空でもTerminal Failed Operationを表現できる。
- InlineとDeferred WorkerのAttempt開始後Failureは、従来どおり`attempt.failed -> operation.failed`を使う。

## Decision

[DECISION]

1. Operation ID発行後、Attempt開始前の予期しないFramework／Application Throwableに対し、`received -> operation.failed`を正式なTerminal遷移として追加する。
2. Deferred受付Transactionのrollback後、別TransactionでReceivedとOperation Failedを記録する。
3. HTTP 500、Framework Log、Canonical Journalは同じOperation IDで相関させる。
4. Attempt開始前FailureにAttemptを架空に作成しない。Diagnostics AggregateはAttemptsが空のTerminal Failed Operationを表現する。
5. Expected Authorization Rejectionは`operation.rejected`、Attempt開始後のFailureは`attempt.failed -> operation.failed`を従来どおり使用する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Lifecycle State MachineはAttemptが存在しないTerminal Failureを扱う。
- Operation ID発行後のDeferred受付FailureもJournalから追跡でき、`operation:inspect`がUnavailableになる例外を作らない。
- Failure記録用の別Transactionが失敗した場合は原Throwableを維持し、安全なFramework Error Logで二次障害を報告する。
- Safe SurfaceはFailure Type／Classificationだけを使用し、例外Messageを露出しない。

[/CONSEQUENCES]

## References

- [D097 Phase 14 Operation Diagnostics](097-phase-14-operation-diagnostics.md)
- [Lifecycle and Journal](../spec/02-lifecycle-and-journal.md)
- [Lifecycle State Machine](../spec/30-lifecycle-state-machine.md)
- [Execution](../spec/03-execution.md)
- [HTTP Adapter](../spec/05-http.md)
- [Durable Journal and Transactions](../spec/11-durable-journal-and-transactions.md)
