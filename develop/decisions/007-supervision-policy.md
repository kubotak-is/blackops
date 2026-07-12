# D007: Supervision Policy

Status: Decided

## Context

D006により、予期された業務上の拒否はRejected Result、システム障害は例外として表現することを決定した。

FWの実行境界は例外を捕捉して `AttemptFailed` を記録する。その後にRetry、Operation Failed、Dead Letterのどれを選ぶかをSupervision Policyが判断する。

## Question 1: Policyの関連付け

OperationとSupervision Policyをどのように関連付けるか。

### Options

- A: Operation Definitionへ `#[SupervisedBy(...)]` Attributeを付与する
- B: Execution Strategyごとに一つのPolicyをConfigで設定する
- C: Strategyの既定PolicyをConfigで設定し、OperationのAttributeで上書き可能にする

### Recommendation

Cを推奨する。

```php
#[ExecuteWith(Deferred::class)]
#[SupervisedBy(PaymentRetryPolicy::class)]
final class CapturePayment implements Operation
{
}
```

通常はStrategyの既定値を使い、決済など特別な要件を持つOperationだけ明示的に上書きできる。

[ANSWER]

C

[/ANSWER]

## Question 2: 例外の分類

Retry可能かどうかを、どのように判定するか。

### Options

- A: すべての例外を既定回数までRetryする
- B: `RetryableException` などのmarker interfaceを実装した例外だけRetryする
- C: Policyが例外型とAttempt情報を受け取り、Retry、Fail、Dead Letterを返す

### Recommendation

Cを推奨する。

```php
interface SupervisionPolicy
{
    public function decide(
        Throwable $error,
        AttemptContext $attempt,
    ): SupervisionDecision;
}
```

Policyは例外型、試行回数、経過時間などを使って判断できる。単純な既定Policyでは、marker interfaceを判断材料として利用できる。

[ANSWER]

C

[/ANSWER]

## Question 3: Inline StrategyのRetry

HTTPリクエストなどのInline実行で、FWがHandlerを自動Retryするか。

### Options

- A: InlineでもDeferredと同じ回数だけ自動Retryする
- B: Inlineは既定で自動Retryせず、例外をFailure Responseへ変換する
- C: Inlineでは常に一度だけRetryする

### Recommendation

Bを推奨する。

Inlineでの自動Retryはレスポンス時間を伸ばし、利用者から見えない重複副作用を起こしやすい。必要なOperationだけPolicyで明示的に許可する余地は残す。

[ANSWER]

B

[/ANSWER]

## Question 4: Deferred Strategyの既定Retry

Deferred実行の標準Policyをどうするか。

### Options

- A: Retryを既定で無効にする
- B: Retry可能と判断された例外を、上限回数付き指数BackoffとJitterでRetryする
- C: すべての例外を無期限にRetryする

### Recommendation

Bを推奨する。

一時的な依存サービス障害から回復でき、Jitterによって多数のWorkerが同時に再試行することを避けられる。具体的な回数と時間はConfigで変更可能にする。

[ANSWER]

B

[/ANSWER]

## Question 5: Dead Letter

Retry不能または上限到達したDeferred Operationをどう扱うか。

### Options

- A: Execution Transportから削除し、Journalに失敗だけを記録する
- B: Dead Letter Transportへ移し、Journalに `OperationDeadLettered` を記録する
- C: 元のExecution Transportに残し続ける

### Recommendation

Bを推奨する。

自動実行のループから隔離しつつ、調査と手動Replayの材料を残せる。手動Replay時はD004の決定に従い、新しいOperation IDを発行する。

[ANSWER]

B

[/ANSWER]

## Question 6: Retryと冪等性

Retryによる同一Operationの再実行で、重複副作用へどう対処するか。

### Options

- A: Handlerの完全な冪等性をユーザーへ要求し、FWは支援しない
- B: FWがOperation IDを使ったInbox/Deduplication機構を提供し、Handlerにも冪等な設計を推奨する
- C: FWがExactly Once実行を保証する

### Recommendation

Bを推奨する。

分散システムで一般的なExecution Transportを使い、完全なExactly Onceを抽象的に保証するのは現実的ではない。FWは重複検知を支援するが、外部API呼び出しなどの副作用にはHandler側のIdempotency Keyも必要になる。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

1. Execution Strategyごとの既定Supervision PolicyをConfigで設定する。
2. Operation Definitionは `#[SupervisedBy(...)]` Attributeによって既定Policyを上書きできる。
3. Supervision Policyは例外とAttempt Contextを受け取り、Retry、Fail、Dead Letterのいずれかを返す。
4. 単純な既定Policyでは、`RetryableException` などのmarker interfaceを例外分類の判断材料として利用できる。
5. Inline Strategyは既定で自動Retryしない。捕捉した例外をFailure Responseへ変換する。
6. Inline Operationで自動Retryが必要な場合は、Operation固有のPolicyによって明示的に許可する。
7. Deferred Strategyは、Retry可能と判断された例外を、上限回数付き指数BackoffとJitterでRetryする。
8. Retry不能または上限到達したDeferred OperationはDead Letter Transportへ移し、`OperationDeadLettered` Journal Entryを記録する。
9. Dead Letterからの手動Replayは新しいOperation IDを発行し、元Operationとの因果関係を記録する。
10. FWはOperation IDを使ったInbox/Deduplication機構を提供する。
11. FWはExactly Once実行を保証しない。Handlerには冪等な設計を推奨し、外部副作用にはIdempotency Keyの利用を求める。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Strategyごとに一般的な障害処理を共有し、特別なOperationだけPolicyを上書きできる。
- 例外型、試行回数、経過時間などを材料に障害処理を決定できる。
- Inline実行で暗黙のRetryによる遅延や重複副作用が起きにくくなる。
- Deferred実行は一時障害から自動回復でき、JitterによってRetry Stormを抑制できる。
- 自動処理不能なOperationをDead Letterへ隔離し、調査と手動Replayが可能になる。
- Inbox/Deduplication Storeの抽象、保持期間、トランザクション境界を設計する必要がある。
- Backoff、最大試行回数、Attempt Timeoutなどの具体的な既定値をConfig仕様で決める必要がある。
- Handlerが外部サービスへ行った副作用をFWだけで重複排除することはできないため、アプリケーション側の冪等性設計が必要になる。

[/CONSEQUENCES]
