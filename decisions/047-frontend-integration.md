# D047: Frontend Integration

Status: Discussing

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

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

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

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

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

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

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

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Decision

[DECISION]

<!-- 回答を基に、合意後AIが記入する。 -->

[/DECISION]

## Consequences

[CONSEQUENCES]

<!-- 決定による利点、制約、後続タスクを、合意後AIが記入する。 -->

[/CONSEQUENCES]
