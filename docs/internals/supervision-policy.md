# Supervision Policy

Supervision Policyは、Handler例外を記録した後に、Frameworkが次に行う処置を決める境界である。

Policyは例外とAttempt Contextを受け取り、次のいずれかを返す。

- Retry
- Fail
- Dead Letter

Deferred実行の既定Policyは、Retry可能な例外だけを指数BackoffとJitterで再試行する。既定値は最大3 Attempt、初期Delay 1秒、倍率2.0、最大Delay 60秒、Jitter ±20% とする。

Retry予定は `attempt.retry_scheduled` としてJournalへ記録される。Dataには失敗したAttempt ID、次Attempt番号、予定時刻、Delay Millisecondsを保存する。

Retry不能な例外、または最大Attempt回数に到達した例外はOperation全体の失敗として扱う。Dead Letter Transportが実装されるまでは `operation.failed` へ遷移する。

Jitterは多数のOperationが同じ時刻に再試行されることを避けるため、基準Delayへランダムな揺らぎを加える仕組みである。たとえば1秒のDelayに±20%のJitterを適用すると、実際のDelayはおおむね0.8秒から1.2秒の範囲に分散する。
