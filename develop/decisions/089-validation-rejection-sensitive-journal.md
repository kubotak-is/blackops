# D089: Validation Rejection Sensitive Journal

Status: Decided

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

A

[/ANSWER]

## Decision

[DECISION]

1. Aを採用し、Value Validation FailureでもCanonical `OperationReceivedData`は実OperationValueを保持する。
2. Canonical Journalは再現可能性を担うRestricted Data Storeとして扱い、`#[Sensitive]` Propertyを含む場合がある。
3. Observed Journal Projectionは従来どおり`#[Sensitive]` Propertyをマスクする。
4. HTTP Response、Exception、`OperationRejectedData`、ViolationにはRaw／Sensitive Value、Symfony Message、Constraint設定を含めない。
5. P10-005E2のSensitive非露出AcceptanceはObserved JournalとValidation Error Surfaceを対象とし、既存Canonical `OperationReceivedData` Contractを変更しない。
6. Binding FailureはValue未生成のため、D087どおりCanonical JournalにもReceived Recordを作らない。
7. Canonical JournalのField-level暗号化はKey管理、Rotation、Migrationを含む独立したSecurity Taskとして扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 成功、Value Validation Failure、Handler Rejectionで`operation.received`の再現可能性が一貫する。
- Validation FailureだけSafe Markerへ変える分岐を追加しない。
- Canonical JournalへのAccess Control、保存時暗号化、RetentionはApplication／運用側の責任境界として明示する必要がある。
- QuickstartのJSONL Observerと公開Error例ではSensitive値が必ずマスクまたは省略される。
- TestはCanonical Recordの再現可能Valueと、Observed／Rejected／Responseの非露出を別々に検証する。

[/CONSEQUENCES]
