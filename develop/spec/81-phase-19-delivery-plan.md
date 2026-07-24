# Phase 19 Delivery Plan

## Goal

Reliability and DeliveryをIdempotency Core、Duplicate Lifecycle、Transactional Outbox、Relay、Replay、Community Board統合の順に実装し、各障害境界を独立して検証する。

## Delivery Order

```text
P19-001 Decision, Specification, and Failure Matrix
  -> P19-002 Idempotency Core Contract
    -> P19-003 HTTP and PHP Duplicate Lifecycle and Retention
      -> P19-004 Transactional Outbox Persistence
        -> P19-005 Relay Runtime and CLI
          -> P19-006 Canonical Observer Replay
            -> P19-007 Community Board Reliability Journey
              -> P19-008 Consumer, Documentation, and Phase Closeout
```

Taskは依存順に実装し、同一Production Surfaceへ並行変更しない。各TaskでPublic／Internal Contract、Unit／Integration Test、PostgreSQL Evidenceを完成してから次へ進む。Community Board全体の統合はP19-007まで行わず、先行TaskはFramework Fixtureと最小Consumerだけを使う。

## P19-001: Decision, Specification, and Failure Matrix

Status: Complete.

Owner: Orchestrator

- D109をReliability and Delivery仕様へ具体化する
- Idempotency、Outbox、Relay、Replayの責任分界とFailure Matrixを固定する
- Phase 19 Task境界、依存順、Acceptance Criteriaを固定する
- P19-002 Task Packetを作成する

Production Codeは変更しない。

## P19-002: Idempotency Core Contract

Status: Accepted.

- Public Key／Opaque Hash ValueとShape Invariantを実装する
- ExecutionContext Root／Attempt／Deferred Codec／Child非伝播を実装する
- Version付きScope／Fingerprint Canonicalizerを実装する
- Atomic Claim／Lookup／Terminalize用Internal Storage ContractとIn-memory Fixtureを実装する
- Raw Key／Sensitive Value／Credential非保存Guardを実装する
- Public API Architecture、Context Codec、Worker Compatibilityを回帰する

HTTP Header、Dispatcher Option、PostgreSQL Adapter、Retentionは変更しない。

## P19-003: HTTP and PHP Duplicate Lifecycle and Retention

Status: Accepted.

- Mutation HeaderとPHP Dispatch Optionを実装する
- Binding／Validation／Authentication／Authorization後のClaim順序を実装する
- Inline Terminal ResponseとDeferred 202／Operation IDの再利用を実装する
- Conflict／In-progress／Expired／Anonymous／Unsupported Failure Matrixを実装する
- PostgreSQL Idempotency Store、Migration、Crash Recovery、Integrity Failureを実装する
- 独立Retention、Hold、Plan／Purge／Auditを実装する
- Real HTTP、Concurrent Claim、Worker Reuse、Sensitive Testを追加する

Outbox／Relay、Community Board Product Journeyは変更しない。

## P19-004: Transactional Outbox Persistence

Status: Accepted.

- Deferred child Operationを登録するPublic Capabilityを実装する
- 固定Outbox Record／child Operation IDとContext親子関係を実装する
- 同一Named ConnectionのTransaction参加とRollbackを実装する
- Transaction外／異Connection／所有者不明Manual TransactionをFail-fastする
- PostgreSQL Outbox Schema、Migration、Store、Claim前Stateを実装する
- Direct Transportを回帰し、異Connectionで原子的保証を出さない

Relay Loop、Dead Letter再開、Observer Replayは変更しない。

## P19-005: Relay Runtime and CLI

Status: Accepted.

- PostgreSQL Claim／Lease／Heartbeat／Fencing／有限Batchを実装する
- 同じRecord／child Operation IDで既存Deferred Transportへ配送する
- 指数Backoff、最大Attempt、Dead Letter、Lease切れRecoveryを実装する
- `outbox:relay:run`／`outbox:relay:daemon`を実装する
- `outbox:dead-letter:retry`を監査付きで実装する
- Scheduler、Graceful Shutdown、Worker Reuse、Crash Windowを検証する

Operation Replay、Observer Replay、Community Board UIは変更しない。

## P19-006: Canonical Observer Replay

Status: Accepted.

- Canonical Record IDを維持するObserver Replay Serviceを実装する
- Operation／Record／時刻範囲と対象Observerの選択を実装する
- Dry-run、有限Batch、Checkpoint、監査を実装する
- Current Sensitive Filterを再適用し、Canonical Storeを変更しない
- Operation ReplayとOutbox Dead Letter再開を別Command／Identityとして維持する
- Duplicate Projection、Observer Failure、ResumeをIntegration Testする

Community Board Product Journeyは変更しない。

## P19-007: Community Board Reliability Journey

Status: Accepted.

- Digest FormへServer-generated Idempotency Keyを接続する
- 同じKeyの二重Submitを同じ202／Operation ID／Digestへ収束させる
- 別Keyの同一User／Weekは別Digestになる既存Contractを維持する
- Comment Transactionから`NotifyPostOwner`をOutboxへ登録する
- Application-owned Notification Store／Operation／UIを実装する
- Relay停止／再開／重複配送／Browser二重Submitを実PostgreSQLで検証する
- Server-only Generated ClientとCredential Boundaryを維持する

外部Email／Push、External Publication／Deployは行わない。

## P19-008: Consumer, Documentation, and Phase Closeout

- QuickstartまたはPermanent FixtureでIdempotency／Outbox／Relay／Replayを完走する
- Skeleton、Config、Migration、BlackOps CLI、Upgrade、Guide、Internal Referenceを同期する
- Community Board Clean Install、Product、Digest、Notification、Browserを完走する
- Framework Full PHPUnit、Mago、Deptrac、Frontend、Publication Dry-run、Websiteを回帰する
- Retention、Sensitive、Worker Reuse、Artifact、Migration Current Schemaを検証する
- Report、TODO、STATE、Roadmapを同期してPhase 19をCloseする

External Publication／Deployは行わない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立再検証、Commitする
- Public API、Security、Transaction、Identity、Retentionの仕様矛盾はTaskを広げずBlockerとして返す
- KeyなしPath、Direct Transport、Current Authentication／Authorizationを各Taskで回帰する
- Task間で同じProduction Fileを先取り変更しない
- Generated／Dependency／Runtime／Browser ArtifactはTask完了前にCleanupする
- Documentation WebsiteとCommunity Boardを外部公開しない

## Phase Acceptance Criteria

- [x] D109とPhase 19 Specification／Failure Matrix／Delivery PlanがDecidedである
- [x] Idempotency Core Value／Context／Storage Contractが実装される
- [x] HTTP／PHP Duplicate Lifecycleと独立Retentionが実装される
- [x] Transactional Outboxが同一Named Connectionへ原子的に参加する
- [x] Relay Claim／Retry／Fencing／Dead Letter CLIが実装される
- [x] Canonical Observer ReplayがRecord Identityを維持する
- [x] Community BoardのDigest／Notification JourneyがBrowserから完走する
- [ ] Sensitive、Crash、Worker Reuse、Migration、Retention回帰が固定される
- [ ] Full Framework／Consumer／Frontend／Website Gateが成功する
- [ ] External Publication／Deployを行わない

## Traceability

- Decision: [D109 Phase 19 Idempotency and Outbox](../decisions/109-phase-18-idempotency-and-outbox.md)
- Contract: [Reliability and Delivery](80-reliability-and-delivery.md)
- Transaction: [Durable Journal and Transactions](11-durable-journal-and-transactions.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
