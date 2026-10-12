# Specification Index

## 入口と正本

- 現在の作業と再開手順: [STATE](../STATE.md)
- 未決事項と優先順位: [TODO](../TODO.md)
- 開発運用: [仕様109](109-ai-development-workflow.md)、[Task/Reportと更新手順](../orchestration/README.md)
- 公開Releaseとimmutable source refs: [Release Authority](release-authority.json)
- 図解とLanding: [仕様110](110-documentation-diagrams-and-presentation.md)、[D159](../decisions/159-documentation-diagrams-and-presentation.md)、[P23-008 Task](../orchestration/tasks/P23-008-archify-documentation-diagrams.md)、[Report](../orchestration/reports/P23-008-archify-documentation-diagrams.md)
- Accepted Status source: [D2S1 Task](../orchestration/tasks/P23-003D2S1-tenant-scoped-status-source.md)、[Report](../orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md)
- Status Audit adapter: [D2S Task](../orchestration/tasks/P23-003D2S-operation-status-audit-adapter.md)、[Report](../orchestration/reports/P23-003D2S-operation-status-audit-adapter.md)

ここでは文書への導線を管理する。Task途中の進捗、検証件数、Reviewの往復を追記せず、現在のSTATE/Reportを参照する。
表にあるProposalや過去のAcceptedはRelease済みCapabilityを意味しない。Current Releaseは上記Authorityで確認する。
以前のhandoffを含む全文は[2026-09-08の旧Index](../orchestration/history/2026-09-08-spec-index.md)へ原文のまま保存した。
その履歴中の相対リンクは元の `develop/spec/README.md` を基準に解釈する。

## 文書の規則

- 確定契約は仕様、未決事項はTODO、判断理由はDecisionに記載する。
- ProposalとRelease済みの契約を区別する。
- 契約を変更するときは対象Taskと理由を明示してReviewする。
- 置き換えられたDecisionは履歴として保持し、現在の正本へ案内する。
- spec106のpreflight/Acceptance同期では参照先との整合を確認し、同じ進捗文を複製しない。

## 仕様書

| 文書 | 内容 |
| --- | --- |
| [00-framework-identity.md](00-framework-identity.md) | 正式名称、PHP名前空間、Composerパッケージ名 |
| [01-core-model.md](01-core-model.md) | Operation、Value、Envelope、Context、識別子 |
| [02-lifecycle-and-journal.md](02-lifecycle-and-journal.md) | ライフサイクル、Journal、Schema、セキュリティ |
| [03-execution.md](03-execution.md) | Execution Strategy、Transport、Supervision |
| [04-handler-and-result.md](04-handler-and-result.md) | Binding、Validation、Handler、Outcome |
| [05-http.md](05-http.md) | Route、HTTP Binding、Responder、Manifest |
| [06-auth-and-middleware.md](06-auth-and-middleware.md) | 認証、認可、Middleware、入口別Operation |
| [07-project-structure.md](07-project-structure.md) | 探索ディレクトリと推奨プロジェクト構造 |
| [08-registry-and-manifest.md](08-registry-and-manifest.md) | Operation探索、Provider、Manifest、Runtime Registry |
| [09-runtime-and-di.md](09-runtime-and-di.md) | PHP Runtime、PSR-11、Symfony DI、Container Compile |
| [10-logging-and-traceability.md](10-logging-and-traceability.md) | PSR-3 Logger、自動Context、Journal Log、OTel相関 |
| [11-durable-journal-and-transactions.md](11-durable-journal-and-transactions.md) | Named Connection、Transaction Attribute、After Commit、Outbox |
| [12-mvp-scope.md](12-mvp-scope.md) | 最初のVertical Sliceと完了条件 |
| [13-mvp-technical-stack.md](13-mvp-technical-stack.md) | PostgreSQL、HTTP PSR、FastRoute、UID、Console、Logger、Test |
| [14-package-architecture.md](14-package-architecture.md) | Composer Packageの公開単位 |
| [15-source-layout.md](15-source-layout.md) | 責務別Namespaceと内部API境界 |
| [16-namespace-dependencies.md](16-namespace-dependencies.md) | Namespace間の依存方向とDeptrac |
| [17-core-api.md](17-core-api.md) | Marker Interface、Handler、PHP Public API |
| [18-operation-envelope.md](18-operation-envelope.md) | 不変Envelope、Getter、Context委譲 |
| [19-execution-context-api.md](19-execution-context-api.md) | 不変Context、AttemptContext、内部Factory |
| [20-identifier-value-objects.md](20-identifier-value-objects.md) | UUIDv7 ID型、生成Factory、文字列表現 |
| [21-clock-and-time.md](21-clock-and-time.md) | PSR-20、UTC、時刻文字列、順序との分離 |
| [22-journal-record-schema.md](22-journal-record-schema.md) | Journal Envelope、Event名、Sequence、Data |
| [23-journal-record-api.md](23-journal-record-api.md) | JournalRecord、JournalEvent、JournalData、Factory |
| [24-lifecycle-event-data.md](24-lifecycle-event-data.md) | Canonical Payload、Outcome、Error、Empty Data |
| [25-sensitive-projection.md](25-sensitive-projection.md) | Sensitive Filter、Observer Projection、Canonical Store |
| [26-journal-ports.md](26-journal-ports.md) | Observer、Projection、Writer、Reader、Flush |
| [27-journal-sequence-allocation.md](27-journal-sequence-allocation.md) | Sequence管理、競合、欠番、再配送 |
| [28-mvp-lifecycle-events.md](28-mvp-lifecycle-events.md) | Retry、Accepted、Succeeded、Terminal Event |
| [29-handler-result-contract.md](29-handler-result-contract.md) | Completed、Rejected、EmptyOutcome |
| [30-lifecycle-state-machine.md](30-lifecycle-state-machine.md) | Operation State、遷移、Terminal制約 |
| [31-deferred-claim-and-attempt.md](31-deferred-claim-and-attempt.md) | Attempt番号、Lease、開始境界、Fencing |
| [32-worker-crash-recovery.md](32-worker-crash-recovery.md) | Heartbeat、Crash Recovery、Shutdown |
| [33-execution-transport-contract.md](33-execution-transport-contract.md) | Message、Acknowledgement、Claim、分割Port |
| [34-mvp-database-transport.md](34-mvp-database-transport.md) | PostgreSQL Reference TransportとDocker Compose |
| [35-postgresql-transport-schema.md](35-postgresql-transport-schema.md) | Column型、Claim、Index、Migration |
| [36-postgresql-transaction-boundaries.md](36-postgresql-transaction-boundaries.md) | State、Journal、Outcome、Observer配送 |
| [37-postgresql-table-layout.md](37-postgresql-table-layout.md) | Schema、Journal、Outcome、Dead Letter |
| [38-data-retention-and-deletion.md](38-data-retention-and-deletion.md) | Retention、Tombstone、削除制約、Legal Hold |
| [39-retention-runtime.md](39-retention-runtime.md) | Retention CLI、Scheduler、Hold、Audit |
| [40-mvp-delivery-plan.md](40-mvp-delivery-plan.md) | 実装Phase、最初のVertical Slice、品質Tool |
| [41-developer-experience-roadmap.md](41-developer-experience-roadmap.md) | Installed Example、Composer Skeleton、BlackOps CLI、Documentation Website |
| [42-installed-application-boundary.md](42-installed-application-boundary.md) | 独立Consumer Package、Public Composition、Process Boundary |
| [43-installed-application-layout-and-bootstrap.md](43-installed-application-layout-and-bootstrap.md) | Feature-first Skeleton、Application Bootstrap、Local Runtime |
| [44-public-application-bootstrap-api.md](44-public-application-bootstrap-api.md) | Application Builder、HTTP／Console Composition、Failure Contract |
| [45-phase-7-delivery-plan.md](45-phase-7-delivery-plan.md) | Public BootstrapからQuickstart Consumer E2EまでのTask順序 |
| [46-composer-skeleton-publication.md](46-composer-skeleton-publication.md) | Split Repository、Version、Lock、Post-create、Release Pipeline |
| [47-public-http-runtime-configuration.md](47-public-http-runtime-configuration.md) | Build／Database Config、Inline／Deferred HTTP Composition、Fail-fast |
| [48-public-console-kernel-composition.md](48-public-console-kernel-composition.md) | Public Console Kernel、Application Command Composition、Exit Contract |
| [49-feature-first-quickstart-application.md](49-feature-first-quickstart-application.md) | Install直後と同じFeature-first Quickstart Application |
| [50-operation-authoring-and-build-discovery.md](50-operation-authoring-and-build-discovery.md) | Self-handled Operation、Build-time Discovery、Handler自動DI登録 |
| [51-local-runtime-and-consumer-e2e.md](51-local-runtime-and-consumer-e2e.md) | Quickstart Docker Runtime、JSONL Journal、独立Consumer E2E |
| [52-phase-8-delivery-plan.md](52-phase-8-delivery-plan.md) | Post-createからDistribution PublicationまでのPhase 8 Task順序 |
| [53-typed-self-handled-operation-invocation.md](53-typed-self-handled-operation-invocation.md) | Native Value／Optional ContextによるSelf-handled Invocation |
| [54-native-outcome-and-rejection-exception.md](54-native-outcome-and-rejection-exception.md) | Native Outcome／Void Returnと業務拒否Exception |
| [55-project-generators-and-application-migrations.md](55-project-generators-and-application-migrations.md) | Operation／Migration GeneratorとApplication Migration実行境界 |
| [56-phase-9-delivery-plan.md](56-phase-9-delivery-plan.md) | Generator実装からFramework Update SmokeまでのPhase 9 Task順序 |
| [57-documentation-website-delivery-contract.md](57-documentation-website-delivery-contract.md) | 利用者向けSingle Source WebsiteとCloudflare Pages公開境界 |
| [58-phase-10-delivery-plan.md](58-phase-10-delivery-plan.md) | Documentation RenameからCloudflare Pages CloseoutまでのTask順序 |
| [59-documentation-reader-experience.md](59-documentation-reader-experience.md) | 初見導線、Concept Diagram、Tutorial、Glossary、Security、Referenceの読者体験 |
| [60-post-phase-10-roadmap.md](60-post-phase-10-roadmap.md) | Stable 1.1からMiddleware、DB、Diagnostics、Frontend Bridge、Deferred API以降の実装順序 |
| [61-experimental-release-contract.md](61-experimental-release-contract.md) | Public Readiness前の破壊的Minor Release、Version、Documentation、Publication境界 |
| [62-phase-11-delivery-plan.md](62-phase-11-delivery-plan.md) | Release Surface ResetからFramework／Skeleton 1.1.0公開までのTask順序 |
| [63-phase-12-delivery-plan.md](63-phase-12-delivery-plan.md) | Actor ContextからDeferred再認可とPhase CloseoutまでのTask順序 |
| [64-phase-13-delivery-plan.md](64-phase-13-delivery-plan.md) | Named Connection、Build-time AOP、Transaction、After CommitからPhase CloseoutまでのTask順序 |
| [65-operation-diagnostics.md](65-operation-diagnostics.md) | Operation ID相関、内部Diagnostics Aggregate、CLI、Local Viewer、安全な表示境界 |
| [66-phase-14-delivery-plan.md](66-phase-14-delivery-plan.md) | Failure相関からDiagnostics Consumer ExperienceまでのPhase 14 Task順序 |
| [67-operation-frontend-bridge.md](67-operation-frontend-bridge.md) | Operation Object、Frontend Contract Manifest、Typed Fetch、生成／Drift境界 |
| [68-phase-15-delivery-plan.md](68-phase-15-delivery-plan.md) | Frontend ContractからConsumer ExperienceまでのPhase 15 Task順序 |
| [69-deferred-status-and-outcome-api.md](69-deferred-status-and-outcome-api.md) | Public Status Query、Query認可、Retention、HTTP Resource、Generated Polling境界 |
| [70-phase-16-delivery-plan.md](70-phase-16-delivery-plan.md) | Public Query ContractからConsumer ExperienceまでのPhase 16 Task順序 |
| [71-full-stack-reference-application.md](71-full-stack-reference-application.md) | SvelteKit BFF、認証、投稿、Deferred Digestを統合するReference Application |
| [72-phase-17-delivery-plan.md](72-phase-17-delivery-plan.md) | Full-stack Reference ApplicationのTask順序と受入境界 |
| [73-structured-outcome-contract.md](73-structured-outcome-contract.md) | Readonly Nested DTO、Typed List、HTTP／Persistence／Frontendの共通Outcome Shape |
| [74-application-ergonomics.md](74-application-ergonomics.md) | Typed Environment、Bound Client、Console、Session AuthのApplication責任境界 |
| [75-phase-18-delivery-plan.md](75-phase-18-delivery-plan.md) | Application ErgonomicsのTask順序と受入境界 |
| [76-database-seeding.md](76-database-seeding.md) | Framework-owned Seed Command、Seeder／Runner、Build、Generator境界 |
| [77-phase-18-follow-up-delivery-plan.md](77-phase-18-follow-up-delivery-plan.md) | Seeder CoreからConsumer移行までのPhase 18 Follow-up順序 |
| [78-application-runtime-and-bootstrap.md](78-application-runtime-and-bootstrap.md) | Environment File、Classic／Worker SAPI Runtime、Public UUIDv7 Generator境界 |
| [79-phase-18-runtime-follow-up-delivery-plan.md](79-phase-18-runtime-follow-up-delivery-plan.md) | Runtime BootstrapからDependency CloseoutまでのPhase 18 Follow-up順序 |
| [80-reliability-and-delivery.md](80-reliability-and-delivery.md) | Idempotency、Transactional Outbox、Relay／Replayの契約とFailure Matrix |
| [81-phase-19-delivery-plan.md](81-phase-19-delivery-plan.md) | Idempotency CoreからCommunity Board統合までのPhase 19 Task順序 |
| [82-operation-dispatch-and-deferred-authoring.md](82-operation-dispatch-and-deferred-authoring.md) | `#[Deferred]`、Transactional child Operation dispatch、将来のScheduled入口境界 |
| [83-blume-documentation-experience.md](83-blume-documentation-experience.md) | Blume Website、Operation／Journal／Headless Landing、Experimental Notice、利用者向けNavigation |
| [84-documentation-learning-journey.md](84-documentation-learning-journey.md) | 正確性、全Page Navigation、Stable入門動線、Task-oriented Guide、Site UXの段階的改善 |
| [85-documentation-landing-visual-hierarchy.md](85-documentation-landing-visual-hierarchy.md) | Landingの視覚階層、GitHub導線、非対称Feature、Custom Page Link Integrity |
| [86-documentation-brand-expression-and-active-navigation.md](86-documentation-brand-expression-and-active-navigation.md) | Astro参照のLanding Product Expression、Header GitHub導線、Sidebar現在地表示 |
| [87-documentation-second-review-and-feature-parity.md](87-documentation-second-review-and-feature-parity.md) | Landing Feature均等化、第2回Review退行、Website正本整合 |
| [88-executable-stable-onboarding.md](88-executable-stable-onboarding.md) | Stable 1.1完走導線、main Preview Quickstart、Authentication手順 |
| [89-blume-mermaid-rendering.md](89-blume-mermaid-rendering.md) | Mermaid PageのMDX生成、Blume native描画、Artifact／Browser Contract |
| [90-documentation-third-review-accuracy.md](90-documentation-third-review-accuracy.md) | 第3回ReviewのLanding／Banner、P1正確性、Internal Link H1 Guard |
| [91-mermaid-diagram-legibility.md](91-mermaid-diagram-legibility.md) | Mermaid DiagramのDesktop可読幅、Mobile局所横Scroll、Browser寸法Contract |
| [92-documentation-review-agent.md](92-documentation-review-agent.md) | Read-only Documentation Reviewer、Evidence Hierarchy、Browser Review、Finding Format |
| [93-implicit-inline-ephemeral-outcome.md](93-implicit-inline-ephemeral-outcome.md) | Ephemeral Outcomeの暗黙Inline、Security Guard、ExecuteWith互換境界 |
| [94-journal-documentation.md](94-journal-documentation.md) | Journal独立Guide、Observed JSONL、Security、OpenTelemetry将来構想 |
| [95-task-oriented-operations-documentation.md](95-task-oriented-operations-documentation.md) | Testing、Deployment、ConsoleCommand、Outbox、BlackOps CLIの実行可能な利用者Journey |
| [96-documentation-site-ux.md](96-documentation-site-ux.md) | Blume Callout、Code Copy、Previous／Next、Edit Link、日本語Font、Responsive Article UX |
| [97-documentation-editorial-style.md](97-documentation-editorial-style.md) | 全Public Pageの表記Guideline、一般語の日本語化、Page種別Reader Contract、文章編集Pass |
| [98-scheduled-application-operation.md](98-scheduled-application-operation.md) | ScheduledBy、Schedule Context、Cron／Timezone、Misfire／Overlap、Occurrence、Actor、BlackOps CLI |
| [99-tenant-isolation-and-protected-operation-data.md](99-tenant-isolation-and-protected-operation-data.md) | TenantRef、Tenant伝播、Default-deny Data Read、Encrypted Envelope、Rotation、Breaking Upgrade境界 |
| [100-structured-logging-and-opentelemetry.md](100-structured-logging-and-opentelemetry.md) | Structured Record v1、W3C Trace、OpenTelemetry Trace／Metric、Health／Readiness、Local Collector／Local Grafana LGTM境界 |
| [101-framework-owned-transaction-proxy.md](101-framework-owned-transaction-proxy.md) | Framework-owned Transactional／AfterCommit Proxy contract |
| [102-phase-21-delivery-plan.md](102-phase-21-delivery-plan.md) | Phase 21 proxy replacement delivery order and removal gate |
| [103-stable-1-2-release-plan.md](103-stable-1-2-release-plan.md) | Stable 1.1 preservation and main 1.2.0 Release Candidate baseline |
| [104-documentation-release-lifecycle-and-information-architecture.md](104-documentation-release-lifecycle-and-information-architecture.md) | Release Authority、全公開Source／Artifact guard、目的別Information Architecture、Task／Review契約 |
| [110-documentation-diagrams-and-presentation.md](110-documentation-diagrams-and-presentation.md) | Archify diagrams, Landing Hero Reel and scoped motion controls under D167 |

### Phase 23 Proposal lane（未Release）

| 文書 | 内容 | Status |
| --- | --- | --- |
| [105-blackops-1-3-capability-contract.md](105-blackops-1-3-capability-contract.md) | BlackOps `1.3` CLI、runtime、Audit TrailのCapability Contract | Proposal — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |
| [106-phase-23-delivery-plan.md](106-phase-23-delivery-plan.md) | Phase 23のDAG、Migration、独立Gate、Release境界 | Proposal — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |
| [107-operation-status-audit-adapter.md](107-operation-status-audit-adapter.md) | Per-read tenant-bound Status Audit adapter | Authority Green; D2S1 Accepted; Product progress in Report/STATE; Stable `1.2` current / `1.3` unreleased |
| [108-tenant-scoped-status-source.md](108-tenant-scoped-status-source.md) | Explicit tenant scope, SQL predicates, Status snapshot and before-decode boundary | D2S1 Authority Green; Product progress in Report/STATE; Stable `1.2` current / `1.3` unreleased |
| [111-operation-data-read-audit-adapters.md](111-operation-data-read-audit-adapters.md) | Journal/Outcome read Audit adapters, tenant guard and inherited exception traces | Authority Green; Product progress in D2J Report; `1.3` unreleased |
| [112-operation-diagnostics-audit-adapter.md](112-operation-diagnostics-audit-adapter.md) | Global Diagnostics read Audit, pre-read authorization and safe exception reconstruction | Authority Green; Product progress in D2X Report; `1.3` unreleased |
| [113-http-authentication-audit-adapter.md](113-http-authentication-audit-adapter.md) | Explicit HTTP authentication result Audit, durable response gate and credential privacy | Accepted locally; implementation and recovery evidence in D3 Report; `1.3` unreleased |
| [114-authorization-audit-evaluation-foundation.md](114-authorization-audit-evaluation-foundation.md) | Explicit policy binding and no-policy/allow/deny evaluation | D4A/D4B Accepted (local); canonical activation pending; `1.3` unreleased |
| [115-authorization-audit-delivery.md](115-authorization-audit-delivery.md) | Authorization event projection, native delivery and mutation transaction boundaries | D4B Accepted (local); canonical activation pending; `1.3` unreleased |
| [116-retention-hold-audit-adapter.md](116-retention-hold-audit-adapter.md) | Hold mutation and durable audit delivery in one native transaction | D5H Accepted (local); integration pending; `1.3` unreleased |
| [117-retention-purge-audit-adapter.md](117-retention-purge-audit-adapter.md) | Per-effect purge audit within unchanged native category roots | D5P Accepted (local); ordinary composition pending; `1.3` unreleased |
| [118-storage-rotation-transaction-outcomes.md](118-storage-rotation-transaction-outcomes.md) | Native rotation commit ambiguity and single finalization | D5R1/D5R2 Accepted (local); `1.3` unreleased |
| [119-storage-rotation-audit-adapter.md](119-storage-rotation-audit-adapter.md) | Typed rotation phase hook, strict subjects and native-root durable events | D5R2 Accepted (local); `1.3` unreleased |
| [120-observer-replay-transaction-outcomes.md](120-observer-replay-transaction-outcomes.md) | Native replay root admission, commit ambiguity and failure ordering | D5O1 Accepted (local); `1.3` unreleased |
| [121-observer-replay-audit-adapter.md](121-observer-replay-audit-adapter.md) | Selected Journal lineage and native-root Replay phase events | Bounded implementation Accepted (local); `1.3` unreleased |
| [122-audit-signed-artifact-primitives.md](122-audit-signed-artifact-primitives.md) | Canonical checkpoint/receipt, Ed25519 and supplied-trust verification | Bounded implementation Accepted (local); `1.3` unreleased |
| [123-generic-outbox-audit-adapter.md](123-generic-outbox-audit-adapter.md) | Native generic Outbox events and owned transaction cleanup | Accepted locally; normal activation pending; `1.3` unreleased |
| [124-application-audit-catalog-registry.md](124-application-audit-catalog-registry.md) | Public declaration/provider and trusted combined Catalog registry | Accepted locally; normal activation pending; `1.3` unreleased |
| [125-audit-checkpoint-persistence.md](125-audit-checkpoint-persistence.md) | Complete closed-segment proof and immutable checkpoint persistence | Accepted locally and committed; signing/seal remain later F; `1.3` unreleased |
| [126-audit-anchor-intent-state.md](126-audit-anchor-intent-state.md) | Pure complete AnchorIntent state and explicit authenticated-restore handoff | Pure F3 implementation Accepted and source delivered; `1.3` unreleased |
| [127-audit-anchor-durable-external.md](127-audit-anchor-durable-external.md) | Durable Anchor reservation, exact-chain recovery and canonical frame bound | Bounded choices adopted; complete F4 issuance pending; `1.3` unreleased |
| [128-audit-anchor-provider-protocol.md](128-audit-anchor-provider-protocol.md) | Exact external Anchor request/result, privileges and commit/flush protocol | Provider contract adopted; native implementation pending; `1.3` unreleased |

## 決定の参照

| Decision | 内容 | Status |
| --- | --- | --- |
| [D001](../decisions/001-operation-definition.md) | Operationの定義 | Decided |
| [D002](../decisions/002-operation-lifecycle.md) | Operationのライフサイクル | Decided |
| [D003](../decisions/003-execution-strategy.md) | Execution Strategyと再現可能なJournal | Decided |
| [D004](../decisions/004-journal-schema-and-security.md) | Journal Schemaとセキュリティ | Decided |
| [D005](../decisions/005-operation-value-and-validation.md) | OperationValueとバリデーション | Decided |
| [D006](../decisions/006-handler-and-outcome.md) | HandlerとOutcome | Decided |
| [D007](../decisions/007-supervision-policy.md) | Supervision Policy | Decided |
| [D008](../decisions/008-http-routing-and-binding.md) | HTTP RoutingとBinding | Decided |
| [D009](../decisions/009-execution-context.md) | ExecutionContext | Decided |
| [D010](../decisions/010-authentication-and-middleware.md) | AuthenticationとMiddleware | Partially Superseded by D095 |
| [D011](../decisions/011-project-structure.md) | Project Structure | Decided |
| [D012](../decisions/012-operation-registry-and-manifest.md) | Operation RegistryとManifest | Decided |
| [D013](../decisions/013-runtime-and-dependency-injection.md) | PHP RuntimeとDependency Injection | Decided |
| [D014](../decisions/014-logging-and-traceability.md) | LoggingとTraceability | Decided |
| [D015](../decisions/015-log-delivery-and-retention.md) | Log DeliveryとRetention | Decided |
| [D016](../decisions/016-durable-journal-transaction.md) | Durable JournalとTransaction | Partially Superseded by D096 |
| [D017](../decisions/017-mvp-scope.md) | MVP Scope | Decided |
| [D018](../decisions/018-mvp-technical-stack.md) | MVP Technical Stack | Decided |
| [D019](../decisions/019-framework-name.md) | Framework Name | Decided |
| [D020](../decisions/020-package-architecture.md) | Package Architecture | Decided |
| [D021](../decisions/021-source-layout.md) | Source Layout | Decided |
| [D022](../decisions/022-namespace-dependencies.md) | Namespace Dependencies | Partially Superseded by D142 |
| [D023](../decisions/023-core-api-shape.md) | Core API Shape | Decided |
| [D024](../decisions/024-operation-envelope-api.md) | Operation Envelope API | Decided |
| [D025](../decisions/025-execution-context-api.md) | ExecutionContext API | Decided |
| [D026](../decisions/026-identifier-value-objects.md) | Identifier Value Objects | Decided |
| [D027](../decisions/027-clock-and-time.md) | Clock and Time | Decided |
| [D028](../decisions/028-journal-record-schema.md) | Journal Record Schema | Decided |
| [D029](../decisions/029-journal-record-api.md) | Journal Record PHP API | Decided |
| [D030](../decisions/030-lifecycle-event-data.md) | Lifecycle Event Data | Decided |
| [D031](../decisions/031-sensitive-projection.md) | Sensitive Projection | Decided |
| [D032](../decisions/032-journal-ports.md) | Journal Ports | Decided |
| [D033](../decisions/033-journal-sequence-allocation.md) | Journal Sequence Allocation | Decided |
| [D034](../decisions/034-mvp-lifecycle-events.md) | MVP Lifecycle Events | Decided |
| [D035](../decisions/035-handler-result-contract.md) | Handler Result Contract | Decided |
| [D036](../decisions/036-lifecycle-state-machine.md) | Lifecycle State Machine | Decided |
| [D037](../decisions/037-deferred-claim-and-attempt.md) | Deferred Claim and Attempt | Decided |
| [D038](../decisions/038-worker-crash-recovery.md) | Worker Crash Recovery | Decided |
| [D039](../decisions/039-execution-transport-contract.md) | Execution Transport Contract | Decided |
| [D040](../decisions/040-mvp-database-transport.md) | MVP Database Transport | Decided |
| [D041](../decisions/041-postgresql-transport-schema.md) | PostgreSQL Transport Schema | Decided |
| [D042](../decisions/042-postgresql-transaction-boundaries.md) | PostgreSQL Transaction Boundaries | Decided |
| [D043](../decisions/043-postgresql-table-layout.md) | PostgreSQL Table Layout | Decided |
| [D044](../decisions/044-data-retention-and-deletion.md) | Data Retention and Deletion | Decided |
| [D045](../decisions/045-retention-mvp-scope.md) | Retention MVP Scope | Decided |
| [D046](../decisions/046-mvp-delivery-plan.md) | MVP Delivery Plan | Decided |
| [D047](../decisions/047-frontend-integration.md) | Frontend Integration | Decided |
| [D048](../decisions/048-implementation-orchestration.md) | Implementation Orchestration | Decided |
| [D049](../decisions/049-identifier-public-api.md) | Identifier Public API | Decided |
| [D050](../decisions/050-execution-context-public-api.md) | ExecutionContext Public API | Decided |
| [D051](../decisions/051-operation-envelope-and-strategy-api.md) | Operation Envelope and Strategy API | Decided |
| [D052](../decisions/052-handler-result-public-api.md) | Handler Result Public API | Decided |
| [D053](../decisions/053-operation-metadata-api.md) | Operation Metadata API | Decided |
| [D054](../decisions/054-runtime-operation-registry-api.md) | Runtime Operation Registry API | Decided |
| [D055](../decisions/055-inline-dispatcher-api.md) | Inline Dispatcher API | Decided |
| [D056](../decisions/056-journal-record-public-api.md) | Journal Record Public API | Decided |
| [D057](../decisions/057-database-access-and-migration-library.md) | Database Access and Migration Library | Decided |
| [D058](../decisions/058-frankenphp-runtime-premise.md) | FrankenPHP Runtime Premise | Decided |
| [D059](../decisions/059-worker-heartbeat-runtime.md) | Worker Heartbeat Runtime | Decided |
| [D060](../decisions/060-typed-outcome-store-contract.md) | Typed Outcome Store Contract | Decided |
| [D061](../decisions/061-retention-operation-reference.md) | Retention Operation Reference | Decided |
| [D062](../decisions/062-retention-audit-log-delivery.md) | Retention Audit Log Delivery | Decided |
| [D063](../decisions/063-developer-experience-roadmap.md) | Developer Experience Roadmap | Decided |
| [D064](../decisions/064-installed-application-layout-and-bootstrap.md) | Installed Application Layout and Bootstrap | Decided |
| [D065](../decisions/065-composer-skeleton-publication.md) | Composer Skeleton Publication | Decided |
| [D066](../decisions/066-development-metadata-layout.md) | Development Metadata Layout | Decided |
| [D067](../decisions/067-legacy-setup-helper-removal.md) | Legacy Setup Helper Removal | Decided |
| [D068](../decisions/068-public-console-kernel-composition.md) | Public Console Kernel Composition | Decided |
| [D069](../decisions/069-skeleton-http-entrypoint-adapters.md) | Skeleton HTTP Entrypoint Adapters | Decided |
| [D070](../decisions/070-quickstart-journal-observer.md) | Quickstart Journal Observer | Decided |
| [D071](../decisions/071-operation-authoring-and-discovery.md) | Operation Authoring and Discovery | Decided |
| [D072](../decisions/072-skeleton-empty-directory-policy.md) | Skeleton Empty Directory Policy | Decided |
| [D073](../decisions/073-skeleton-distribution-publication-boundary.md) | Skeleton Distribution Publication Boundary | Superseded by D076 |
| [D074](../decisions/074-typed-self-handled-operation-signature.md) | Typed Self-handled Operation Signature | Decided |
| [D075](../decisions/075-native-outcome-and-rejection-exception.md) | Native Outcome and Rejection Exception | Decided |
| [D076](../decisions/076-framework-and-skeleton-repository-naming.md) | Framework and Skeleton Repository Naming | Decided |
| [D077](../decisions/077-implementation-worker-model-upgrade.md) | Implementation Worker Model Upgrade | Superseded by D091 |
| [D078](../decisions/078-initial-stable-release-version.md) | Initial Stable Release Version | Partially Superseded by D094 |
| [D079](../decisions/079-immutable-release-publication-recovery.md) | Immutable Release Publication Recovery | Decided |
| [D080](../decisions/080-project-generator-command-contract.md) | Project Generator Command Contract | Decided |
| [D081](../decisions/081-documentation-website-delivery-contract.md) | Documentation Website Delivery Contract | Partially Superseded by D093 |
| [D082](../decisions/082-documentation-reader-experience.md) | Documentation Reader Experience | Decided |
| [D083](../decisions/083-project-root-blackops-entrypoint.md) | Project Root BlackOps Entrypoint | Decided |
| [D084](../decisions/084-documentation-reader-journey-corrections.md) | Documentation Reader Journey Corrections | Decided |
| [D085](../decisions/085-http-configuration-snapshot-lifecycle.md) | HTTP Configuration Snapshot Lifecycle | Decided |
| [D086](../decisions/086-operation-value-validation-runtime.md) | OperationValue Validation Runtime | Decided |
| [D087](../decisions/087-pre-binding-rejection-journal.md) | Pre-binding Rejection Journal | Decided |
| [D088](../decisions/088-validation-backend.md) | Validation Backend | Decided |
| [D089](../decisions/089-validation-rejection-sensitive-journal.md) | Validation Rejection Sensitive Journal | Decided |
| [D090](../decisions/090-documentation-information-architecture.md) | Documentation Information Architecture | Decided |
| [D091](../decisions/091-orchestrator-worker-model-configuration.md) | Orchestrator and Worker Model Configuration | Superseded for Profile selection by D144 |
| [D092](../decisions/092-project-cli-command-names.md) | BlackOps CLI Command Names | Partially Superseded by D094 |
| [D093](../decisions/093-post-phase-10-roadmap.md) | Post Phase 10 Roadmap | Decided |
| [D094](../decisions/094-stable-1-1-release-contract.md) | Stable 1.1 Release Contract | Decided |
| [D095](../decisions/095-phase-12-middleware-and-authorization-runtime.md) | Phase 12 Middleware and Authorization Runtime | Decided |
| [D096](../decisions/096-phase-13-database-and-transaction-runtime.md) | Phase 13 Database and Transaction Runtime | Decided |
| [D097](../decisions/097-phase-14-operation-diagnostics.md) | Phase 14 Operation Diagnostics | Decided |
| [D098](../decisions/098-deferred-acceptance-failure-lifecycle.md) | Deferred Acceptance Failure Lifecycle | Decided |
| [D099](../decisions/099-production-logging-configuration.md) | Production Logging Configuration | Decided |
| [D100](../decisions/100-phase-15-operation-frontend-bridge.md) | Phase 15 Operation Frontend Bridge | Decided |
| [D101](../decisions/101-http-scalar-binding-coercion.md) | HTTP Scalar Binding Coercion | Decided |
| [D102](../decisions/102-phase-16-deferred-status-and-outcome-api.md) | Phase 16 Deferred Status and Outcome API | Decided |
| [D103](../decisions/103-full-stack-reference-application.md) | Full-stack Reference Application | Decided |
| [D104](../decisions/104-structured-outcome-contract.md) | Structured Outcome Contract | Decided |
| [D105](../decisions/105-community-board-deletion-policy.md) | Community Board Deletion Policy | Decided |
| [D106](../decisions/106-community-board-domain-layering.md) | Community Board Domain Layering | Decided |
| [D107](../decisions/107-community-board-deferred-digest.md) | Community Board Deferred Digest | Decided |
| [D108](../decisions/108-ray-aop-upstream-and-phase-order.md) | Ray.Aop Upstream and Phase Order | Decided |
| [D109](../decisions/109-phase-18-idempotency-and-outbox.md) | Phase 19 Idempotency and Outbox | Decided |
| [D110](../decisions/110-application-ergonomics.md) | Application Ergonomics | Decided |
| [D111](../decisions/111-session-auth-package-contract.md) | Session Authentication Contract | Decided |
| [D112](../decisions/112-authentication-credential-response-boundary.md) | Authentication Credential Response Boundary | Partially Superseded by D126 |
| [D113](../decisions/113-database-seeder-contract.md) | Database Seeder Contract | Decided |
| [D114](../decisions/114-application-runtime-and-bootstrap-dependency-boundary.md) | Application Runtime and Bootstrap Dependency Boundary | Decided |
| [D115](../decisions/115-deferred-authoring-and-operation-dispatch.md) | Deferred Authoring and Operation Dispatch | Decided |
| [D116](../decisions/116-blume-documentation-site.md) | Blume Documentation Site | Decided |
| [D117](../decisions/117-documentation-learning-journey.md) | Documentation Learning Journey | Decided |
| [D118](../decisions/118-documentation-landing-visual-hierarchy.md) | Documentation Landing Visual Hierarchy | Decided |
| [D119](../decisions/119-documentation-brand-expression-and-active-navigation.md) | Documentation Brand Expression and Active Navigation | Decided |
| [D120](../decisions/120-documentation-second-review-and-feature-parity.md) | Documentation Second Review and Feature Parity | Decided |
| [D121](../decisions/121-executable-stable-onboarding.md) | Executable Stable Onboarding | Decided |
| [D122](../decisions/122-blume-mermaid-rendering.md) | Blume Mermaid Rendering | Decided |
| [D123](../decisions/123-documentation-third-review-accuracy.md) | Documentation Third Review Accuracy | Decided |
| [D124](../decisions/124-mermaid-diagram-legibility.md) | Mermaid Diagram Legibility | Decided |
| [D125](../decisions/125-documentation-review-agent.md) | Documentation Review Agent | Superseded for Profile selection by D144 |
| [D126](../decisions/126-implicit-inline-ephemeral-outcome.md) | Implicit Inline Ephemeral Outcome | Decided |
| [D127](../decisions/127-journal-documentation.md) | Journal Documentation | Decided |
| [D128](../decisions/128-observed-journal-wire-shape.md) | Observed Journal Wire Shape | Decided |
| [D129](../decisions/129-documentation-website-publication.md) | Documentation Website Publication | Decided |
| [D130](../decisions/130-blume-production-search-verification.md) | Blume Production Search Verification | Decided |
| [D131](../decisions/131-task-oriented-operations-documentation.md) | Task-oriented Operations Documentation | Decided |
| [D132](../decisions/132-documentation-site-ux.md) | Documentation Site UX | Decided |
| [D133](../decisions/133-documentation-editorial-style.md) | Documentation Editorial Style | Decided |
| [D134](../decisions/134-scheduled-application-operation.md) | Scheduled Application Operation | Decided |
| [D135](../decisions/135-tenant-isolation-and-protected-operation-data.md) | Tenant Isolation and Protected Operation Data | Decided |
| [D136](../decisions/136-structured-logging-and-opentelemetry.md) | Structured Logging and OpenTelemetry | Decided |
| [D137](../decisions/137-framework-owned-transaction-proxy.md) | Framework-owned Transaction Proxy Contract | Decided |
| [D138](../decisions/138-local-grafana-lgtm-development-backend.md) | Local Grafana LGTM Development Backend | Decided |
| [D139](../decisions/139-stable-1-2-version-baseline.md) | Stable 1.2 Version Baseline | Decided |
| [D140](../decisions/140-release-quality-tooling-baseline.md) | Release Quality Tooling Baseline | Decided |
| [D141](../decisions/141-release-architecture-and-export-boundary.md) | Release Architecture and Export Boundary | Decided |
| [D142](../decisions/142-public-facade-and-internal-implementation-cycles.md) | Public Facade and Internal Implementation Cycles | Decided |
| [D143](../decisions/143-documentation-release-truth-and-information-architecture.md) | Documentation Release Truth and Information Architecture | Decided |
| [D144](../decisions/144-agent-reasoning-profile-upgrade.md) | Agent Reasoning Profile Upgrade | Decided |
| [D167](../decisions/167-landing-hero-reel-and-motion-controls.md) | Landing Hero Reel, first-viewport hierarchy and two-player motion exception | Decided; P23-018 Accepted and delivered |

### Phase 23 Proposal decisions（未Release）

| Decision | 内容 | Status |
| --- | --- | --- |
| [D145](../decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md) | BlackOps `1.3` command、FrankenPHP runtime、Audit Trail boundary | Proposed — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |
| [D146](../decisions/146-projection-build-set-php-filesystem-boundary.md) | ProjectionBuildSet PHP filesystem／locator boundary | Proposed — P23-002A1/A2/B/C Accepted; complete projection activation implemented for unreleased 1.3 |
| [D147](../decisions/147-audit-catalog-and-safe-projection-boundary.md) | Audit Catalog、keyed opaque ID、record stages、observed safe-projection boundary | Accepted P23-003A1／A2 and parent P23-003A; P23-003B1／B2 and parent P23-003B Accepted; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D148](../decisions/148-audit-canonical-record-and-protected-store-boundary.md) | Audit canonical record、hash／chain、Audit専用BOPD protection、B1／B2 boundary | P23-003B1／B2 and parent P23-003B Accepted; B2 candidate commit／same-SHA package export Green; Stable `1.2` unchanged / BlackOps `1.3` unreleased |
| [D149](../decisions/149-audit-postgresql-canonical-store-boundary.md) | Audit PostgreSQL canonical append、segment、tenant-aware read、migration boundary | P23-003B2 Accepted; Complete Final Gate and fresh DB／General Sol closure Green; candidate `968afe2b...` package export Green; Stable `1.2` unchanged / BlackOps `1.3` unreleased |
| [D150](../decisions/150-audit-outbox-state-and-delivery-split.md) | P23-003C serial C1／C2／C3／C4 split and pure Audit Outbox state boundary | Accepted — P23-003C1 through C4 and parent C; final `d5ab347...` same-SHA package export Green; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D151](../decisions/151-audit-postgresql-outbox-persistence-boundary.md) | Audit PostgreSQL Outbox row、idempotent append、FIFO／CAS、transaction boundary | Accepted P23-003C2; candidate `a92bef70...` and same-SHA package export Green; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D152](../decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md) | Catalog-fixed delivery、same-root transaction、protected-read gate、Application Audit key composition | Accepted P23-003C3; candidate `b7b352a8...` and same-SHA package export Green; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D153](../decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md) | Bounded Audit Outbox relay／recovery、three-root crash semantics、failure／FIFO／nonactivation boundary | Accepted P23-003C4／parent C; final `d5ab347...` same-SHA package export Green; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D154](../decisions/154-framework-operational-audit-catalog-and-adapter-split.md) | Exact14 Framework operational Catalog／trusted lookup lineage and bounded P23-003D adapter DAG | D1/D2/D2S1 Accepted; parent D incomplete; D-control waits for Accepted F; Stable `1.2` current / `1.3` unreleased |
| [D155](../decisions/155-operation-read-audit-session-foundation.md) | Operation-read 16-byte nonce／opaque target／fixed draft／one-shot session／fail-closed terminal and Diagnostics authorization foundation | D2 Accepted at `0cd699e...`; parent D incomplete; Stable `1.2` current / `1.3` unreleased |
| [D156](../decisions/156-operation-status-audit-adapter.md) | Status Audit adapter, trusted read identity and exception trace protection | Decided; Product progress in Report/STATE; Stable `1.2` current / `1.3` unreleased |
| [D157](../decisions/157-tenant-scoped-status-source.md) | Tenant-scoped Status-source prerequisite and legacy-preserving split | D2S1 Authority Green; Product progress in Report/STATE; Stable `1.2` current / `1.3` unreleased |
| [D163](../decisions/163-authorization-audit-evaluation-foundation.md) | D4A typed evaluation and D4B delivery split | D4A/D4B Accepted (local); canonical activation pending; `1.3` unreleased |
| [D164](../decisions/164-authorization-audit-delivery.md) | D4B event delivery and native transaction boundaries | Decided; D4B Accepted (local); `1.3` unreleased |
| [D165](../decisions/165-retention-hold-audit-adapter.md) | D5H hold-only adapter and explicit D4B identity-helper dependency | Decided; D5H Accepted (local); `1.3` unreleased |
| [D166](../decisions/166-retention-purge-audit-adapter.md) | D5P native-root purge audit and persisted payload tenant | Decided; D5P Accepted (local); `1.3` unreleased |
| [D168](../decisions/168-storage-rotation-transaction-outcomes.md) | D5R1 native transaction outcomes before the rotation event adapter | Decided; D5R1 Accepted (local); `1.3` unreleased |
| [D169](../decisions/169-storage-rotation-audit-adapter.md) | D5R2 rotation phase delivery and bounded interruption recovery | Decided; D5R2 Accepted (local); `1.3` unreleased |
| [D170](../decisions/170-observer-replay-transaction-outcomes.md) | D5O1 native replay transaction outcomes before phase delivery | Decided; D5O1 Accepted (local); `1.3` unreleased |
| [D171](../decisions/171-observer-replay-audit-adapter.md) | Four replay events, trusted execution and selected-source identity | Decided; authority Green; `1.3` unreleased |
| [D172](../decisions/172-audit-signed-artifact-primitives.md) | Pure signed artifact scope and joint O2/F1 verification | Decided; authority Green; `1.3` unreleased |
| [D173](../decisions/173-generic-outbox-audit-adapter.md) | Returned-row Outbox authority and owned cleanup | Decided; bounded implementation Accepted locally; `1.3` unreleased |
| [D174](../decisions/174-application-audit-catalog-registry.md) | Public metadata capture, reference rejection and explicit joint qualification | Decided; bounded implementation Accepted locally; `1.3` unreleased |
| [D175](../decisions/175-audit-checkpoint-persistence.md) | Persistence-only checkpoint authority, crash recovery and migration coverage | Decided; bounded implementation Accepted and committed; `1.3` unreleased |
| [D176](../decisions/176-audit-anchor-intent-state.md) | Exact AnchorIntent wire with prepared binding and original claim ownership | Decided; pure F3 implementation Accepted; `1.3` unreleased |
| [D177](../decisions/177-audit-anchor-reservation-and-frame-bound.md) | One checkpoint reservation, same-chain continuation and 8192-byte canonical frame | Decided; complete F4 implementation contract pending; `1.3` unreleased |
| [D178](../decisions/178-audit-anchor-provider-protocol.md) | External Anchor provider protocol and same-session durability boundary | Decided; Product/native qualification pending |
| [D160](../decisions/160-operation-data-read-audit-adapters.md) | Journal/Outcome Audit adapters, fixed read execution and inherited trace protection | Decided; Product progress in D2J Report; `1.3` unreleased |
| [D161](../decisions/161-operation-diagnostics-audit-adapter.md) | Global Diagnostics Audit and fresh same-code exception release | Decided; Product progress in D2X Report; `1.3` unreleased |
| [D162](../decisions/162-http-authentication-audit-adapter.md) | HTTP credential-check result, Session mutation boundary and durable response gate | Decided; Product progress in D3 Report; `1.3` unreleased |

## 開発運用の更新

- [仕様109](109-ai-development-workflow.md): 検証選択、資料管理、STATEローテーション、チーム運用。
- [D158](../decisions/158-ai-workflow-efficiency-and-state-rotation.md): 今回の運用改善の判断。製品Capabilityの追加には含めない。
