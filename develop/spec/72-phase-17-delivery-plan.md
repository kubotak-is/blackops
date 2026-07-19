# Phase 17 Delivery Plan

## Goal

認証付きCommunity Boardを、Application Foundation、Identity Boundary、Inline Domain、SvelteKit BFF、Deferred Digest、Visual／Browser Experienceの順に構築し、既存BlackOps Public APIのFull-stack Consumer Evidenceにする。

## Delivery Order

```text
P17-001 Decision, Specification, and Delivery Plan
  -> P17-002 Application and SvelteKit Foundation
    -> P17-003 Identity, Session, and BFF Boundary
      -> P17-004 Structured Outcome Contract
        -> P17-005 Post and Comment Operations
          -> P17-006 Generated Operations and SvelteKit Product Journey
            -> P17-007 Deferred Digest and Progress
              -> P17-008 Visual, Accessibility, and Browser E2E
                -> P17-009 Consumer, Documentation, and Closeout
```

Taskは依存順に実装し、同一Source Treeへ並行変更しない。各TaskでReference Application自身のTarget Testを先に通し、Phase CloseoutでFramework、Quickstart、Skeleton、Websiteを含むFull Gateを実行する。

## P17-001: Decision, Specification, and Delivery Plan

Owner: Orchestrator

- D103の8問とServer-only補足をDecidedにする
- RoadmapをPhase 17 Reference Application、Phase 18 Reliability、Phase 19 Securityへ更新する
- Full-stack Reference Application仕様を固定する
- Task境界、Acceptance Criteria、Framework Gapの停止条件を固定する
- P17-002 Task Packetを作成する

Production Codeは変更しない。

## P17-002: Application and SvelteKit Foundation

- `examples/community-board/`へ独立したPHP Application Layoutを作る
- Quickstartを参照しても、Skeleton Publication Sourceとの同期関係を作らない
- PostgreSQL、PHP HTTP、Deferred Worker、SvelteKitのCompose Topologyを作る
- Application Migration、Build、Frontend Generate、Seed、Startを分離したCommand入口を作る
- SvelteKitのStrict TypeScript、Lint／Check／Test、Server-only Directoryを構成する
- `config/frontend.php`をServer-only Generated Outputへ向ける
- Minimal Landing／Health JourneyとCI Artifact Guardを作る
- Quickstart／SkeletonがByte単位で変更されていないことをGuardする

Identity、Post Domain、Digest Business Logic、Framework Production Codeは変更しない。

## P17-003: Identity, Session, and BFF Boundary

- User／Session MigrationとApplication-owned Repositoryを実装する
- Registration、Session Create、Session RevokeのApplication-owned Authentication Routerを実装する
- Outer RouterがAuthentication Route以外をBlackOps Handlerへ委譲する
- Password Hash、Token Hash、Expiry、Revocation、Rotationを実装する
- SvelteKit Login／Registration／LogoutをForm ActionとHttpOnly Cookieで実装する
- SvelteKit Server-only WrapperがCredentialをPHPへ付与する
- `HttpAuthenticator`がCredentialをActorRefへ変換する
- CSRF、No-store、Safe Error、Cookie Configuration、Sensitive Guardを固定する

Authentication RouteをOperationへせず、CredentialをJournal／Outcome／Generated Contractへ入れない。

## P17-004: Structured Outcome Contract

- Public `OutcomeData` Markerと`#[ListOf]` Attributeを追加する
- Outcome OutputだけにReadonly Nested DTO／Nullable DTO／Typed `list<DTO>`を追加する
- Build時の再帰Schema、Cycle／Unsupported／Sensitive Failureを固定する
- Frontend Manifest SchemaとGeneration Markerを更新する
- Readonly TypeScript DTO／ReadonlyArrayとStrict Recursive Decoderを生成する
- Inline HTTP／Deferred Status／Canonical Journalで同じStructured Shapeを使う
- PostgreSQL Outcome Codec Version 2を実装し、Version 1を非対応にする
- Existing Scalar／Empty OutcomeとOperationValue Bindingを回帰させない

Community BoardのPost／Comment DomainとOperationValueのNested／Array Inputは実装しない。

## P17-005: Post and Comment Operations

- Post／Comment MigrationとApplication-owned Repositoryを実装する
- List、Show、Create、Update、Delete PostとAdd Comment Operationを実装する
- OperationValue Validationと422 Field Errorを固定する
- Authenticated／Owner AuthorizationとUnknown／Deny境界を実装する
- Mutationへ`#[Transactional]`を適用する
- Unit／Integration／Real HTTP Testを追加する
- Operation／HTTP／Frontend Manifestを同期する

SvelteKit Product PageとDigestは実装しない。

## P17-006: Generated Operations and SvelteKit Product Journey

- Server-only DirectoryへFrontend Contractを生成する
- Application-owned `.server.ts` WrapperでInjected Fetch、Base URL、Credentialを構成する
- Feed、Post Detail、Create／Edit／Delete、CommentのPage Server Load／Form Actionを実装する
- 422、401、404、Conflict、Transport、Internal Resultを画面向けSafe DTOへ投影する
- Browser Bundle／Client ImportからGenerated SourceとCredentialを除外するGuardを作る
- Server-side TypeScript Runtime TestとReal HTTP Journeyを追加する

Framework固有Svelte Adapter、Global Client、Browser Direct Fetchを追加しない。

## P17-007: Deferred Digest and Progress

- Digest Migration、Repository、`GenerateWeeklyDigest`、`ShowDigest`を実装する
- Deferred Transaction、Worker再認可、Status Authorizerを接続する
- `.fetch()`の202からOperation IDをBFFへ返す
- Server-only `.status()`／`.wait()` WrapperとFinite Abortを実装する
- accepted／running／retry_scheduled／completed／failed UI Stateを実装する
- Typed Outcomeから保存済みDigest表示へ接続する
- Development／Test限定Failure AdapterでRetry UIをDeterministicに検証する
- Unknown／Deny／Expired／Malformed／Timeout／AbortをSafeに表示する

Production Business Logicへ意図的Failureを埋め込まない。

## P17-008: Visual, Accessibility, and Browser E2E

- `design-taste-frontend`を使ってDesign Briefを作成する
- 公式`reicon-svelte`をLockfileへ固定し、使用IconだけをStatic Importする
- Landing、Authentication、Typography、Color、Spacing、Motion Tokenを実装する
- Feed、Detail、Form、Digest ProgressをResponsive Product UIとして仕上げる
- Empty、Loading、Validation、Unauthorized、Retry、Failure、Completed Stateを揃える
- Keyboard、Focus、Contrast、Label、Error Association、Reduced Motionを検証する
- 装飾Icon、Icon-only Action、Text併記StateのAccessibilityと、別Icon Library／CDN非混在を検証する
- Register／Login／Post／Comment／Digest／LogoutのReal Browser E2Eを追加する
- ScreenshotをCredentialなしのDocumentation Assetとして生成する

Visual変更はProduct Journeyを壊さず、Taste SkillのLanding向けRuleをProduct UIへ一律適用しない。

## P17-009: Consumer, Documentation, and Closeout

- `examples/community-board/README.md`をSetupからFull Journeyまで通す
- `docs/guide/`へQuickstartとの差、BFF、Authentication責任、Deferred UIを同期する
- Local ComposeをClean Installから再実行する
- Community BoardのBackend／Frontend／Browser／Sensitive／Artifact Gateを実行する
- Framework Full PHPUnit、Mago、Deptrac、Quickstart Consumer、Skeleton Publication Dry-runを実行する
- Documentation Website Content／Test／Buildを実行してArtifactをCleanupする
- Phase 17 Report、TODO、STATE、Current StatusをCloseする

Documentation WebsiteとCommunity BoardのExternal Publication／Deployは行わない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High Workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立再検証、Commitを行う
- `examples/community-board/`はReference ApplicationでありSkeleton Publication Sourceではない
- Quickstart／Skeleton変更が必要に見える場合、Taskを広げずFramework Gapとして返す
- Public Framework API、Schema、Security、Credential境界の変更は別Decisionなしに行わない
- Generated Source、Build Artifact、Node Modules、Browser ArtifactはTask完了前にCleanupまたはIgnore／Tracking Guardへ含める
- WebsiteとCommunity Boardを外部公開しない

## Phase Acceptance Criteria

- [x] D103とPhase 17 Specification／Delivery PlanがDecidedである
- [x] Independent Community Board Foundationが起動する
- [x] Server-only BFFとApplication-owned AuthenticationがCredentialを安全に扱う
- [ ] Structured OutcomeがNested DTO／Typed ListをHTTP／Persistence／Frontendで一貫して扱う
- [ ] Post／Comment JourneyがValidation／Authorization／Transaction付きで完走する
- [ ] Generated Operation ObjectがSvelteKit Serverだけから使われる
- [ ] Deferred Digestが202からStatus／Wait／Typed Outcome／表示まで完走する
- [ ] Accessible／Responsive Product UIとReal Browser E2Eが完成する
- [ ] README／Guide／CI／Consumer／Website Gateが成功する
- [ ] Quickstart／Skeleton／Publication Contractが回帰しない
- [ ] External Publication／Deployを行わない

## Traceability

- Decision: [D103 Full-stack Reference Application](../decisions/103-full-stack-reference-application.md)
- Contract: [Full-stack Reference Application](71-full-stack-reference-application.md)
- Structured Outcome: [Structured Outcome Contract](73-structured-outcome-contract.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
