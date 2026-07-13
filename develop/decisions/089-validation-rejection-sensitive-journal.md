# D089: Validation Rejection Sensitive Journal

Status: Proposed

## Context

D087により、Binding FailureはValue未生成のため`operation.received`を作らず、Sequence 1の`operation.rejected`だけを記録する。一方、Binding後のValue Validation Failureは再現可能なEnvelopeが存在するため、通常どおり`operation.received`から`operation.rejected`へ遷移する。

既存ContractではCanonical `OperationReceivedData`が実OperationValueを保持し、Observerへ出すProjectionだけが`#[Sensitive]`をマスクする。P10-005E2の「Raw／Sensitive ValueをResponse、Exception、Journalへ含めない」という制約をCanonical Received Recordにも適用すると、Value Validation FailureだけReceivedの再現可能性を失う。

## Question: Value Validation FailureのCanonical ReceivedへSensitive Valueを保持するか

### Options

- A: 既存Contractを維持する。Canonical `OperationReceivedData`は実Valueを保持し、Observed Journal、HTTP Response、Exception、`OperationRejectedData`にはRaw／Sensitive Valueを含めない
- B: Value Validation FailureのReceivedだけSafe Markerへ置き換え、Canonical Journalにも実Valueを保存しない
- C: Canonical JournalのSensitive Field暗号化を本Taskへ追加し、暗号化済み実Valueを保持する

### Recommendation

Aを推奨する。

Canonical Journalは再現可能な正本、Observed Journalは安全な観測Projectionという既存責務を維持できる。Validation FailureだけReceived Shapeを変えず、今回追加するViolation／Rejected DetailからRaw Valueを確実に排除する。

Bは`operation.received`の再現可能性を壊し、成功OperationとValidation Failureで同じEventの意味が変わる。CはKey管理、Rotation、既存Record Migrationを伴う別のSecurity Taskであり、HTTP Validation Lifecycleの範囲を大きく超える。

[ANSWER]


[/ANSWER]

## Decision

回答後に確定する。

## Consequences

回答後に確定する。
