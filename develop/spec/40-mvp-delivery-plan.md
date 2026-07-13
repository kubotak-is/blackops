# MVP Delivery Plan

## Phase

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

## 最初の到達点

HTTP `GET /welcome` をOperationへBindingし、HandlerとResponderを通してResponseを返す。

同じ実行で次のLifecycleをPostgreSQL Canonical Journalへ記録する。

```text
operation.received
attempt.started
attempt.succeeded
operation.completed
```

## Code Quality

```text
Lint:             Mago
Static Analysis:  Mago
Test:             PHPUnit
Architecture:     Deptrac
```

Magoの設定は `mago.toml` として管理し、CIで `mago lint` と `mago analyze` を実行する。

## Design Freeze

D046以後はMVP実装へ移る。

実装で判明した矛盾または新しい設計判断だけを、新しいDecisionとしてMarkdownへ戻す。CodeとTestを設計の検証材料とする。

ただし、Frontend接合方式は未確定のためD047で決定してからHTTP Response Contractを実装する。

実装作業の役割分担はD048のOrchestration Protocolに従う。

## Implementation Orchestration

CodexをOrchestrator兼Reviewer、Codex GPT-5.6 Luna High workerを実装依頼先とする。

実装はWSL2内の `/home/kubotak/projects/blackops` で行う。Orchestrator CodexがTask Packetを作成し、GPT-5.6 Luna High workerが実装、Test、Reportを行い、Orchestrator CodexのReview完了後にCommitする。Model／Profileを指定できない場合は別Modelへ黙ってFallbackしない。

進行状態は `develop/STATE.md` に保存する。再開時は `AGENTS.md`、Checkpoint、現在のTask Packet、Reportの順に確認する。

ドキュメントは次の対象読者別に管理する。

```text
docs/internals/  Framework実装者向け
docs/guide/      Framework利用者向け
```
