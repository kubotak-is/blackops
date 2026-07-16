# Post Phase 10 Roadmap

## Goal

Phase 7からPhase 10で完成したInstalled Application、Composer Skeleton、Project CLI、Validation、Worker Runtime、Repository DocumentationをStable Releaseへ届け、その後にApplication開発と運用に必要なRuntime機能を依存順に実装する。

Documentation WebsiteのCloudflare公開はRoadmapの完了条件に含めない。Repository内Source、Website Build、Search、Artifact Guard、CIは維持し、公開再開はUserが明示した将来Taskで扱う。

## Delivery Order

```text
Phase 11 Stable 1.1 Release
  -> Phase 12 Middleware and Authorization Runtime
    -> Phase 13 Database and Transaction Runtime
      -> Phase 14 Operation Diagnostics
        -> Phase 15 Operation Frontend Bridge
          -> Phase 16 Deferred Status and Outcome API
            -> Phase 17 Reliability and Delivery
              -> Phase 18 Security Hardening and Observability
```

## Phase 11: Stable 1.1 Release

- Stable `1.0.0`から`main`へのCompatibility Audit
- Project Root `blackops`、Canonical Command、旧Command AliasのUpgrade Guide
- Typed Self-handled Operation、Validation、Worker Mode DefaultのRelease Note
- Framework／Skeleton Version、Constraint、Create-project Smoke
- Full Quality／Consumer／Publication Gate
- FrameworkとSkeleton `1.1.0` Publication

完了時、新規ApplicationはStable `1.1` Skeletonから作成でき、Stable向けQuickstartを完走できる。

## Phase 12: Middleware and Authorization Runtime

- HTTP用PSR-15 Adapter MiddlewareとOperation Middlewareの玉ねぎPipeline
- Global／Endpoint／Operation単位の登録、除外、順序
- Dispatch／Execution ScopeとManifest Compile検証
- Credentialを保持しないAuthenticator／ActorContext境界
- Authorization Policy、Rejected Lifecycle、HTTP 401／403
- Deferred受付時とWorker実行時の再認可

既存のAuthentication and Middleware Specificationを出発点とし、実装前にFrameworkとApplicationの所有境界を再確認する。

## Phase 13: Database and Transaction Runtime

- Named Doctrine DBAL ConnectionのApplication ConfigurationとDI
- Repository／Application ServiceへのConstructor Injection
- Transaction Operation Middlewareと明示Attribute
- Manual Transaction、Nested呼び出し、複数Connectionの境界
- Worker ModeのConnection Health Check／Reset／Reconnect
- 業務DBとBlackOps Storeが同一または別Connectionの場合の保証差

初期ScopeではORMとRepository基底Classを標準化せず、Connection、DI、Transaction境界を提供する案を開始点とする。最終ScopeはPhase Decisionで確定する。

## Phase 14: Operation Diagnostics

- Error ResponseとApplication LogからOperation IDへ到達できる相関
- `php blackops operation:inspect <operation-id>`によるLifecycle、Attempt、Error、Outcome表示
- 同じQuery Modelを使うDevelopment限定Local Viewer
- Sensitive Projection、Local Bind、明示起動、Access制御
- Production Log、Journal Query、Remote Observabilityとの相関
- Missing／Purged／Unauthorized Operation IDの安全な表示

Terminal Queryを最小Vertical Slice候補とし、Local ViewerとProduction運用境界はPhase Decisionで確定する。

## Phase 15: Operation Frontend Bridge

- Operation／HTTP Manifestを正本とするFrontend Contract Manifest
- URL、HTTP Method、Path／Query／Header／Body BindingのTypeScript関数
- OperationValue、Validation Violation、Outcome、Rejectedの型表現
- Inline Typed ResponseとDeferred Acknowledgement
- Sensitive Fieldの除外／明示許可Rule
- Generated Artifact Drift TestとFrontend Build連携

最初のVertical SliceをWayfinder相当のRequest Descriptorにするか、Full Typed Clientまで含めるかはPhase開始前に決定する。React／Vue／Svelte等への依存有無も同じDecisionで確定する。

## Phase 16: Deferred Status and Outcome API

- Operation IDによるStatus Query
- Pending／Running／Retry Scheduled／Completed／Rejected／Failed／Dead Letteredの安定表現
- Typed OutcomeとTerminal Errorの安全な表現
- HTTP Status／Outcome Endpoint、`Location`、Polling Contract
- Authentication／Authorization、Tenant、Retention境界
- Quickstart、Integration Test、Tutorial

Generated ClientへのPolling統合はPhase 15のContractを利用する後続Taskとし、Phase 16の必須完了条件にはしない。

## Phase 17: Reliability and Delivery

- Idempotency Keyの受付、保存、重複時Contract
- Transactional Outbox Persistence AdapterとRelay
- Canonical JournalからObserver Projectionを再送するCLI
- at-least-once、Fencing、Retry、Dead Letter運用
- HandlerのIdempotency責務とFramework支援

## Phase 18: Security Hardening and Observability

- Journal／Status／Outcome参照制御とTenant分離
- Canonical Payload／Transportの暗号化Capability
- 構造化Log Schema Version
- OpenTelemetry Trace／Metric Adapter
- Health／ReadinessとWorker／Scheduler運用指標

## Deferred Ecosystem Scope

- Documentation Website Publication、Custom Domain、Version Selector
- OpenAPI、Contract Diff、Frontend Framework Adapter
- SQLite、MySQL、SQS、Kafka Adapter
- Scheduled Operation、Batch、Saga
- Admin UI、検索、再試行、Cancel

## Phase Workflow

各Phaseは次の順序を守る。

1. User Goalと既存Specificationを照合する
2. 未決のPublic API、Security、CompatibilityをDecisionで確定する
3. Delivery PlanとTask Packetを作成する
4. GPT-5.6 Luna High workerがTask単位で実装・検証する
5. OrchestratorがReview、独立再検証、Commitを行う
6. Phase Closeout ReportとSTATEを同期する

## Traceability

- Decision: [D093 Post Phase 10 Roadmap](../decisions/093-post-phase-10-roadmap.md)
- Previous Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Documentation Phase: [Phase 10 Delivery Plan](58-phase-10-delivery-plan.md)
