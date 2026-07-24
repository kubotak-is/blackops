# D093: Post Phase 10 Roadmap

Status: Decided

## Context

Phase 7からPhase 10で、Install直後と同じQuickstart、Composer Skeleton、Project Root CLI、Validation、FrankenPHP Worker Mode、利用者向けDocumentation WebsiteのRepository内実装まで完了した。

Documentation WebsiteはLocal／CIでBuild、Test、Search、Artifact境界を検証済みだが、Cloudflare Pages ProjectとCredentialは設定していない。Userは2026-07-15に、Websiteの公開を一旦行わず、Framework本体の今後のRoadmapを検討する方針を示した。

Latest Stable `1.0.0`はPhase 8時点であり、`main`にあるBlackOps CLI、Typed Self-handled Operation改善、Validation、Worker Mode Default、Documentation改善はまだStable Releaseへ含まれていない。

## Confirmed Direction

- Documentation Websiteは当面公開しない。
- `docs/guide/`、`docs/internal/`、`docs/website/`はRepository内の正本と検証可能なSiteとして維持する。
- Cloudflare PagesのProject作成、Credential設定、Preview／Production Live EvidenceはPhase 10のBlockerから外し、将来のPublication Taskへ延期する。
- 次のRoadmapは公開SiteよりFramework利用者がApplicationを構築・運用するための機能を優先する。
- Laravel Wayfinderのように、Backendの定義を正本としてFrontendから型付きで呼び出せるOperation／Frontend Bridgeを提供したい。

## User-defined Roadmap Goals

このSectionは選択肢に限定しない。実現したい機能、優先したい利用体験、後回しにしたい項目を自由に追加・並べ替える。

[ROADMAP_GOALS]

- 認証・認可の仕組みを導入する
- Laravelのミドルウェアのように特定のエンドポイントに対して前後処理を挟めるようにする
- DB接続やトランザクションについて設計を考えたい
- 開発時のエラー発生でログを見やすいようにblackopsコマンドになんらか仕様が欲しい
    - エラー時に画面にOperationIdを表示して、そのIdを渡すといい感じのViewを立ち上げる等
    - 本番運用でもエラー時のログの取り扱いについて考えたい
- Operationを正本としてFrontendから型付きで呼び出せる仕組みを提供する。
- Laravel WayfinderのようにURLやHTTP Methodを手書きせず、生成したTypeScript関数からOperationへ接続できるようにする。


[/ROADMAP_GOALS]

## Existing Design Baseline

User-defined Goalのうち、次は基本設計がすでに確定しているがProduction Runtimeへ未実装である。

- Adapter MiddlewareとOperation Middlewareを分離し、どちらも`next`の前後を処理できる玉ねぎ構造とする。
- HTTP MiddlewareはPSR-15を使い、Global登録とOperation／Route単位の追加・除外・順序をBuild時に確定する。
- Credential AuthenticationはAdapter Middlewareで行い、Credentialを破棄してActorContextだけをOperationへ渡す。
- AuthorizationはOperationのPolicyとして宣言し、DeferredはWorker実行時にも最新権限で再認可する。
- DB ServiceはPSR-11／Symfony DIからConstructor Injectionし、Long-running WorkerでHealth Check／ResetできるResourceとして扱う。
- Transactionは必要なOperationへ明示適用するOperation Middlewareを標準とし、複雑な場合はHandler内の手動Transactionも許可する。

したがってRoadmapでは、Middleware／認証認可／Transactionをゼロから再設計せず、現行Specを利用者視点で再確認してPublic API、Runtime、Testへ実装する。

## Operation Frontend Bridge Concept

[Laravel Wayfinder](https://github.com/laravel/wayfinder)はLaravelのControllerとRouteから、URLとHTTP Methodを解決するimport可能なTypeScript関数を生成する。Route Parameter、Query Parameter、Form Variant、Vite Build／Watch時の再生成も扱う。

BlackOpsではControllerではなくOperationをFrontend Contractの正本にできる。段階的な境界は次とする。

1. Compiled Operation／HTTP ManifestからFrontend用Contract Manifestを生成する。
2. `#[Route]`、HTTP Method、Path／Query／Header／Body BindingからTypeScriptのRequest Functionを生成する。
3. `OperationValue`から入力型、`Outcome`から成功型、Validation ViolationとRejectedからError型を生成する。
4. Inline OperationはTyped Response、Deferred OperationはOperation IDとAcknowledgementを返すClient APIとして表現する。
5. Deferred Status／Outcome API確立後、生成ClientからPollingとTyped Outcome取得を提供する。
6. `#[Sensitive]` FieldはClient Contractへ含める可否を明示Ruleで制御し、暗黙に露出しない。

Framework非依存のTypeScript関数を生成してFramework固有Adapterを後から追加する方式を有力候補とするが、初期実装のDepthとFrontend TargetはPhase 15の設計対話で確定する。いずれの方式でも、生成物はApplication所有のBuild Artifactとし、PHP Sourceと手動で二重管理しない。

## Proposed Roadmap

### Phase 10 Closeout: Repository Documentation Complete

- Website Content、Navigation、Search、Build、Artifact Guardを完了条件とする。
- Cloudflare Pages Live DeployをPhase AcceptanceからDeferred項目へ移す。
- Website公開を再開するまで、Local／CIでDocument DriftとBuild Failureだけを検出する。

### Phase 11: Stable 1.1 Release

現在の`main`を新機能追加前にRelease可能な単位へ固定する。

- Stable `1.0.0`からのCompatibility Audit
- Project Root `blackops`、Canonical Command、旧Command AliasのUpgrade Guide
- Typed Self-handled Operation、Validation、Worker Mode DefaultのRelease Note
- Framework／SkeletonのVersion、Constraint、Create-project Smoke
- Full Quality／Consumer／Publication Gate後にFrameworkとSkeleton `1.1.0`を公開

完了条件は、新規Applicationが`composer create-project blackops/skeleton:^1.1`で作成でき、Documented Quickstartを完走できることとする。

### Phase 12: Middleware and Authorization Runtime

- PSR-15 Adapter MiddlewareのGlobal／Route単位登録
- `next`前後でRequest／Responseを処理する玉ねぎPipeline
- Dispatch／Execution Operation Middleware
- Middleware順序、重複、依存関係のManifest Compile検証
- AuthenticatorからCredentialを除いたActorContextへの変換
- `#[Authorize]` Policy、Rejected Lifecycle、HTTP 401／403境界
- Deferred受付時／Worker実行時の再認可

### Phase 13: Database and Transaction Runtime

- Named DBAL ConnectionのApplication ConfigurationとDI登録
- Repository／Application ServiceへのConstructor Injection例
- `#[Transactional(connection: 'default')]`とTransaction Operation Middleware
- Commit／Rollback、Nested呼び出し、複数Connection、Manual Transactionの境界
- Worker ModeでのConnection Health Check／Reset／Reconnect
- 業務DBとBlackOps Journal／Outcome DBが同一または別Connectionの場合の保証差

初期ScopeではORMやRepository基底ClassをFrameworkへ組み込まず、DBAL ConnectionとTransaction境界を提供する。

### Phase 14: Operation Diagnostics

- Error ResponseとApplication LogへOperation IDを必ず相関可能な形で表示
- `php blackops operation:inspect <operation-id>`でLifecycle、Attempt、Error、OutcomeをTerminal表示
- Development限定のLocal ViewerをCLIから起動し、Operation IDのTimelineをBrowser表示
- Sensitive Projection、Credential非表示、Local Bind、明示起動の安全境界
- ProductionではViewerを自動起動せず、構造化Log、Journal Query、外部Observabilityへの相関方法を提供
- Missing／Purged／Unauthorized Operation IDの安全なError表示

### Phase 15: Operation Frontend Bridge Foundation

OperationからFrontend ContractとTypeScript呼び出し関数を生成する。

- Operation／HTTP Manifestを正本とするFrontend Contract Manifest
- `php blackops frontend:generate`等の明示Generator Command
- URL、HTTP Method、Path／Query／Header／Body Parameterの型付き関数
- `OperationValue`入力型、Validation Violation、成功`Outcome`型
- Request DescriptorとFetch Clientの責務境界
- Generated Artifact Drift TestとVite Build連携
- Sensitive Fieldの除外／明示許可Rule

Wayfinder相当のURL／Method生成を最初のVertical Sliceとする案と、Typed Request／Responseまで同時に含める案は、Phase 15のDecisionで選択する。

### Phase 16: Deferred Status and Outcome API

202 Accepted後に、利用者がDatabase実装を直接触らずOperationの状態と結果を取得できる境界を提供する。

- Operation IDによるStatus Query
- Pending／Running／Retry Scheduled／Completed／Rejected／Failed／Dead Letteredの安定した表現
- Typed Outcome取得とTerminal Errorの安全な表現
- HTTP Status／Outcome Endpointと`Location`等のPolling Contract
- Applicationが認証・認可を差し込めるAccess Policy境界
- Quickstart、Integration Test、利用者向けTutorial

初期ScopeではGenerated TypeScript SDKを含めず、HTTP Contractが安定してからClient Generationを別Phaseで扱う案を推奨する。

Operation Frontend BridgeからDeferred結果まで一貫して到達できるようにする。

### Phase 17: Reliability and Delivery

- Idempotency Keyの受付、保存、重複時Contract
- Transactional Outbox Persistence AdapterとRelay
- Canonical JournalからObserver Projectionを再送するCLI
- Relay／Replayのat-least-once、Fencing、Retry、Dead Letter運用
- Handler側Idempotency責務とFramework支援の明文化

### Phase 18: Security Hardening and Observability

- Journal／Status／Outcome参照制御とTenant分離
- Canonical Payload／Transportの暗号化Capability
- 構造化Log Schema Version
- OpenTelemetry Trace／Metric Adapter、Health／Readiness

### Later: Ecosystem Expansion

- OpenAPI／Contract Diff、React／Vue等のFramework Adapter
- SQLite、MySQL、SQS、Kafka Adapter
- Scheduled Operation、Batch、Saga
- Admin UI、検索、再試行、Cancel
- Documentation Website Publication、Custom Domain、Version Selector

## Editable Roadmap Order

この順序はUserが自由に変更できる。番号、項目名、Phase間の依存関係も直接編集してよい。

[ROADMAP_ORDER]

1. Stable `1.1.0` Release
2. Middleware and Authorization Runtime
3. Database and Transaction Runtime
4. Operation Diagnostics
5. Operation Frontend Bridge
6. Deferred Status and Outcome API
7. Reliability and Delivery
8. Security Hardening and Observability

[/ROADMAP_ORDER]

## Question 1: Roadmap Order

`[ROADMAP_ORDER]`の順序をどうするか。

### Options

- A: 現在の`[ROADMAP_ORDER]`を採用する
- B: `[ROADMAP_ORDER]`をUserが直接編集し、その順序を採用する

### Recommendation

Aを推奨する。

Phase 9／10の成果をStableへ固定した後、Applicationの横断処理とSecurity境界、DB／Transaction、Diagnosticsを先に整える。その上でFrontend BridgeとDeferred Query APIを接続すると、生成Clientへ認証、Error、Transaction保証を後付けせずに済む。

[ANSWER]

A

[/ANSWER]

## Question 2: Deferred API Scope

Deferred Status／Outcome APIをどこまで含めるか。

### Options

- A: Status／Outcome HTTP APIとPolling Contractまで。Generated Clientは後続にする
- B: Status／Outcome APIとTypeScript Client Generatorを同時に作る
- C: Framework内のPHP Query APIだけを作り、HTTP EndpointはApplicationへ任せる

### Recommendation

Aを推奨する。

BlackOpsが202 Responseを返すなら、Operation IDから結果へ到達する標準HTTP Contractまで提供する方が一貫する。一方、Client GeneratorはSchema、Versioning、Error Result、Frontend Securityまで判断範囲が広いため分離する。

[ANSWER]

A

[/ANSWER]

## Question 3: Dormant Documentation Delivery

Websiteを公開しない間、GitHub ActionsのCloudflare Deploy Jobをどう扱うか。

### Options

- A: Build／Artifact検証とCredential-gated Deploy Jobを維持する。Credential未設定のため公開は発生しない
- B: Deploy JobをWorkflowから外し、Build／Artifact検証だけを残す
- C: Documentation Workflow全体を停止し、PHP CIだけを実行する

### Recommendation

Aを推奨する。

現状でもCredential未設定時はDeploy StepをSkipし、Website品質だけを継続検証できる。公開再開時の再実装を避けられ、Secretを登録しない限り外部公開は行われない。

[ANSWER]

A

[/ANSWER]

## Question 4: Operation Frontend Bridge Depth

最初のFrontend Bridgeをどこまで実装するか。

### Options

- A: URL／HTTP Method／Route Parameterを返すWayfinder相当のRequest Descriptorだけを生成する
- B: OperationValue／Outcome／Validation／Rejectedまで含むFull Typed Fetch Clientを一度に生成する
- C: Aを最初のVertical Sliceとし、Contract Manifestを維持したままBへ段階的に拡張する
- D: `[ROADMAP_GOALS]`へ別の到達点を自由記述する

### Recommendation

Cを推奨する。

最初にRouteとOperationの接続を小さく検証しながら、Manifestを後続のValue／Outcome型生成にも使える形で設計する。一度にFull Clientを作ると、PHPからTypeScriptへの型変換、Error Model、Deferred Polling、認証まで同時に固定する必要がある。

[ANSWER]

Deferred to Phase 15 design dialogue.

[/ANSWER]

## Question 5: Frontend Target and Generation

生成物とFrontend Frameworkの境界をどうするか。

### Options

- A: Framework非依存のTypeScript関数を生成し、React／Vue／Svelte等から共通利用する。Vite Pluginは薄い再生成Adapterとする
- B: React Hooksを最初の正式APIとして生成する
- C: OpenAPIを先に生成し、既存の外部Client Generatorへ委ねる
- D: `[ROADMAP_GOALS]`へ別方式を自由記述する

### Recommendation

Aを推奨する。

BlackOpsはHeadless Frameworkなので、生成ContractもFrontend Frameworkへ依存させない。React Query、Vue Query、SvelteKit等との統合は、安定したTypeScript関数の上にAdapterとして追加できる。

[ANSWER]

Deferred to Phase 15 design dialogue.

[/ANSWER]

## Phase-specific Decisions

Roadmap確定時点では各機能の詳細選択を強制しない。実装Phase開始前に、既存Specを土台として次を個別Decisionで確認する。

- Middleware: Adapter／Operation Pipeline、Global／Endpoint単位登録、順序、Response加工
- Authentication／Authorization: Framework ContractとApplication所有範囲、Session／JWT／外部IdP Adapter
- Database／Transaction: Named Connection、Transaction Middleware、Manual API、ORMをScopeへ含めるか
- Operation Diagnostics: Terminal Inspect、Local Viewer、Production Log／Observability境界
- Frontend Bridge: PHP／TypeScript型変換、Generated Artifact、Vite、Sensitive、Deferred Client

## Decision

[DECISION]

1. Phase 10をRepository Documentation CompleteとしてCloseし、Cloudflare Live DeployをLaterへ移す。
2. Credential-gated Documentation Deploy Jobは維持する。Credential未設定のため公開は発生せず、Build／Artifact検証を継続する。
3. Phase 11以降は`[ROADMAP_ORDER]`の順序を採用する。
4. Deferred Status／OutcomeはHTTP APIとPolling Contractまでを標準Scopeとし、Generated Client統合はFrontend Bridgeの後続設計で扱う。
5. User-defined Goalと`[ROADMAP_ORDER]`を正本として、Phase 11以降の順序と各Phaseの完了条件をPost Phase 10 Roadmap Specificationへ記録する。
6. Frontend Bridgeの初期DepthとFrontend TargetはPhase 15開始前の設計対話へDeferredし、このDecisionでは固定しない。
7. PhaseごとにDecision／Specification／Task Packet／Report／Commit境界を維持する。
8. Website公開再開はCredential設定だけで暗黙に開始せず、新しい明示TaskとしてUser確認後に行う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Phase 10は公開Hostを持たなくてもRepository Documentation Completeとして完了できる。
- Cloudflare Pages設定は未解決Blockerではなく、Userが再開を決めるまでのDeferred Scopeとなる。
- Stable Release後はMiddleware／認証認可、DB／Transaction、Diagnostics、Frontend Bridgeの順でApplication開発体験を構築する。
- Middleware、Authentication、Transactionは既存Specを出発点にし、Phase開始時のDecisionでPublic APIと実装Scopeを再確認する。
- Operation DiagnosticsはTerminalとDevelopment Viewer、Production Observabilityを同じOperation ID Query Modelへ接続する。

[/CONSEQUENCES]

## References

- [Developer Experience Roadmap](../spec/41-developer-experience-roadmap.md)
- [Phase 10 Delivery Plan](../spec/58-phase-10-delivery-plan.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [Authentication and Middleware](../spec/06-auth-and-middleware.md)
- [Durable Journal and Transactions](../spec/11-durable-journal-and-transactions.md)
