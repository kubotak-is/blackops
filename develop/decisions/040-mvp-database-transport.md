# D040: MVP Database Transport

Status: Decided

## Context

MVPのDatabase Execution Transportを選定する。Deferred Operation State、Payload、Retry予定、Lease、Attempt番号、Sequence、Fencing TokenをProcess間で安全に共有する必要がある。

当初はPDO SQLiteを予定していたが、複数Worker、行Lock、Fencingをより現実的に検証するためPostgreSQL案を比較した。

## Question 1: Operation Table

MVPでは一つのOperation State Tableを中心にするか。

```text
blackops_operations
  operation_id          primary key
  operation_type
  schema_version
  encoded_payload
  encoded_context
  state
  state_version
  attempt_number
  next_sequence
  available_at
  lease_owner           nullable
  lease_expires_at      nullable
  fencing_token
  created_at
  updated_at
```

### Options

- A: この単一State Tableを基本とする
- B: Payload、Lease、Stateを最初から別Tableへ正規化する
- C: SQLiteのKey-Value一行へ全状態をJSON保存する

### Recommendation

Aを推奨する。

Claim対象の検索と原子的なState更新が単純になる。Canonical Journal、Outcome、Dead Letterは責務が異なるため、必要に応じて別Tableとする。

[ANSWER]

A、MVPはPostgreSQLでもいいかもなー、DockerComposeで。

[/ANSWER]

## Follow-up 1-1: SQLiteかPostgreSQLか

当初は「外部Serviceなしで動くMVP」を優先してSQLiteを選定した。一方、BlackOpsが強みとするDeferred実行、Lease、複数Worker、Fencingを現実的なDatabaseで検証するならPostgreSQLが適している。

### SQLite

- PHPとFileだけで起動できる
- 初期導入とCIが軽い
- 単一WorkerのVertical Sliceを最短で作れる
- Write LockがDatabase全体に及びやすく、多数Workerの並行性検証には向かない
- Claimに短い `BEGIN IMMEDIATE` と条件付きUPDATEが必要

### PostgreSQL + Docker Compose

- `FOR UPDATE SKIP LOCKED` による並行Claimを実装できる
- 行Lock、Transaction、ConstraintをProductionに近い形で検証できる
- 複数Worker、Lease、FencingというBlackOpsの中核を実証しやすい
- Docker環境とService起動が必要
- 初回体験はSQLiteより少し重い

### Options

- A: SQLiteをMVPのReference Transportとして維持する
- B: PostgreSQLをMVPのReference Transportへ変更し、Docker Composeを公式開発環境にする
- C: SQLiteとPostgreSQLを両方MVPで実装する

### Recommendation

Bを推奨する。

BlackOpsのMVPは単なるHTTP Frameworkではなく、Deferred OperationがCrashや競合に耐えることを証明する必要がある。Docker Composeを許容できるなら、PostgreSQLの方が設計の本丸を正しく試せる。

SQLite AdapterはMVP後のZero-setup Adapter候補として残す。Unit TestにはDatabase非依存のInMemory Transportを用意する。

[ANSWER]

B

[/ANSWER]

以下のQuestion 2〜4はSQLiteを維持する場合の質問である。PostgreSQLへ変更する場合は、回答不要としPostgreSQL向けの質問へ置き換える。

## Question 2: Claim Transaction

SQLiteには一般的な `FOR UPDATE SKIP LOCKED` がない。Claimをどう実装するか。

### Options

- A: `BEGIN IMMEDIATE` の短いTransactionで候補選択と条件付きUPDATEを行う
- B: SELECT後、Transaction外でUPDATEする
- C: File LockだけでWorkerを一台に制限する

### Recommendation

Aを推奨する。

Eligibleな一件を選び、State Version、Lease、Fencing Tokenを条件付き更新して即Commitする。Handler実行中はTransactionを保持しない。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Question 3: SQLite内の時刻

### Options

- A: 固定長のUTC RFC 3339マイクロ秒文字列をTEXTで保存する
- B: Unix秒をINTEGERで保存する
- C: Adapterごとに任意形式を使う

### Recommendation

Aを推奨する。

既定のCanonical Time Formatと一致し、固定長UTC表現ならLexicographic比較で `available_at` とLease期限を検索できる。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Question 4: Payload保存

### Options

- A: Canonical JSONをTEXTとして保存し、Capabilityが必要ならEnvelope Encryptionを適用する
- B: PHP `serialize()` のBLOBを保存する
- C: OperationValueの各PropertyをColumnへ展開する

### Recommendation

Aを推奨する。

Schema VersionとUpcasterを利用でき、PHP Class変更から保存形式を分離できる。MVPはPayload暗号化を実装範囲外としているため、暗号化必須のOperationをSQLite Transportへ割り当てた構成は起動時に拒否する。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Decision

[DECISION]

MVPのReference Database Execution TransportをSQLiteからPostgreSQLへ変更する。公式開発環境はDocker ComposeでPostgreSQLを起動する。

Unit TestにはDatabase非依存のInMemory Transportを用意する。SQLite AdapterはMVPへ含めず、MVP後のZero-setup Adapter候補として残す。

PostgreSQL Transportは一つのOperation State Tableを中心とし、Claim対象検索と原子的なState更新を行う。Canonical Journal、Outcome、Dead Letterは責務が異なるため、必要に応じて別Tableへ分離する。

Question 2〜4のSQLite固有設計は、この決定では採用しない。PostgreSQL固有のClaim、Column型、Index、Payload形式は後続Decisionで定める。

[/DECISION]

## Consequences

[CONSEQUENCES]

- `FOR UPDATE SKIP LOCKED` と行Lockを利用した複数Worker Claimを検証できる。
- Lease、Fencing、Transaction、ConstraintをProductionに近いDatabaseで実証できる。
- 開発とIntegration TestにはDocker環境が必要になる。
- Zero-setupという初期体験は後退するため、InMemory TransportとDocker Composeの簡潔な起動手順を提供する。
- D018のPDO SQLite採用を部分的に置き換える。他の技術選定は維持する。
- PostgreSQLのSchema、Index、Migration、Transaction境界を追加設計する必要がある。

[/CONSEQUENCES]
