# Journal Sequence Allocation

## 管理場所

Inline OperationはExecution Scope内で次Sequenceを管理する。

Deferred OperationはExecution Transportの永続Operation Stateに `next_sequence` を保持し、HTTP Process、Worker、Retry Workerを越えて管理する。

## 競合制御

DeferredのSequence予約は、Compare-and-Swapまたは行Lockを用いた短いTransactionで原子的に行う。

期待するState Versionまたは次Sequenceが変化していた場合は競合として再試行し、不正なJournal Recordを発行しない。

PostgreSQL MVPではOperation Stateの更新と必要な永続Journal書き込みを、可能な範囲で同じTransaction境界へ含める。

## 欠番

Sequenceは同一Operation内で次を保証する。

- 1から開始する
- 重複しない
- 単調増加する

Gaplessは保証せず、欠番を許容する。欠番は配送障害やProcess CrashのSignalとして監視し、既存RecordのSequenceを書き換えない。

## 再配送

ObserverまたはCanonical Storeへの配送Retryでは、同じRecord IDとSequenceを維持する。

配送Retryは新しいLifecycle Eventではない。SinkはRecord IDまたはOperation IDとSequenceの組で冪等に重複排除できる。
