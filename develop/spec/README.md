# Framework Specification

このディレクトリは、本フレームワークで合意済みの仕様をまとめた正本である。

## 文書の規則

- 確定した仕様だけを記載する
- 未決事項は [TODO.md](../TODO.md) で管理する
- 判断の経緯は [decisions/](../decisions/) に残す
- 仕様変更前に新しい設計対話を行う
- 置き換えられたDecisionは削除せず `Superseded` として残す

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
| [11-durable-journal-and-transactions.md](11-durable-journal-and-transactions.md) | Durable保証、Transaction Middleware、Outbox |
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
| [41-developer-experience-roadmap.md](41-developer-experience-roadmap.md) | Installed Example、Composer Skeleton、Project CLI、Documentation Website |
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
| [D010](../decisions/010-authentication-and-middleware.md) | AuthenticationとMiddleware | Decided |
| [D011](../decisions/011-project-structure.md) | Project Structure | Decided |
| [D012](../decisions/012-operation-registry-and-manifest.md) | Operation RegistryとManifest | Decided |
| [D013](../decisions/013-runtime-and-dependency-injection.md) | PHP RuntimeとDependency Injection | Decided |
| [D014](../decisions/014-logging-and-traceability.md) | LoggingとTraceability | Decided |
| [D015](../decisions/015-log-delivery-and-retention.md) | Log DeliveryとRetention | Decided |
| [D016](../decisions/016-durable-journal-transaction.md) | Durable JournalとTransaction | Decided |
| [D017](../decisions/017-mvp-scope.md) | MVP Scope | Decided |
| [D018](../decisions/018-mvp-technical-stack.md) | MVP Technical Stack | Decided |
| [D019](../decisions/019-framework-name.md) | Framework Name | Decided |
| [D020](../decisions/020-package-architecture.md) | Package Architecture | Decided |
| [D021](../decisions/021-source-layout.md) | Source Layout | Decided |
| [D022](../decisions/022-namespace-dependencies.md) | Namespace Dependencies | Decided |
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
| [D077](../decisions/077-implementation-worker-model-upgrade.md) | Implementation Worker Model Upgrade | Decided |
| [D078](../decisions/078-initial-stable-release-version.md) | Initial Stable Release Version | Decided |
| [D079](../decisions/079-immutable-release-publication-recovery.md) | Immutable Release Publication Recovery | Decided |
| [D080](../decisions/080-project-generator-command-contract.md) | Project Generator Command Contract | Decided |
| [D081](../decisions/081-documentation-website-delivery-contract.md) | Documentation Website Delivery Contract | Decided |
| [D082](../decisions/082-documentation-reader-experience.md) | Documentation Reader Experience | Decided |
