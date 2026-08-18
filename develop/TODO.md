# TODO

この文書では、フレームワークの設計課題と実装タスクを管理する。

## 運用ルール

- `[ ]` 未着手
- `[x]` 完了
- `[~]` 検討中
- 重要な設計判断は、結論だけでなく理由もREADMEまたは設計文書へ残す
- 用語の変更時は、コードと文書をまとめて更新する

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
- [~] D143／Specification 104／P22-005でRelease Authority、全公開Source／Artifact guard、目的別Information Architectureを整備する（P22-005A／B／C Accepted。C final Sol xHigh review P1=0／P2=0／P3=0、D remains）
- [x] P22-005AでStable `1.2.0` current claim、historical allowlist、Search／LLM artifact、CI wiringをfail closedへ修正する（Accepted。mapped page lane、Roadmap／metadata、exact history、future-authority counterexamplesを含む92／92 Green、Documentation Review P1=0／P2=0／P3=0）
- [x] P22-005BでLanding／Sidebar／Content MapをStart Here、Build、Async and Lifecycle、Data and Security、Operate、Reference、Releasesへ再編する（Accepted。Website 95/95、全Local Gate、final Sol xHigh re-review P1=0／P2=0／P3=0）
- [x] P22-005Cで全公開PageをTutorial／How-to／Concept／Reference／Troubleshooting契約へ再分類する（40 Page inventory確定。Tutorial 3／How-to 18／Concept 10／Reference 8／Troubleshooting 1。最新Unified HTML State-Scanner補正は既存direct jsdom dependencyとquote-aware raw-text preflightでreader surfaceを構造処理し、Full Local GateはWebsite test 109/109、check 41 pages、build 42 pages、site:check 41 pages、artifact guard、Quickstart E2E、version baseline、Mago、PHP management-ID、diff check全PASS。final Sol xHigh review P1=0／P2=0／P3=0、Accepted）
- [~] P22-005DでBrowser／Accessibility／Search／Production canonical verificationを完了する（Local Accepted。Luna Maxの4 contrast／local-scroller補正、共有SearchFocusBoundary、typed Releases flat baselineを実装。Orchestrator complete Local GateはWebsite 112／112、check 0/0/0、fresh 42-page build／41-page site check、release source／artifact、version baseline、Mago、PHP management-ID scan、diff checkをPASS。最終Artifactと同じhashで127／127 execution、failure 0、Axe critical／serious 0、empty／non-empty Search focus returnを確認。Final Sol xHigh review P1=0／P2=0／P3=0。P22-005EもAccepted。Parentのreviewed exact Commit／same-SHA CI／Documentation delivery、authorized Production verification、P22-005 closeoutが残る）
- [x] P22-005EはFinal Sol xHigh P1=0／P2=0／P3=0、P22-005E Local Acceptance支持によりAccepted。Post-sync SolはP1=0／P2=1／P3=0で、唯一のP2（Parent P22-005 Acceptance Criteria未同期）は解消済み。Task／Report／STATE／TODO／parentをcurrent statusとevidenceへ同期した。Local evidenceはWebsite 118／118、check 0／0／0、fresh 42-page build／41-page site、Release Source／Artifact、version baseline、Quickstart E2E、Mago、PHP ID、diff PASS。Browserは公開Artifact不変のため`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`の41 route／127 execution、failure 0、Axe 0、Search／theme／reduced-motion PASSとhash（index `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef`、Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`）を保持し、Browserは再実行していない。P22-005Eの残りはなし。上位parentの残件はreviewed exact Commit、same-SHA CI／Documentation delivery、authorized Production verification、parent P22-005 closeout。Next Action: OrchestratorがAccepted exact snapshotをreviewed exact Commitへ固定する

- [x] P22-005FはSol xHigh final P1=0／P2=0／P3=0でAccepted。実dist unavailableのfocused 1/1／full 118/118、release source／check／fresh build／site／artifact／generated-public boundary／diff PASS、dist restoreを独立確認し、accepted current Artifact hashはindex `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef`／Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`。Browser／Quickstart／Mago／PHP management-IDはtest＋management-onlyで公開Source／Artifact不変のためNot Run。P22-005F Remaining Issuesはnone。Parent remainingはreviewed exact Commit、same-SHA CI／Documentation delivery、Production canonical verification、parent closeout。Next Action: Orchestratorがexact accepted snapshotをreviewed exact Commitへ固定しUser-approved parent remote gatesへ進む（P22-005F child taskではCommit／Stage／Push／CI rerun／Deploy未実施。Parent deliveryはUser承認済みで、Orchestratorがreviewed exact Commit後に実施する）

### Phase 23 Proposal: BlackOps 1.3

以下はUser提示の候補Roadmapであり、実現可能性ReviewとDecision前は公開済みCapabilityとして扱わない。

- [ ] BlackOps CLIのCommand discoveryを設計する。Symfony Console既定`list`を正式Contract化し、`ls` aliasの要否を決定する
- [ ] `route:list`／`route:ls`でCompiled HTTP Routeを安全に一覧する
- [ ] `schedule:list`／`schedule:ls`でCompiled Scheduleを安全に一覧する
- [ ] `worker:list`／`worker:ls`の「Job」をqueued Operation、Worker definition、Transport statsのどれとして表すか決定する
- [ ] `about`、`diagnostics:check`、queue／dead-letter status等の追加CLI候補を既存Commandと重複しない形で評価する
- [ ] FrankenPHP HTTP Worker ModeとDeferred Queue Workerの責務を分けたFeasibility Taskを実施する
- [ ] FrankenPHP Extension Workers／custom Caddy moduleを使う単一OS Process案と、別Process維持案のFailure isolation、Signal、Heartbeat、DB connection、memory、restart、deployを比較する
- [ ] Classic Mode廃止のMigration、Safe 500 negative lane、rollback、1.x breaking release boundaryを決定する
- [ ] 上記Decision後に`1.3.0` Delivery PlanとTask順序を確定する

### Deferred: Documentation Website Publication

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
