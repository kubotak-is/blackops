# Post Phase 10 Roadmap

## Goal

Phase 7からPhase 10で完成したInstalled Application、Composer Skeleton、BlackOps CLI、Validation、Worker Runtime、Repository DocumentationをStable Releaseへ届け、その後にApplication開発と運用に必要なRuntime機能を依存順に実装する。

Documentation WebsiteのCloudflare公開、Build、Search、Artifact Guard、CIは現行の完了済みSurfaceとして維持する。Custom DomainとVersion Selector、複数Releaseの切替UIはDeferred Ecosystem Scopeに残す。

## Delivery Order

```text
Phase 11 Stable 1.1 Release
  -> Phase 12 Middleware and Authorization Runtime
    -> Phase 13 Database and Transaction Runtime
      -> Phase 14 Operation Diagnostics
        -> Phase 15 Operation Frontend Bridge
          -> Phase 16 Deferred Status and Outcome API
            -> Phase 17 Full-stack Reference Application
              -> Phase 18 Application Ergonomics
                -> Phase 19 Reliability and Delivery
                  -> Phase 20 Security Hardening and Observability
                    -> Phase 21 Framework-owned Transaction Interception
                      -> Phase 22 Stable 1.2 Version Baseline and Release Gate
```

## Phase 11: Stable 1.1 Release

Release Version、Experimental Compatibility Policy、Task順序は[Experimental Release Contract](61-experimental-release-contract.md)と[Phase 11 Delivery Plan](62-phase-11-delivery-plan.md)を正本とする。

- Stable `1.0.0`から`main`へのCompatibility Audit
- Project Root `blackops`、Canonical Command、旧Command AliasのUpgrade Guide
- Typed Self-handled Operation、Validation、Worker Mode DefaultのRelease Note
- Framework／Skeleton Version、Constraint、Create-project Smoke
- Full Quality／Consumer／Publication Gate
- FrameworkとSkeleton `1.1.0` Publication

完了時、新規ApplicationはStable `1.1` Skeletonから作成でき、Stable向けQuickstartを完走できる。

## Phase 12: Middleware and Authorization Runtime

- HTTP用PSR-15 Middlewareの玉ねぎPipeline
- Global HTTP MiddlewareのConfig登録順と型検証
- Credentialを保持しないAuthenticator／ActorContext境界
- Authorization Policy、Rejected Lifecycle、HTTP 401／403
- Deferred受付時とWorker実行時の再認可

Operation Middleware、Operation単位の汎用Middleware Attribute、Dispatch／Execution Scopeは実装しない。AuthorizationはFramework固定のOperation Lifecycle Stageとする。詳細は[Authentication and HTTP Middleware](06-auth-and-middleware.md)と[Phase 12 Delivery Plan](63-phase-12-delivery-plan.md)を正本とする。

## Phase 13: Database and Transaction Runtime

- Named Doctrine DBAL ConnectionのApplication ConfigurationとDI
- Repository／Application ServiceへのConstructor Injection
- Transaction Lifecycle Contractと明示Attribute
- Ray.AopをSymfony DIのBuild時Compilerへ統合するMethod Interception
- Operation以外のCommand／Application Serviceへ適用できる`#[Transactional]`
- Transaction Scopeへ呼出をQueueする`#[AfterCommit]`とFailure Reporter
- Manual Transaction、Nested呼び出し、複数Connectionの境界
- Worker ModeのConnection Health Check／Reset／Reconnect
- 業務DBとBlackOps Storeが同一または別Connectionの場合の保証差

初期ScopeではORMとRepository基底Classを標準化せず、Connection、DI、Transaction境界を提供する。Operation Transactionは固定Lifecycle、一般Service TransactionはCompiled Method Interceptorとし、汎用Operation Middlewareは導入しない。After Commitは同期Best-effort、Transactional OutboxはPhase 19とする。

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

Status: Complete

- Operation IDによるStatus Query
- Pending／Running／Retry Scheduled／Completed／Rejected／Failed／Dead Letteredの安定表現
- Typed OutcomeとTerminal Errorの安全な表現
- HTTP Status／Outcome Endpoint、`Location`、Polling Contract
- Authentication／Authorization、Tenant、Retention境界
- Generated Operation Objectの`.status()`／`.wait()`
- Quickstart、Integration Test、Tutorial

Generated ClientへのPolling統合はPhase 15のOperation Object Contractを利用し、Phase 16の必須完了条件とする。`.fetch()`は自動Pollingせず、明示的な`.status()`とAbort可能で有限な`.wait()`を追加する。詳細は[Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)を正本とする。

## Phase 17: Full-stack Reference Application

Status: Complete

- `examples/community-board/`の独立したBlackOps Board Application
- SvelteKit Same-origin BFFとServer-only Generated Operation Object
- Application-owned User Registration、Password、Session、Login／Logout
- Post Feed、Post Detail、Create／Edit／Delete、Comment
- Validation、ActorContext、Owner Authorization、DBAL Repository、Transaction
- Deferred `GenerateWeeklyDigest`の202、Status／Wait、Typed Outcome UI
- Taste SkillをDesign Directionに使うAccessible／Responsive Product UI
- Local Compose、Seed、Real Browser E2E、Screenshot／Guide、CI

Quickstart／Skeletonは変更せず、External Hostingを含めない。Documentation WebsiteはRepository内のGuideをCloudflare Pagesへ公開する現行Surfaceとして扱う。Phase 17時点のAuthentication EndpointはOperation外のApplication-owned HTTP Boundaryとしたが、Phase 18でEphemeral OperationとFramework Session Coreへ移行した。BrowserからBlackOpsへの直接通信とCredentialのJournal保存は引き続き行わない。詳細は[Full-stack Reference Application](71-full-stack-reference-application.md)と[Application Ergonomics](74-application-ergonomics.md)を正本とする。

## Phase 18: Application Ergonomics

Status: Complete

- Typed `Environment` SnapshotとConfiguration Closure
- Framework-neutralなFrontend Bound Client Factory
- Symfony `#[AsCommand]`のBuild時DiscoveryとCompiled Container DI
- 明示的`#[ConsoleCommand]`によるOperation Console Adapter
- Framework同梱のOpt-in `BlackOps\Auth\Session` Capabilityと`make:auth` Generator
- Community BoardのFrontend／Identity／Command／Dependency簡素化
- Clean Install、Generated Artifact、Sensitive BoundaryのConsumer Gate

CoreをSvelteKit、DBAL、Session Authenticationへ固定せず、Applicationが直接利用するVendor Packageは明示Dependencyとして維持する。詳細は[Application Ergonomics](74-application-ergonomics.md)と[Phase 18 Delivery Plan](75-phase-18-delivery-plan.md)を正本とする。

## Phase 19: Reliability and Delivery

Status: Complete

- Idempotency Keyの受付、保存、重複時Contract
- Transactional Outbox Persistence AdapterとRelay
- Canonical JournalからObserver Projectionを再送するCLI
- at-least-once、Fencing、Retry、Dead Letter運用
- HandlerのIdempotency責務とFramework支援

Community Boardの二重投稿防止と通知配送をConcrete Acceptance Journeyとして利用する。
詳細は[Reliability and Delivery](80-reliability-and-delivery.md)と[Phase 19 Delivery Plan](81-phase-19-delivery-plan.md)を正本とする。

Phase 19のConsumer／Documentation／Full GateはP19-008で完了した。External Publication／Deploy、Stable Release、Tag、Remote Skeleton更新は行わない。

## Phase 20: Security Hardening and Observability

- Journal／Status／Outcome参照制御とTenant分離
- Canonical Payload／Transportの暗号化Capability
- 構造化Log Schema Version
- OpenTelemetry Trace／Metric Adapter
- Health／ReadinessとWorker／Scheduler運用指標
- Development／Demo／Test専用のLocal Grafana LGTM Trace／Metric閲覧Consumer

Phase 20のLocal Grafana LGTM backendはD138でApplication-owned／Development-onlyへ限定する。Grafana、Tempo、PrometheusのHost公開、Default Compose、Production Dependency、Readiness、Remote Credential、Persistent Volume、DeploymentはこのRoadmapの完了条件に含めない。

## Phase 21: Framework-owned Transaction Interception

- Ray.Aopを置き換えるBuild-time Subclass Proxy Generator
- `#[Transactional]`と`#[AfterCommit]`だけに限定したMethod Interception
- PHP Public Method Signature、Inheritance、`readonly`、Reference、Variadic、Union／Intersection TypeのCompatibility Matrix
- 決定的なGenerated Artifact、Stale Cleanup、Symfony DI Service登録
- Operation固定Transaction Lifecycleと一般Service Interceptorの既存保証維持
- Ray.Aop互換Regression、Migration、Dependency Removal Gate

汎用AOP Engineは実装しない。Production RuntimeでのSource Scan／Proxy生成も導入しない。D137／Specification 101で契約を確定し、Specification 102とP21-002〜P21-007で、Contract Guard→Generator／Artifact→Symfony DI→Runtime Ownership→Compatibility／Migration→Ray Removalの順に実装した。Phase 21はP21-007のRemoval Gateを受入済みで、Framework-only artifact chainを維持する。

## Phase 22: Stable 1.2 Version Baseline

P22-001は公開済みStable `1.1.0`をImmutableな履歴として維持し、Repository `main`の未公開Release Candidateを`1.2.0`へ同期する。Main root version、OpenTelemetry scope、Skeleton Source、Candidate Consumer、active Documentationを更新し、Stable install journey、Tag／Release／Packagist claim、歴史的記録は変更しない。完全なRelease Gate、Tag、Publication、Packagist反映は後続Taskで扱う。

## Deferred Ecosystem Scope

- Custom Domain、Version Selector、複数ReleaseのDocumentation切替
- OpenAPI、Contract Diff、Frontend Framework Adapter
- SQLite、MySQL、SQS、Kafka Adapter
- Batch、Saga
- Admin UI、検索、再試行、Cancel

Scheduled Operationとone-shot CLI、Documentation WebsiteのCloudflare Pages公開はPhase 22までに実装済みであり、Deferred Scopeではない。外部Supervisor、Custom Domain、Version Selector、Batch／Sagaなどの追加Ecosystem Surfaceは引き続き後続Taskで扱う。

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
