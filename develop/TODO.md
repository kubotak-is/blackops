# TODO

この文書は設計課題、未決事項、作業の優先順位を扱う。実行中の詳細は[STATE](STATE.md)と担当Reportを参照する。

## 運用ルール

- `[ ]` 未着手、`[x]` 完了、`[~]` 検討中。
- 判断理由はDecision、確定契約はspec、検証結果はReportに残す。
- 着手/完了時にTaskへの導線を整え、中間checkpointの全文を追記しない。
- 用語を変えるときは対応するCodeと文書を更新する。

## 現在の作業への導線

- Audit Checkpoint persistence: [F2 Task](orchestration/tasks/P23-003F2-audit-checkpoint-persistence.md)、[Report](orchestration/reports/P23-003F2-audit-checkpoint-persistence.md)。実装をAcceptedとし、`9768edf4`へ分離コミット。全3,915件、独立Review、コミット済みExportは合格。管理checkpoint／pushを続ける。署名・anchor・seal・完全検証と1.3全体の公開工程は未完了。

- 1.3 Release: [P23-006](orchestration/tasks/P23-006-blackops-1-3-release.md)。統合ブランチでは O2／F1 の6コミットに続き、Outbox `a179781` と Catalog `3baf147` を分離コミット済み。[006E](orchestration/tasks/P23-006E-outbox-catalog-integration.md)で必須Exportは合格し、最終ReviewとローカルAcceptanceも完了。checkpoint `f01e16b` の通常pushとremote SHA一致も確認済み。1.3全体の残り工程はSTATEと親Reportを参照。

- Status Audit adapter: [D2S Task](orchestration/tasks/P23-003D2S-operation-status-audit-adapter.md)、[Report](orchestration/reports/P23-003D2S-operation-status-audit-adapter.md)。実装と関連レビュー・全件検証が通過。完了条件と配布物検証はReportを正本とする。HTTPへの組込みは親D統合で行う。
- 1.2.1開発ブランチ同期: [P23-013 Task](orchestration/tasks/P23-013-sync-stable-1-2-1.md)、[Report](orchestration/reports/P23-013-sync-stable-1-2-1.md)。Accepted。既存作業保存、main取込、公開仕様と開発中実装の検証分離、clean SHAのpackage exportまで完了。

- 1.2.1 Release: [P23-012 Task](orchestration/tasks/P23-012-release-1-2-1.md)、[Report](orchestration/reports/P23-012-release-1-2-1.md)。2026-09-10 Accepted。Framework／Skeleton公開、Remote Install／Quickstart、同一main SHAのCI・本番サイト1.2.1配信、全公開本文検証、Skeleton README同期まで完了。1.3作業は保持。

- 5秒の処理フロー・残り時間表示・曇りガラス: [P23-011 Task](orchestration/tasks/P23-011-walkthrough-progress-control.md)、[Report](orchestration/reports/P23-011-walkthrough-progress-control.md)。ローカル改修Accepted。5秒・進捗リング・停止/再開・曇りガラスをPC/スマートフォンと両テーマで確認。
- 監査ログの予定とSensitive属性の使い分け: [P23-010 Task](orchestration/tasks/P23-010-audit-and-sensitive-documentation.md)、[Report](orchestration/reports/P23-010-audit-and-sensitive-documentation.md)。ローカル改修Accepted。未公開1.3の計画とSensitive比較を追加し、本文・検索・LLM・リンクを確認。
- Frameworkパッチ版: [P23-009 Task](orchestration/tasks/P23-009-framework-patch-version.md)、[Report](orchestration/reports/P23-009-framework-patch-version.md)。`/tmp/blackops-framework-1.2.1`の1.2.1準備Accepted。差分を[patch](orchestration/artifacts/P23-009-framework-1.2.1.patch)へ保存。後続P23-012でRelease Gate・公開を完了。

- TOPの構成と読み進め操作: [P23-008A Task](orchestration/tasks/P23-008A-walkthrough-reading-controls.md)、[Report](orchestration/reports/P23-008A-walkthrough-reading-controls.md)。ローカル改修Accepted。Inline初期表示、経路タブ、Journalタグ、読み時間・詳細リンク、Tutorial導線、背景とタイトルの点灯演出を確認。15関連Test、PC/スマートフォン・両テーマの操作と表示がPASS。後続P23-012で1.2.1として公開済み。

- ドキュメントサイト全体のリテイク: [仕様110](spec/110-documentation-diagrams-and-presentation.md)、[P23-008 Task](orchestration/tasks/P23-008-archify-documentation-diagrams.md)、[Report](orchestration/reports/P23-008-archify-documentation-diagrams.md)。ローカル改修Accepted。全10図とdesktop variantをArchifyで統一し、日本語フォント、単一のTOPループ、図と説明の上下配置、クリック中の番号、スクロール演出を確認。127関連Testと36表示条件がPASS。後続P23-012で1.2.1として公開済み。低速回線を模したローカル初期表示の計測限界と残課題はReportへ記録。

- AIワークフロー改善: [P23-007 Task](orchestration/tasks/P23-007-ai-workflow-efficiency.md)、[Report](orchestration/reports/P23-007-ai-workflow-efficiency.md)。STATEローテーション、検証の重複削減、資料/チーム運用を扱う。
- Tenant-scoped Status source: [D2S1 Task](orchestration/tasks/P23-003D2S1-tenant-scoped-status-source.md)、[Report](orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md)。AcceptedとなったSourceの検証と次工程はReportを正本とする。
- D2S adapter/reader children/親D統合とpost-F controlは既存の[Phase23計画](spec/106-phase-23-delivery-plan.md)へ接続する。

従来のhandoff/checkpointを含む全文は[2026-09-08の旧TODO](orchestration/history/2026-09-08-todo.md)へ原文のまま保存した。
その履歴中の相対リンクは元の `develop/TODO.md` を基準に解釈する。以下の設計課題とPhase一覧は移行前の本文を保持する。checkboxは記録時点の計画であり、現在の完了判定には使わない。現在の実装状況は対象Task/Reportと仕様で照合してから採用・更新する。

## MVP Closeout

MVP Definition of Doneは [MVP Status](../docs/guide/mvp-status.md) と [Closeout Report](orchestration/reports/P6-015-mvp-closeout.md) で実装・Test証拠に対応付ける。MVP CompleteはProduction ReadyやStable Releaseを意味しない。

MVP後に残す主要項目:

- [ ] Transactional OutboxのPersistence AdapterとRelay
- [ ] Canonical JournalからObserver Projectionを再送するCLI
- [ ] Authentication／AuthorizationとJournal参照制御
- [x] Deferred Status／Outcome HTTP EndpointとGenerated Client
- [ ] Canonical PayloadとTransportの暗号化
- [ ] OpenTelemetry／CloudWatch／Remote Log Adapter
- [ ] SQLite／MySQL／SQS／Kafka Adapter
- [ ] Admin UI／Scheduled Operation Strategy
- [x] Packagist公開／Git Tag／Stable Release

## Post-MVP Developer Experience Roadmap

確定した順序と配布境界は [Developer Experience Roadmap](spec/41-developer-experience-roadmap.md) を正本とする。

### Phase 7: Installed Application Example and Skeleton Layout

- [x] Public Composition APIをFramework外のConsumer視点でAuditする
- [x] `examples/quickstart/` をInstall直後と同じApplication Layoutへ更新する
- [x] Inline／Deferred／Worker／Migration／RetentionのConsumer E2Eを整備する
- [x] Exampleと `blackops/skeleton` の共通Sourceとして配布可能にする

### Phase 8: Composer Project Bootstrap

- [x] `examples/quickstart/` を `blackops/skeleton` Composer Project Packageとして定義する
- [x] 再実行可能なPost-create Setupと `--no-scripts` Manual Setupを提供する
- [x] Committed QuickstartからLocal通常／`--no-scripts` Create-project Smokeを実行する
- [x] Distribution Repository／Credential／Packagist更新境界を確定する
- [x] `composer create-project blackops/skeleton my-app` を提供する
- [x] Install後Smoke Testを整備する

### Phase 9: Project BlackOps CLI

- [x] Project所有の薄い `bin/blackops` とFramework Console Kernelを設計する
- [x] `make:operation` と `make:migration` を提供する
- [x] Migration／Worker／Build／Retention／Scheduler CommandをApplicationから構成する
- [x] Framework UpdateでCommand実装とGenerator Stubが更新されることを検証する
- [x] BlackOps CLIのCanonical Commandから重複する `blackops:` Prefixを除去する

### Phase 10: Documentation Website

- [x] `docs/website/` にAstro Starlightを構築する
- [x] Framework実装者向けDocumentationを `docs/internal/` へ移行し、Repository内参照を同期する
- [x] `docs/guide/` だけを公開Source of Truthとする静的Buildを整備する
- [x] `docs/internal/` をWebsite Artifact、Navigation、Search Indexへ含めない
- [x] Cloudflare PagesのPreview／Production WorkflowとCredential／Artifact境界を整備する
- [x] Cloudflare Live PublicationをPhase 10 Blockerから分離し、明示Publication Taskへ延期する
- [x] Diátaxisを意識したSidebar、Landing、Testing／Deployment入口へ情報構造を再編する

### Phase 11: Stable 1.1 Release

- [x] Stable `1.0.0`からのBreaking／Additive SurfaceとUpgrade Pathを監査する
- [x] Main BranchのBlackOps CLI、Typed Operation、Validation、Worker ModeをRelease Noteへ固定する
- [x] Framework／Skeleton `1.1.0`のConsumer／Publication Gateを実行する
- [x] `composer create-project blackops/skeleton:^1.1`からQuickstartを完走する

### Phase 12: Middleware and Authorization Runtime

- [x] Global PSR-15 HTTP MiddlewareをConfig登録順でRuntimeへ実装する
- [x] ActorContextの伝播、Codec、Sensitive Journal境界を実装する
- [x] Authentication、ActorContext、`#[Authorize]`、Deferred再認可を実装する

### Phase 13: Database and Transaction Runtime

- [x] Named DBAL ConnectionとDI登録を実装する
- [x] Ray.AopのBuild-time Proxy生成とAttribute Guardを実装する
- [x] Command／Application Serviceの`#[Transactional]`を実装する
- [x] Operationの`#[Transactional]`を固定Lifecycleへ統合する
- [x] Nested Required／Rollback-only／Manual Transaction境界を実装する
- [x] `#[AfterCommit]` QueueとFailure Reporterを実装する
- [x] Transactional OperationとTerminal Journal／Outcomeの同一Connection Commitを実装する
- [x] Worker ModeのConnection Health Check／Reset／Reconnectを実装する
- [x] Install直後のQuickstart／SkeletonへRepository、Transactional Command、After CommitのOrder Journeyを統合する
- [x] Full PHP／Consumer／Website Gateを完走し、Phase 13をCloseする

### Phase 14: Operation Diagnostics

- [x] `OperationDiagnostics`、`operation:inspect`、`operation:viewer`、Safe Projection、Production Log責任境界をD097と仕様書へ固定する
- [x] D098でOperation ID発行後かつAttempt開始前の`received -> operation.failed` Lifecycleを確定する
- [x] Error Response／Journal／`ExecutionScopedLogger`を同じOperation IDで相関可能にする
- [x] Internal `OperationDiagnostics` Queryと`operation.unavailable` Availability境界を実装する
- [x] `operation:inspect`のHuman／JSON／Exit Code Contractを実装する
- [x] `diagnostics.viewer.enabled`で既定無効のDevelopment用`operation:viewer`を実装する
- [x] Production Log／Journal／Observabilityの責任境界をRegression TestとInternal Documentationへ同期する
- [x] Quickstart／Skeleton／Guide／Consumer E2Eを同期してPhase 14をCloseする

### Phase 15: Operation Frontend Bridge

- [x] Bridgeの初期Depth、Operation Object API、Frontend Target、生成／Sensitive境界をD100で決定する
- [x] Operation／HTTP Manifestから言語中立なFrontend Contract Manifestを生成する
- [x] Frontend Contract Schema Version 2でPHP Native Scalar Kindを保持する
- [x] `.url()`／`.toRequest()`／Readonly Metadataを持つTypeScript Operation Objectを生成する
- [x] `.fetch()`とInline／Deferred／Rejected／Failure／Transport Typed Resultを実装する
- [x] `frontend:check`、TypeScript Compile／Runtime Test、CI連携を実装する
- [x] Quickstart／Skeleton／Guide／Consumer E2Eを同期してPhase 15をCloseする

### Phase 16: Deferred Status and Outcome API

- [x] D102で単一Resource、7 State、専用Query Authorizer、Retention、Generated `.status()`／`.wait()`を決定する
- [x] Status／Outcome ContractとPhase 16 Delivery Planを仕様化する
- [x] Public PHP Status QueryとFail-closed専用Query Authorizerを実装する
- [x] PostgreSQL Status ProjectionとRetention境界を実装する
- [x] `GET /operations/{operationId}`とDeferred 202の`Location`／`Retry-After`を実装する
- [x] Generated `.status()`とTyped Outcome Decoderを実装する
- [x] Abort可能で有限なGenerated `.wait()`とFrontend CIを実装する
- [x] Quickstart／Skeleton／Guide／Website Source／Consumer E2Eを同期してPhase 16をCloseする

### Phase 17: Full-stack Reference Application

- [x] D103でRoadmap順序、BlackOps Board Scope、BFF、Authentication、Deferred Journey、Persistence、Design、Publication境界を決定する
- [x] Full-stack Reference Application仕様とPhase 17 Delivery Planを作成する
- [x] `examples/community-board/`のApplication／SvelteKit／Compose Foundationを構築する
- [x] Application-owned Authentication EndpointとSvelteKit Server-only Sessionを実装する
- [x] D104でStructured OutcomeのNested DTO／Typed List／Persistence境界を決定する
- [x] P17-004でStructured OutcomeをHTTP／Persistence／Frontendへ実装する
- [x] D105でCommunity BoardのPost削除方式とComment保持境界を決定する
- [x] Post／CommentのInline Operation、Validation、Authorization、Transactionを実装する
- [x] Generated Operation ObjectをServer-only BFFへ接続し、投稿Journeyを実装する
- [x] D107でDeferred DigestのWeek、Content、再生成、Failure Adapterを決定する
- [x] Deferred `GenerateWeeklyDigest`とStatus／Wait／Typed Outcome UIを実装する
- [x] Framework Source Archive／Composer PackageからRepository開発資産を除外し、Migration／Generator Stubを保持する
- [x] D108でliteral Strategy回避を維持してPhase 17を先行し、Framework-owned Transaction Interceptionを独立Phaseへ送る
- [x] Taste SkillをDesign Directionへ適用し、`reicon.dev`をIcon Sourceとして、Accessibility／Responsive／State UIを完成する
- [x] Real Browser E2E、CI、Credential-free Screenshotを追加する
- [x] Deterministic SeedとClean Install Consumerを完成する
- [x] README／Guide、Website、全品質Gateを同期してPhase 17をCloseする

### Phase 18: Application Ergonomics

- [x] D110でFrontend Bound Client、Typed Environment、Dependency、Session Auth、Console DXと実装順を決定する
- [x] Phase 18 Specification／Delivery PlanとTask境界を確定する
- [x] Typed Environment／Configuration Closureを実装する
- [x] Frontend Bound Client Factoryを実装する
- [x] Application Command Discovery／DIを実装する
- [x] Operation Console Adapterを実装する
- [x] Session Authentication CoreをFramework同梱のOpt-in Capabilityとして実装する
- [x] D112でPublic Ephemeral Outcomeと非永続化Lifecycle境界を決定する
- [x] Ephemeral Outcome CoreとFrontend直接Fetch Contractを実装する
- [x] `make:auth` GeneratorとFresh Consumerを実装する
- [x] Community Boardを簡素化し、Clean Install Consumerで検証する
- [ ] Nullable PropertyへString系Validation Attributeを付けた場合、`null`をskipするかwrong-targetにするかのValidation Contractを確定する

### Phase 19: Reliability and Delivery

- [x] D109でIdempotency Key、Transactional Outbox、Relay／Replay、Community Board Journeyを決定する
- [x] P19-001でReliability and Delivery仕様、Failure Matrix、Delivery Planを確定する
- [x] P19-002でIdempotency Key／ExecutionContext／Scope／Fingerprint／Storage Contractを実装する
- [x] P19-003でHTTP／PHP Duplicate Lifecycle、PostgreSQL Store、Retentionを実装する
- [x] P19-004でTransactional Outbox Persistenceと同一Connection Transaction参加を実装する
- [x] P19-005でRelay Claim／Retry／Fencing／Dead Letter CLIを実装する
- [x] P19-006でCanonical Observer Replayを実装する
- [x] P19-007でCommunity Board Digest／Notification Journeyを実装する
- [x] P19-008でGuide、Consumer、Full Gate、Phase Closeoutを完了する
- [x] Idempotency Keyと重複時Contractを実装する
- [x] Transactional Outbox、Relay、Replayを実装する

### Post-Phase 18 Application Ergonomics Follow-up

- [x] D113でDatabase Seeder、Framework-owned `database:seed`／`make:seeder`、Compiled Container DI、Application責任境界を確定する
- [x] Seeder Core、Console、Generator、Consumer移行を`P18-008A`／`P18-008B`／`P18-008C`へ分割する
- [x] Public Seeder API、Build-time Discovery、Compiled Container Locator、Cycle Guardを実装する
- [x] Framework-owned `database:seed`／`make:seeder`を実装する
- [x] Community BoardからSeeder用Symfony Console実装と不要になった直接Dependencyを削除し、Clean Installで再検証する
- [x] D114でHTTP Runtime、Environment Bootstrap、UUIDv7のApplication Direct Dependency境界とPhase 19前のDelivery順を確定する
- [x] Runtime Bootstrap Follow-upを`P18-009A`から`P18-009D`へ分割する
- [x] Public Environment File BootstrapとQuickstart Consumerを実装する
- [x] Framework-owned Classic／FrankenPHP SAPI Runtimeを実装する
- [x] Public UUIDv7 GeneratorとAuth／Community Board Consumerを実装する
- [x] Skeleton／Documentation／Dependency Auditを同期してRuntime Follow-upをCloseする

### Phase 20: Security Hardening and Observability

- [x] `#[Deferred]`とTransactional `Operations::dispatch()`でApplication child Operation authoringを簡素化する
- [x] Documentation WebsiteをBlumeへ移行し、Operation／Journal／Headless、Experimental Notice、利用者向けNavigationを再構成する
- [x] Landing指定文言とSidebar対象Pageの利用者向けHow-toを補正する（P20-002）
- [x] Documentation Reviewの正確性5件、孤立Page、Anchor／Navigation Guardを補正する（P20-003）
- [x] LandingのLink Integrity、視覚階層、GitHub導線を再設計する（P20-004）
- [x] Astro参照のLanding再設計、Sidebar現在地表示、Blume native Header GitHub導線を補正する（P20-005）
- [x] 第2回Documentation Reviewの即時退行、Landing Feature均等化、Website正本を補正する（P20-006）
- [x] Stable 1.1.0で完走できる入門動線とQuickstartを再建する（P20-007）
- [x] Mermaid PageをBlume native Diagramとして描画し、Artifact／Browser Guardを補正する（P20-008）
- [x] 第3回Documentation ReviewのLanding／Banner、P1正確性、Internal Link H1 Guardを補正する（P20-009）
- [x] Mermaid DiagramをDesktop本文幅とMobile局所横Scrollで判読可能にする（P20-008A）
- [x] Accuracy／User Journey／Browser実表示をEvidence付きで確認するDocumentation Reviewerを整備する（P20-009A）
- [x] Agent Model／Reasoning Profileの正本をProduction Luna Max、調査／Orchestrator Review／Documentation Review Sol xHighへ同期する（D144／P20-009H、Accepted）
- [x] Ephemeral Outcomeから明示Inline `#[ExecuteWith]`を不要にし、Generator／Example／Guideを暗黙Inlineへ統一する（P20-009B）
- [x] JournalのJSON構造、運用、安全境界、OpenTelemetry将来構想を独立Guideへ統合する（P20-009C）
- [x] Journal JSONLのParameter説明をTableへ整理する（P20-009D、P20-009EでRuntime Projection修正後にAcceptance）
- [x] Observed Journalの空Object、Identifier、日時Wire Shapeを修正する（P20-009E）
- [x] Testing／Deployment／Referenceを含むTask-oriented Guideを増強する（P20-010）
- [x] Blume native Callout、Copy、Previous／Next、Edit Link、日本語Fontを接続する（P20-011、Accepted）
- [x] 表記Guidelineを確定し、全Pageの文章編集Passを行う（P20-012、Accepted）
- [x] Scheduled Application Operationの入口、Timezone、Misfire、Overlap、Identity、Idempotencyを別Decisionで確定する（D134／P20-013、Accepted）
- [x] Scheduled Application OperationをAuthoring／Persistence／Invocation／CLI／Guideに分割して実装する（Specification 98／P20-014A〜E、Accepted）
- [x] Journal／Outcome参照制御、Tenant分離、暗号化CapabilityのContractを確定する（D135／Specification 99／P20-015、Accepted）
- [x] TenantRefとRoot／Child／Worker／Retry Tenant伝播を実装する（P20-016A、Accepted）
- [x] XChaCha20-Poly1305 EnvelopeとStorage Key Providerを実装する（P20-016B、Accepted）
- [x] PostgreSQL Tenant MetadataとDecode前Isolationを実装する（P20-016C、Accepted）
- [x] Tenant-aware StatusとDefault-deny Journal／Outcome Readを実装する（P20-016D、Accepted）
- [x] Journal／Deferred Payload／Context／OutcomeをEncrypted Envelopeへ移行する（P20-016E、Accepted）
- [x] Outbox／Dead Letter Reason／Idempotency Response／Resultを保護する（P20-016F、Accepted）
- [x] Storage Key Rotation CLI、Audit、Checkpoint／Resumeを実装する（P20-016G、Accepted）
- [x] Tenant／Storage ProtectionのGuideとDocumentation Reviewを完了する（P20-016H、Accepted）
- [x] 構造化Log Schema、OpenTelemetry Trace／Metric Adapter、Health／Readiness境界を確定する（D136／Specification 100／P20-017、Accepted）
- [x] Structured Record v1とCanonical JSONL Formatterを実装する（P20-018A、Accepted）
- [x] W3C Telemetry ContextとProcess越しPropagationを実装する（P20-018B、Accepted）
- [x] OpenTelemetry Trace AdapterとSpan Lifecycleを実装する（P20-018C、Accepted）
- [x] 低Cardinality Metric AdapterとRuntime Instrumentationを実装する（P20-018D、Accepted）
- [x] Liveness／ReadinessとLocal Docker Collector Consumerを実装する（P20-018E、Accepted）
- [x] Observability GuideとDocumentation Reviewを完了する（P20-018F、Accepted）
- [x] Local Grafana LGTMでTrace／Metricを閲覧するDevelopment-only Consumer Journeyを実装する（P20-018G、Accepted）

### Phase 21: Framework-owned Transaction Interception

- [x] PHP Signature Matrix、Generated Artifact、Symfony DI統合、Migration、Ray.Aop Removal GateをDecisionで確定する（P21-001、D137／Specification 101／102、Accepted）
- [x] P21-002 Contract／metadata／signature／ownership guardを実装する（Accepted）
- [x] P21-003 Framework Proxy generator／artifact／manifest／driftを実装する（Accepted）
- [x] P21-004 Symfony DI Definition preservationを実装する（Accepted）
- [x] P21-005 Transaction／AfterCommit runtime ownershipを実装する（Accepted）
- [x] P21-006 Ray／Framework compatibility／migration／consumer matrixを実装する（Accepted）
- [x] P21-007 Ray removal／Composer／package export／Phase 21 closeoutを実装する（Accepted）

### Phase 22: Stable 1.2 Version Baseline

- [x] 公開済みStable `1.1.0`とRepository `main` candidate `1.2.0`のVersion境界をDecision／Specificationへ固定する（P22-001 Accepted）
- [x] Main root／Telemetry scope／Skeleton constraint／Candidate Consumerを`1.2.0`系列へ同期する（P22-001 Accepted）
- [x] Stable install journey、Tag／Release／Packagist claim、歴史的記録を変更しないVersion inventory guardを整備する（P22-001 Accepted）
- [x] `1.1.0...main` Surface Audit、完全な`1.2.0` Release Note／Upgrade、actual Framework Update Consumerを整備する（P22-002 Accepted、Documentation Reviewer P1=0／P2=0／P3=0）
- [x] Fixed `1.2.0` Release Candidate SHAでRuntime Consumer（Stable 2 migration→candidate 9 migration、Provider-present／missing HTTP／Worker）、全Local／Consumer／Website／CI Gateを実施する（P22-003 Accepted。candidate `3332fd1`、CI `31771509163`、merge `5471491`、Reviewer P1=0／P2=0／P3=0）
- [x] Runtime disposable Git identity／Quality full-history checkout／Guide PHP opening-tag normalizationを3 Source fileへ限定して修正する（P22-003C Accepted、Reviewer P1=0／P2=0／P3=0、Commit `96383e1`）
- [x] Runtime ConsumerのGit index modeを本文不変の`100755`へ限定修正する（P22-003D Accepted、Commit `3332fd1`、Reviewer P1=0／P2=0／P3=0）
- [~] Mago既存Debtをtracked strict baselineへ固定し、DeptracをPHP 8.5対応4.7.1へ限定更新する（D140／P22-003Aはpartial tooling checkpoint `0eca056`。残ったArchitecture／export blockerはP22-003Bで解消）
- [x] Deptracのpublic／bounded internal Layerを同期し、generic Internal permissionなしで152 violations／59 uncoveredとMago baseline archive exclusionを閉じる（D141／D142 Option B／P22-003B Accepted、candidate `577cc224`、post-commit exact export PASS、Reviewer P1=0／P2=0／P3=0）
- [x] User承認済みのGreen-gated Tag／Push、Skeleton publication、Packagist／GitHub Release／Remote Smokeを実施する（P22-004 Accepted。Framework／Skeleton `1.2.0`、Packagist、GitHub Release、Remote smokeがlive。P22-004G final Reviewer P1=0／P2=0／P3=0）
- [x] Stable `1.2.0`の公開README／CHANGELOG／UPGRADE／Guide／Website Source、operation:inspect ownership limitation、version baseline、P22-004／Phase 22管理文書を同期する（P22-004G Accepted、final Reviewer P1=0／P2=0／P3=0）
- [x] PR #9のP22-004H CI contract correctionを統合する（merge `2cf9ddb`。Manual Recovery restore／README-only runtime equality／Blume 1.3.0 local-font provider-only・remote-provider・asset SHA/license rejectionを同期）
- [x] D143／Specification 104／P22-005でRelease Authority、全公開Source／Artifact guard、目的別Information Architectureを整備する（P22-005A〜G Accepted。Sol xHigh final P1=0／P2=0／P3=0、findingなし。P22-005D Production HTTP／Artifact／Browser verified 41 routes／127 executions。P22-005G management gate／PR／merge／Production evidenceを同期。Remaining Issuesはnone。Next Action: Orchestrator publication workflowでexact commit／PR CI／mergeを記録し、parent closeout後P23-001 feasibility proposalへ進む。BlackOps 1.3.0は未Release）
- [x] P22-005AでStable `1.2.0` current claim、historical allowlist、Search／LLM artifact、CI wiringをfail closedへ修正する（Accepted。mapped page lane、Roadmap／metadata、exact history、future-authority counterexamplesを含む92／92 Green、Documentation Review P1=0／P2=0／P3=0）
- [x] P22-005BでLanding／Sidebar／Content MapをStart Here、Build、Async and Lifecycle、Data and Security、Operate、Reference、Releasesへ再編する（Accepted。Website 95/95、全Local Gate、final Sol xHigh re-review P1=0／P2=0／P3=0）
- [x] P22-005Cで全公開PageをTutorial／How-to／Concept／Reference／Troubleshooting契約へ再分類する（40 Page inventory確定。Tutorial 3／How-to 18／Concept 10／Reference 8／Troubleshooting 1。最新Unified HTML State-Scanner補正は既存direct jsdom dependencyとquote-aware raw-text preflightでreader surfaceを構造処理し、Full Local GateはWebsite test 109/109、check 41 pages、build 42 pages、site:check 41 pages、artifact guard、Quickstart E2E、version baseline、Mago、PHP management-ID、diff check全PASS。final Sol xHigh review P1=0／P2=0／P3=0、Accepted）
- [x] P22-005DでBrowser／Accessibility／Search／Production canonical verificationを完了する（Accepted — Production Verified。Local evidence、PR #10／main CI／Documentation、Production HTTP／Artifact parity、current Production Browser 41 canonical routes／127 executions、Axe violations 0、console/page/request 0、horizontal overflow failure 0、accessibility-name entries 127、Search／theme／reduced-motion Green。Sol xHigh final P1=0／P2=0／P3=0、findingなし。Remaining Issuesはnone。Next Action: Orchestrator publication workflowでexact commit／PR CI／mergeを記録し、parent closeout後P23-001 feasibility proposalへ進む。BlackOps 1.3.0は未Release）
- [x] P22-005EはFinal Sol xHigh P1=0／P2=0／P3=0、P22-005E Local Acceptance支持によりAccepted。Post-sync SolはP1=0／P2=1／P3=0で、唯一のP2（Parent P22-005 Acceptance Criteria未同期）は解消済み。Task／Report／STATE／TODO／parentをcurrent statusとevidenceへ同期した。Local evidenceはWebsite 118／118、check 0／0／0、fresh 42-page build／41-page site、Release Source／Artifact、version baseline、Quickstart E2E、Mago、PHP ID、diff PASS。Browserは公開Artifact不変のため`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`の41 route／127 execution、failure 0、Axe 0、Search／theme／reduced-motion PASSとhash（index `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef`、Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`）を保持し、Browserは再実行していない。P22-005Eの残りはなし。上位parentの残件はreviewed exact Commit、same-SHA CI／Documentation delivery、authorized Production verification、parent P22-005 closeout。Next Action: OrchestratorがAccepted exact snapshotをreviewed exact Commitへ固定する

- [x] P22-005FはSol xHigh final P1=0／P2=0／P3=0でAccepted。実dist unavailableのfocused 1/1／full 118/118、release source／check／fresh build／site／artifact／generated-public boundary／diff PASS、dist restoreを独立確認し、accepted current Artifact hashはindex `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef`／Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`。Browser／Quickstart／Mago／PHP management-IDはtest＋management-onlyで公開Source／Artifact不変のためNot Run。P22-005F Remaining Issuesはnone。Parent remainingはreviewed exact Commit、same-SHA CI／Documentation delivery、Production canonical verification、parent closeout。Next Action: Orchestratorがexact accepted snapshotをreviewed exact Commitへ固定しUser-approved parent remote gatesへ進む（P22-005F child taskではCommit／Stage／Push／CI rerun／Deploy未実施。Parent deliveryはUser承認済みで、Orchestratorがreviewed exact Commit後に実施する）
- [x] P22-005G Documentation Production CloseoutはAccepted。Sol xHigh final P1=0／P2=0／P3=0、findingなし。PR #10 head `76aa6218ee80f6eaf7ae44cd6d4a0db215a6f1de`、PR/main CI／Documentation、merge `36d3206c37e33165b89b78b4eb333562e9d37b61`、production job `95653679057`、canonical HTTP／redirect／Link header、Artifact `9317633470`、index／CLI normalization付きparity、Search／llms／llms-full／index.md byte exact、hashes、current Production Browser 41 routes／127 executions／Axe 0／console-page-request 0／overflow 0／Search／theme／reduced-motion Greenを記録。Orchestrator management gateはrelease:check:source、Website 118/118、check 0/0/0、site:check 41 pages、release:check:artifact、diff check PASS。Remaining Issuesはnone。Next Action: Orchestrator publication workflowでexact commit／PR CI／mergeとauthorized publication gatesを記録し、parent closeout後P23-001 feasibility proposalへ進む。BlackOps 1.3.0は未Release。Workerによるcommit／stage／push／CI rerun／deployなし

### Phase 23 Proposal: BlackOps 1.3

以下はP23-001 Discoveryで整理した未Release ProposalのDecisionと、独立review／Acceptance後に実施する順序付きTaskである。Stable `1.2` current capabilityとして扱わない。既存Structured Log／OpenTelemetryのOperation／Correlation／Trace／Span相関と`ExecutionScopedLogger`／ObservabilityはStable currentであり、新しい四surface Audit相関契約だけがfuture proposalである。Round 6 independent evidence is separate: full review P1=3／P2=4／P3=1 and Audit review P1=2／P2=6／P3=1; no aggregate is claimed。Round 7 Changes Requested is historical (General P1=4／P2=1／P3=1、Audit P1=6／P2=6／P3=0)。Round 8 fresh review is historical Changes Requested (General P1=2／P2=1／P3=0、Audit P1=5／P2=4／P3=1), also not combined。Round 9 through Round 29 prior correction checkpoints are Historical evidence. The current status is `P23-001 Accepted — Stable 1.2 current / BlackOps 1.3 unreleased proposal`. Stable `1.2`とunreleased `1.3`を分離し、Search／Auditは混ぜない。Search Sol, Audit, and Parent General Sol are all Accepted at `P1=0/P2=0/P3=0`; P23-001R is also Accepted with final Sol `P1=0/P2=0/P3=0`. Next Action is the Specification 106 P23-002A/B/C atomic projection slice, beginning with the P23-002A Task Packet.

#### Historical P23-001 Round29 handoff — Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested

Updated At: 2026-08-26T01:51:40+09:00
Round29 Combined Local Gate Green Management Sync Started At: 2026-08-26T01:46:59+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-26T01:51:40+09:00

Status: `Round29 Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Fresh Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; independent Orchestrator exact probe HTML `22` plus fence `9` PASS; product framing `8/8`; Search focused `6/6`; full Website `146/146` in `277824ms`; `pnpm check` `41` pages with `0` errors/warnings/hints; the initial sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and existing nonfatal chunk-size/route-priority warnings only; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered, no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches; exact22/trailing-whitespace/diff/staged `0` PASS. Node/PHP Read/Access goldens match.

Search exact4 remains frozen at patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`, lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`, reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`, tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`, installed helper `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`. Audit exact3 remains frozen at D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; Round29 block `71,378` bytes / `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35/11/0`. Management links are `271/259/0` (total/local/missing).

The fresh gate is Green only for this exact22. Fresh separate Sol Search/Audit re-reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending. A non-blocking post-P23-001 test-performance follow-up is proposed: split Fast Gate from final Full Gate, isolate the two slow integration cases (`~192s` / `~76s`), and reuse one generated 41-page fixture. No test changes are made in this Green candidate.

#### Historical P23-001 Round29 handoff — Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — source-change-before Combined Local Gate snapshot

Historical status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This Pending snapshot predates the fresh Green gate and is retained as Historical evidence only; it is not reusable as current evidence.

Updated At: 2026-08-26T00:41:04+09:00
Round29 Fresh Search/Audit Bounded Corrections Started At: 2026-08-26T00:20:39+09:00
Round29 Fresh Search/Audit Bounded Corrections Completed At: 2026-08-26T00:20:39+09:00
Round29 Search Raw-start Boundary Parity Correction Started At: 2026-08-26T00:41:04+09:00
Round29 Search Raw-start Boundary Parity Correction Completed At: 2026-08-26T00:41:04+09:00

Status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Search and Audit remain separate; severity is not aggregated. Stable `1.2` remains current, Phase 23/`1.3` remains unreleased, and the parent is not Accepted, Decided, or Released.

Search review record: `P1=0/P2=3/P3=0`. The corrected roots are list-continuation visual-column normalization before same-container Setext detection, block-start empty list markers without inherited paragraphs, and incomplete CommonMark type-3 through type-6 HTML block masking. RawStarter lines without a standalone `>` now treat the first later `>` as opening completion rather than a closing raw tag, matching rendered Satteri/JSDOM visibility. Same-line `<template>` remains inert and multiline `<template` remains visible. Audit review record: `P1=0/P2=1/P3=0`; A5 is technically Green and the active D145 index status is synchronized.

Search exact4 current hashes: patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`, lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`, reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`, tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`, active helper `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`. Focused fixtures `7/7`, explicit no-standalone script/style parity `6/6`, Node syntax `2/2`, and offline frozen install/binding PASS.

Audit exact3 remains frozen at D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; its Round29 block is `71,378` bytes / `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35/11/0`.

The previous Green evidence is Historical source-change-before and is not reusable. Remaining: fresh Combined Local Gate on new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance. Next Action: freeze final exact22/new exact9 hashes, send separate Search/Audit packets, and run the fresh Combined Local Gate. Full gate/build/CI/release is not claimed.

#### Historical P23-001 Round29 handoff — Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — source-change-before Search correction snapshot

Updated At: 2026-08-25T23:15:57+09:00
Round29 Combined Local Gate Green Management Sync Started At: 2026-08-25T23:15:57+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-25T23:15:57+09:00

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable as current evidence. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Search exact4 is frozen: patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2` and syntax/offline install/binding/diff-check PASS.

Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; the current Round29 block is `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, byte-identical exact3, JSON `7/7`, Audit links `35 total / 11 local / 0 missing`.

Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; product framing `8/8`; Search focused `2/2`; full Website `144/144` in `279725ms`; Website check `41` pages with `0` errors/warnings/hints; the first sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and only existing nonfatal chunk-size/route-priority warnings; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered, no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches.

Read Node+Docker scope is `150/192` with digest `bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`; Access is `103/147` with digest `1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`; domain/algorithm/nonce negatives PASS. Management links are `271 total / 259 local / 0 missing` before this sync and remain bounded; exact22 hashes PASS, staged `0`, `git diff --check` PASS, and `HEAD=main` is `5ad87557c08e20c3bcc6d77be83804001ba3eb80`.

Remaining: fresh separate Sol Search/Audit read-only reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance. Next Action: freeze final exact22 and new exact5 hashes and send separate Search/Audit packets. Do not claim parent Accepted, Decided, or Released.

#### Historical P23-001 Round29 Pending snapshot before Combined Local Gate Green

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This is the immediately preceding Pending snapshot and its evidence before the current Combined Local Gate Green result; it is management-before evidence, not current and not reusable as Green evidence.

Updated At: 2026-08-25T17:32:04+09:00
Round29 Management Correction-complete / Gate-pending Sync Started At: 2026-08-25T17:32:04+09:00
Round29 Management Correction-complete / Gate-pending Sync Completed At: 2026-08-25T17:32:04+09:00
Round29 Audit Exact3 Block Resync Started At: 2026-08-25T17:50:37+09:00
Round29 Audit Exact3 Block Resync Completed At: 2026-08-25T17:50:37+09:00

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit are separate lanes and severity is not aggregated. Luna corrections and Orchestrator review are complete; the parent is not Accepted, Decided, or Released.

Search final Sol verdict: Changes Requested `P1=0/P2=2/P3=0`; roots are the Setext H2 boundary and script/style tag-name physical-line boundary. Search exact4 hashes are patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2`, syntax/offline install/binding/diff-check PASS.

Audit final Sol verdict: Changes Requested `P1=1/P2=0/P3=0`; root is the active attempt32 pre_call/post_call phase conflict. Audit exact3 hashes are D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; independently checked heading-inclusive Round29 block through the next-H2 boundary (excluding that boundary LF) is `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, byte-identical exact3, JSON `7/7`, links `35 total / 11 local / 0 missing`, phrase guards, and diff-check PASS.

The previous Green record is Historical source-change-before evidence and is not reusable. The fresh Combined Local Gate on new exact22 has not run and is Pending; no new Green result is claimed. Changed scope is exact5 management files only; Search exact4, Audit exact3, and the other17 exact22 paths remain frozen.

Acceptance: Search/Audit corrections and Orchestrator review are complete; fresh Combined Local Gate, fresh separate Sol Search/Audit reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending.

Remaining: fresh Combined Local Gate from scratch on new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

Next Action: freeze exact22 and new exact5 hashes, then run the Combined Local Gate from scratch. Do not claim Accepted, Decided, or Released.

#### Historical P23-001 Round29 handoff — Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — source-change-before snapshot

Updated At: 2026-08-25T16:31:07+09:00
Round29 Combined Gate Green Management Sync Started At: 2026-08-25T13:56:20+09:00
Round29 Combined Gate Green Management Sync Completed At: 2026-08-25T13:56:20+09:00
Round29 Exact5 Management Sync Started At: 2026-08-25T14:55:51+09:00
Round29 Exact5 Management Sync Completed At: 2026-08-25T14:55:51+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Started At: 2026-08-25T16:31:07+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Completed At: 2026-08-25T16:31:07+09:00

Historical status: `Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable for the new exact22.

#### Historical pre-exact5 correction inputs and Combined Local Gate evidence

Separate correction inputs from the immediately prior source snapshot remain
distinct: Search Sol Changes Requested `P1=0/P2=1/P3=0`, rooted in the
CommonMark type-6 incomplete block-tag prefix and type-7 no-whitespace tag-name
boundary; Audit Sol Changes Requested `P1=1/P2=0/P3=0`, rooted in non-unique
`nextAvailableAt` generation for positive-attempt prepared rest rows. Counts are
not aggregated.

Combined Local Gate evidence on the frozen pre-management exact22 is PASS:
offline frozen install; Node syntax `4/4`; product framing `8/8`; Search/
CommonMark focused `2/2`; full Website `140/140` with `0` failures in
`270020.279299ms`; Website check `41` pages with `0` errors/warnings/hints;
fresh build `42` pages with static artifact boundary PASS and embedded site
check `41` PASS; explicit site check `41` PASS; release source/artifact PASS;
`bash -n` and version baseline `published=1.2.0 historical=1.1.0` PASS;
PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS
with `186` baseline-filtered and `0` new issues; verify-baseline PASS; analyze
exit `0` with existing `71` issues (`46` warning / `25` help); and PHP
management-ID guard with no matches. Only existing nonfatal chunk-size and
route-priority warnings remain.

#### Historical source-change-before correction evidence

Fresh Search Sol verdict: Changes Requested `P1=0/P2=2/P3=0`. The roots were CommonMark thematic-break/list and type-6 slash-boundary divergence, plus complete HTML comments and script/style/template content leaking into Search. Search exact4 corrected these roots. Search exact4 hashes: patch `ba3218266569613f93a0e0275b3f679d34c43efb96f564e5a4674faf3c56c8c7`; lock `634d955259e7250f269af53785d4768155c95895521f14282677ee6bee4578f8`; reader `4d6f9c5b5f7eec7cfee13285a0dec44687be789c62064c2220d2e47b6e45f666`; test `1ba40a38557fa165664b2d4a5d2e81dd885ab2226fcb257ddaf40529b1ab2a9b`. Offline frozen install, syntax, new focused Satteri/reader/DOM `3/3`, existing Satteri HTML/search `2/2`, patch-lock-installed helper binding, exact4 diff-check/staged0: PASS. Installed helper SHA: `357879303ecb3f2dbeb675174450dc358450674089f3bc2a9a45fe157b1742ce`.

Fresh Audit Sol verdict: Changes Requested `P1=1/P2=0/P3=0`. The root was `AuditIngressEnvelope` binding `recordedAt` to nonexistent `logicalRecord.recordedAt` instead of closed-schema `logicalRecord.time.recordedAt`. Audit exact3 corrected this root. Audit exact3 hashes: D145 `b7903d679f60b5af9d7eb6974e1ff0346042af6dbd8de5077b5f570c3ae2991f`; Specification 105 `b4c09319563cb590161b3fa8c93ff8d99e9cbe42277b2bb0c9d3cc5d54892a4e`; Specification 106 `2934acf7c135664342dc0233e45366ef875c34caa0e58002f9e490892ff86e14`. The current Round29 block is `71,066` bytes in each file, byte-identical, SHA-256 `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON `7/7`; links `35 total / 11 local / 0 missing`; nested-path positive and four negative object-path guards PASS; exact3 staged0/diff-check PASS.

No CI, Browser, deploy, publication, release, commit, or push is claimed.

Historical post-correction Combined Local Gate evidence: offline frozen install PASS; Node syntax `4/4` PASS; product framing focused `8/8` PASS; Search correction focused new Satteri/reader/DOM `3/3` and existing HTML/Search `2/2` PASS; patch-lock-installed helper binding PASS; full Website test `142/142` with `0` failures in `266698.091552ms`; Website check `41` pages with `0` errors/warnings/hints; approved-retry build PASS. This evidence belongs only to the source-change-before snapshot and is not current.

Audit current Round29 blocks are `71,066` bytes each, byte-identical, SHA-256 `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON fences `7/7`; Markdown links `35 total / 11 local / 0 missing`; nested recordedAt path object guard `5/5`; Node + Docker PHP golden parity scope `311/348` with digest `d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`, Access `374/418` with requestRef `oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.

Freeze evidence: exact22, staged `0`, `HEAD=main` `5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and `git diff --check` PASS; exact5 links `271 total / 259 local / 0 missing`. No CI, Browser, deploy, publication, release, commit, or push is claimed.

#### Historical pre-exact5 Round29 Combined Local Gate Green evidence

Search evidence remains separate: patch-lock-installed binding PASS with patch
`11d1ac9...`, lock patch hash equal, and installed helper SHA `197f23de...`.
Audit evidence remains separate: current exact3 Round29 block `70,561` bytes,
SHA-256 `c60bfad4977987c25c76340475a25192ebe89cdce31b26dd958719132d4d97bb`,
byte-identical; JSON `7/7`; Markdown links `35 total / 11 local / 0 missing`;
Node+Docker PHP scope `311/348` with digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; Access
`374/418` with requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`;
and six scheduling branches/external-wire guards PASS. One overly literal Audit
substring assertion failed in a diagnostic harness and the equivalent
12-needle guard then PASSed; this was not a product or gate failure. Earlier
`ERR_PNPM_INVALID_PATCH` and `31/32` reader results remain Historical
diagnostic evidence only.

#### Historical prior Local Gate Green evidence (superseded source snapshot)

The following prior evidence is retained for auditability only and cannot be
used as current evidence: offline frozen install PASS; Node syntax
`4/4`; product-framing focused `8/8`; changed CommonMark focused `5/5`; full
Website `140/140` with `0` failures; Website check `41` pages and `0`
errors/warnings/hints; fresh build `42` pages with static artifact boundary and
embedded site check `41` PASS; explicit site/source/artifact checks PASS; version
baseline `published=1.2.0 historical=1.1.0` PASS; PHPUnit `30/30`, `250`
assertions PASS; Mago format, lint, verify-baseline, and PHP management-ID
guard PASS; analyze exit `0` with existing `71` issues (`46` warning / `25`
help). Only existing nonfatal chunk-size/route-priority warnings remain.
Audit exact3 is `65,269` byte-identical bytes with SHA-256
`6129ff07ffdf1ae6f50030836a5c5e9e30240cb2a8b0c372786645110b0b951c`; JSON
`7/7`; links `35 total / 11 local / 0 missing`; Node/Docker PHP parity and
negative matrices PASS. Worker extraction evidence `14d07ea5...` is not reused
because it was shifted by one byte.

Frozen exact7 hashes: Search patch
`896b653128be21f0698848ee7a58b9b53b7c34a0ba0bd24a36f827f776e46f14`, lock
`088c9b2f46112e101460218d701968796b38fc251b62203445f60f853107d415`, reader
`4f74827dc8b6a66346ed7a2f74633fa600a56cea2f5fbd49116f8ffb1a4cce3d`, test
`e63ee1dc6f4c3a6b16488ec7a3ef17bbc026ba5375f0f8649dbab3fd7e3c1cc9`; Audit
D145 `f376f71dc476972abcf57c25491f49ccfd288cbb36c30961266c9a3fabab37a9`,
Spec105 `e005b49bc2c1d4f24465ac53ed8f084f786b00cca5dddea754ef7a49d9bc86d0`,
Spec106 `8b406ab4aef1c24a11ec1a91370a4b0ede5b53d82ac6f0b513274dbdfdf4e7e2`.
Freeze is branch `agent/p23-001-blackops-1-3-feasibility`,
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, exact22, staged `0`,
no repository temporary path, active Blume new patch, and `git diff --check`
PASS. Diagnostic Node regex and aggregate-git issues were corrected or
independently checked and are not product failures.

Historical prior exact7 hashes for the superseded Local Gate candidate were:
Search patch `896b653128be21f0698848ee7a58b9b53b7c34a0ba0bd24a36f827f776e46f14`,
lock `088c9b2f46112e101460218d701968796b38fc251b62203445f60f853107d415`,
reader `4f74827dc8b6a66346ed7a2f74633fa600a56cea2f5fbd49116f8ffb1a4cce3d`,
test `e63ee1dc6f4c3a6b16488ec7a3ef17bbc026ba5375f0f8649dbab3fd7e3c1cc9`;
Audit D145 `f376f71dc476972abcf57c25491f49ccfd288cbb36c30961266c9a3fabab37a9`,
Spec105 `e005b49bc2c1d4f24465ac53ed8f084f786b00cca5dddea754ef7a49d9bc86d0`,
Spec106 `8b406ab4aef1c24a11ec1a91370a4b0ede5b53d82ac6f0b513274dbdfdf4e7e2`.

#### Historical post-correction exact7 freeze (source-change-before snapshot)

Current exact7 hashes are Search patch
`ba3218266569613f93a0e0275b3f679d34c43efb96f564e5a4674faf3c56c8c7`, lock
`634d955259e7250f269af53785d4768155c95895521f14282677ee6bee4578f8`, reader
`4d6f9c5b5f7eec7cfee13285a0dec44687be789c62064c2220d2e47b6e45f666`, test
`1ba40a38557fa165664b2d4a5d2e81dd885ab2226fcb257ddaf40529b1ab2a9b`; Audit
D145 `b7903d679f60b5af9d7eb6974e1ff0346042af6dbd8de5077b5f570c3ae2991f`,
Spec105 `b4c09319563cb590161b3fa8c93ff8d99e9cbe42277b2bb0c9d3cc5d54892a4e`,
Spec106 `2934acf7c135664342dc0233e45366ef875c34caa0e58002f9e490892ff86e14`.

Historical post-correction gate was Green: Website `142/142`, check `41`,
approved-retry build `42` with artifact/site `41`, PHP `30/30` and `250`
assertions; Audit `71,066` bytes each, SHA `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`,
JSON `7/7`, links `35/11/0`, goldens `311/348` and `374/418`; exact22,
staged0, HEAD/main, diff-check, and exact5 links `271/259/0` PASS. The
pre-correction Green snapshot remains Historical/non-reusable.

Acceptance: [x] Search exact4 correction and focused evidence; [x] Audit exact3
correction and static evidence; [x] post-correction Combined Local Gate for
frozen exact22; [ ] fresh separate Sol Search/Audit read-only re-reviews (requested);
[ ] separate slice decisions; [ ] parent General review; [ ] Orchestrator
Acceptance.

Remaining: fresh separate Sol Search and Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

Next Action: Request fresh separate Sol Search/Audit reviews against this exact22. Do not claim Accepted, Decided, or Released.

#### Historical P23-001 Round29 prior Local Gate and review handoff (superseded)

Status: `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate. Local Gate Green is an Orchestrator gate result only; the parent is not Accepted, Decided, or Released.

Orchestrator evidence from start reference `2026-08-25T10:03:33+09:00`: offline frozen `pnpm install` PASS; Node syntax `4/4 PASS`; focused product-framing tests `8/8 PASS`; focused Search/Satteri/legacy regression tests `9/9 PASS`; full Website test `139/139 PASS`; Website check `0 errors / 0 warnings / 0 hints`; sandbox build first failed only `listen EPERM: operation not permitted 0.0.0.0`, then approved sandbox-outside rerun PASSed `42` pages with static artifact boundary PASS and site check `41` pages PASS; existing nonfatal chunk/route warnings only; explicit `site:check`, `release:check:source`, and `release:check:artifact` PASS; `bash -n` and version baseline PASS (`published=1.2.0 historical=1.1.0`); PHPUnit `30 tests / 250 assertions` PASS; Mago format PASS; canonical lint PASS with `186` baseline-filtered/no issues; verify-baseline PASS; analyze exit `0` with existing `71` issues (`46 warning / 25 help`); PHP management-ID guard no matches. Audit exact3 current block is `52,679` bytes with SHA-256 `a2734271a864c24fd4f1932e4a0b0b7c2fb133fbd6c54822b2a7a135eb80fa1d`; JSON `7/7`; Markdown links exact3 union `35 total / 11 local / 0 missing`; Node/Docker PHP parity PASS, scope JCS/LP `311/348`, digest `d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`, access JCS/LP `374/418`, requestRef `oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.
Frozen exact7 hashes: Search patch `ae8c375cb95592810d917bf93f18895bedc1e58536b047cb872777b224566543`, lock `dd111150c4a5e4ebda8f8cc268202dd550cf8d812e4a4d1e1372ff4d88f49107`, reader `6e808dde20ba2801e050bec8abeea5223a2f247e50e759535cf94dfc6688463f`, test `6e9ab1cce442122293de6cc2d4de501244b02c603e36cafc51531de62b377924`; Audit D145 `0f9574b12156ece70762c0bfbcec9cfc8f2a33c0854abb7f8c5eaaa4a09ab8f7`, Spec105 `bc4a184f6539c976fea4580d2d8af8de084913646b662ec9e1e453e3076d5ad8`, Spec106 `5b641bd94416f0418dd258e9595cf05aba012786767e22353bcaa11ac5be0050`. Freeze: branch `agent/p23-001-blackops-1-3-feasibility`; `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact22 paths; staged `0`; no repository-scoped temporary path is present beyond the exact22 worktree; pre-existing `/tmp` review evidence remains and was neither deleted nor counted as worktree scope; `git diff --check` PASS.

Acceptance: [x] Search exact4 correction and Local Gate; [x] Audit exact3 correction and Local Gate; [ ] fresh separate Sol Search/Audit read-only re-reviews; [ ] separate slice decisions; [ ] parent General review; [ ] Orchestrator Acceptance.

Remaining: fresh separate Sol Search/Audit read-only re-reviews; separate slice decisions; parent General review; Orchestrator Acceptance; and, after acceptance, P23-001R and ordered Phase 23 work.

Next Action: freeze the new exact5 and exact7 hashes and request fresh separate Sol Search and Sol Audit read-only reviews; then record separate slice decisions, request parent General review, and proceed to Orchestrator Acceptance. Do not claim Accepted, Decided, or Released.

- [x] Historical P23-001 Round 25 Discovery correction completed at `2026-08-20T07:46:09+09:00`; its source-changing Phase 1 build and complete Local Gate evidence are historical. The later management-only static closeout did not rerun a build.
- [x] Historical P23-001 Round 26 correction implemented at `2026-08-20T18:39:33+09:00`: current AuditQuery fixed-head/cursor continuation, closed Outbox claim/sent/retry/dead-letter matrix, prepared/submitted/accepted/rejected AnchorIntent, and exact RetentionState/Hold wire were synchronized in the management/specification scope. Its status was `Round 26 Correction Implemented / Review Requested`; the fresh Search and Audit re-reviews now both request bounded correction in separate lanes, parent remains not Accepted, and Stable `1.2`/unreleased `1.3` boundaries are unchanged.
- [x] Historical P23-001 Round 8 pre-review bounded correction (`2026-08-19T08:02:47+09:00`): multi-segment namespace diagnostics/exit remain the Symfony Stable `1.2.0` goldens with no blanket namespace exit `2`; the single Stage 0-7 verifier order, exact `AuditIngressEnvelope v1` fields, 256-byte typed evidence boundary, list recovery exception, and three non-overlapping AnchorIntent crash fixtures are synchronized. Historical status was Correction Implemented / Review Requested; fresh Sol xHigh review and Orchestrator acceptance remained pending.
- [x] Historical P23-001 Round 9 bounded correction (`2026-08-19T09:10:43+09:00`): schedule:list/schedule:ls complete artifact exits are `0` valid, `1` operational read, `2` artifact failure and are distinct from list/ls framework recovery. The six trusted application projection states and ordered precedence, exact XML/Markdown canonical bytes and SHA-256 goldens, classification-required `safe_code|opaque_id` evidence, durable-only ingress, exact `AuditOutboxRecord v1` row/AAD/idempotency/head FIFO/lease/poison contract, verifier mode/context null matrix and full endpoint refs, fenced deterministic AnchorIntent recovery, actor/read matrix, nofollow root resolver, checkpoint control events, metadata-only retention, and split checkpoint-vs-anchor P3 ownership are synchronized. Round 8 verdict remains separate General `P1=2/P2=1/P3=0` / Audit `P1=5/P2=4/P3=1`; its status was Correction Implemented / Review Requested.
- [x] Historical P23-001 Round 10 bounded correction (`2026-08-19T09:42:10+09:00`): ProjectionBuildSet v1 installation-root identity, atomic projection/root publication, dedicated command/route/schedule artifacts, six-state recovery precedence, finite safe projection bounds and side-effect-free explicit/discovered list startup, exact JSON/raw/XML/Markdown wires and hidden `--all`, singular segment checkpoint/receipt endpoints, BOPD v1 canonical/outbox protection with tenant-union AAD, durable-only ingress and fenced outbox state, safe_code 256-pass/257-fail and opaque_id 71-pass/70/72-fail reachability with nullable null, Stage 0-7 verifier refs/codes, same-fd nofollow semantics, closed AnchorIntent state matrix/crash fixtures, non-recursive control stream, and P23-003B/D/F/G ownership are synchronized. Round 8 verdict remains separate and Round 9 has no invented Sol verdict. Historical status was Correction Implemented / Review Requested; no 1.3 current claim.
- Historical Round 9 freshness fixture is exact: `checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <= receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`; equality and one-microsecond-after boundaries were required.
- [x] Historical P23-001 Round 11 bounded correction (`2026-08-19T13:38:47+09:00`): the exact closed ProjectionBuildSet v1 root/self-hash, exact command/route/schedule projection kinds and canonical row shapes/sort keys, finite root/projection/field caps, immutable content-addressed publication, configured-root-only reader boundary, and one recovery precedence table were synchronized. `AuditIngressEnvelope v1` included `tenantBinding`; closed `AuditStoredRecord v1`, canonical BOPD/AAD/key/cap rules, nested `AuditReadContext`, manifest artifact identity/order and raw-versus-semantic hashes, Stage 0-7 total-function precedence, and `AnchorIntent` anchorReceiptId/lease/hash exactness were synchronized. Round 11 was Changes Requested (General `P1=2/P2=2/P3=0`、Audit `P1=7/P2=3/P3=1`); no aggregate or 1.3 current claim.
- [x] Historical P23-001 Round 12 bounded correction (`2026-08-19T14:51:24+09:00`): the then-current `Correction Implemented / Review Requested` management checkpoint synchronized one `ProjectionBuildSet v1` wire with exact flock publication/GC, dedicated projections, `operation:list --strategy=inline|deferred`, partition-global `AuditSegmentState`, canonical BOPD/AAD and its 232/344-byte goldens, v1 outbox re-encryption prohibition, Stage 0-7 plus external `expectedManifest`, F/G ownership, four-surface `.unavailable` tuples, `AuditAccessContext`, metadata-only `AuditRetentionState`, and signed artifact identity. Stable `1.2` remained current and 1.3 remained unreleased; no Production/public artifact changed.
- [x] Historical Round 12 AAD binding detail: canonical AAD is the ordered `LP(U32BE length + exact bytes)` concatenation of domain, purpose, version, algorithm, key, tenant union, and clear identity; the pinned system/genesis and tenant/non-genesis goldens are 232/344 bytes with the hashes recorded in D145/Specifications 105/106.
- [x] Historical P23-001S acceptance (`2026-08-19T18:01:23+09:00`, Sol xHigh `P1=0/P2=0/P3=0`) is preserved; Round 13 reopens the child for DOM visibility and requires fresh General review. Its Guide/runtime/Source/Artifact evidence remains a separate non-1.3 correction; no Production Code or 1.3 capability changed.
- [x] Historical P23-001 Round 13 correction (`2026-08-19T19:13:27+09:00`) synchronized atomic A/B/C ProjectionBuildSet ownership and pre-generation exclusive flock, injective custom strategy wire, partition-global segment/outbox/AnchorIntent/query/hold/read-event contracts, verifier trust-window and expectedManifest pin, and child P23-001S reopen. The exact-15 Local Gate was Green, but fresh review returned General `P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`; therefore Round 13 is historical Changes Requested and was not Accepted.
- [x] Historical P23-001 Round 14 Correction Implemented / Review Requested (`2026-08-19T19:56:30+09:00`): active route/schedule readers now consume only dedicated build-time projections; the fixed flock publisher/reader/GC lifecycle, atomic A/B/C acceptance, and custom strategy wire are synchronized. The Website visible-text guard retains visually displayed `aria-hidden`／`inert` text and visibility-overridden descendants; sign access events, AuditQuery/cursor, AnchorIntent, Stage 6 precedence, requestRef, and multi-hold retention contracts are synchronized. Round 14 Local Gate is frozen PASS; independent child/parent review remains, and parent/child are not Accepted while 1.3 remains unreleased.
- [x] Historical P23-001 Round 15 bounded correction (`2026-08-19T20:45:20+09:00`): Website body/title・`visibility:initial`・block boundary、former/current Guide wording、requestRef golden、exact Query cursor/result wire、closed AnchorIntent field/state matrixを同じexact-15 scopeで補正した。StatusはCorrection Implemented / Review Requestedで、Round 14 PASSはhistorical。Fresh Local Gateとchild General／parent General/Audit review、Orchestrator acceptanceが残る。
- [x] Historical P23-001 Round 16 requestRef golden correction (`2026-08-19T21:18:38+09:00`): shell quotingでJSON quoteを失った149-byte診断を棄却し、literal JCS 121 bytes／LP preimage 165 bytes／SHA-256 `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`へcurrent contractを戻した。Fresh full exact-15 Local GateはGreen。Child General／parent General+Audit review、Orchestrator acceptanceが残る。
- [x] Historical P23-001 Round 17 bounded child correction (`2026-08-19T21:59:03+09:00`): Round 16 child General `P1=1/P2=2/P3=0`のclass-string／prebuilt instance境界、`hidden`＋inline display override、child Parent pointer、および一覧形式に依存しない生成・再利用境界の文言をexact-15内で補正した。Round 17 statusはCorrection Implemented / Review Requestedで、fresh Local GateはPASS。child／parent reviewは未完了。
- [x] Historical P23-001 Round 18 bounded child correction (`2026-08-19T22:20:07+09:00`): Round 17 child General `P1=0/P2=2/P3=0`のnative closed `details`／`dialog` visibility（paired primary/alias fixturesを含む）とcurrent status／DAG／Remaining／Next Action同期をexact-15内で補正した。Round 18 statusはCorrection Implemented / Review Requestedで、fresh complete Local GateはPASS。Round 18 child General returned `P1=0/P2=1/P3=0`; Round 19 was the next historical checkpoint。
- [x] Historical P23-001 Round 21 bounded management correction (`2026-08-20T00:41:09+09:00`): D145、Specification 105／106のactive Round 19 gate paragraphsをRound 21 currentへ更新し、Specification 106のchild-authority cutoffを全prior checkpoint historicalへ修正した。Round 20 child General `P1=0/P2=1/P3=0`はhistorical Changes Requested。Fresh complete Round 21 Local GateはPASS。Fresh child General `P1=0/P2=0/P3=0`後、P23-001SをAcceptedとし、parentはParent Review Requestedである。Remainingはparent General/Audit review、parent Orchestrator acceptance、続くP23-001R／downstream。Next Actionはaccepted-child freezeを維持してparent reviewをrequestすること。1.3はunreleasedである。
- [x] Historical P23-001 Round 22 bounded parent correction (`2026-08-20T03:25:45+09:00`): exact eight management/specification filesだけを更新し、Application-owned/unreserved worker names、shared-lock all-three ProjectionBuildSet validation、Round 21 accepted child status、canonical `{operationId}` routeとstructural collision、exact operation/service audit families、sequence `1-42`／`43-50` continuity、finite control signing exemption、closed AuditHold v1、exact AuditReadContext v1 requestRefをD145／Spec 105／106／README／Task／Report／STATE／TODOへ同期した。P23-001S Task/ReportとGuide／Website／PHP testsはfreeze、fresh complete Round 22 Local GateはPASS、parent General `P1=2／P2=2／P3=0`とAudit `P1=4／P2=2／P3=0`はseparate。StatusはRound 22 Correction Implemented / Review Requestedで、parent Green／Accepted／Decided／Releasedはclaimしない。Remainingはparent General/Audit review、Orchestrator acceptance、続くP23-001R／downstream。Next Actionはexact-15 hashとgateをindependent reviewへ渡すこと。
- [x] P23-001RとしてRetention Lazy定義の`idempotency-record-days`欠落を独立したBounded Stable correctionで修正・reviewする（2026-08-26 Accepted。Final Sol `P1=0/P2=0/P3=0`。1.3実装へ隠さない）
- [x] P23-002AをA1/A2へbounded分割して実装・独立reviewする。A1はD146のinjected trusted root、exact ProjectionBuildSet wire、verified-handle reader／publisher／lock／GCを非接続componentとして実装する。A2はBlackOps-owned `list`／`ls` command projection、Stable optional namespace／`--raw`／`--short`／`--format=txt|json|xml|md`互換、1.3-only `--json`／`--all`、global reservation/collision、将来`operation:list --strategy=inline|deferred` filterを実装する。A/Bはpartial rootをpublish/activateせず、Cだけがall-three production activationを行う。Stable no-filter Human behaviorを維持し、P23-006までは未Release
- [x] P23-002BでP23-002A/B/C atomic slice内のCompiled Operation Route projection（build時にframework-fixed descriptorを結合）を実装する
- [x] P23-002CでP23-002A/B/C atomic slice内のdedicated schedule projectionとfinal full-root activationを実装する
- [x] P23-002Dで`diagnostics:check`とaggregate-only `queue:status`のsafe contractを実装する（Accepted。Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`）
- [x] P23-002Eで`about`と`outbox:status`を定義・実装する（Accepted。Fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`）。`dead-letter:list`はoperation/outbox名、tenant、pagination、redaction確定まで延期する
- [x] P23-003Aをserial A1/A2へ分割する。A1でclosed domain-hashed AuditEventCatalog v1／Catalog-only RFC 8785 JCS/hash、exact 30-definition Framework security/access Catalog、bounded evidence/semantic validation、keyed opaque ID、AuditReadRequestBasis／AuditReadContextを実装する。A2はAccepted A1をconsumeし、AuditRecord draft/logical/complete stage schema、resolved semantic/evidence validation、exact observed safe projectionを実装する。AuditRecord JCS logical body/hash／chain appendはP23-003B、dedicated opaque-key providerはP23-003C、runtime adapterはP23-003D、application registration/projectorはP23-003E。P23-003A1／A2とparent P23-003A are Accepted; P23-003B is next
- [x] P23-003Bをserial B1／B2で実装する。B1／B2 and parent P23-003B are Accepted。Reviewed B2 candidate commitとsame-SHA package exportはGreenである
- [x] P23-003Cをserial C1→C2→C3→C4で実装する。required same-transaction append、auth/read decisionのresponse前 dedicated local append/outbox commit（allow failureはdata非開示fail-closed、deny/invalid failureはbounded denial）、durable Audit Outboxのimmutable identity/bindings/protected envelopeとfenced state-field mutation、at-least-once same-ID/contentHash relayを全child Acceptance後に成立させる。Parent Cはfinal commit `d5ab347...`／same-SHA package export GreenでAccepted
- [x] P23-003C1でpure Audit Outbox state snapshot、configuration、deterministic backoff、claim／retry／recover／relay／poison transitionを実装する。Complete Final Gate／fresh Sol Max review `P1=0/P2=0/P3=0`でAccepted。DB／runtime activationは行わない
- [x] P23-003C2でPostgreSQL Audit Outbox row／migration／hydrator／idempotency／ordinal／FIFO／fenced CASを実装する。Complete Final Gate／fresh review／candidate commit／same-SHA package export GreenでAccepted
- [x] P23-003C3でCatalogのfixed delivery、explicit `policy.auditDelivery`、required same-root transaction、protected-read response gate、Application key compositionを実装する。Complete Final Gate／fresh review／candidate commit `b7b352a8...`／same-SHA package export GreenでAccepted
- [x] P23-003C4でsame recordId／contentHashのat-least-once relay、append-before-ack recovery、retry／poison runtimeを実装する。Complete Final Gate／fresh three-lane review／candidate commit `d5ab347...`／same-SHA package export GreenでAccepted
- [~] P23-003DをD154のbounded DAGで実装する。D1はexact14 Framework operational Catalogとtrusted lookup lineage。D2 read、D3 authentication、D4 authorization、D5 retention／hold・rotation・replay・generic Outbox source children、ordinary integrationは別Task。D-controlはAccepted P23-003F後にのみ開始する
- [x] P23-003D1でAccepted security/access exact30を維持しつつ、exact14 `blackops.framework.operations` Catalog、two-provider Framework provenance、PHP／Node／Architecture evidence、package exact75→76を追加した。Final commit `35afacd...`／same-SHA package export GreenでAccepted
- [ ] P23-003EでApplication versioned event registration/projector API、reference journey、business-action non-inferenceを検証する
- [ ] P23-003FでP23-003Bの完成AuditRecordをconsumeし、reusable catalog/hash／record／checkpoint／anchor／freshness verifier、immutable AuditCheckpoint／AuditAnchorReceipt、sealed concrete-segment graph、pure RFC 8032 Ed25519、pinned role-separated trust／revocation、external anchor、hash recomputation without appendを実装する（Fがcatalog／record／checkpoint／anchor／freshness coreと再計算を所有し、append／record mutation／physical deletionは行わない。同一`checkpointId`＋同一complete bytes/hashはpersist-before-ack後のidempotent success、異なるbytes/hashまたは同一segmentの別checkpointはintegrity failure）
- [ ] P23-003GでP23-003Fを呼び出すmanifest／bundle／startBoundary／file／wire／scope verifier orchestration（再実装なし）、bounded default-deny query、`coverageMode: complete_sealed_segments`／`filter:null`のAuditExportManifest v1（partition-chain-ordered ranges、distinct-partition genesis／selected_range startBoundaries、両mode同一8-key `anchorStoreRef`／`anchorObjectRef`／predecessor／`prefixComplete`、exact anchor triple `{partitionId,anchorStoreRef,anchorObjectRef}`／cross-store negatives、selectedはpredecessor existence／continuity／prefix legitimacy非主張、recordCount／chainHeadHash、checkpoint／anchor／distinct catalogHashごとの`event_catalog` hashes、exact-once role/path/artifact files／raw digests、actor/time/source、multi-partition `current|as_of_checkpoint` result／deterministic first failure／freshness code 18）／AuditExportRecord JCS/LF wire／finite verifier codes、bounded audit-of-audit stream family、category retention、legal holdを実装する（GはFを呼び出し、catalog／record／checkpoint／anchor／hash verificationを再実装しない。Physical deletion／signed deletion manifest／sealed bytes・keys deletionはAudit v1から外し、将来P23-003Hへ延期。任意filtered subsetは別safe projection）
- [ ] P23-003G Round 7 verifier boundary (historical, superseded by Round 8): downstream implementation must use the single Stage 0-7 table—bounded external context and expected hash code `19` with zero manifest/listed-file opens; bounded raw-manifest schema/JCS/count/version and declared lengths code `10|17`; manifest hash; trusted export key; Ed25519 with zero listed-file opens; only then root-confined actual lengths/sums/raw digests code `13`; catalog; and partition checks. Global stages 0-6 return `partitions:[]`, only Stage 7 emits entries with four non-null endpoint refs, and all cap/hash/context/tenant fixtures remain required.
- [x] Historical P23-001 Round 8 bounded correction: preserve Stable current `list` and all accepted namespace/raw/short/format inputs; make repeated format last-wins, `--json`/`--all` 1.3-only, `--raw` txt-only, `--short` exact no-op, and fix namespace behavior/exit by Stable `1.2.0` goldens. Define always-available framework recovery/status (`available|absent|malformed|unsupported|stale|build_mismatch`), exact XML/Markdown v1 wires, and the shared P23-002A/global reservation registry (including all B/C/D/E names; never reserve `worker:list`). Define `AuditIngressEnvelope v1`, ingress-only recordedAt high-water stamping, canonical-only sequence, outbox FIFO `ingressOrdinal`, independent BOPD `AuditOutboxRecord` with AAD/ciphertext retry, synthetic complete-record 1 MiB cap, non-null semantic tuples, <=256-byte typed evidence, sealed framework namespaces/owner authority, and independent phase validation. Synchronize one verifier Stage 0-7/result-reference matrix, freshness chronology, requester-partition deny/absent mapping, AnchorIntent crash recovery, 15/16 MiB manifest caps, and exact underlying connection/root transaction identity before required effects. Round 8 is Correction Implemented / Review Requested; fresh independent review remains pending.
- [ ] P23-003Hを将来の別review taskとして定義する（physical deletion／sealed bytes・keys destruction／signed deletion manifest／legal-hold boundary）。Audit v1には含めず、P23-006 dependencyにしない。受入れまでは削除せずcompliance claimもしない
- [ ] P23-004Aで既存defaultのFrankenPHP HTTP Worker Modeをsole supported production HTTP profileへhardenし、Classic removal migration、safe 500／readiness／memory／upgrade／rolling／rollback gatesを実装・検証する（separate services/containersを推奨。同一container multiprocessはOS-process crash isolationのみで、shared cgroup／lifecycle／rollout／rollbackとsupervision／signal forwardingを明記し、same-process Deferred claimなし）
- [ ] P23-004Bでoptional Extension Worker falsification spikeを実施する場合はnative crash／OOM HTTP couplingでfailし、1.3 release dependencyにしない
- [ ] P23-005でaccepted implementation後のCommands chapter、Audit vs Journal／Observability、newcomer value propositionをStable／Roadmap guard付きで同期する
- [ ] P23-006でexact candidateのLocal／same-SHA Remote／Package／Documentation／Release／Production gateを完走する（Tag／Deploy／Publishは別承認）

Historical P23-001 Round 12 Remaining: obtain fresh Sol xHigh General and Audit
review plus Orchestrator acceptance; then P23-001R and the ordered Phase 23
downstream tasks. P23-001S was Accepted historically and is now reopened.
Round 18 is historical Changes Requested (child General `P1=0/P2=1/P3=0`).
Round 19 was then Correction Implemented / Review Requested, not Green,
Accepted, or Decided. Its complete Local Gate is historical; Round 20
supersedes that checkpoint.
`best_effort` remains
an observed JSONL projection only, and no current Stable claim is changed.

### Deferred: Documentation Website Publication

- [x] [P23-018](orchestration/tasks/P23-018-landing-hero-reel.md): Landing Hero ReelはD167の配置・自動再生・停止契約でAccepted。PR14/15で分離Commit、same-SHA CI・Documentation delivery・公開URL確認を完了。Pagesの章移動も修正済み。D5Pと既存の未コミットDocumentationは保持する。

- [x] Userが公開再開を明示し、Cloudflare Project／GitHub Environmentを設定する
- [x] `blackops-php`へRepository設定を同期し、Preview／Production DeployとLive Verificationを実行する（P20-009F／P20-009G）。Production Deploy、Top／Installation／Blume Search IndexのHTTP 200、Desktop Keyboard／Mobile Button Searchを確認済み。

## 現在の優先事項

- [x] Operationの定義と責務を決める
- [x] Operationのライフサイクルを決める
- [x] InlineとDeferred Strategyの実行保証を決める
- [x] Journalの役割と記録形式を決める
- [x] MVP SampleでInline／Deferredの処理全体を検証する

## 1. ユビキタス言語

- [x] 実行される処理単位を `Operation` と呼ぶ
- [x] Operationのログを `Journal` と呼ぶ
- [x] 追跡識別子の正式名称を `Operation ID` とする
- [x] 型付けされた業務入力を `OperationValue` とする
- [x] 成功時の業務結果を `Outcome` とする
- [x] Deferred処理の受付結果を `DeferredAcknowledgement` とする
- [x] 固定的なDispatch ModeではなくExecution Strategyを使用する
- [x] 個々の実行試行を `Attempt` とする
- [x] Journalに記録する一件を `Journal Entry` とする

## 2. Operation

- [x] Operationは要求から最終結果まで続く論理的な処理単位とする
- [x] Operation Envelopeが最低限保持する値を決める
  - [x] Operation ID
  - [x] 発生日時
  - [x] 入力値
  - [x] Execution Strategy
- [x] Operation IDはFWがUUIDv7で発行する
- [ ] Operationを不変オブジェクトとするか決める
- [x] 初期設計ではCommandとQueryを区別せずOperationとして扱う
- [x] Operation DefinitionとOutcome型を `#[Returns]` で関連付ける
- [x] Operation DefinitionとHandlerを `#[HandledBy]` で関連付ける
- [x] Self-handled OperationとOptional `#[HandledBy]` 互換を実装する
- [x] Self-handled OperationのNative Value／Optional `ExecutionContext` SignatureをBuild、Manifest、Runtimeへ接続する
- [x] Application Build／Operation ListへBuild-time Discoveryを接続する
- [x] Compiled HandlerをRuntime Containerへ自動登録する
- [x] Public JSONL Journal設定をApplication HTTP Compositionへ接続する
- [x] QuickstartへPHP 8.5／FrankenPHP 1／PostgreSQL 18 Compose Runtimeを追加する
- [x] Temp Consumer copy installとInline／Deferred／Retention E2Eを自動検証する
- [x] Operation DefinitionとOperationValue型を `#[Accepts]` で関連付ける
- [x] Legacy Handlerは読み取り専用Operation Envelopeを一つだけ受け取る
- [x] Typed Self-handled HandlerはNative ValueとOptional `ExecutionContext` を受け取る
- [x] ContextなどのメタデータをOperation Envelopeへ分離する
  - [x] Actor IDとTypeをOptional要素として保持する
  - [ ] Trace ID
  - [x] Correlation ID
  - [x] Causation ID
  - [x] Tenant IDをOptional要素として扱う
- [ ] 冪等性キーをコア仕様に含めるか決める
- [ ] 期限、優先度、キャンセルをコア仕様に含めるか決める

## 3. ライフサイクル

- [x] OperationとAttemptの基本ライフサイクルを定義する
- [x] Inline Strategyの正常系を詳細化する
  - [x] Received
  - [x] Started
  - [x] Completed
- [x] Deferred Strategyの正常系を詳細化する
  - [x] Received
  - [x] Accepted
  - [x] Started
  - [x] Completed
- [x] 業務上の拒否とAttempt/Operationの失敗を区別する
  - [x] Rejected
  - [x] Attempt Failed
  - [x] Retry Scheduled
  - [x] Operation Failed
  - [x] Dead Lettered
  - [ ] Cancelled
  - [ ] Expired
- [x] 不正な状態遷移をJournal生成前に拒否する
- [ ] 現在状態をJournal Entryから導出するか決める
- [ ] 状態スナップショットを保持するか決める

## 4. Journal

- [x] Journal RecordをRetention削除まで不変の追記記録とする
- [x] Journal Recordの共通スキーマを定義する
- [x] Journal Recordに記録する時刻の意味を定義する
- [x] Operation入力を `operation.received` でCanonical記録する
- [x] Inline実行時のJournal Observer失敗をDelivery Policyで扱う
- [x] 正規Journal形式を共有し、Journal ObserverとExecution Transportを分離する
- [x] Canonical JournalとObserved／Purge Auditの責務を分離する
- [ ] JournalとDomain Eventの関係を定義する
- [x] schema versionとUpcasterによるJournal Recordのバージョニングを採用する
- [x] 保持期間と削除方針を決める
- [ ] 改ざん検知が必要か検討する
- [ ] 個人情報の削除要求と不変記録の両立方法を検討する

## 5. 実行方式

### Inline Strategy

- [x] Handlerの戻り値と例外の扱いを決める
- [ ] タイムアウトの扱いを決める
- [x] Canonical Journal失敗とObserver Delivery Policyの継続条件を決める
- [ ] HTTP接続切断時のOperationの扱いを決める

### Deferred Strategy

- [x] StateとReceived／Accepted JournalのCommit成功をDeferred受付完了とする
- [x] Deferred配送はat-least-onceで重複実行し得ると定義する
- [x] Deferred実行は重複し得るものとしExactly Onceを保証しない
- [x] Operation IDによるInbox/Deduplication機構を提供する
- [x] 既定のリトライ回数を定義する
- [x] 指数BackoffとJitterを採用する
- [ ] タイムアウトを定義する
- [x] Lease、Heartbeat、可視性タイムアウト、Fencingを定義する
- [x] Worker停止時は新規Claimを止め、Grace超過時はLease Expired Recoveryへ委ねる
- [x] Dead Letter Transportへ隔離しJournalへ記録する
- [ ] 順序保証の有無と単位を決める
- [ ] 並列実行の単位を決める
- [x] 非同期OutcomeをTyped Outcome Storeへ保存しOperation IDで取得する

## 6. トランザクションと整合性

- [x] Durable基本保証とTransactional Guaranteeを区別する
- [x] Transactional OutboxのPortを初期設計へ含める
- [ ] Transactional OutboxのPersistence AdapterとRelayを実装する
- [ ] Inboxパターンを採用するか検討する
- [ ] Handlerに冪等性を要求するか決める
- [ ] Frameworkが提供する冪等性支援を決める
- [x] Outcome保存失敗時はWorker完了Transaction全体をRollbackする
- [x] Deferred受付のStateとJournalを同一Transactionで保存する

## 7. セキュリティとプライバシー

- [x] センシティブ値は `#[Sensitive]` Attributeを基本として宣言する
- [ ] マスク、除外、暗号化の使い分けを決める
- [x] 認証情報をOperation Value／Context／Transport／Journalへ保存しない境界を実装する
- [ ] Journal参照権限を定義する
- [ ] Tenant間の分離方法を決める
- [ ] 保存データの暗号化要件を決める
- [ ] Execution Transportの暗号化Capabilityを設計する
- [ ] Type IDとAttributeの整合性を検査するPHPStan拡張を検討する
- [ ] 監査対象となる操作を定義できるようにする

## 8. アダプタ

- [x] Journal ObserverとExecution Transportの責務を分離する
- [x] 遅延配送を担う抽象を `Execution Transport` とする
- [x] `Journal Observer` インターフェースを設計する
- [x] `Execution Transport` を責務別Portとして設計する
- [x] `Outcome Store` インターフェースを設計する
- [x] `FlushableJournalObserver` を追加Capabilityとして設計する
- [x] MVP Reference DB AdapterをPostgreSQLとする
- [ ] KVSアダプタの候補を決める
- [ ] Queueアダプタの候補を決める
- [x] PSR-3ログアダプタにMonolog JSONL Backendを採用する
- [ ] OpenTelemetryアダプタを検討する
- [ ] CloudWatchアダプタを検討する

## LoggingとTraceability

- [x] FW LoggerをPSR-3互換Decoratorとして設計する
- [x] LoggerへExecutionContextを自動付与する
- [x] Operation IDをすべてのOperation内Application Logへ自動付与する
- [x] Attempt ID、Correlation ID、Causation IDを自動付与する
- [x] Operation Type ID、Execution Strategy、Journal Event名を構造化フィールドとして扱う
- [x] originActor、executionActor、authorizationActorはIDだけを自動付与する
- [x] Application LogとLifecycle Journal RecordをRecord Kindで区別する
- [x] FWが標準Operation lifecycle logを自動生成する
- [ ] 構造化ログの安定したSchemaとVersionを定義する
- [x] FW予約Fieldの上書きを禁止しユーザーContextを別namespaceへ格納する
- [x] `#[Sensitive]` とLogger Contextの共通Filterを統合する
- [x] Operation外のLogをOperation IDなしで記録する
- [x] PHP-FPM、長期Worker、Fiber対応のExecution Scopeを採用する
- [x] Application Log障害時はOperationを継続する
- [x] Journal DeliveryをBestEffort／Required／Durableに分ける
- [x] PSR-3 Logger AdapterとMonolog JSONL Backendを実装する
- [x] OTel IDをOperation IDと分離し構造化Logで関連付ける
- [ ] CloudWatch向け構造化ログAdapterを設計する
- [x] Application LogだけをSampling可能としLifecycle JournalはSamplingしない

## Frontend IntegrationとClient Generation

- [ ] FWをHTML Rendering機能を持たないAPI-only／Headless Frameworkとして定義する
- [ ] HTML Responseを標準Responderの対象外とするか決める
- [ ] React、Vue、Next.js、Nuxt等のFrontend Frameworkと協調する境界を定義する
- [ ] Operation DefinitionをFrontend向けContractのSource of Truthにする
- [ ] OperationValueから入力Schemaを生成する
- [ ] Outcomeから成功Response Schemaを生成する
- [ ] Rejection ReasonからError Response Schemaを生成する
- [ ] AcknowledgementからDeferred受付Response Schemaを生成する
- [ ] PHP型からJSON Schemaへの変換規則を定義する
- [ ] PHP型からTypeScript型への変換規則を定義する
- [ ] nullable、union、enum、日時、UUID、Collection、Value Objectの型変換規則を決める
- [ ] `#[Sensitive]` Propertyを生成SchemaとClientから除外する規則を決める
- [ ] Route、HTTP Method、Binding MetadataからClient Methodを生成する
- [ ] HTTP通信を隠蔽する型安全なClient SDK Generatorを設計する
- [ ] Client Methodの命名をOperation Type IDまたはDefinition Classから生成する規則を決める
- [ ] Path、Query、Header、Bodyの組み立てをClient内部へ隠蔽する
- [ ] Completed、Rejected、FailedをClient側で表現するResult型を設計する
- [ ] Deferred Operationの202、Operation ID、状態確認、PollingをClient APIで抽象化する
- [ ] Cancellation、Timeout、Retry、AbortSignal相当のClient APIを検討する
- [ ] 認証Tokenの付与と更新をClient Middleware／Interceptorとして設計する
- [ ] Correlation ID等のTrace ContextをClientから伝播する方法を決める
- [ ] OpenAPIを生成するか、独自Operation Manifestから直接Clientを生成するか決める
- [ ] OpenAPI生成時のOperation ID、Schema名、Error定義の規則を決める
- [ ] TypeScript以外のClient SDK生成を拡張可能にするGenerator Portを設計する
- [ ] Generated ClientのVersionとServer Manifestの互換性検証を設計する
- [ ] Breaking ChangeをCIで検出するContract Diffを設計する
- [ ] CORS、CSRF、Cookie／Bearer認証などFrontend接続時のSecurity要件を整理する
- [ ] File Upload／Download、Streaming、SSE、WebSocketを対象に含めるか決める
- [ ] Frontend Integration専用の設計対話をMVP後に作成する

## 9. HTTP境界

- [x] Adapter Middlewareで認証しCredentialを除いたActorContextを生成する
- [x] Global PSR-15 MiddlewareとFramework固定Operation Lifecycle Stageを分離する
- [x] origin、execution、authorizationのActor責務を分離する
- [x] HTTPリクエストからOperationValueへのBindingとValidationを定義する
- [x] Operation Definitionの `#[Route]` でHTTPルートを宣言する
- [x] BindingとOperationValue Validationの境界を定義する
- [x] WebアダプタのResponderがOutcomeをHTTPレスポンスへ変換する
- [x] Deferred受付成功は既定でHTTP 202を返す
- [ ] Deferred Operationの状態確認APIを設計する
- [x] Invalid CredentialはOperation受理前、Actor Authorizationは受理後に評価する

## 10. 最小プロトタイプ

- [x] 対象をPHP 8.5以上とする
- [x] MVPのReference Execution TransportをPostgreSQLへ変更する
- [x] 公式開発環境にDocker Composeを採用する
- [x] PostgreSQL TransportのSchema、Index、Migrationを定義する
- [x] PostgreSQL PayloadとContextをCodec済み `bytea` で保存する
- [x] StateをTEXT + CHECK、時刻をTIMESTAMPTZとする
- [x] Claimへ `FOR UPDATE SKIP LOCKED` を採用する
- [x] Framework管理のVersion付きMigration CLIを提供する
- [x] PostgreSQL Table、Partial Index、Migration SQLを実装する
- [x] PostgreSQL AdapterへCanonical Journal Storeを含める
- [x] Deferred受付のStateとJournalを同一Transactionで保存する
- [x] WorkerのLifecycle境界を短いTransactionへ分割する
- [x] ObserverをCommit後にBestEffort配送する
- [ ] Canonical JournalからObserver Projectionを再送するCLIを設計する
- [x] PostgreSQL専用Schema `blackops` を採用する
- [x] DB Adapter間で論理Table名を共通化する
- [x] Canonical Journalを検索Column + Encoded RecordのHybrid構造にする
- [x] OutcomeとDead Letterを別Tableにする
- [ ] MySQL AdapterをMVP後の候補として検討する
- [x] Payload、Journal、Outcome、Dead LetterのRetentionを分離する
- [x] Terminal OperationのPayloadをTombstone化可能にする
- [x] Retention対象外部キーを `ON DELETE RESTRICT` とする
- [x] Operation単位のLegal Holdを設ける
- [x] Retention Policy Contractを実装する
- [x] Retention Serviceを設計・実装する
- [x] Retention Hold Portを設計・実装する
- [x] Retention SchedulerをMVPへ含める
- [x] Retention期間はProductionで明示設定を要求する
- [x] Holdを `retention_holds` として一般化する
- [x] Purge Auditを別Tableとfail-closed System Logへ記録する
- [x] Retention CLIとFramework Maintenance Scheduler Workerを実装する
- [x] Inline／Deferred Canonical JournalのRetention削除を実装する
- [x] Inline OperationへRetention HoldとPurge Auditを保存可能にする
- [x] MVP実装Phaseと最初のVertical Sliceを確定する
- [x] LintとStatic AnalysisにMagoを採用する
- [x] Test RunnerにPHPUnitを採用する
- [x] Phase 0: Foundationを実装する
- [x] Phase 1: Journal付きInline Vertical Sliceを実装する
- [x] Frontend接合方式をD047で確定する
- [x] Codex GPT-5.4-mini workerへの実装依頼方式へOrchestrationを更新する
- [x] Codex GPT-5.4-mini workerへ渡すTask Packet Templateを維持する
- [x] `develop/STATE.md` のCheckpoint Templateを作成する
- [x] Orchestrator Codex／GPT-5.4-mini worker共通規約をRootの `AGENTS.md` に記述する
- [x] Implementation WorkerをGPT-5.6 Luna Highへ更新し、別Modelへの黙示Fallbackを禁止する
- [x] MVP範囲のFramework実装者向け `docs/internal/` を整備する
- [x] MVP範囲のFramework利用者向け `docs/guide/` を整備する
- [x] WSL2 Distributionを導入する
- [x] WSL2の `/home/kubotak/projects/blackops` に実装Repositoryを準備する
- [x] WSL2内のOpenCode CLI導入は旧方式の履歴として保持する
- [x] GLM-5.2 Provider非対話実行確認は新方式では不要とする
- [x] Docker ComposeでPHP 8.5、Composer、Mago、PHPUnit、Deptrac、PostgreSQL環境を準備する
- [x] InMemory TransportをUnit Test向けに実装する
- [ ] SQLite AdapterをMVP後の候補として検討する
- [x] HTTP ContractにPSR-7／15／17を採用する
- [x] FrankenPHP 1／PHP 8.5のReference HTTP RuntimeとPSR-15 Front Controllerを実装する
- [x] RouterにFastRouteを採用し、Compile済みDispatcher DataをHTTP Manifestへ保存する
- [x] 開発用Dynamic Operation DiscoveryでPSR-4、Classmap、Token Scanを統合する
- [x] `operation:list`と開発用Operation／HTTP Manifest CompileへDynamic Discoveryを接続する
- [x] UUIDv7生成にSymfony UIDを採用する
- [x] CLIにSymfony Consoleを採用する
- [x] Logger BackendにMonolog 3を採用する
- [x] Monolog 3をExecutionScopedLogger向けJSONL Backendとして構成する
- [x] Test FrameworkにPHPUnitを採用する
- [x] MVPは `blackops/framework` 単一Composer Packageとする
- [x] 単一Package内を責務別Namespaceへ分割する
- [x] 内部専用実装を `BlackOps\Internal` へ配置する
- [x] Namespace間の依存方向を定義する
- [x] Deptracで依存違反をCI検証する
- [x] `deptrac.yaml` を実装する
- [x] Operation、OperationValue、OutcomeをMarker Interfaceとする
- [x] Handlerを単一の `handle()` Contractとする
- [x] 互換性を保証するPHP Public APIへ `#[PublicApi]` を付ける
- [x] `#[PublicApi]` の付与とInternal型露出をCI検証する
- [x] Operation EnvelopeをFramework管理の `final readonly class` とする
- [x] Envelopeの識別情報はExecutionContextを正本とする
- [x] Operation Envelopeを実装する
- [x] ExecutionContextをFramework管理の `final readonly class` とする
- [x] Attempt IDと開始時刻をOptionalなAttemptContextへまとめる
- [x] ExecutionContextの生成と遷移を内部Factoryへ限定する
- [x] ExecutionContext、AttemptContext、内部Factoryを実装する
- [x] Framework IDを意味ごとの独立した `final readonly class` とする
- [x] UUIDv7生成を内部IdentifierFactoryへ集約する
- [x] IDの正規文字列表現と変換APIを定義する
- [x] IDの同値比較APIと不正入力時の例外型を決める
- [x] ID Value ObjectとIdentifierFactoryを実装する
- [x] PSR-20 Clockを採用する
- [x] 時刻をUTCの `DateTimeImmutable` で扱う
- [x] 時刻文字列をマイクロ秒付きRFC 3339 UTCへ統一する
- [x] TimestampとLifecycle順序保証を分離する
- [x] 共通Time Codecを実装する
- [x] Journal RecordをNested Envelopeとする
- [x] Lifecycle EventのWire NameをDot-separated形式とする
- [x] Operationごとの単調増加SequenceをJournal Recordへ必須化する
- [x] Event固有Fieldを型付き `data` Objectへ格納する
- [ ] Journal Record共通SchemaのJSON Schemaを定義する
- [x] Sequenceの永続割当と競合制御を設計する
- [x] Journal Recordを共通の `final readonly class` とする
- [x] Lifecycle EventをString-backed Enumで表す
- [x] Event Dataを型付き `JournalData` とする
- [x] Journal Record生成を内部Factoryへ限定する
- [x] JournalRecord、JournalEvent、JournalData、内部Factoryを実装する
- [x] Received Journalへ再現可能なCanonical Payloadを保持する
- [x] Completed JournalへCanonical Outcomeを保持する
- [x] Failure Journalの安全な構造化Errorを定義する
- [x] DataなしEventを `EmptyJournalData` として表す
- [x] Lifecycle EventごとのData ClassとCodecを実装する
- [x] Canonical JournalからObserver ProjectionをFW共通Pipelineで生成する
- [x] `#[Sensitive]` にOmit、Mask、HMACを定義する
- [x] 予約Key Patternによる防御的Omitを行う
- [x] ObserverとCanonicalJournalStoreを型レベルで分離する
- [x] Sensitive FilterとObserver Projectionを実装する
- [ ] Canonical StoreのCapability検証を設計する
- [x] Observer専用の `ObservedJournalRecord` を定義する
- [x] Journal Port失敗を専用Exceptionで表す
- [x] Flushを追加Capabilityとして分離する
- [x] Canonical JournalのWriterとReaderを分離する
- [x] Journal Port InterfaceとExceptionを実装する
- [x] InlineとDeferredのSequence管理場所を定義する
- [x] Deferred SequenceをTransactionで原子的に予約する
- [x] Sequenceの欠番を許容し監視対象とする
- [x] 再配送時にRecord IDとSequenceを維持する
- [x] Deferred Operation StateのVersionと `next_sequence` を実装する
- [x] `attempt.retry_scheduled` を標準Lifecycle Eventへ追加する
- [x] `operation.accepted` をDeferredのDurable受付に限定する
- [x] Attempt SucceededとOperation Completedを区別する
- [x] FailedとDead Letteredを排他的なTerminal Eventとする
- [x] Lifecycle状態遷移表と検証器を設計する
- [x] Handlerの戻り値を `OperationResult<TOutcome>` に統一する
- [x] Typed Self-handledのValue／OutcomeをNative Signatureから推論し、業務拒否をFramework例外でLifecycleへ統合する
- [x] OperationResultの生成をStatic Factoryへ限定する
- [x] 値のない成功を `EmptyOutcome` として扱う
- [x] OperationResult、RejectionReason、EmptyOutcomeを実装する
- [x] MVP Lifecycle Stateと遷移を定義する
- [x] Lifecycle状態遷移をMermaid図で記録する
- [x] 不正遷移をJournal生成前に拒否する
- [x] Terminal後の新規EventとHandler実行を拒否する
- [x] Lifecycle Transition Tableと検証器を実装する
- [ ] JournalからLifecycle Stateを再構築する専用Readerを実装する
- [x] AttemptContextへID、番号、開始時刻を保持する
- [x] Lease MetadataをTransport内部へ限定する
- [x] Attempt Started記録後にHandlerを呼び出す
- [x] Claimへ単調増加Fencing Tokenを付与する
- [x] Deferred Claim、Attempt開始、Fencing検証を実装する
- [x] Handler実行中にHeartbeatでLeaseを延長する
- [x] CrashしたRunning AttemptをLease Expired Failureとして閉じる
- [x] Heartbeat失敗後の完了更新を禁止する
- [x] Graceful Shutdown時はLeaseを自然失効させる
- [x] Worker Heartbeatを実装する
- [x] Crash Recoveryを実装する
- [x] Signal処理を実装する
- [x] Transport境界をCodec済みDeferredOperationMessageとする
- [x] Durable受付結果をDeferredAcknowledgementとする
- [x] MVPのClaimを一件単位とする
- [x] Execution Transportを責務別Portへ分割する
- [x] Execution Transport PortとMessage型を実装する
- [x] `Operation` を実装する
- [x] `OperationId` を実装する
- [x] `OperationValue` を実装する
- [x] `Outcome` を実装する
- [x] `OperationHandler` を実装する
- [x] `Dispatcher` を実装する
- [x] `DispatchMode`の確定名称である `ExecutionStrategy` を実装する
- [x] `Journal Entry`の確定名称である `JournalRecord` を実装する
- [ ] インメモリJournal Observerを実装する
- [x] Unit Test用InMemory Execution Transportを実装する
- [x] Inline Strategyを実装する
- [x] Deferred Strategyを実装する
- [x] 最小Workerを実装する
- [ ] 冪等性の基本機構を実装する

## 11. 検証用ユースケース

- [x] MVP検証用の `ShowWelcome`／`GenerateReport` Operationを定義する
- [x] HTTP入力からOperationを生成する
- [x] Inline Strategyで正常終了させる
- [x] Deferred Strategyで受付後にWorkerから実行する
- [x] Journalからライフサイクルを確認する
- [x] Handler失敗後に再試行する
- [x] Operation IDの一意制約とFencingで重複受付／Stale更新を防止する
- [x] 再試行上限後にデッドレターへ移動する
- [x] 非同期Outcomeを取得する
- [x] Canonical Journalが再現用の値を保持し、Observed Projection／JSONLで機密値を安全化する境界を確認する

## 12. ドキュメント

- [x] 旧 `SPECIFICATION.md` の入口を `develop/spec/README.md` へ統合する
- [x] 確定仕様を分野別の `spec/` 文書へ分割する
- [x] READMEの用語を `Operation` と新しい `Journal` の定義へ更新する
- [x] アーキテクチャ概要を作成する
- [x] Operationのライフサイクル図を作成する
- [x] Inline Strategyのシーケンス図を作成する
- [x] Deferred Strategyのシーケンス図を作成する
- [x] 障害時のシーケンス図を作成する
- [x] `develop/decisions/` の設計対話形式で判断履歴を記録する
- [ ] Welcome Pageと初期Application Skeletonを後工程で設計する

## 後で検討すること

- [ ] BlackOpsの正式公開前に、主要公開地域とソフトウェア関連区分を対象とした商標クリアランスを行う
- [ ] `BlackOps` / `BlackOpsPHP` のComposer Vendor、GitHub Organization、Domain、SNS識別子の利用可能性を確認する
- [ ] Operation同士の依存関係
- [ ] Operationの連鎖とSaga
- [ ] 複数のDeferred Operationを一定期間で集約するCoalesceの意味論
- [ ] Coalesce後も個々のOperationをJournal上で追跡できる設計
- [ ] センシティブ値を持つOperationで許可するExecution Strategyを定義する
- [ ] スケジュール実行
- [ ] バッチ処理
- [ ] 複数Worker間の負荷分散
- [ ] 管理画面からの検索、再試行、キャンセル
- [ ] Event Sourcingを採用するアプリケーション向けの拡張
- [ ] Actor Modelに近い逐次処理単位の提供
- [ ] Operation Feature一式を生成するCLIを設計する
- [ ] OperationValue、Handler、Outcome、Responderの選択生成を設計する
- [ ] Http／Console／Internal Operationの雛形生成を設計する
- [ ] Middlewareの雛形生成を設計する


### P23-006C: Accepted-source integration

- [x] Published Reel a419f375 is merged separately at70f6193; its public Source remains exact.
- [x] D2J+D2X, D3+D4A+D4B+D5H, D5P, R1, R2 and O1 are imported from pinned payloads in six separate commits. Original child attribution and local evidence are retained.
- [x] Final81 endpoint bytes/modes and1693 runtime bytes match their selected evidence; actual115/66 package inventory passes.
- [x] Independent integration Review and current management closeout (edb12f16; reviewe0a9dd84 Green): [P23-006C](orchestration/tasks/P23-006C-accepted-audit-integration.md), [Report](orchestration/reports/P23-006C-accepted-audit-integration.md).
- [ ] Parent1.3 dependencies, residual Documentation, final Owner preview, final local/same-SHA CI/export and immutable publication/live proof remain with [P23-006](orchestration/tasks/P23-006-blackops-1-3-release.md). The historical006C boundary ends at O1; the subsequent006D import below adds O2/F1.

## Accepted O2/F1 isolated integration

Replay audit O2 and signed-artifact primitives F1 are Accepted locally after
the shared3756-case full and independent Review. [006D](orchestration/tasks/P23-006D-o2-f1-accepted-integration.md)
imports their52 final paths and8 documents in four attributed turns, with
149/103 package checks and source convergence complete. Independent final Review
is Green and Root accepts006D locally. Parent1.3 dependencies and release gates
remain; the published Reel and through-O1 integration are preserved.
