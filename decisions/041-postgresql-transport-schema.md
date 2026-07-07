# D041: PostgreSQL Transport Schema

Status: Decided

## Context

MVPのReference Execution TransportをPostgreSQLへ変更した。Operation State Tableを中心に、複数Workerが安全かつ効率的にClaimできるSchemaを決める。

## Question 1: PayloadとContextのColumn型

### Options

- A: `jsonb` として保存する
- B: Codec済みの不透明な `bytea` として保存し、Encoding Metadataを別Columnに持つ
- C: PHP ObjectのPropertyを個別Columnへ展開する

### Recommendation

Bを推奨する。

TransportはPayload内部を検索しないため、Codecの出力を不透明なMessageとして保存する。将来Envelope Encryptionを適用してもColumn型を変えずに済む。

```text
encoded_payload   bytea
encoded_context   bytea
content_type      text
encoding          text
key_id            text nullable
```

[ANSWER]

B

[/ANSWER]

## Question 2: Stateと時刻の型

### Options

- A: Stateは `text` + CHECK Constraint、時刻は `timestamptz`
- B: StateはPostgreSQL ENUM、時刻はTEXT
- C: すべてJSONB内へ保存する

### Recommendation

Aを推奨する。

PostgreSQL ENUMよりMigrationが扱いやすく、不正StateはCHECKで拒否できる。時刻はDatabase内で `timestamptz` として比較し、PHP・Wire境界では決定済みのUTC RFC 3339形式へ変換する。

[ANSWER]

A

[/ANSWER]

## Question 3: Claim QueryとIndex

### Options

- A: `FOR UPDATE SKIP LOCKED` で一件選択し、同一TransactionでLeaseとFencingを更新する
- B: SELECT後にApplication側で競合を解決する
- C: PostgreSQL Advisory Lockだけを使用する

### Recommendation

Aを推奨する。

概念Query：

```sql
SELECT operation_id
FROM blackops_operations
WHERE state IN ('accepted', 'retry_scheduled')
  AND available_at <= CURRENT_TIMESTAMP
ORDER BY available_at, operation_id
FOR UPDATE SKIP LOCKED
LIMIT 1;
```

Eligible検索用のPartial Indexを設ける。Lease失効したRunning OperationのRecoveryには別のPartial Indexを設ける。

[ANSWER]

A

[/ANSWER]

## Question 4: Migration管理

### Options

- A: FrameworkがVersion付きSQL Migrationと専用CLI Commandを提供する
- B: 利用アプリケーションがSchemaを手作業で管理する
- C: 起動時に毎回 `CREATE TABLE IF NOT EXISTS` を実行する

### Recommendation

Aを推奨する。

```text
blackops transport:migrate
blackops transport:migrate --dry-run
blackops transport:status
```

Production起動時に暗黙のDDLを実行せず、ApplicationのDeployment手順へ組み込める。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Operation PayloadとExecution ContextはCanonical CodecでEncodeし、不透明な `bytea` として保存する。`content_type`、`encoding`、Optionalな `key_id` を別Columnに持つ。

Operation Stateは `text` とCHECK Constraintで表し、時刻は `timestamptz` で保存する。PHP・Wire境界ではUTCのCanonical RFC 3339形式へ変換する。

Claimは `FOR UPDATE SKIP LOCKED` でEligibleな一件を選択し、同じ短いTransaction内でLease、State Version、Fencing Tokenを更新する。Handler実行中はDatabase Transactionを保持しない。

Eligible Operation検索とLease失効Recoveryへ、それぞれPartial Indexを設ける。

FrameworkはVersion付きSQL Migrationを提供し、専用CLI Commandで明示的に適用する。Production起動時に暗黙のDDLを実行しない。

```text
blackops transport:migrate
blackops transport:migrate --dry-run
blackops transport:status
```

[/DECISION]

## Consequences

[CONSEQUENCES]

- Payloadを検索対象から外し、Codecや将来のEnvelope EncryptionをColumn変更なしで導入できる。
- PostgreSQL ENUMへ固定せず、Version付きMigrationでState集合を変更できる。
- Database内では時刻比較を安全に行い、外部境界では既定のCanonical形式を維持できる。
- 複数Workerが同じOperationをClaimせず、異なるOperationを並行取得できる。
- Handler実行中の長時間TransactionとLock保持を避けられる。
- Framework SchemaのVersionと適用状態をDeployment時に検査できる。
- Partial Indexの具体条件とMigration SQLを実装時にTestする必要がある。

[/CONSEQUENCES]
