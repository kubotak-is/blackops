# D100: Phase 15 Operation Frontend Bridge

Status: Awaiting Decision

## Context

Phase 15では、BackendのOperation／HTTP定義を正本として、Frontendから型付きでOperationを参照できるBridgeを提供する。利用者がURL、HTTP Method、Path Parameter、Query、Header、Body、Inline Outcome、Deferred Acknowledgementを手作業で二重管理しないことが目的である。

User GoalはLaravel Wayfinderのような体験である。2026-07-19時点のWayfinderは、LaravelのController／RouteからImport可能なTypeScript関数を生成し、関数呼出から`{ url, method }`を返す。`.url()`、HTTP Method Variant、Form Helper、Vite連携を提供する一方、HTTP Request自体の送信はFrontend側へ委ねる。生成物はBuildごとに全再生成でき、Repositoryで追跡しない運用も選べる。

BlackOpsではRouteだけでなく、Operation Type、OperationValue、Execution Strategy、Outcome、Validation／Rejection、Operation IDを同じContractへ接続できる。ただしPhase 16のDeferred Status／Outcome APIより先に、Polling ClientやDiagnostics UIまで固定してはならない。

## Existing BlackOps Boundary

- `build:compile`はOperation ManifestとHTTP Manifestを同じApplication Build IDで生成する
- Operation ManifestはType ID、Operation、OperationValue、Outcome、Execution Strategyを保持する
- HTTP ManifestはMethod、Path、Operation Type、Value Classを保持する
- HTTP BindingはOperationValue Constructorの`#[FromPath]`、`#[FromQuery]`、`#[FromHeader]`、`#[FromBody]`と、AttributeなしのBody Bindingを扱う
- 現在のHTTP入力型はScalar、Nullable、Default Valueを中心とし、複雑なObject BindingをPublic Contractにしていない
- Inline成功はOutcomeのPublic PropertyをJSON 200、`void`は204、Deferred受付はOperation ID付きJSON 202を返す
- Validation RejectionはOperation IDとViolationを持つ422、業務拒否はCategory／Codeを持つ4xx、成立後FailureはOperation ID付き500を返す
- `#[Sensitive]`はJournal／Diagnostics Projectionの境界であり、HTTP Requestへの入力可否やFrontend公開可否を意味しない

## Question 1: Initial Vertical Slice

最初の生成物はどこまで責務を持つか。

### Options

- A: Wayfinder相当のFramework-neutral Request DescriptorとTypeを生成する。生成関数はOperationValue型の単一引数を受け、`url`、`method`、`path`、`query`、`headers`、`body`を解決する。Inline Outcome、Deferred Acknowledgement、Validation／Rejected／Internal ErrorのTypeも生成するが、`fetch`実行、認証、Retry、Polling、State管理は持たない
- B: Aに加えてBuilt-in `fetch` Clientを生成し、Request送信、JSON Decode、HTTP ErrorからTyped Resultへの変換までPhase 15で提供する
- C: Request DescriptorとFull Typed Clientを同時にPublic APIとして提供し、Applicationが選択できるようにする

### Recommendation

Aを推奨する。

Wayfinderに近い小さな責務で、React／Vue／Svelte／Inertia／独自HTTP Clientのどれからも利用できる。BlackOps固有のOperationValue、Outcome、Deferred Acknowledgement、Rejection Typeまでは表現しつつ、認証方式、CSRF、Cookie、Base URL、Timeout、Abort、RetryをApplication責務のまま保てる。Phase 16でStatus／Outcome APIが決まる前にPolling Clientを固定することも避けられる。

想定する利用形は次である。名称とPathは最終Specificationで衝突検査を含めて固定する。

```ts
import { triggerFailure } from '@/blackops/operations/diagnostics/failure/trigger';

const request = triggerFailure({
  reference: 'checkout-1042',
  sensitiveNote: 'not-for-logs',
});

// {
//   url: '/failures',
//   method: 'POST',
//   body: { reference: 'checkout-1042', sensitiveNote: 'not-for-logs' }
// }
await http(request);
```

[ANSWER]



[/ANSWER]

## Question 2: Frontend Target

Phase 15の生成物をどのFrontendへ依存させるか。

### Options

- A: TypeScript ESMだけに依存するFramework-neutral Moduleとする。DOM、React、Vue、Svelte、Inertia、Viteへ依存しない
- B: Reactを最初のCanonical Targetとし、Hook／Form Integrationまで生成する
- C: React、Vue、Svelte AdapterをPhase 15で同時提供する

### Recommendation

Aを推奨する。

BlackOpsはHeadless Frameworkであり、Backend Contractから生成する最下層はFrontend Frameworkに依存しない方が責務と合う。Tree-shake可能なESMなら各Frameworkから薄いAdapterを後付けできる。Framework AdapterとForm Integrationは実利用から必要性を確認して別Phaseへ追加できる。

[ANSWER]



[/ANSWER]

## Question 3: Generation and Frontend Build Integration

生成CommandとBuild連携をどうするか。

### Options

- A: `php blackops frontend:generate`を明示Commandとして追加し、現在のOperation／HTTP Build Artifactを検証してTypeScriptを決定的に全再生成する。`php blackops frontend:check`でDriftを検出する。`build:compile`はBackend Artifactだけを作り、`package.json`のFrontend Build ScriptまたはCIが`build:compile -> frontend:generate -> frontend build`を順に呼ぶ。Vite Pluginは後続Phaseへ送る
- B: `php blackops build:compile`がBackend ArtifactとTypeScriptを常に同時生成する。Frontendを持たないApplicationにも生成先設定を必須にする
- C: Phase 15からNode PackageとVite Pluginを提供し、Vite Dev／BuildがPHP Commandを自動実行する。Project CLIの明示生成は補助とする

### Recommendation

Aを推奨する。

Backend-only ApplicationへNode／Frontend Directoryを要求せず、生成の入口はFramework Update後も現在のProject CLIから解決される。Vite固有Process管理、Windows／WSL Path、PHP起動、Watch、Stale Route Cacheに相当する問題をPhase 15へ持ち込まない。Drift CheckをCIへ置けば、生成物をCommitするApplicationと`.gitignore`するApplicationの両方を支援できる。

既定出力先候補は`resources/js/blackops/`とし、Application Configで変更可能にする。生成中断時に半端なTreeを残さないAtomic Replaceと、生成元Build IDを持つManifestを必須とする。

[ANSWER]



[/ANSWER]

## Question 4: Which Operations Are Generated

どのOperationをFrontend Contractへ含めるか。

### Options

- A: `#[Route]`を持つHTTP Operationをすべて生成対象とし、Console-only／Internal Operationを除外する。新しい`#[Frontend]` Attributeは追加しない。生成対象の絞り込みは将来必要になった時点でConfig Allowlist／Excludeを検討する
- B: 新しい`#[Frontend]` Attributeを付けたHTTP Operationだけを生成する。RouteとFrontend公開を二段階の明示Opt-inにする
- C: `config/frontend.php`のOperation Type Allowlistだけを正本とし、各OperationにはAttributeを追加しない

### Recommendation

Aを推奨する。

HTTP Routeはすでに外部Requestを受けるPublic Transport Contractであり、生成されたLocal TypeScriptはEndpointを新たに公開しない。毎回`#[Route]`と`#[Frontend]`を重ねると、Operation追加時の同期漏れと記述量が増える。Authorization、Authentication、CORS、CSRFは生成対象かどうかに関係なくApplication／HTTP Runtimeが強制しなければならない。

[ANSWER]



[/ANSWER]

## Question 5: Sensitive Input and Output

`#[Sensitive]` Propertyを生成Contractでどう扱うか。

### Options

- A: OperationValueのSensitive PropertyはRequestに必要なWrite-only Inputとして名前とTypeだけを生成し、値、Default、Example、Log Helperを生成しない。Generated Result TypeへOperationValueを混ぜない。OutcomeのSensitive PropertyはHTTP ResponseとGenerated Typeの不一致や意図しない公開を避けるため、Phase 15ではBuild Errorにする
- B: Sensitive PropertyをOperationValue Input Typeからも除外し、新しいField単位の明示許可Attributeがある場合だけ生成する
- C: Sensitive AttributeはDiagnostics専用としてFrontend生成では考慮せず、Value／Outcomeの全Public Propertyを通常どおり生成する

### Recommendation

Aを推奨する。

PasswordやToken相当の入力はFrontendから送る必要があるため、Typeから消すとOperationを呼べない。一方、Sensitive Outcomeを通常の成功Response Typeへ含めるのは安全な既定ではない。入力Typeと出力Typeを分離し、生成物、Test Fixture、Error Messageへ実値を一切埋め込まないContractが、`#[Sensitive]`の意図と利用可能性を両立する。

[ANSWER]



[/ANSWER]

## Proposed Impact of A / A / A / A / A

- Language-neutralなFrontend Contract ManifestをOperation／HTTP Manifestと同じBuild IDから生成する
- Frontend Contract CompilerがOperationValue Constructor、HTTP Binding Attribute、Outcome、Execution Strategy、Validation Metadataを検査する
- `frontend:generate`がContract ManifestからFramework-neutral TypeScript ESMをAtomicに全再生成する
- `frontend:check`が現在のBackend Artifact、Contract Manifest、TypeScript TreeのDriftをExit Codeで報告する
- ModuleはOperation Typeごとに分割し、Named ExportでTree-shakingできる構成にする
- 入力はOperationValue型の単一Objectとし、GeneratorがPath／Query／Header／Bodyへ振り分ける
- DescriptorはURL／MethodとTransport Dataを返すが、Network Request、Authentication、Retry、Polling、State管理を実行しない
- Inline Outcome、204、Deferred 202、Validation 422、業務Rejected、Internal 500のTypeを生成する
- HTTP Routeを持つOperationだけを対象とし、新しいOperation単位Opt-in Attributeを要求しない
- Sensitive ValueはWrite-only Input Typeへ残すが、値／Default／Exampleを生成しない。Sensitive OutcomeはBuild Errorにする
- React／Vue／Svelte／Inertia Adapter、Vite Plugin、Generated Polling ClientはPhase 15の初期Scopeに含めない

## Decision

[DECISION]

Awaiting user answers.

[/DECISION]

## Consequences

[CONSEQUENCES]

Awaiting decision.

[/CONSEQUENCES]

## References

- [Laravel Wayfinder](https://github.com/laravel/wayfinder)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [Core Model](../spec/01-core-model.md)
- [Handler and Result](../spec/04-handler-and-result.md)
- [HTTP Adapter](../spec/05-http.md)
- [Registry and Manifest](../spec/08-registry-and-manifest.md)
- [Sensitive Projection](../spec/25-sensitive-projection.md)
- [Operation Authoring and Build Discovery](../spec/50-operation-authoring-and-build-discovery.md)
- [Operation Diagnostics](../spec/65-operation-diagnostics.md)
