# Full-stack Reference Application

## Goal

`examples/community-board/`に、BlackOps PHP BackendとSvelteKit Frontendを持つ認証付きコミュニティ掲示板`BlackOps Board`を構築する。

このApplicationは、Validation、Authentication、Authorization、Database Transaction、Inline／Deferred Operation、Generated Frontend Contract、Status／Outcomeを実Applicationに近いUser Journeyで統合するReference Exampleである。`examples/quickstart/`と`blackops/skeleton`のSource of Truth、Publication Contract、Install後の最小構成は変更しない。

## Product Scope

Initial Scopeは次とする。

- User登録、Login、Logout
- Post FeedとPost Detail
- Post作成、編集、削除
- Comment作成
- User自身が作成したPostだけを編集／削除できるAuthorization
- 週単位のPost／Commentを集計するDeferred Digest生成
- Deferred受付、処理中、再試行、完了、失敗を示すProgress UI
- Completed Typed Outcomeから保存済みDigestを表示するJourney

Image、File Upload、Rich Text、OAuth、MFA、Password Reset、Email Verification、Like、Follow、Search、Moderation、Admin UIはInitial Scopeに含めない。

## Runtime Topology

```text
Browser
  -> SvelteKit
     - Page Server Load
     - Form Actions
     - BFF Route Handlers
     - HttpOnly Session Cookie
     - Server-only Generated Operation Wrapper
       -> PHP Application Front Controller
          - Application-owned Authentication Router
          - BlackOps Application HTTP Handler
            -> PostgreSQL
            -> Deferred Worker
```

BrowserはSvelteKit Originだけへ接続する。BlackOps HTTP Base URL、Application Session Credential、Generated Operation ObjectをBrowser JavaScriptへ公開しない。

## Source Layout

```text
examples/community-board/
├── app/
│   ├── Domain/
│   │   └── Board/
│   ├── Infrastructure/
│   ├── Feature/
│   ├── Http/
│   ├── Security/
│   └── ApplicationServiceProvider.php
├── bootstrap/
├── config/
├── migrations/
├── public/
├── frontend/
│   ├── src/lib/server/blackops/generated/
│   ├── src/lib/server/blackops/*.server.ts
│   ├── src/routes/
│   ├── static/
│   └── package.json
├── tests/
│   ├── Consumer/
│   ├── Frontend/
│   └── Browser/
├── compose.yaml
└── README.md
```

Generated DirectoryはOwnership Markerを持つFramework生成物とし、Application-owned `.server.ts` Wrapperを上書きしない。`frontend:check`はServer-only OutputのPath／Bytes／余剰Fileを検証する。

BoardのDomain Model、Repository Port、Clock／ID Generator Port、Domain Exception、DomainServiceは`app/Domain/Board/`が所有する。Doctrine DBAL Repository、System Clock、Symfony UUIDv7 Generator等の技術Adapterは`app/Infrastructure/`が所有する。

OperationはBlackOps／HTTPのApplication Boundaryとし、Value／ActorをDomainServiceへ渡してDomain ResultをOutcomeへ変換する。Postの存在確認、Owner判定、Row Lockを伴う更新／削除判断、ID／時刻生成、Repository操作順序等のDomain LogicをOperationへ置かない。Domain LayerはBlackOps Attribute、Operation、Outcome、Operation Exceptionへ依存しない。

## BFF and Server-only Contract

- `config/frontend.php`のOutputは`frontend/src/lib/server/blackops/generated`とする。
- Generated Moduleは`frontend/src/lib/server/blackops/*.server.ts`からだけImportする。
- `.svelte`、`+page.ts`、`+layout.ts`、Browser用Shared ModuleからGenerated ModuleをImportしない。
- Page Server Load、Form Action、`+server.ts`はApplication-owned Wrapperを呼ぶ。
- CIはBrowser BundleとClient Module GraphへGenerated Module、BlackOps Base URL、Credential Injection Codeが入らないことを検証する。
- Browserへ返すBFF DTOは画面に必要なFieldだけを持ち、Framework Internal Error、Actor、Credential、Journal Recordを透過しない。

## Authentication and Session Boundary

AuthenticationはOperationとして実装しない。PHP Front ControllerのApplication-owned RouterがAuthentication Routeを処理し、それ以外を`Application::http()`のBlackOps Handlerへ委譲する。

Initial Authentication Routeは概念上、次を持つ。

```text
POST   /auth/users
POST   /auth/sessions
DELETE /auth/sessions/current
```

- User PasswordはPHPのCurrent Recommended Password Hashで保存し、PlaintextをLog、Journal、Outcome、Frontend Artifactへ含めない。
- Session CredentialはCryptographically Secure Random Valueとし、DatabaseにはHashだけを保存する。
- Raw Session CredentialはSession作成時にSvelteKit Serverへ一度だけ返す。
- SvelteKitはCredentialをHttpOnly／SameSite Cookieへ保存し、Browser JavaScriptへ返さない。
- Production CookieはSecureを必須とし、Local HTTPでは明示ConfigurationでのみSecureなしを許可する。
- SvelteKit ServerはBlackOps API呼出時にCredentialをAuthorization Headerへ付与する。
- Application `HttpAuthenticator`はCredential Hash、Expiry、Revocationを検証し、`ActorRef`だけをRuntimeへ渡す。
- Login成功時にSessionをRotationし、Logout時にServer側Sessionを失効する。
- Authentication Responseは`Cache-Control: private, no-store`とする。
- Registration／LoginのValidation ErrorはCredential存在を過剰に漏らさない安定Codeへ投影する。

Authentication RouterはApplication Codeであり、FrameworkのOperation Manifest、Frontend Contract、Journalへ含めない。Outer RouterのUnknown Route、Method、Malformed Body、FailureはSafe Responseを返し、BlackOps Routeと衝突させない。

## Data Model

Application Migrationは最低限、次のTableを所有する。

```text
board_users
board_sessions
board_posts
board_comments
board_digests
```

- Primary KeyはUUIDv7またはFrameworkと衝突しないApplication-owned IDとする。
- User EmailはCanonical比較用の正規化値と表示値を分離し、Unique制約を持つ。
- SessionはToken Hash、User ID、Issued At、Expires At、Revoked Atを持つ。
- PostはAuthor ID、Title、Body、Created At、Updated Atを持つ。
- CommentはPost ID、Author ID、Body、Created Atを持つ。
- DigestはUUID ID、Requested User ID、ISO Week、Statusに依存しない保存済みPlain Text Content、Post Count、Comment Count、Created Atを持つ。Requested User／WeekをUniqueにせず、成功したGenerate Requestごとに新しいImmutable Rowを作る。
- Digest Contentは件数だけから決定的に生成し、Post Title／Body／PreviewとComment本文を保存しない。後からPostをHard Deleteしても既存Digest Snapshotは書き換えず、再生成時はその時点で存在するRowだけを改めて集計する。
- PostはHard Deleteし、配下CommentもForeign Key Cascadeで同じTransaction内に削除する。削除後はFeed／Detail／Digestの対象外とし、復元機能を持たない。
- Hard Deleteの対象はApplication Tableである。Canonical JournalはFramework Retention Contractに従ってOperationValueを保持し得るため、Post削除をJournal Scrubbingとして扱わない。
- Application Data RetentionとUser削除はInitial Scopeで無断決定せず、必要になった時点でDecisionへ返す。

Data AccessはDoctrine DBALとApplication-owned Repositoryを使う。ORM、Active Record、Framework Repository Base Classを導入しない。

## Operation Model

Initial HTTP Operationは次を基本とする。実際のType ID、Path、Value／Outcome名はTask実装前にTask Packetで固定する。

| Capability | Strategy | Authorization | Result |
|---|---|---|---|
| List posts | Inline | Authenticated | Paginated safe post summaries |
| Show post | Inline | Authenticated | Post detail and safe comments |
| Create post | Inline | Authenticated | Created post reference |
| Update post | Inline | Owner | Updated post reference |
| Delete post | Inline | Owner | Empty outcome |
| Add comment | Inline | Authenticated | Created comment reference |
| Generate weekly digest | Deferred | Authenticated | Digest reference and aggregate counts |
| Show digest | Inline | Requesting user | Stored digest detail |

FeedとDetailのPaginated Summary／Comment Listは[Structured Outcome Contract](73-structured-outcome-contract.md)のReadonly DTOとTyped Listで表現し、JSON StringやFrontend生成Opt-outを使わない。

Mutation Operationは`#[Transactional]`を使い、業務更新と成功Terminal Journal／Outcomeを同じApplication Connectionへ含める。RepositoryはCurrent Actorを暗黙Globalから読まず、Operation／Application Serviceから明示的なUser IDを受け取る。

## Validation

- Registration: Email形式、Password Length、Display Name Length
- Post: Title Length、Body Length
- Comment: Body Length
- Digest: ISO WeekのCanonical `YYYY-Www`形式と、そのISO Yearに実在するWeek
- Path／Query Native Scalarは既存HTTP Coercion Contractを使う
- 422 ResponseはGenerated Validation ResultからSvelteKit Form ErrorへField単位で投影する
- Password、Session Credential、Authorization HeaderをValidationのRejected ValueやUIへ反射しない

具体的なLength値はUI CopyとDatabase Columnを同時に決めるTask Packetで固定し、PHPとTypeScriptへ手書きで二重定義しない。

## Deferred Digest Journey

`GenerateWeeklyDigest`は指定週の、認可されたUserが参照可能なPost／Commentを集計して`board_digests`へ保存する。

集計境界はUTCのISO Weekとし、月曜00:00以上、翌月曜00:00未満を対象にする。実行時点でApplication Tableに存在するPost／Commentだけを数え、Plain Text Contentは件数から決定的に生成する。同じUser／Weekの再実行は新しいDigest ID／Rowを作る。

```text
SvelteKit Form Action
  -> GenerateWeeklyDigest.fetch()
    -> 202 accepted + operationId
      -> BFF status/wait endpoint
        -> GenerateWeeklyDigest.status() / wait()
          -> completed typed outcome
            -> ShowDigest.fetch()
              -> Digest page
```

- `.fetch()`は自動Pollingしない。
- BrowserはOperation IDをBFFへ渡せるが、BlackOps Status Resourceへ直接接続しない。
- BFFはCurrent Sessionを使ってStatus Query Authorizationを行う。
- `.wait()`はRequest Abort Signalと有限`maxWaitMilliseconds`を必須とする。
- UIは少なくともaccepted、running、retry_scheduled、completed、failedを区別する。
- Retryを見せるためにProduction Logicへ意図的Failureを埋め込まない。Deterministic Failure AdapterはTest／Development Compositionだけに限定する。
- Failure AdapterはOperation実行境界のApplication-owned Portとし、ProductionはNo-op、Development／Testだけが明示Configuration時にAttempt 1をRetryable Failureにする。Failure FlagをOperationValueやBrowserへ追加しない。
- Digest OutcomeはCredential、Raw Post Body全体、Journal Dataを含めず、Digest ID、Week、集計数等のSafe Fieldだけを返す。

## Authorization

- Authentication MiddlewareはSessionからCurrent Actorを構成する。
- Create／List／Show／Comment／Digest RequestはAuthenticated Actorを必須とする。
- Update／DeleteはPost Ownerだけを許可する。
- Show DigestとDigest StatusはRequesting Userだけを許可する。
- Unknown ResourceとUnauthorized ResourceのResponseから存在を推測できないようにする。
- Deferred DigestはWorker実行時にも既存再認可Contractを使う。
- Operation Status AuthorizerはOrigin ActorとCurrent Actorの一致をApplication Policyで検証する。

## Visual and Interaction Design

`design-taste-frontend`はVisual Directionを決める際に使用する。ただしSkillのLanding Page向けRuleをProduct UIへ一律適用しない。

一般UI Iconは[Reicon](https://reicon.dev/)の公式`reicon-svelte` Packageを使用する。Versionは導入Task時のStable Releaseを`package.json`と`pnpm-lock.yaml`へ固定し、Named／Individual Static Importで使用IconだけをBundleへ含める。CDN、Runtime Icon Fetch、Icon Font、別のGeneral-purpose Icon Libraryを併用しない。Application固有Brand Markは一般UI Iconと分けて扱う。

Phase 17は実装前に短いDesign Briefを作り、次を固定する。

- Audience、Mood、Typography、Color、Spacing、Motion Level
- Landing／AuthenticationとProduct Surfaceの共通Token
- Feed、Detail、Form、Digest ProgressのHierarchy
- Mobile／Desktop Breakpoint Behavior
- Empty、Loading、Validation、Unauthorized、Failure、Retry、Completed State

WCAG AA相当のContrast、Keyboard Navigation、Visible Focus、Semantic Landmark、Form Label、Error Association、Reduced Motion、Touch TargetをAcceptance Criteriaにする。Animationは状態理解を助ける範囲に限定する。

装飾だけのIconはAssistive Technologyから隠し、操作や状態を単独で伝えるIconはButton／LinkのAccessible Nameまたは同等のTextを必須とする。IconだけをValidation Error、Retry、Completed等の唯一の伝達手段にしない。

## Local Runtime and Seed

One-commandまたはDocumentedな少数Commandで次を起動できるようにする。

```text
PostgreSQL
BlackOps PHP HTTP Runtime
BlackOps Deferred Worker
SvelteKit Development／Preview Server
```

Seedは複数User、Post、Commentを決定的に作成する。SecretをRepositoryへ固定せず、Demo CredentialはLocal／Test用途であることをREADMEへ明記する。Setupを再実行可能にし、Migration、Build、Frontend Generate、Seed、Service Startの副作用をCommandごとに分離する。

## Testing and CI

最低限、次をPermanent Evidenceにする。

- PHP Unit／Integration Test
- Authentication RouterとSession Lifecycle Test
- Operation Validation／Authorization／Transaction Test
- Frontend Generate／CheckとStrict TypeScript Test
- Server-only Import／Browser Bundle Secret Guard
- SvelteKit Component／Server Action Test
- Real HTTP Consumer E2E
- Real Browser E2E: Register／Login／Post／Comment／Digest Progress／Logout
- Keyboard、Focus、Accessible Name、Reduced MotionのAutomated Check
- Sensitive Marker、Credential、Generated Artifact、Tracking Guard

Browser E2EのTool選定はDelivery TaskでRepository DependencyとContainer再現性を比較して決める。External Browser Serviceを必須にしない。

## Documentation and Publication

- `examples/community-board/README.md`はSetup、Architecture、User Journey、Troubleshootingを扱う。
- `docs/guide/`はQuickstartとReference Applicationの目的差、Server-only BFF、Deferred UIを説明する。
- ScreenshotはRepository内Document Assetとして扱い、CredentialやLocal Absolute Pathを含めない。
- Documentation WebsiteはLocal／CI Buildだけを維持し、外部Publication／Deployを行わない。
- Community Board自体も外部Hostingしない。

## Phase Acceptance Criteria

- [x] `examples/community-board/`が`examples/quickstart/`と独立している
- [x] BrowserはSvelteKitだけへ接続し、BlackOps通信がServer-only Moduleに限定される
- [x] CredentialがOperation、Journal、Outcome、Generated Contract、Browser Bundleへ入らない
- [x] Registration、Login、Logout、Session Expiry／Revocationが完走する
- [x] Post Feed、Detail、Create、Edit、Delete、CommentがValidation／Authorization付きで完走する
- [x] DBAL RepositoryとTransactional OperationがApplication-owned Dataを保存する
- [x] `GenerateWeeklyDigest`が202からStatus／Wait、Typed Outcome、Digest表示まで完走する
- [x] Progress UIがNon-terminal、Retry、TerminalをAccessibleに表示する
- [x] Taste Skillに基づくVisual DirectionとProduct UI Accessibilityが両立する
- [x] ReiconをStatic Importし、別Icon Library／CDN／Runtime FetchなしでAccessibleに使用する
- [x] Local Compose、Seed、Real Browser E2E、CI、README／Guideが成功する
- [x] Quickstart／Skeleton／Publication Gateが回帰しない
- [x] Documentation WebsiteとCommunity Boardを外部公開しない

## Non-goals

- Phase 19のIdempotency Key、Transactional Outbox、Relay、Replay
- Phase 20のEncryption Adapter、OpenTelemetry、Multi-tenant Framework
- Frontend Framework固有のBlackOps Public Adapter／Store／Form Generator
- ORM、Repository Base Class、Active Record
- External Hosting、Custom Domain、Documentation Website Publication

## Traceability

- Decision: [D103 Full-stack Reference Application](../decisions/103-full-stack-reference-application.md)
- Deletion Policy: [D105 Community Board Deletion Policy](../decisions/105-community-board-deletion-policy.md)
- Domain Layering: [D106 Community Board Domain Layering](../decisions/106-community-board-domain-layering.md)
- Delivery Plan: [Phase 17 Delivery Plan](72-phase-17-delivery-plan.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
- Frontend Contract: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Deferred Contract: [Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)
