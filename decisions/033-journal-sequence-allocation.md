# D033: Journal Sequence Allocation

Status: Decided

## Context

すべてのJournal Recordは、同一Operation内で1から始まり、重複せず単調増加する `sequence` を持つ。

Inline処理は一Process内で完結できるが、Deferred処理はHTTP Process、Worker、Retry Workerへまたがる。並行ClaimやProcess CrashがあってもSequenceを重複させず、同じRecordの再配送で新しいSequenceを発行しない仕組みが必要である。

## Question 1: Sequenceの管理場所

### Options

- A: InlineはExecution Scope、Deferredは永続Operation Stateで次Sequenceを管理する
- B: すべてCanonical Journal Storeへ問い合わせて最大値+1を使う
- C: 各ProcessのMemory Counterだけで管理する

### Recommendation

Aを推奨する。

InlineはStoreなしでも動作できる。DeferredではExecution TransportのOperation Stateに `next_sequence` を保存し、Processを越えて継続する。

[ANSWER]

A

[/ANSWER]

## Question 2: 競合制御

### Options

- A: 永続StateをCompare-and-Swapまたは行Lockで更新し、Sequenceを原子的に予約する
- B: Worker IDごとにSequence範囲を予約する
- C: 重複した場合だけ後から番号を振り直す

### Recommendation

Aを推奨する。

SQLite MVPでは短いTransaction内でOperation Stateを更新する。期待したVersionまたは次Sequenceが変わっていれば競合として再試行し、不正なLifecycle Recordを発行しない。

[ANSWER]

A

[/ANSWER]

## Question 3: 欠番

Sequence予約後、Observer配送前にProcessが停止すると欠番が起こり得る。

### Options

- A: 重複禁止と単調増加を保証し、欠番は許容して監視対象とする
- B: 必ずGaplessにし、欠番があるOperationは処理不能とする
- C: 欠番を詰めるため既存RecordのSequenceを書き換える

### Recommendation

Aを推奨する。

Gapless保証にはLifecycle更新と全Sink配送を単一Transactionへ入れる必要があり、Adapter交換性を損なう。欠番は配送障害やCrashのSignalとして記録・監視し、既存Recordの番号は変更しない。

[ANSWER]

A

[/ANSWER]

## Question 4: 再配送

ObserverまたはCanonical Storeへの配送をRetryするときの扱いを決める。

### Options

- A: 同じRecord IDとSequenceを維持して再配送する
- B: Retryごとに新しいRecord IDとSequenceを発行する
- C: Observerごとに別Sequenceを発行する

### Recommendation

Aを推奨する。

配送Retryは新しいLifecycle Eventではない。同じRecordとして冪等に扱い、SinkはRecord IDまたはOperation ID＋Sequenceで重複排除できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Inline OperationはExecution Scope内で次Sequenceを管理する。Deferred OperationはExecution Transportの永続Operation Stateに `next_sequence` を保持し、Processを越えて管理する。

DeferredのSequence予約は、Compare-and-Swapまたは行Lockを用いた短いTransactionで原子的に行う。期待するState Versionまたは次Sequenceが変化していた場合は競合として再試行し、不正なRecordを発行しない。

Sequenceは同一Operation内の重複禁止と単調増加を保証するが、欠番は許容する。欠番は配送障害やProcess CrashのSignalとして監視し、既存RecordのSequenceを書き換えない。

ObserverまたはCanonical Storeへの配送Retryでは、同じRecord IDとSequenceを維持する。配送Retryを新しいLifecycle Eventとして扱わない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- InlineはCanonical StoreなしでもSequenceを割り当てられる。
- DeferredはHTTP Process、Worker、Retry Workerを越えてSequenceを継続できる。
- 並行Claimが発生しても同じSequenceの異なるRecordを発行しない。
- Gapless保証のために全Observerを単一Transactionへ含める必要がなく、Adapter交換性を保てる。
- Consumerは欠番を記録欠落または配送障害の可能性として検知できる。
- SinkはRecord IDまたはOperation IDとSequenceの組で再配送を重複排除できる。
- Deferred Operation StateにState Versionと `next_sequence` が必要になる。

[/CONSEQUENCES]
