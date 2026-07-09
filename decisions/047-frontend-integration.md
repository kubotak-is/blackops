# D047: Frontend Integration

Status: Decided

## Context

BlackOpsはHTML Renderingを持たないAPI-first／Headless Frameworkを志向している。一方、Frontend Frameworkはまだ確定していない。

React、Vue、Next.js等の一つへ依存せず、Operation DefinitionをSource of Truthとして型と通信Clientを生成できれば、Frontend選定をApplication側へ委ねられる。

## Question 1: Framework境界

### Options

- A: BlackOpsはJSON APIまでを担当し、HTML Renderingを提供しない
- B: Template EngineとServer-side HTML Renderingも提供する
- C: React専用IntegrationをCoreへ含める

### Recommendation

Aを推奨する。

Frontend Frameworkを固定せず、BlackOpsはOperation、HTTP Contract、認証Context、Journalに集中する。

[ANSWER]

A
TypeScriptのライブラリとか使ってもいいですが、Operationのインターフェースから自動的にTSのクライアントファイルを出力してそれとフロントフレームワークが接続する形にしたい。
ただし、ブラウザとBlackOpsが直接通信せずに、基本的にはBFFを経由する。
つまり、NextやNuxt、SvelteKitのようなFWを前提にしたい。（Astroもできるっけ？）
この辺はいい感じのプラクティスがないか調査してほしい。ベターだとOpenAPIの定義を吐いてそれからクライアントが作られるとかかなと思いますがもっと良いソリューションがあれば採用したい

[/ANSWER]

## Question 2: ContractのSource of Truth

### Options

- A: Operation ManifestからJSON SchemaとOpenAPIを生成する
- B: OpenAPIを手書きし、PHP側を合わせる
- C: Frontend側のTypeScript型を正本にする

### Recommendation

Aを推奨する。

`OperationValue`、`Outcome`、`RejectionReason`、Route Metadataを一度定義すれば、Server検証、Documentation、Frontend型を同じManifestから生成できる。

[ANSWER]

A

Operation ManifestをSource of Truthとし、そこからOpenAPIとJSON Schemaを生成する。

Frontend FrameworkはBlackOpsと直接結合せず、BFFがOpenAPIまたは生成Clientを使ってBlackOps HTTP APIへ接続する。OpenAPIはFrontend／BFF側Toolingとの交換形式として扱い、手書きの正本にはしない。

[/ANSWER]

## Question 3: Generated Client

### Options

- A: Framework非依存のTypeScript型付きClientを生成する
- B: OpenAPIだけ生成し、Client生成は利用者へ任せる
- C: React Hook専用Clientを生成する

### Recommendation

Aを推奨する。

```ts
const result = await client.showWelcome();
```

Client内部でURL、HTTP Method、Binding、JSON Decode、Error変換、Operation ID／Correlation ID Headerを扱う。React Query等へのIntegrationは別Packageまたは利用Application側で行う。

[ANSWER]

A

最終的にはFramework非依存のTypeScript型付きClientを生成する。

Clientはfetchベースを基本とし、Next.js、Nuxt、SvelteKit、AstroなどのBFFから利用できる形にする。React Query、Svelte Query等のFramework別IntegrationはCoreへ含めず、別PackageまたはApplication側の責務にする。

OpenAPIを先に安定させることで、既存のOpenAPI Toolingも利用可能にしつつ、BlackOps公式Client Generatorを後続Phaseで追加できるようにする。

[/ANSWER]

## Question 4: MVP範囲

### Options

- A: MVPでOpenAPI、TypeScript型、最小Clientまで生成する
- B: MVPはJSON Response ContractとOpenAPI生成までにする
- C: MVPではFrontend Integrationを実装しない

### Recommendation

Bを推奨する。

最初のVertical SliceでContractの一貫性を検証しつつ、TypeScript Generatorの実装でCore MVPを遅らせない。Generator PortとManifest Fieldは壊さないように設計し、最小Clientは次Phaseで追加する。

[ANSWER]

B

MVPはJSON Response ContractとOpenAPI生成までにする。

TypeScript Client GeneratorはOpenAPIとDeferred HTTP Contractが安定してから追加する。Generator PortやManifest Fieldは後方互換性を意識して設計し、Phase 3のDeferred Vertical Sliceを遅らせない。

[/ANSWER]

## Decision

[DECISION]

BlackOps CoreはAPI-first／Headless Frameworkとして維持し、HTML Renderingや特定Frontend Framework向けIntegrationをCoreへ含めない。

Frontend Applicationは原則としてBFFを経由してBlackOpsへ接続する。BFFはNext.js、Nuxt、SvelteKit、AstroなどのServer Route／Endpoint層を想定し、BrowserがBlackOpsへ直接通信する形は主経路にしない。

HTTP ContractのSource of TruthはOperation Manifestとする。BlackOpsはOperation ManifestからOpenAPIとJSON Schemaを生成し、OpenAPIをFrontend／BFF側Toolingとの交換形式として扱う。

TypeScript ClientはFramework非依存のfetchベースClientを目標にする。Framework別のHooksやData Fetching IntegrationはCore外のAdapter PackageまたはApplication側で扱う。

MVPではJSON Response ContractとOpenAPI生成を先に実装し、TypeScript Client GeneratorはOpenAPIとDeferred HTTP Contractが安定した後続Phaseで追加する。

[/DECISION]

## Consequences

[CONSEQUENCES]

利点:

- PHP CoreがFrontend Frameworkに依存せず、Framework利用者がNext.js、Nuxt、SvelteKit、Astro等を選べる。
- BFF前提にすることで、認証、Cookie、Secret、Server-only処理をFrontend Framework側へ自然に置ける。
- OpenAPIとJSON Schemaを生成物にすることで、既存のTypeScript／API Toolingを利用できる。
- React専用Hooks等へ早期固定せず、Framework非依存Clientから段階的に広げられる。

制約:

- BlackOps CoreはHTML Rendering、Template Engine、React Hooksを提供しない。
- BrowserからBlackOpsへ直接接続するClientは主経路として扱わない。
- TypeScript Client GeneratorはMVP範囲外のため、初期PhaseではOpenAPI Toolingまたは手薄いBFF実装で接続する。

後続タスク:

- JSON Response Contract、Deferred HTTP Response、Status Endpointの仕様へOpenAPI生成前提を反映する。
- Operation ManifestからOpenAPIとJSON Schemaを生成するTask Packetを作成する。
- BFF接続Patternを利用者向けGuideへ追加する。
- OpenAPI安定後、Framework非依存TypeScript Client Generatorと必要に応じたFramework別Adapterを検討する。

[/CONSEQUENCES]
