# D043: PostgreSQL Table Layout

Status: Decided

## Context

PostgreSQLへOperation State、Canonical Journal、Outcomeを同一Transactionで保存することを決定した。次に、各責務のTableと主要Constraintを決める。

## Question 1: Database Schema

### Options

- A: 専用PostgreSQL Schema `blackops` を作り、短いTable名を使う
- B: `public` Schemaへ `blackops_` Prefix付きTableを作る
- C: 利用者がTable名をすべて自由設定する

### Recommendation

Aを推奨する。

```text
blackops.operations
blackops.journal
blackops.outcomes
blackops.dead_letters
blackops.schema_migrations
```

Application Tableと名前空間・権限を分離できる。Schema名はConfigで変更可能にするが、Table単位の任意改名はMVPでは提供しない。

[ANSWER]

これってDB名ってこと？ドット以降がテーブル名？

[/ANSWER]

### Follow-up 1-1: Database、Schema、Table

`blackops` はDatabase名ではなく **PostgreSQL Schema名** で、ドット以降がTable名である。

```text
PostgreSQL Server
└─ Database: app
   ├─ Schema: public
   │  ├─ users
   │  └─ orders
   └─ Schema: blackops
      ├─ operations
      ├─ journal
      ├─ outcomes
      ├─ dead_letters
      └─ schema_migrations
```

SQLでは `schema.table` の形式で参照する。

```sql
SELECT *
FROM blackops.operations;
```

Database名はApplication環境ごとに決められる。

```text
開発: blackops_dev
Test: blackops_test
本番: 利用ApplicationのDatabase
```

そのDatabase内へBlackOps専用Schemaを作る。Applicationの `public.users` 等とBlackOps内部Tableを名前空間・権限の面で分離できる。

### Question

この構成を採用するか。

### Options

- A: Application Database内に専用Schema `blackops` を作る
- B: `public` Schemaへ `blackops_operations` 等のPrefix付きTableを作る

### Recommendation

Aを推奨する。Schema名はConfigで変更可能とし、既定値を `blackops` とする。

[ANSWER]

A

[/ANSWER]

### Follow-up 1-2: MySQL Adapterを追加する場合

MySQLでは `SCHEMA` は実質的にDatabaseの別名として扱われるため、PostgreSQLのようにApplication Database内を `public` と `blackops` のSchemaへ分ける構成は、そのまま対応しない。

物理名はAdapterごとに次のように対応できる。

| 論理Table | PostgreSQL | MySQL |
| --- | --- | --- |
| Operations | `blackops.operations` | `blackops_operations` |
| Journal | `blackops.journal` | `blackops_journal` |
| Outcomes | `blackops.outcomes` | `blackops_outcomes` |
| Dead Letters | `blackops.dead_letters` | `blackops_dead_letters` |
| Migrations | `blackops.schema_migrations` | `blackops_schema_migrations` |

Column型とSQLもAdapterが変換する。

| 意味 | PostgreSQL | MySQL候補 |
| --- | --- | --- |
| UUID | `uuid` | `binary(16)` |
| Encoded Payload | `bytea` | `longblob` |
| UTC時刻 | `timestamptz` | UTCへ正規化した `datetime(6)` |
| Claim | `FOR UPDATE SKIP LOCKED` | 対応Versionの `FOR UPDATE SKIP LOCKED` |
| State制約 | `text` + CHECK | `varchar` + CHECKまたはApplication検証 |

Execution Transport、Canonical Journal Store、Outcome Store等のPHP Portは変えない。Migration SQL、Claim Query、Column Codec、Capability宣言だけをDatabase Adapterごとに実装する。

MySQLを別Database `blackops` に分離し、`blackops.operations` の形を似せることもできる。しかしDatabase作成権限が必要になり、Application DatabaseとのTransactionやDeployment設定が複雑になるため、既定案にはしない。

### Question

将来のMySQL対応を考慮し、物理的な名前空間をどう扱うか。

### Options

- A: 論理Table名を共通化し、PostgreSQLはSchema、MySQLはTable PrefixでAdapterが変換する
- B: PostgreSQLでも `public.blackops_operations` のようなPrefix方式に統一する
- C: PostgreSQLとMySQLの両方でBlackOps専用Databaseを要求する

### Recommendation

Aを推奨する。

PostgreSQLのSchema分離という利点を保ちつつ、MySQL対応をPHP Public APIへ漏らさず追加できる。Configは物理名を一つずつ指定するのではなく、PostgreSQLのSchema名またはMySQLのTable Prefixだけを指定可能にする。

[ANSWER]

A

[/ANSWER]

## Question 2: Canonical Journal Table

次のHybrid構造を採用するか。

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

### Options

- A: 検索用Columnと完全なEncoded Recordを併存させる
- B: 全RecordをJSONB一つだけで保存する
- C: Journalの全Nested Fieldを個別Columnへ展開する

### Recommendation

Aを推奨する。Operation ID、Sequence、Event、時刻で効率的に検索でき、Canonical Schema全体はCodec済みRecordとして保持できる。

[ANSWER]

A

[/ANSWER]

## Question 3: Outcome Table

### Options

- A: `outcomes` を別Tableにし、Operation IDで一対一に保存する
- B: OutcomeをOperations TableのColumnへ保存する
- C: OutcomeはCompleted Journalから毎回復元する

### Recommendation

Aを推奨する。

```text
outcomes
  operation_id       uuid primary key
  outcome_type       text not null
  schema_version     integer not null
  encoded_payload    bytea not null
  completed_at       timestamptz not null
```

Deferred Outcome取得を高速化し、Journal RetentionとOutcome Retentionを分離できる。Canonical JournalにもOutcomeは残す。

[ANSWER]

A

[/ANSWER]

## Question 4: Dead Letter Table

### Options

- A: Terminal StateはOperationsにも残し、調査用IndexとしてDead Letters Tableへ一対一Recordを追加する
- B: Dead Letter時にOperationsから行を移動する
- C: Dead Letter Tableを作らずJournalだけで検索する

### Recommendation

Aを推奨する。

Operation StateとPayloadを移動せず、Dead Letter一覧、理由、最終Attempt、移動時刻を効率的に検索できる。手動Replayは元Operationを変更せず、新しいOperation IDを発行する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

PostgreSQLではApplication Database内に専用Schemaを作成し、既定名を `blackops` とする。Schema名はConfigで変更可能とするが、MVPではTable単位の任意改名を提供しない。

Database非依存の論理Table名を定義し、Adapterが物理名へ変換する。

```text
Operations
Journal
Outcomes
Dead Letters
Schema Migrations
```

PostgreSQLでは `blackops.operations`、MySQL Adapterを将来追加する場合は `blackops_operations` のようなPrefix方式を使う。PHP PortへDatabase固有の物理名を露出させない。

Canonical Journalは検索用Columnと完全なEncoded Recordを併存させるHybrid構造とし、Record IDをPrimary Key、Operation IDとSequenceの組をUniqueとする。

OutcomeはOperation IDで一対一となる別Tableへ保存する。Canonical JournalにもOutcomeを保持しつつ、Deferred Outcome取得と独立Retentionを可能にする。

Dead Letter時もOperationsの行は移動しない。OperationsをTerminal Stateとして残し、調査用IndexとなるDead Letters Tableへ一対一Recordを追加する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Application TableとBlackOps内部TableをPostgreSQL Schemaと権限で分離できる。
- 将来のMySQL対応ではTable Prefixへ変換でき、PHP Public APIを変更せずに済む。
- JournalをOperation ID、Sequence、Event、時刻で検索しつつ、完全なCanonical Recordを復元できる。
- Outcome取得のたびにJournal全件を再生する必要がない。
- Journal、Outcome、Transport PayloadのRetentionを分離できる。
- Dead Letter一覧を効率的に検索でき、元Operationを変更せずReplayできる。
- 外部キー、削除順序、Payload消去、Legal HoldをRetention設計で決める必要がある。

[/CONSEQUENCES]
