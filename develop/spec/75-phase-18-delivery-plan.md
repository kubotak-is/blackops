# Phase 18 Delivery Plan

## Goal

Application ErgonomicsをTyped Environment、Frontend Bound Client、Console基盤、Operation Console公開、Session Auth、Reference Application簡素化の順に実装し、利用者が繰り返すFramework配線を削減する。

## Delivery Order

```text
P18-001 Decision, Specification, and Delivery Plan
  -> P18-002 Typed Environment and Configuration Closure
    -> P18-003 Frontend Bound Client Factory
      -> P18-004 Application Command Discovery and DI
        -> P18-005 Operation Console Adapter
          -> P18-006A Session Authentication Core
            -> P18-006B Ephemeral Outcome Contract
              -> P18-006C Auth Generator and Fresh Consumer
                -> P18-007 Community Board Migration and Phase Closeout
```

Taskを並行実装しない。各TaskはPublic Contract、Unit／Integration Test、QuickstartまたはPermanent Fixtureを完成してから次へ進む。Community Board全体の書換えはP18-007まで行わず、先行Taskでは必要な最小Consumerだけを使う。

## P18-001: Decision, Specification, and Delivery Plan

Owner: Orchestrator

- D110をDecidedにする
- Application Ergonomics仕様と責任分界を固定する
- RoadmapのReliability／Security／Transaction InterceptionをPhase 19／20／21へ移す
- Phase 18 Task境界、依存順、Acceptance Criteriaを固定する
- P18-002 Task Packetを作成する

Production Codeは変更しない。

## P18-002: Typed Environment and Configuration Closure

- Public Readonly `Environment`と型付きAccessorを実装する
- `ApplicationBuilder`がConfiguration評価を`create()`まで遅延し、呼出順非依存にする
- Configuration FileのArray／Environment Closureを一回だけ評価する
- EnvironmentをCompiled Artifact／Containerへ保存せず、検証済みConfigurationだけをApplication Serviceへ渡す
- Missing／Invalid／Default／Empty／Safe Error MatrixをTestする
- Quickstart、Skeleton、Community BoardのConfigurationをClosureへ移す
- Bootstrap Snapshot、Worker Reuse、Framework Update、Create-projectを回帰Testする

Frontend、Command、Session Authは変更しない。

## P18-003: Frontend Bound Client Factory

- Generated `createBlackOpsClient`とBound Operation Objectを実装する
- SvelteKit Server `fetch`／Global FetchをAdapterなしで受理する
- Base URL、Default／Call Header、Credential、Signal、Idempotency Keyを安全にBindingする
- Short Name Collision、Protected Header、GET Idempotency、Mutation IsolationをTestする
- Existing Unbound `.fetch()`／`.status()`／`.wait()`／Request APIを維持する
- Permanent Frontend Fixture、Fresh Check、Strict TypeScript、Bundle Sensitive Guardを同期する
- Community BoardにはP18-007で移行できるFixture-level Evidenceだけを追加する

PHP Environment、Console、Session Authは変更しない。

## P18-004: Application Command Discovery and DI

- Configured Source PathからSymfony `#[AsCommand]`をBuild時Discoveryする
- Discovery ArtifactとStale CleanupをBuild Lifecycleへ追加する
- Command ClassをCompiled Containerへ登録し、Constructor Injectionする
- 明示`commands`／`withCommands()`をOverride／追加用に維持する
- Built-in／Application／Package CommandのName CollisionとDependency FailureをBuild時に拒否する
- Runtime Source Scanと引数なしConstructor制約をDiscovery Commandから除く
- Seed相当のFixture CommandでDIとConsole KernelをIntegration Testする

Operation Console Attributeは追加しない。

## P18-005: Operation Console Adapter

- Public `#[ConsoleCommand]` AttributeとBuild Metadataを実装する
- OperationValue Scalar PropertyをNamed Optionへ決定的に写像する
- Unsupported／Sensitive／Collision／Invalid NameをBuild Errorにする
- Console Actor Providerと既定Denyを既存Actor／Authorization Contractへ接続する
- Inline Outcome／Void、Deferred Acceptance、Rejected、Validation、Internal、`--json`、Exit Codeを実装する
- HTTP／PHP Dispatchと同じLifecycle、Journal、Sensitive Projectionを通るTestを追加する
- QuickstartまたはPermanent ConsumerでOperationからCLIまで完走する

位置引数、Prompt、Wait、Renderer Pluginは追加しない。

## P18-006A: Session Authentication Core

Status: Accepted.

- Framework同梱のOpt-in `BlackOps\Auth\Session` Public APIを実装する
- Opaque Token、Hash、TTL、Rotation、Revocation、Cleanupを実装する
- Doctrine DBAL Store、Migration Template、Bearer／Cookie HTTP Authenticator Adapterを実装する
- User Provider等のApplication-owned Portを最小化する
- Concurrent Rotation／Revocation、Last-used Touch、Sensitive Surfaceを実PostgreSQLで固定する
- Sessionを登録しないExisting ConsumerのBuild／Runtimeを回帰する

## P18-006B: Ephemeral Outcome Contract

Status: Accepted.

- Public `EphemeralOutcome extends Outcome` Markerを追加する
- Route付き明示Inlineだけを許可し、Deferred／Console／Status／Wait／Outcome Storeを拒否する
- Ephemeral OperationのReceived Valueを`EmptyJournalData`、Completed Outcomeを`EmptyOutcome`として記録する
- 実Ephemeral Outcomeを同一HTTP Request中だけResponseへ一度投影する
- Credential Propertyの`#[Sensitive]`、Manifest整合、Runtime Type、Safe Failureを固定する
- Frontend Generatorで直接`fetch()`だけを型生成し、Status／Waitを省く
- Permanent FixtureでRaw SecretがJournal／Outcome／Log／Artifactへ残らないことを検証する

Auth Session Core、Generator、Community Boardは変更しない。

## P18-006C: Auth Generator and Fresh Consumer

Status: Implemented, pending Orchestrator review.

- Built-in `make:auth` Command／Generator／Stubを実装する
- User、DBAL Repository、Password Verifier、Registration Policy、Identity Provider、Register／Login／Logout Operationを生成する
- Service Provider、Configuration、User Migration／Framework Session Migrationを生成する
- `make:auth` GeneratorとConflict／No-overwrite／Fresh Install Testを実装する
- Session Configuration／Binding／MigrationのないApplicationでCapabilityが有効化しないことをGuardする
- Fresh ConsumerでGenerate、Migration、Login／Logout／Expiry／Rotation／Revocationを完走する

Session Authentication用の外部Repository作成、Packagist登録、Tag／Releaseは行わない。独立配布が必要になった時点で新しいDecisionを作成する。

## P18-007: Community Board Migration and Phase Closeout

- Community BoardをTyped Configuration、Bound Client、Command Discovery、Session Authへ移行する
- User／Password／Registration／Role／Safe View ModelをApplicationに維持する
- Seed CommandをConstructor DIへ移し、Operation Console Journeyを一つ追加する
- 直接ImportしなくなったComposer Dependencyだけを削除する
- Manual／Generated／Dependency Fileと主要配線行数のBefore／AfterをReportする
- Existing Foundation、Identity、Post／Comment、Digest、Browser、Clean Install Consumerを完走する
- Quickstart／Skeleton／Framework Update／Publication Dry-run／Website Buildを回帰する
- Guide、Reference、Security、CLI、Configuration、Example READMEを同期する
- Full Quality Gateを実行しPhase 18をCloseする

Documentation WebsiteとCommunity Boardを外部公開せず、Session Authentication用の別Package／Repositoryを作成しない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立再検証、Commitする
- Public API、Security、Package Publication、Compatibilityの仕様矛盾はTaskを広げずBlockerとして返す
- Task間で同じProduction Fileを先取り変更しない
- Generated／Dependency／Runtime／Browser ArtifactはTask完了前にCleanupする。ただしUserが起動中のCommunity Board Runtimeは、停止が必要になるTaskまで維持する

## Phase Acceptance Criteria

- [x] D110とPhase 18 Specification／Delivery PlanがDecidedである
- [x] Typed Environment／Configuration Closureが実装される
- [x] Frontend Bound Client Factoryが実装される
- [x] Application Command Discovery／DIが実装される
- [x] Operation Console Adapterが実装される
- [x] Session Authentication／Generatorが実装される
- [ ] Community Boardが新Contractへ移行し、手動配線削減を証明する
- [ ] Direct Dependency、Sensitive、Worker Reuse、Build Artifact境界がRegression Testで固定される
- [ ] Full PHP／Frontend／Consumer／Website Quality Gateが成功する
- [ ] External Publication／Deployを行わない

## Traceability

- Decisions: [D110 Application Ergonomics](../decisions/110-application-ergonomics.md)、[D111 Session Authentication Contract](../decisions/111-session-auth-package-contract.md)、[D112 Authentication Credential Response Boundary](../decisions/112-authentication-credential-response-boundary.md)
- Contract: [Application Ergonomics](74-application-ergonomics.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
