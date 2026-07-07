# PostgreSQL Transport Schema

## Encoded Message

Operation PayloadとExecution ContextはCanonical CodecでEncodeし、不透明な `bytea` として保存する。

```text
encoded_payload   bytea
encoded_context   bytea
content_type      text
encoding          text
key_id            text nullable
```

TransportはPayload内部を検索しない。将来Envelope Encryptionを適用する場合もColumn型を変更しない。

## Stateと時刻

Operation Stateは `text` とCHECK Constraintで表す。PostgreSQL ENUMは使用しない。

Database内の時刻は `timestamptz` とする。PHPおよびWire境界ではUTCのCanonical RFC 3339マイクロ秒形式へ変換する。

## Claim

Eligibleな一件を `FOR UPDATE SKIP LOCKED` で選択する。

```sql
SELECT operation_id
FROM blackops_operations
WHERE state IN ('accepted', 'retry_scheduled')
  AND available_at <= CURRENT_TIMESTAMP
ORDER BY available_at, operation_id
FOR UPDATE SKIP LOCKED
LIMIT 1;
```

同じ短いTransaction内でLease、State Version、Fencing Tokenを更新してCommitする。Handler実行中はTransactionを保持しない。

次の用途へ個別のPartial Indexを設ける。

- Accepted／Retry ScheduledのEligible検索
- Leaseが失効したRunning OperationのRecovery

## Migration

FrameworkはVersion付きSQL Migrationを提供する。

```text
blackops transport:migrate
blackops transport:migrate --dry-run
blackops transport:status
```

Production起動時に暗黙のDDLを実行しない。Migration適用状態はDeployment時に確認する。
