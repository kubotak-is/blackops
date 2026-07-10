# PostgreSQL Table Layout

## Schema

Application Database内にBlackOps専用PostgreSQL Schemaを作る。既定名は `blackops` とし、Configで変更可能とする。

```text
blackops.operations
blackops.journal
blackops.outcomes
blackops.dead_letters
blackops.schema_migrations
```

MVPではTable単位の任意改名を提供しない。

## Database Adapter間の論理名

Database非依存の論理Table名を共通化し、Adapterが物理名へ変換する。

| 論理Table | PostgreSQL | MySQL候補 |
| --- | --- | --- |
| Operations | `blackops.operations` | `blackops_operations` |
| Journal | `blackops.journal` | `blackops_journal` |
| Outcomes | `blackops.outcomes` | `blackops_outcomes` |
| Dead Letters | `blackops.dead_letters` | `blackops_dead_letters` |
| Migrations | `blackops.schema_migrations` | `blackops_schema_migrations` |

PHP PortへDatabase固有の物理名を露出させない。

## Canonical Journal

検索用Columnと完全なEncoded Recordを併存させる。

```text
journal
  record_id          uuid primary key
  operation_id       uuid not null
  sequence           bigint not null
  event              text not null
  attempt_id         uuid nullable
  schema_version     integer not null
  occurred_at        timestamptz not null
  encoded_record     bytea not null

  unique(operation_id, sequence)
```

## Outcomes

OutcomeはOperation IDで一対一となる別Tableへ保存する。

```text
outcomes
  operation_id       uuid primary key
  outcome_type       text not null
  schema_version     integer not null
  encoded_payload    bytea not null
  completed_at       timestamptz not null
```

Canonical JournalにもOutcomeを保持する。Outcomes TableはDeferred Outcome取得と独立Retentionのための取得用Storeとする。

## Dead Letters

Dead Letter時もOperationsの行を移動しない。

OperationsをDead Lettered Terminal Stateとして残し、調査用IndexとなるDead Letters Tableへ一対一Recordを追加する。手動Replayは元Operationを変更せず、新しいOperation IDを発行する。

```text
dead_letters
  operation_id            uuid primary key
  final_attempt_id        uuid nullable
  final_attempt_number    integer nullable
  reason_type             text not null
  reason_message          text not null
  moved_at                timestamptz not null
  created_at              timestamptz not null default current_timestamp
```

`operation.dead_lettered` Journal Dataは同じ安全な理由情報、最終Attempt ID、最終Attempt番号、移動時刻を保持する。
