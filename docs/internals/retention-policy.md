# Retention Policy

Retention Policyは、Frameworkが保持期限を評価するためのPublic Contractである。

## Retention Targets

Retention対象は次の4つに分離する。

```text
transport_payload
journal
outcome
dead_letter
```

Transport Payload、Canonical Journal、Outcome、Dead Letterは用途と保持要件が異なるため、同じ期限へまとめない。

## Retention Period

`RetentionPeriod` は明示的な正の期間だけを受け入れる。暗黙の既定期間は持たない。

現在のContractでは秒数または日数から生成できる。内部では秒数として保持し、基準時刻から期限時刻を計算できる。

## Retention Policy

`RetentionPolicy` は4対象すべての期間をConstructorで受け取る。

未設定のPolicyを表すNull Objectや既定値は提供しない。ProductionでPolicyが未設定の場合の警告、Purge停止、Manifest Compile検証は後続Taskで扱う。

## Deferred Items

次は後続Taskで扱う。

- Retention Hold Store
- Tombstone実行
- Purge Plan / Purge Service
- Retention CLI
- Framework Maintenance Scheduler Worker

## PostgreSQL Tombstone Columns

Operations TableはTerminal OperationのTransport PayloadをTombstone化できるよう、次の列を持つ。

```text
encoded_payload nullable
encoded_context nullable
payload_purged_at nullable
```

未完了OperationはPayloadを必要とするため、Schema ConstraintでTombstone化をTerminal Stateだけに制限する。
