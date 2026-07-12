# D046: MVP Delivery Plan

Status: Decided

> D047でFrontend接合仕様を確定するまで、HTTP Vertical SliceのResponse Contractは未確定とする。D048でCodexをOrchestrator兼Reviewer、Codex GPT-5.4-mini workerを実装依頼先とする。

## Context

Core Model、Journal、PostgreSQL Deferred Transport、Worker Recovery、RetentionまでMVP仕様が具体化した。

このまま全機能を同時実装すると、動作する成果が出るまで長くなる。縦に動く小さなSliceを積み上げ、各Phaseで仕様と実装を照合する。

## Question 1: 実装Phase

### Proposed Plan

```text
Phase 0: Foundation
  Composer、Namespace、DI、Docker Compose、PostgreSQL
  Mago、PHPUnit、Deptrac

Phase 1: Inline Vertical Slice
  Operation、Value、Envelope、Handler、Result、Dispatcher
  GET /welcome -> ShowWelcome -> WelcomeShown

Phase 2: Journal and Logging
  Lifecycle State Machine、Canonical Journal、Projection、JSONL Logger

Phase 3: Deferred Vertical Slice
  POST /reports -> PostgreSQL -> Worker -> Outcome
  HTTP 202 + Operation ID

Phase 4: Resilience
  Retry、Lease、Heartbeat、Fencing、Crash Recovery、Dead Letter

Phase 5: Retention
  Policy、Tombstone、Hold、Audit、Scheduler Worker

Phase 6: Compile and Polish
  Manifest、Container Compile、Architecture Test、Documentation
```

### Options

- A: このPhase順で進める
- B: JournalをPhase 1へ含め、最初からJournal付きInline Sliceを作る
- C: 先に全ContractとDatabase Schemaを実装してから動作Sliceを作る

### Recommendation

Bを推奨する。

BlackOpsはJournalが本体なので、最初の動作Sliceから `operation.received`、`attempt.started`、`attempt.succeeded`、`operation.completed` を記録する。Phase 2はSensitive ProjectionとLogging Adapterの強化に絞る。

[ANSWER]

B

[/ANSWER]

## Question 2: 最初の実装到達点

### Options

- A: CLIだけでInline OperationをDispatchする
- B: HTTP `GET /welcome` まで通し、PostgreSQL Journalへ記録する
- C: InlineとDeferredを同時に完成させる

### Recommendation

Bを推奨する。

HTTP BindingからHandler、Outcome、Responder、Journalまで最小のBlackOps体験を一周できる。

[ANSWER]

B

[/ANSWER]

## Question 3: Design Freeze

### Options

- A: D046確定後はMVP実装へ入り、実装で判明した矛盾だけ新しいDecisionで解消する
- B: すべてのInterface MethodとSQLを先にDecisionで確定する
- C: 実装と無関係に設計対話だけを継続する

### Recommendation

Aを推奨する。

主要な責務とInvariantは十分に決まった。以後は動くCodeとTestを設計の検証材料にし、必要な判断だけMarkdownのDecisionへ戻す。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

JournalをPhase 1へ含め、最初のVertical SliceからLifecycle Journalを記録する。

最初の実装到達点は、HTTP `GET /welcome` をOperationへBindingし、HandlerとResponderを通してHTTP Responseを返し、PostgreSQL Canonical JournalへLifecycleを記録することとする。

D046確定後はMVP実装へ移る。実装で判明した矛盾または新しい設計判断だけを、新しいDecisionとしてMarkdownへ戻す。

実装Phaseは次とする。

```text
Phase 0: Foundation
  Composer、Namespace、DI、Docker Compose、PostgreSQL
  Mago、PHPUnit、Deptrac

Phase 1: Journal付きInline Vertical Slice
  Operation、Value、Envelope、Handler、Result、Dispatcher
  Lifecycle State Machine、PostgreSQL Canonical Journal
  GET /welcome -> ShowWelcome -> WelcomeShown

Phase 2: Projection and Logging
  Sensitive Projection、JSONL Logger、Execution Scope

Phase 3: Deferred Vertical Slice
  POST /reports -> PostgreSQL -> Worker -> Outcome
  HTTP 202 + Operation ID

Phase 4: Resilience
  Retry、Lease、Heartbeat、Fencing、Crash Recovery、Dead Letter

Phase 5: Retention
  Policy、Tombstone、Hold、Audit、Scheduler Worker

Phase 6: Compile and Polish
  Manifest、Container Compile、Architecture Test、Documentation
```

Code Qualityは次を採用する。

```text
Lint:             Mago
Static Analysis:  Mago
Test:             PHPUnit
Architecture:     Deptrac
```

Magoの設定は `mago.toml` としてRepositoryで管理し、CIで `mago lint` と `mago analyze` を実行する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- BlackOpsの中核であるJournalを最初の動作Sliceから検証できる。
- HTTP、Operation、Handler、Outcome、Responder、Journalを早期に一周できる。
- DeferredやRetentionを待たず、利用感と公開APIの問題を発見できる。
- 以後はCodeとTestを設計の検証材料とし、事前設計を無制限に増やさない。
- MagoでLintとStatic Analysisを統一し、PHPDoc Genericを含む型契約を検証する。
- PHPUnitをUnit TestとIntegration Testの標準Runnerとする。
- Deptracは既決定のNamespace依存方向を検証する専用Toolとして維持する。

[/CONSEQUENCES]
