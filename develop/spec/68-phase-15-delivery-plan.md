# Phase 15 Delivery Plan

## Goal

Operation／HTTP Manifestを正本として、Frontendから`CreateOrder.fetch(value, options)`で型付き実行でき、`toRequest()`／`url()`／Readonly Metadataも参照できるFramework-neutral TypeScript ESMを生成する。

Backend Artifact、Frontend Contract、Generated Tree、Typed Fetch Runtime、Drift／Frontend Build、Consumer Experienceの順に実装し、Source Reflection、Credential、Sensitive ValueをFrontend Artifactへ流さない。

## P15-001: Decision, Specification, and Delivery Plan

- D100でOperation Object API、Frontend Target、生成Command、生成対象、Sensitive境界を確定する
- Frontend Contract Manifest、Supported Type、Binding、Result Union、Generation Safetyを仕様化する
- `fetch()`、`toRequest()`、`url()`、Readonly Metadataの責務を固定する
- Phase 15のTask順序とPhase 16以降の境界を固定する

Acceptance Gate:

- Decision、Specification、TODO、STATEが同期する
- Production Code、Test、Quickstart、Guideを変更しない
- Callable／Thenable、Global Mutable Client、Polling、Frontend Framework Adapterを初期Scopeへ含めない

## P15-002: Frontend Contract Manifest

- Frontend Contract DTO、Compiler、Artifact Codec／FileをInternalへ追加する
- Operation／HTTP Manifestと同じApplication Build IDで`frontend.php`を生成する
- `app.build.frontend_manifest`をApplication Build Configurationへ追加する
- HTTP OperationだけをOperation／HTTP Metadataから結合する
- Value／Outcome Scalar Type、Nullable、Required、Binding、Sensitive、Validation MetadataをReflectionからCompileする
- Module Path／Export Nameを決定し、Case-insensitive Collisionを拒否する
- Unsupported Type、Sensitive Outcome、Manifest不整合を安全なBuild Errorにする
- Build Freshness、Atomic Artifact Write、Legacy Internal Build Commandを同じContractへ同期する

Acceptance Gate:

- Quickstart四HTTP OperationのFrontend Contractを同じBuild IDで生成できる
- RouteなしOperationを含めない
- Path／Query／Header／Body、Default Optional、Validation、Sensitive Inputを正しく表現する
- Credential、Runtime Value、Default Value、Example、Absolute Source PathをArtifactへ含めない
- Unsupported型、Sensitive Outcome、Duplicate Module／Export、Build ID不整合を拒否する
- Runtime HTTP／Worker CompositionがFrontend Artifactを読まない

## P15-003: Operation Object and Request Generation

- Optional `config/frontend.php`と安全なOutput Path Validationを追加する
- `frontend:generate`をProject Console KernelへLazy登録する
- Deterministic TypeScript ESM Tree、Marker、Atomic Replaceを実装する
- OperationごとのPascalCase immutable Objectを生成する
- `url()`、`toRequest()`、Readonly `type`／`method`／`path`／`strategy`を実装する
- Path Encode、Query、Header、Body、Optional、Base URL、Protected Headerを共通Runtimeへ実装する
- Non-marker Directory、Symlink、Repository外Path、Partial Generationを拒否する

Acceptance Gate:

- Quickstart Contractから決定的なModule Treeを生成できる
- 同じContractから二回生成したBytesが一致する
- `url()`と`toRequest()`が実HTTP Binderと同じTransport Shapeを返す
- Failure時に既存Treeを維持しTemporary Treeをcleanupする
- Frontendを持たないApplicationのBackend Build／Runtimeが回帰しない

## P15-004: Typed Fetch Runtime and Results

- Operation Objectへ`.fetch()`を追加する
- Browser既定Fetch、SSR／Node／Test Injected Fetch、Per-call Base URL／Header／Credential／Signalを実装する
- Inline 200／204、Deferred 202、Protocol 400、Rejected 4xx、Validation 422、Internal 500をTyped ResultへDecodeする
- Missing Fetch、Invalid Base URL、Network Failure、Abort、Unexpected ResponseをTransport Resultへ変換する
- Operation ID Optional境界とOperation固有Outcome Scalar Decoderを実装する
- Raw Body、Credential、Exception DetailをError Resultへ出さない

Acceptance Gate:

- Browser互換とInjected Fetchの両方でInline／Deferredを実行できる
- `result.ok`／`result.kind`／`result.status`でTypeScript Narrowingできる
- 204、202、422、Operation IDあり／なし500、Network／Abort／Malformed Responseを区別する
- Method／Binding／Body／Content-TypeをCall Optionから上書きできない
- Retry、Polling、Cache、Global Mutable Clientを追加しない

## P15-005: Drift and Frontend Build Integration

- `frontend:check`のFresh／Missing／Drift／Invalid Exit Contractを実装する
- Expected TreeとPath／Bytes／余剰Fileを非破壊比較する
- TypeScript CompileとNode Runtime Test用の独立Frontend Fixtureを追加する
- Generated OperationのTree-shaking可能なESM、Structural Fetch Type、Narrowingを検証する
- GitHub ActionsへFrontend Contract Generate／Check／TypeScript Testを追加する
- Framework UpdateでApplication-owned Frontend Source／Configを保持する

Acceptance Gate:

- Fresh 0、Missing／Drift 1、Invalid 2とstdout／stderrが仕様どおりである
- Drift CheckがGenerated Treeを変更しない
- Node／SSR Injected Fetchで実Runtime Testが成功する
- CIがPHP Quality GateとFrontend Generated Artifact Gateを実行する
- Generated Tree、Temporary Tree、Build Artifactを意図せずCommitしない

## P15-006: Consumer Experience and Closeout

- Quickstart／SkeletonへFrontend Configと生成Journeyを追加する
- Welcome Inline、Report Deferred、Order Transaction、Failure OperationをGenerated Objectから実HTTP実行するConsumer E2Eを追加する
- `fetch()`、`toRequest()`、`url()`、Metadata、Typed Resultを入力と実出力の対でGuideへ記載する
- Project CLI、Configuration、Security、Troubleshooting、Directory Structure、Current Statusを同期する
- Skeleton通常／`--no-scripts`、Publication Dry-run／Workflow、Framework Updateを同期する
- Website Test／Check／Buildを実行するが外部公開しない
- Full PHP／Consumer／Frontend／Website Gateを完走してPhase 15をCloseする

Acceptance Gate:

- Install直後のQuickstartからFrontend Treeを生成し、TypeScript Compile／Runtimeを完走できる
- Inline／Deferred／Validation／Rejected／Internal／Transport Resultの例が実出力と一致する
- Sensitive Input値、Credential、Raw Error BodyがGenerated Artifact／Result／Logへ出ない
- Backend-only Journey、Worker Mode、Framework Update、Skeleton Publicationが回帰しない
- Stable／main表示とExperimental Compatibilityを正直に維持する
- Documentation Websiteを外部公開しない

## Dependency Order

```text
P15-001 Decision, Specification, and Delivery Plan
  -> P15-002 Frontend Contract Manifest
    -> P15-003 Operation Object and Request Generation
      -> P15-004 Typed Fetch Runtime and Results
        -> P15-005 Drift and Frontend Build Integration
          -> P15-006 Consumer Experience and Closeout
```

Contract ManifestのSchema／Type／Binding不整合をGenerated TypeScript側だけで補正しない。P15-004で実HTTP Responseと既存ResponderのShapeが矛盾した場合は、Client Castで隠さずHTTP SpecificationとRuntime境界へ戻す。

## Phase Acceptance Criteria

- [ ] Operation／HTTP／Frontend Manifestが同じApplication Build IDを持つ
- [ ] `#[Route]`を持つ全HTTP OperationだけをFrontend Contractへ含める
- [ ] Unsupported Type、Sensitive Outcome、Module／Export CollisionをBuild時に拒否する
- [ ] `frontend:generate`が安全かつ決定的にTypeScript ESM Treeを生成する
- [ ] Generated Operation Objectが`.fetch()`、`.toRequest()`、`.url()`、Readonly Metadataを提供する
- [ ] Path／Query／Header／Body BindingがHTTP Runtimeと一致する
- [ ] Inline／Void／Deferred／Protocol／Rejected／Validation／Internal／Transport Resultを型付きで区別する
- [ ] BrowserとSSR／Node／TestのFetch境界をFramework-neutralに提供する
- [ ] Sensitive Input値、Credential、Raw Error BodyをGenerated Artifact／Resultへ出さない
- [ ] `frontend:check`とTypeScript Compile／Runtime TestがCIで成功する
- [ ] Quickstart、Skeleton、Guide、Website、Framework Update、Consumer E2Eが同期する
- [ ] Callable／Thenable、Global Mutable Client、Retry、Polling、Frontend Framework Adapter、Vite Pluginを追加しない
- [ ] Full PHP／Consumer／Frontend／Website Quality Gateが成功する

## Deferred Scope

- Phase 16: Deferred Status／Outcome APIとGenerated Polling連携
- Phase 17: Idempotency、Retry Delivery、OutboxとClient Retry Policyの責任境界
- Phase 18: Tenant、Canonical Raw Access、Encryption、OpenTelemetry、Metric
- Ecosystem: Frontend Framework Adapter、Form Helper、Vite Plugin、OpenAPI、NPM Publication
- Type System: Typed Collection、Nested DTO、Enum、Date／Time、Upload／Stream、Custom Responder

## Traceability

- Decision: [D100 Phase 15 Operation Frontend Bridge](../decisions/100-phase-15-operation-frontend-bridge.md)
- Contract: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
- HTTP: [HTTP Adapter](05-http.md)
- Manifest: [Operation Registry and Manifest](08-registry-and-manifest.md)
- Sensitive: [Sensitive Projection](25-sensitive-projection.md)
