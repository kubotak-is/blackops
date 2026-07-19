# D103: Full-stack Reference Application

Status: Decided

## Context

Phase 15で、HTTP Operationを正本としてFramework-neutralなTypeScript Operation Objectを生成し、`.url()`、`.toRequest()`、`.fetch()`を提供した。Phase 16ではDeferred Operationの202受付から`.status()`／`.wait()`でTyped Outcomeへ到達するPublic Contractを完成させた。

`examples/quickstart/`はInstall直後のApplicationと`blackops/skeleton`のSource of Truthを兼ねるため、Frameworkの最小構成と配布互換性を優先している。一方、認証されたUserが実際に画面を操作し、Validation、Authorization、Database Transaction、Inline／Deferred Operation、Generated Frontend Clientを一つのApplicationで体験できるReference Exampleはまだない。

Userは、SvelteKit Frontendを持つLogin必須の投稿Applicationを最初の次期Milestoneにしたいと示した。候補を比較した結果、TODO Applicationよりも同期投稿、非同期生成、認証認可、将来のReliable Deliveryを自然に結び付けられるコミュニティ掲示板を有力案とする。

このReference ApplicationはFrameworkへ不足機能を無計画に追加する理由にはしない。既存Public APIだけで成立する範囲を先に実装し、見つかったFramework Gapは別Decision／Specificationへ返す。

## Decision Drivers

- Install直後のSkeletonではなく、実Applicationに近いConsumer Experienceを提示する
- PHP BackendとSvelteKit Frontendの間でGenerated Operation Objectを実利用する
- Inline CRUDとDeferred Status／Outcomeを同じUser Journeyで見せる
- Authentication、Authorization、Sensitive Data、Browser Credentialの責任分界を実例で示す
- 従来Phase 17として計画していたReliability and DeliveryのIdempotency／Outboxを後から自然に接続できる題材にする
- Local ComposeとCIで再現でき、外部SaaSや公開Websiteを完了条件にしない
- `examples/quickstart/`と`blackops/skeleton`の配布Sourceを肥大化させない
- Visual Designを重視しつつ、掲示板のAccessibilityと操作性を装飾より優先する

## Proposed User Journey

```text
Register / Login
  -> View post feed
    -> Create post with validation
      -> Edit or delete own post through authorization
        -> Add a comment
          -> Request weekly digest generation
            -> 202 accepted
              -> accepted / running / retry_scheduled
                -> completed typed outcome
                  -> View generated digest
```

## Proposed Application Boundary

```text
Browser
  -> SvelteKit UI / BFF
    -> Application-owned authentication and session boundary
      -> BlackOps HTTP Operations
        -> PostgreSQL application data
        -> BlackOps Journal / Outcome
        -> Deferred Worker
```

```text
examples/community-board/
├── app/
├── bootstrap/
├── config/
├── migrations/
├── public/
├── frontend/
│   ├── src/
│   ├── static/
│   └── package.json
├── tests/
├── compose.yaml
└── README.md
```

Generated TypeScriptはSvelteKitのServer-only Boundaryである`frontend/src/lib/server/blackops/generated/`へ出力し、Application-owned Wrapperと生成Sourceを分離する。

## Question 1: Roadmap Placement

Full-stack Reference Applicationを既存Roadmapのどこへ置くか。

### Options

- A: 新しいPhase 17をFull-stack Reference Applicationとし、Reliability and DeliveryをPhase 18、Security Hardening and ObservabilityをPhase 19へ移す
- B: 既存Phase 17 Reliability and Deliveryの最初のTaskとしてReference Applicationを作り、Idempotency／Outboxも同じPhaseで完成させる
- C: 既存Phase 17／18を先に完了し、Reference ApplicationはLater Ecosystemへ置く

### Recommendation

Aを推奨する。

現在完成しているPhase 12からPhase 16のPublic Surfaceだけで、認証、投稿、Validation、Authorization、Transaction、Frontend Bridge、Deferred Resultを一度Consumer Applicationとして統合する。そこで得た実利用上のGapをPhase 18 Reliability and Deliveryへ入力すれば、IdempotencyやOutboxを抽象的に設計せず、二重投稿防止や通知配送という実Journeyで検証できる。

[ANSWER]

A

[/ANSWER]

## Question 2: Product Theme and Initial Feature Set

Reference Applicationの題材と最初の機能範囲をどうするか。

### Options

- A: `BlackOps Board`という認証付きコミュニティ掲示板。登録、Login／Logout、投稿一覧、投稿作成／編集／削除、コメント、週次Digest生成を含む
- B: Team TODO Board。Project、Task、Assignee、Status変更、Activity Report生成を含む
- C: Mini Issue Tracker。Repository、Issue、Comment、Label、Release Note生成を含む

### Recommendation

Aを推奨する。

掲示板は初見の読者がDomainを理解しやすく、User所有ResourceへのAuthorizationと投稿Validationを自然に示せる。週次Digest、通知、検索Projection、User Data Exportを後から追加でき、Deferred OperationとPhase 18のOutboxにも拡張しやすい。

[ANSWER]

A

[/ANSWER]

## Question 3: Example Repository Boundary

`quickstart`／SkeletonとReference Applicationをどう分離するか。

### Options

- A: Main Repository内の独立した`examples/community-board/`として追加し、`examples/quickstart/`とSkeleton Publication Sourceは変更しない
- B: `examples/quickstart/`を掲示板へ拡張し、Install直後からSvelteKitと全機能を含める
- C: BlackOps Repository外の独立Repositoryとして最初から開発する

### Recommendation

Aを推奨する。

FrameworkとExampleをAtomicに検証しながら、SkeletonのInstall速度、理解容易性、Publication Contractを維持できる。Reference Applicationが成熟して独立Releaseを必要とした時点で、Repository Splitを別途判断できる。

[ANSWER]

A

[/ANSWER]

## Question 4: Frontend and Authentication Topology

Browser CredentialとPHP APIへの接続をどう構成するか。

### Options

- A: SvelteKitをSame-origin BFFとして使う。BrowserはHttpOnly／Secure／SameSite Cookieだけを持ち、SvelteKit ServerがApplication-owned Session CredentialをBlackOps APIへ付与する。認証Session発行はOperation OutcomeへSecretを保存しないApplication-owned Endpointとする
- B: BrowserがBlackOps APIへ直接接続し、Login OperationのOutcomeで得たBearer TokenをLocal Storageへ保存する
- C: 認証は固定Demo Headerだけにし、登録、Password、Sessionを実装しない

### Recommendation

Aを推奨する。

Generated Frontend TypeはAuthenticationを代替しないという既存責任境界を維持し、CredentialをBrowser JavaScript、Generated Tree、Operation Outcome、Canonical Journalへ残さない。SvelteKitのServer側でもGenerated Request Contractを再利用し、画面のProgress参照等はSame-origin BFF経由で行う。

Application-owned Authentication Endpointを現在のHTTP Compositionへ安全に追加できない場合は、Production Codeを先に拡張せずFramework GapとしてDecisionへ返す。

[ANSWER]

A
BlackOpsとSvelteKitの通信は.server.tsのみに留める（つまりBFFというのはこの理解で一致していればOK）

[/ANSWER]

## Question 5: First Deferred Journey

UIで最初に完走させるDeferred Operationを何にするか。

### Options

- A: `GenerateWeeklyDigest`。対象週の投稿とコメントを集計し、保存済みDigestへの参照をTyped Outcomeで返す
- B: `ExportMyPosts`。User自身の投稿とコメントをArchiveへまとめ、Download参照をTyped Outcomeで返す
- C: `NotifyPostSubscribers`。投稿後に購読者へ通知し、送信件数をTyped Outcomeで返す

### Recommendation

Aを推奨する。

外部Mail、Object Storage、Message BrokerなしでLocalに完結し、受付、Worker処理、Retry、完了Outcomeを画面へ見せられる。通知はPhase 18のTransactional Outboxを使うJourney、ExportはSecurity／Retentionを含む後続Journeyとして残す。

[ANSWER]

A

[/ANSWER]

## Question 6: Persistence and Application Architecture

業務Data Accessをどこまで標準化して見せるか。

### Options

- A: Doctrine DBAL、Application-owned Repository、Migration、`#[Transactional]`を使う。ORM、Framework Repository基底Class、Generic CRUD層は導入しない
- B: Example専用にDoctrine ORMを追加し、Entity／Repositoryを中心に構成する
- C: FrameworkへActive RecordとRepository基底Classを追加してからExampleを作る

### Recommendation

Aを推奨する。

Phase 13で確定したPublic Contractをそのまま使い、Frameworkが業務ModelやRepository設計を所有しないHeadless境界を示せる。Example固有のQuery／RepositoryはApplication Codeとして実装する。

[ANSWER]

A

[/ANSWER]

## Question 7: Visual Design Direction and Taste Skill Scope

導入済み`design-taste-frontend`をどの範囲へ適用するか。

### Options

- A: Landing、Authentication、Typography、Color、Spacing、MotionのDesign Directionに使う。Feed、Form、Operation Progress等のProduct UIはAccessibility、Responsive Layout、Clear Stateを優先してApplication固有に設計する
- B: SkillのRulesをすべての画面へ一律適用し、掲示板のProduct UIもLanding Pageと同じ表現にする
- C: Reference ApplicationではSkillを使用せず、無装飾の機能確認UIだけを作る

### Recommendation

Aを推奨する。

Taste Skill自身がLanding／Portfolio／Redesignを主対象とし、DashboardやMulti-step Product UIを対象外としている。Visual Systemの出発点として利用しつつ、掲示板の操作性、Focus、Contrast、Error、Loading、Reduced Motionを独立したAcceptance Criteriaにする。

[ANSWER]

A

[/ANSWER]

## Question 8: Delivery and Publication Boundary

Reference ApplicationをどこまでDeliveryするか。

### Options

- A: Local Docker Compose、Seed Data、Browser E2E、Screenshot／Guide、CIを完了条件とし、外部Hosting／Documentation Website Publicationは含めない
- B: Cloudflare等へ公開してLive URLを完了条件にする
- C: Source CodeとUnit Testだけを作り、実Browser JourneyとComposeは後続にする

### Recommendation

Aを推奨する。

外部CredentialやHosting判断なしに再現可能なReference Applicationを完成できる。公開はSecurity、Cost、Domain、Retentionを判断する別Taskとし、既存Documentation Websiteの未公開方針も変更しない。

[ANSWER]

A

[/ANSWER]

## Proposed Initial Scope

Q1からQ8で推奨案を採用する場合、Phase 17は次を含む。

- `examples/community-board/`の独立Application構成
- PHP Backend、SvelteKit、PostgreSQL、Deferred WorkerのLocal Compose
- User登録、Login、Logout、Password Hash、HttpOnly Session
- Post Feed、Post Detail、Create、Edit、Delete
- Comment Create
- OperationValue Validationと422 Field Error表示
- ActorContextとOwner Authorization
- DBAL Repository、Migration、Transactional Operation
- `frontend:generate`によるSvelteKit内Generated Operation Object
- `GenerateWeeklyDigest.fetch()`から`.wait()`／Typed OutcomeまでのUI
- accepted／running／retry_scheduled／completed／failedのAccessible Progress表示
- Sensitive／Credential／Authorization／Unknown OperationのE2E
- Backend、Frontend、Real Browser、Consumer DriftのCI
- Reference Application READMEと利用者向けGuide

## Non-goals

- Image／File Upload、Rich Text Editor
- OAuth／Social Login、MFA、Password Reset、Email Verification
- Real Email／Push Notification／Webhook Delivery
- Full-text Search、Recommendation、Content Moderation、Admin Console
- Like／Reaction、Follow、Private Message
- Multi-tenant Framework、Billing
- Transactional Outbox、Idempotency Key、Observer Replayの同時実装
- Native Svelte Adapter、Store、Form Action GeneratorのFramework Public API追加
- Framework ORM／Repository Base Class／Active Record
- External Hosting、Custom Domain、Documentation Website Publication

## Response

UserはQuestion 1からQuestion 8まですべてAを選択した。Question 4には、BlackOpsとSvelteKitの通信を`.server.ts`だけに留めるという条件を追加した。この理解はBFF境界と一致する。

Generated Operation ObjectはSvelteKitのServer-only Directoryに生成し、Application-owned `.server.ts` WrapperだけがImportする。BrowserはSvelteKitのForm Action／BFF Endpointを呼び、BlackOps HTTP Endpointへ直接接続しない。

## Decision

[DECISION]

1. Phase 17をFull-stack Reference Applicationとし、Reliability and DeliveryをPhase 18、Security Hardening and ObservabilityをPhase 19へ移す。
2. Reference Applicationは`BlackOps Board`という認証付きコミュニティ掲示板とし、User登録、Login／Logout、投稿一覧、投稿作成／編集／削除、コメント、週次Digest生成をInitial Scopeとする。
3. `examples/community-board/`をMain Repository内の独立Applicationとして追加し、`examples/quickstart/`と`blackops/skeleton`のSource／Publication Contractを変更しない。
4. SvelteKitをSame-origin BFFとし、BrowserはSvelteKitだけへ接続する。BlackOps通信はSvelteKitのServer-only Moduleへ限定する。
5. Generated TypeScriptは`frontend/src/lib/server/blackops/generated/`へ出力し、Application-owned `.server.ts` Wrapper以外からImportしない。
6. User登録、Session発行／失効、Password検証はApplication-owned Authentication Endpointで扱い、Operation Outcome、Canonical Journal、Generated ContractへCredentialを保存しない。PHP Front ControllerのApplication-owned RouterがAuthentication Routeを処理し、その他をBlackOps Handlerへ委譲する。
7. Browser SessionはHttpOnly／SameSite Cookieとし、SvelteKit ServerがApplication-owned Session CredentialをBlackOps APIへ付与する。LocalとProductionのSecure Cookie差はConfigurationで明示する。
8. 最初のDeferred Journeyは`GenerateWeeklyDigest`とし、202受付からGenerated `.status()`／`.wait()`、Typed Outcome、保存済みDigest表示までをUIで完走する。
9. PersistenceはDoctrine DBAL、Application-owned Repository、Migration、`#[Transactional]`を使い、ORM、Framework Repository基底Class、Generic CRUD層を追加しない。
10. `design-taste-frontend`はLanding、Authentication、Typography、Color、Spacing、MotionのDesign Directionへ使う。Feed、Form、Operation ProgressはAccessibility、Responsive Layout、Clear Stateを優先する。
11. Phase 17のDeliveryはLocal Docker Compose、Seed Data、Real Browser E2E、Screenshot／Guide、CIまでとし、外部HostingとDocumentation Website Publicationを含めない。
12. Reference ApplicationでFramework Gapを発見した場合、Example内のWorkaroundやProduction Codeの無断拡張を行わず、別Decisionへ返す。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Phase 17はFramework機能追加よりConsumer統合を優先し、既存Public Surfaceの実用性を検証するPhaseになる。
- Reliability and DeliveryとSecurity Hardening and ObservabilityはPhase番号が一つずつ後ろへ移る。
- Quickstart／Skeletonは最小Installed Applicationの役割を維持し、Community BoardのFrontend DependencyやDomain Codeを含まない。
- Browser BundleへGenerated Operation Object、BlackOps Base URL、Application Session Credentialを含めないServer-only Import Guardが必要になる。
- AuthenticationはOperationではないApplication-owned HTTP Boundaryを持ち、CredentialをJournalへ残さない。Outer Router、Authentication Handler、BlackOps Handlerの責務とTestが必要になる。
- SvelteKit BFFはHTTP／Validation／Authorization／Status Resultを画面向けModelへ変換し、Framework ContractをBrowserへそのまま透過しない。
- Phase 18はCommunity Boardの二重投稿防止と通知配送をIdempotency／OutboxのConcrete Acceptance Journeyとして利用できる。
- Visual品質に加え、Keyboard、Focus、Contrast、Reduced Motion、Loading、Error、Empty StateのAcceptance Criteriaが必要になる。
- External Hostingを行わないため、Phase 17完了はLocal／CI Evidenceで判定する。

[/CONSEQUENCES]

## References

- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [D095 Phase 12 Middleware and Authorization Runtime](095-phase-12-middleware-and-authorization-runtime.md)
- [D096 Phase 13 Database and Transaction Runtime](096-phase-13-database-and-transaction-runtime.md)
- [D100 Phase 15 Operation Frontend Bridge](100-phase-15-operation-frontend-bridge.md)
- [D102 Phase 16 Deferred Status and Outcome API](102-phase-16-deferred-status-and-outcome-api.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [Operation Frontend Bridge](../spec/67-operation-frontend-bridge.md)
- [Deferred Status and Outcome API](../spec/69-deferred-status-and-outcome-api.md)
