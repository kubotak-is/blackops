# Phase 16 Delivery Plan

## Goal

Operation IDによるStatus／Outcome参照を、Public Query ContractからPostgreSQL、HTTP、Generated Operation Object、Installed Consumerまで依存順に実装する。

## Delivery Order

```text
P16-001 Decision, Specification, and Delivery Plan
  -> P16-002 Public Status Query Contract
    -> P16-003 PostgreSQL Status Projection and Retention
      -> P16-004 HTTP Status Resource
        -> P16-005 Generated status Capability
          -> P16-006 Generated wait Capability and Frontend CI
            -> P16-007 Consumer Experience and Closeout
```

Taskを並行実装しない。Public Contract、Source Authority、HTTP Wire、Generated Decoderの順で正本を一つずつ固定する。

## P16-001: Decision, Specification, and Delivery Plan

Owner: Orchestrator

- D102の7問をDecidedにする
- Status／Outcome、Query Authorization、Retention、HTTP、Generated Pollingの仕様を固定する
- Phase 16 Task境界と依存順を固定する
- 最初のTask Packetを作成する

Production Codeは変更しない。

## P16-002: Public Status Query Contract

- `BlackOps\Status`のPublic State、Result、Status、Safe Error、Query Exceptionを実装する
- 専用Query Authorizer、Authorization Request／Decision、既定Denyを実装する
- 認可前Subjectと認可後Detailを分離するInternal Source Portを実装する
- Source-neutral Query ServiceとState別Invariantを実装する
- Unknown／Denyの同一Unavailable、Allow後だけExpired、Safe FailureをUnit Testで固定する
- Deptrac／Public API Architectureを同期する

PostgreSQL、HTTP Route、Frontend生成は変更しない。

## P16-003: PostgreSQL Status Projection and Retention

- Operations Row、Canonical Journal、Outcome Store、Dead Letter、Purge AuditをStatus Sourceへ接続する
- Inline／DeferredのSource Authorityを実装する
- Internal `supervising`をPublic `running`へ投影する
- Completed Typed OutcomeとRejected Safe Category／Codeを復元する
- 認可前の最小Subject Queryと認可後のDetail Queryを分離する
- Unknown、Tombstone、Fully Purged、Missing Outcome、Source不整合をIntegration Testで固定する
- Raw Payload、Context、Reason MessageをSELECT／DecodeしないことをGuardする

Migrationは既存Schemaで実現できないことが証明された場合だけOrchestratorへBlockerを返す。Task内で無断追加しない。

## P16-004: HTTP Status Resource

- Framework予約`GET /operations/{operationId}`をBuild／Runtimeへ統合する
- ApplicationのStatus Query Authorizer登録と既定DenyをCompositionへ接続する
- 7 StateのSchema Version 1 JSONを実装する
- 200／404／410／500、`private, no-store`、Non-terminal `Retry-After`を実装する
- Deferred 202へ相対`Location`と`Retry-After`を追加する
- Route Collision、Invalid ID、Authentication、Authorization、RetentionをHTTP Testで固定する
- Classic EntrypointとFrankenPHP Worker Modeで同じContractを使う

Generated Frontend Sourceは変更しない。

## P16-005: Generated status Capability

- Frontend Contract／Generatorへ`.status(operationId, options)`を追加する
- 7 State、Typed Outcome、Safe Error、404／410／Authentication／Internal／Transport Resultを生成型で表す
- Operation Type、Schema Version、State別Fieldを厳密Decodeする
- Framework-neutral ESM、DOMなしStrict TypeScript、Injected Fetch Runtimeを維持する
- `frontend:generate`／`frontend:check`とDrift Testを同期する

`.wait()`と自動Pollingは変更しない。

## P16-006: Generated wait Capability and Frontend CI

- `.wait(operationId, options)`を生成する
- Abort Signalと有限`maxWaitMilliseconds`を必須化する
- `Retry-After`、Terminal停止、Timeout、Abortを実装する
- 401／404／410／5xx／Network／Malformedを自動Retryしない
- Clock／Timer注入、並行Wait分離、CleanupをRuntime Testで固定する
- Independent Frontend CIを新Contractへ同期する

`.fetch()`の自動Polling、Global Mutable Client、任意Retry Policyは追加しない。

## P16-007: Consumer Experience and Closeout

- QuickstartとComposer SkeletonへStatus Authorizer設定例を追加する
- Deferred Operationを202からCompleted／Rejected／Failed／Dead Letteredまで追うReal HTTP E2Eを追加する
- Guide、Internal Documentation、Website SourceへStatus／Outcome／Security／Retention／Pollingを同期する
- Framework UpdateがApplication所有Policy／Frontend Sourceを保持することを確認する
- Skeleton通常／`--no-scripts`、Publication Dry-run、Website Buildを確認する
- Full Quality Gateを実行しPhase 16をCloseする

Documentation WebsiteはBuildまでとし、Publication／Deployしない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High Workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立再検証、Commitを行う
- 仕様矛盾、Migration要求、Public Contract変更、Security境界変更はTaskを広げずBlockerとして返す
- Generated／Quickstart／Skeleton／Website ArtifactはTask完了前にCleanupする

## Phase Acceptance Criteria

- [ ] D102とPhase 16 Specification／Delivery PlanがDecidedである
- [ ] Public PHP Status Queryと専用Query Authorizerが実装される
- [ ] PostgreSQL SourceがInline／Deferred／Retentionを安全に投影する
- [ ] HTTP Resourceが7 Stateと200／404／410／500 Contractを提供する
- [ ] Deferred 202が`Location`／`Retry-After`を提供する
- [ ] Generated `.status()`がTyped Outcomeを取得できる
- [ ] Generated `.wait()`がAbort可能、有限、Retry-After準拠である
- [ ] Unknown／Unauthorized、Expired、Sensitive、Raw Error境界がRegression Testで固定される
- [ ] Quickstart／Skeleton／Framework Update／Consumer E2E／Guide／Website Sourceが同期する
- [ ] Full PHP／Frontend／Consumer／Website Quality Gateが成功する
- [ ] Documentation Websiteを外部公開しない

## Traceability

- Decision: [D102 Phase 16 Deferred Status and Outcome API](../decisions/102-phase-16-deferred-status-and-outcome-api.md)
- Contract: [Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
