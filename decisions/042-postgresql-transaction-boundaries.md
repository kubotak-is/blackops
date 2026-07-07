# D042: PostgreSQL Transaction Boundaries

Status: Decided

## Context

PostgreSQL TransportのState TableとClaim方式が確定した。次に、Operation State、Sequence、Canonical Journal、OutcomeをどのTransactionで更新するかを決める。

JSON Lines等のObserver出力はDatabase Transactionへ参加できない。Canonicalな復旧情報と観測用Projectionを区別して保証する必要がある。

## Question 1: MVPのCanonical Journal Store

### Options

- A: PostgreSQL AdapterにCanonical Journal Tableも実装する
- B: MVPではJSON Lines Observerだけを使い、Canonical Journalを永続化しない
- C: Canonical Journalは別Databaseへ保存する

### Recommendation

Aを推奨する。

BlackOpsの中核である復元可能なJournalをPostgreSQLへDurableに保存できる。Transport PortとJournal Portは分離したまま、同じAdapter PackageとConnectionを使って原子的な更新を提供する。

[ANSWER]

A

[/ANSWER]

## Question 2: Deferred受付Transaction

### Options

- A: Operation StateのInsert、Received／Accepted Journal、初期Sequenceを同一TransactionでCommitする
- B: Operation StateだけCommitし、Journalは後から別処理で保存する
- C: Journalを先にCommitし、その後TransportへInsertする

### Recommendation

Aを推奨する。

受付成功時には、実行可能なOperation Stateと復元可能なJournalが必ず揃う。Transaction Commit後に `DeferredAcknowledgement` を返す。

[ANSWER]

A

[/ANSWER]

## Question 3: WorkerのTransaction境界

Handler実行中はTransactionを保持せず、各Lifecycle境界を短いTransactionにする。

```text
Tx 1: Claim + Fencing更新
Tx 2: Attempt Started + State + Sequence + Canonical Journal
      Commit
Handler実行
Tx 3: Fencing検証 + Result State + Sequence + Canonical Journal + Outcome
      Commit
```

### Options

- A: この短いTransaction境界を採用する
- B: ClaimからHandler完了まで一つのTransactionを保持する
- C: 各UPDATEをAuto-commitし、複数Tableの原子性を求めない

### Recommendation

Aを推奨する。

Handler実行中のLock保持を避けつつ、各Lifecycle遷移内のState、Sequence、Journal、Outcomeを一致させられる。

[ANSWER]

A

[/ANSWER]

## Question 4: Observer配送

### Options

- A: Commit後に安全なProjectionをObserverへBestEffort配送し、失敗時はCanonical Journalから再送可能にする
- B: JSON Lines書き込みをDatabase TransactionのCommit条件にする
- C: Observer配送を行わない

### Recommendation

Aを推奨する。

MVPではTransactional Outbox Relayを実装範囲外として維持する。CommitとObserver配送の間でCrashした場合はProjectionが欠落し得るが、Canonical Journalは残る。手動または後続のReplay Toolで再送可能にする。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

PostgreSQL AdapterはExecution Transportに加え、Canonical Journal Storeを実装する。Portは分離したまま、同じConnectionとTransactionへ参加できるようにする。

Deferred受付では、Operation StateのInsert、Received／Accepted Canonical Journal、初期Sequenceを同一TransactionでCommitする。Commit成功後に `DeferredAcknowledgement` を返す。

WorkerはHandler実行中にTransactionを保持せず、Lifecycle境界ごとに短いTransactionを使用する。

```text
Tx 1: Claim + Fencing更新
Tx 2: Attempt Started + State + Sequence + Canonical Journal
      Commit
Handler実行
Tx 3: Fencing検証 + Result State + Sequence + Canonical Journal + Outcome
      Commit
```

ObserverにはCommit後、安全なProjectionをBestEffortで配送する。配送失敗またはCommit後のCrashでProjectionが欠落してもCanonical Journalを正本として保持し、手動または後続のReplay Toolで再送可能にする。MVPではTransactional Outbox Relayを実装しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Deferred受付成功時に実行可能Stateと復元可能Journalが必ず揃う。
- State、Sequence、Journal、OutcomeのLifecycle境界内の不一致を防げる。
- Handler実行中の長時間TransactionとRow Lockを避けられる。
- TransportとJournalのPort分離を維持しつつ、PostgreSQL Adapterでは追加の原子性を提供できる。
- JSON Lines等の外部Observer障害をDatabase Transactionへ波及させない。
- Observer Projectionには欠落が起こり得るため、Canonical Journalからの再送Toolが必要になる。
- 業務Databaseが別Connectionの場合、その更新との原子性は別途Transactional Outboxを使わない限り保証しない。

[/CONSEQUENCES]
