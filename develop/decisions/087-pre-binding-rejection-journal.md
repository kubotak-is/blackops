# D087: Pre-binding Rejection Journal

Status: Decided

## Context

D086は、Route特定後の必須Field欠落や型不一致をOperation ID付き`OperationRejected`としてJournalへ記録すると決めた。一方、現在のLifecycleは必ず`operation.received`から始まり、そのDataは元のOperation Envelopeを再現できる具象`OperationValue`を要求する。

Binding FailureではOperationValueがまだ生成されていない。Raw HTTP Input、偽のOperationValue、`null`をReceived Valueとして保存すると、Sensitive境界またはOperationReceivedの再現可能性Contractを壊す。このため、Binding Failureだけは通常のReceived Lifecycleと同じ形で表現できない。

## Question: Binding前の拒否をどのJournal Shapeで表すか

### Options

- A: `operation.rejected`をSequence 1のTerminal Recordとして直接記録する。Operation IDは発行するが、`operation.received`はBinding成功後の再現可能なEnvelopeだけに使う
- B: `operation.received`へ`value: null`を記録し、その後`operation.rejected`を記録する。既存のEvent Sequenceを維持する代わりに、OperationReceived DataをNullableへ変更する
- C: `operation.binding_rejected`という新Eventを追加する。Binding Failureを専用Eventで表す代わりに、Wire ContractとLifecycle Eventを増やす

### Recommendation

Aを推奨する。

`operation.rejected`だけでもOperation ID、Operation Type、Execution Strategy、Violation、発生時刻を記録でき、「No operation stays in the dark」を満たす。生成できなかったValueを偽造せず、既存`OperationReceivedData`の非Nullable Public Contractと再現可能性を維持できる。

この場合、`operation.received`は「Routeを特定した」ではなく「OperationValueのBindingが成功し、再現可能なEnvelopeを構成した」境界になる。Value Validation FailureはBinding済みなので、従来どおり`received -> rejected`とする。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Binding FailureはOperation IDを発行し、Sequence 1の`operation.rejected`をTerminal Recordとして直接記録する。
2. Binding Failureでは`operation.received`を記録しない。
3. `operation.received`はOperationValueのBindingが成功し、再現可能なOperation Envelopeを構成できた境界を表す。
4. Value Validation FailureはBinding済みのため、`operation.received`、`operation.rejected`の順で記録する。
5. Binding FailureのRejected RecordはOperation Type、Execution Strategy、Category `validation`、Code `validation.failed`、Raw Valueを含まないField Violationを持つ。
6. `OperationReceivedData`へNullable Value、偽OperationValue、Raw HTTP Inputを導入しない。
7. 新しいJournal Eventは追加せず、既存`operation.rejected` Wire Contractを使用する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Lifecycle State MachineはInitial Stateから`operation.rejected`へのTerminal Transitionを許可する。
- Binding Failureの最初で最後のJournal Sequenceは1になる。
- Operation IDがあるRejected Recordにより「No operation stays in the dark」を維持する。
- OperationReceived Dataの非Nullable Public ContractとEnvelope再現可能性を維持する。
- ObserverとPostgreSQL Canonical Journalは、先行Received RecordがないRejected Recordを正当なLifecycleとして扱う。
- Value ValidationとHandler内のManual Rejectionは従来どおりReceived後のRejectedとなる。

[/CONSEQUENCES]
