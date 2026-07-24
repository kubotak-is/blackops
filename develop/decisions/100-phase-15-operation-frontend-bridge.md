# D100: Phase 15 Operation Frontend Bridge

Status: Decided

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
- D: 生成OperationをimmutableなOperation Objectにし、`.fetch(value, options)`で共通Client Runtimeを使ったTyped HTTP実行、`.toRequest(value, options)`で未送信Request構築、`.url(parameters)`でURL生成、Propertyで静的Metadata参照を提供する。Callable／Thenableにはしない

### Initial Recommendation

Aを推奨する。

Wayfinderに近い小さな責務で、React／Vue／Svelte／Inertia／独自HTTP Clientのどれからも利用できる。BlackOps固有のOperationValue、Outcome、Deferred Acknowledgement、Rejection Typeまでは表現しつつ、認証方式、CSRF、Cookie、Base URL、Timeout、Abort、RetryをApplication責務のまま保てる。Phase 16でStatus／Outcome APIが決まる前にPolling Clientを固定することも避けられる。

想定する利用形は次である。名称とPathは最終Specificationで衝突検査を含めて固定する。

Option AではBlackOpsは`request`までを生成し、実際の送信にはBrowser標準の`fetch`、Axios、Application独自Clientなどを使う。

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
const response = await fetch(request.url, {
  method: request.method,
  headers: {
    'Content-Type': 'application/json',
    ...request.headers,
  },
  body: request.body === undefined
    ? undefined
    : JSON.stringify(request.body),
});
```

初稿の`await http(request)`に登場した`http`は、Applicationが所有するHTTP Clientを表す説明用の仮名であり、BlackOpsのAPIではない。説明として不明瞭だったため、標準`fetch`を使う完全な例へ置き換えた。

### If Option B Is Selected

Option BではRequest Descriptorに加え、BlackOpsがFramework-neutralなTyped Executorも生成する。Operation関数が直接通信するのではなく、DescriptorとExecutorを分けることで、URLだけをLinkへ使う場合とHTTP送信する場合の両方を維持する。

```ts
import { createBlackOpsClient } from '@/blackops/client';
import { triggerFailure } from '@/blackops/operations/diagnostics/failure/trigger';

const blackops = createBlackOpsClient({
  baseUrl: 'https://api.example.com',
  fetch: window.fetch.bind(window),
  credentials: 'include',
  beforeRequest(request) {
    return request;
  },
});

const result = await blackops.execute(triggerFailure({
  reference: 'checkout-1042',
  sensitiveNote: 'not-for-logs',
}));

if (result.ok) {
  // InlineならdataはFailureTriggered、DeferredならDeferredAcknowledgement。
  console.log(result.data);
} else if (result.status === 422) {
  // errorはValidationRejectionとして型付けされる。
  console.log(result.error.violations);
}
```

生成されるResultは概念上、次のDiscriminated Unionになる。

```ts
type TriggerFailureResult =
  | { ok: true; status: 200; data: FailureTriggered }
  | { ok: false; status: 400 | 401 | 403 | 404 | 409; error: OperationRejection }
  | { ok: false; status: 422; error: ValidationRejection }
  | { ok: false; status: 500; error: InternalOperationError };
```

Deferred Operationなら成功側を`{ ok: true; status: 202; data: DeferredAcknowledgement }`にする。`void` Outcomeは`{ ok: true; status: 204; data: undefined }`になる。

Bを選ぶと、BlackOpsはAに加えて次もPublic Contractとして所有する。

- `fetch`互換関数、Base URL、Cookie／Credential、追加Headerの注入方法
- JSON Encode／Decode、204、非JSON Response、Network Failure、Abortの扱い
- HTTP StatusからSuccess／Rejection／Validation／Internal Error Unionへの変換
- BrowserとSSR／Nodeの両方で同じExecutorを使う境界
- Client RuntimeのBackward CompatibilityとGenerated Typeの整合性

この範囲を受け入れるなら、Bは利用側の定型処理を大きく減らせる。実装する場合もReact／Vue／Svelteへは依存せず、Q2はAのまま成立する。Retry、Polling、State管理はBでもPhase 15へ含めない。

[ANSWER]

D。Callable／Thenableではなく、`CreateOrder.fetch(value, options)`をCanonical APIにする。`toRequest()`、`url()`、静的Metadata参照を同じOperation Objectへ持たせる。

[/ANSWER]

### Response to Review Comment: Explicit Operation Object

Userとの追加相談で、直接Callableより明示的なMethodを持つOperation Objectの方が、実行と参照を区別し、将来の拡張点を安全に追加できると判断した。

```ts
import { ShowWelcome } from '@/blackops/operations/welcome/show-welcome';

const result = await ShowWelcome.fetch(
  {},
  {
    signal: abortController.signal,
    credentials: 'include',
  },
);

if (result.ok) {
  console.log(result.data.message);
}
```

入力があるOperationも同じ形になる。

```ts
import { CreateOrder } from '@/blackops/operations/order/create-order';

const result = await CreateOrder.fetch(
  {
    reference: 'order-1042',
    amount: 1200,
  },
  {
    headers: {
      'X-CSRF-Token': csrfToken,
    },
  },
);
```

同じObjectから、HTTP送信なしのRequest構築、URL生成、静的Metadata参照も行える。

```ts
const request = CreateOrder.toRequest(value, options);
const url = CreateOrder.url(urlParameters);

CreateOrder.type;     // 'order.create'
CreateOrder.method;   // 'POST'
CreateOrder.path;     // '/orders'
CreateOrder.strategy; // 'inline'
```

第2引数はFrontend Frameworkに依存しない共通Typeとする。

```ts
type OperationCallOptions = {
  baseUrl?: string;
  headers?: Record<string, string>;
  credentials?: 'omit' | 'same-origin' | 'include';
  signal?: OperationAbortSignal;
  fetch?: OperationFetch;
};
```

- Browserでは既定で`globalThis.fetch`とRelative URLを使う
- SSR／Node／Testでは第2引数の`fetch`と`baseUrl`を差し替えられる
- `headers`、`credentials`、`signal`は呼出単位で渡せる
- HTTP Method、Path Parameter反映、Query、Body、`Content-Type`は生成Contractが所有し、第2引数から上書きできない
- `.fetch()`は`.toRequest()`と生成Tree内の共通Client Runtimeを利用し、各Operationへ通信処理を重複実装しない
- Inline 200／204、Deferred 202、Rejected 4xx、Validation 422、Internal 500をOperation固有のDiscriminated Unionとして返す
- Network FailureとAbortもResult Unionへ安全なTransport Errorとして含め、未型付けの`fetch`例外を通常制御フローへ漏らさない
- Retry、Polling、Cache、State Managementは持たない

`OperationFetch`と`OperationAbortSignal`はWeb Fetch互換の最小Structural Typeとして生成する。`lib.dom.d.ts`の`Window`、UI Element、React等へ依存しない。Q2のAは「UI DOM／Frontend Frameworkに依存しない」という意味を維持しつつ、Option Dが必要とするWeb Fetch互換契約だけを共通Runtime境界にする。

生成名はPHP OperationのShort Class Nameを維持し、`ShowWelcome`、`CreateOrder`のようなPascalCase Operation Objectとする。File PathはOperation DefinitionのNamespace／Short Class Nameから決定的に生成し、同じ出力PathまたはExport名の衝突をBuild Errorにする。

Callable／Thenableは採用しない。JavaScriptのThenable同化による暗黙実行を避け、`.fetch()`が通信、`.toRequest()`が未送信Request、`.url()`がURL参照であることをAPI名から判別できるようにする。将来の`.form()`やDeferred Status連携は同じObjectのCapabilityとして追加できるが、Phase 15には含めない。

### Revised Recommendation

Dを推奨する。

`await ShowWelcome.fetch(value, options)`は通信を明示しつつ、`toRequest()`と`url()`を同じOperation単位で発見できる。Q2のFramework-neutral ESM、Q3の明示生成、Q4の全HTTP Operation、Q5のSensitive境界とも矛盾しない。BlackOpsがHTTP Result変換まで所有するためAよりPublic Contractは広がるが、利用側の定型処理を減らし、将来CapabilityをMethodとして追加できる。

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

A

[/ANSWER]

## Question 3: Generation and Frontend Build Integration

生成CommandとBuild連携をどうするか。

### Options

- A: `php blackops frontend:generate`を明示Commandとして追加し、現在のOperation／HTTP Build Artifactを検証してTypeScriptを決定的に全再生成する。`php blackops frontend:check`でDriftを検出する。`build:compile`はBackend Artifactだけを作り、`package.json`のFrontend Build ScriptまたはCIが`build:compile -> frontend:generate -> frontend build`を順に呼ぶ。Vite Pluginは後続Phaseへ送る
- B: `php blackops build:compile`がBackend ArtifactとTypeScriptを常に同時生成する。Frontendを持たないApplicationにも生成先設定を必須にする
- C: Phase 15からNode PackageとVite Pluginを提供し、Vite Dev／BuildがPHP Commandを自動実行する。BlackOps CLIの明示生成は補助とする

### Recommendation

Aを推奨する。

Backend-only ApplicationへNode／Frontend Directoryを要求せず、生成の入口はFramework Update後も現在のBlackOps CLIから解決される。Vite固有Process管理、Windows／WSL Path、PHP起動、Watch、Stale Route Cacheに相当する問題をPhase 15へ持ち込まない。Drift CheckをCIへ置けば、生成物をCommitするApplicationと`.gitignore`するApplicationの両方を支援できる。

既定出力先候補は`resources/js/blackops/`とし、Application Configで変更可能にする。生成中断時に半端なTreeを残さないAtomic Replaceと、生成元Build IDを持つManifestを必須とする。

[ANSWER]

A

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

A

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

A

[/ANSWER]

## Proposed Impact of D / A / A / A / A

- Language-neutralなFrontend Contract ManifestをOperation／HTTP Manifestと同じBuild IDから生成する
- Frontend Contract CompilerがOperationValue Constructor、HTTP Binding Attribute、Outcome、Execution Strategy、Validation Metadataを検査する
- `frontend:generate`がContract ManifestからFramework-neutral TypeScript ESMをAtomicに全再生成する
- `frontend:check`が現在のBackend Artifact、Contract Manifest、TypeScript TreeのDriftをExit Codeで報告する
- ModuleはOperationごとに分割し、PHP Operation Short Class Nameと同じPascalCase Operation ObjectをNamed Exportする
- `.fetch()`と`.toRequest()`の第1引数はOperationValue型の単一Object、第2引数はFramework-neutralな`OperationCallOptions`とする
- `.fetch()`は共通Client Runtimeを内部利用し、Path／Query／Header／Bodyを構築してOperation固有のTyped Resultを返す
- `.toRequest()`は同じBindingから未送信Request、`.url()`はPath／Queryに必要なParameterだけからURLを返す
- `type`、`method`、`path`、`strategy`をReadonly Propertyとして参照できる
- Browserは`globalThis.fetch`／Relative URLを既定とし、SSR／Node／Testは`fetch`／`baseUrl`を呼出単位で差し替えられる
- Inline Outcome、204、Deferred 202、Validation 422、業務Rejected、Internal 500、Transport ErrorのDiscriminated Unionを生成する
- HTTP Routeを持つOperationだけを対象とし、新しいOperation単位Opt-in Attributeを要求しない
- Sensitive ValueはWrite-only Input Typeへ残すが、値／Default／Exampleを生成しない。Sensitive OutcomeはBuild Errorにする
- Callable／Thenable、Form Helper、React／Vue／Svelte／Inertia Adapter、Vite Plugin、Retry、Generated Polling ClientはPhase 15の初期Scopeに含めない

## Decision

[DECISION]

1. 生成Frontend APIはimmutableなOperation Objectとし、Callable／Thenableにしない。
2. `Operation.fetch(value, options)`は共通Client Runtimeを使ってHTTP Requestを送信し、Operation固有のTyped Resultを返す。
3. `Operation.toRequest(value, options)`は送信せず、同じBinding Contractから未送信Requestを返す。`Operation.url(parameters)`はPath／QueryだけからURLを返す。
4. Operation Objectは`type`、`method`、`path`、`strategy`をReadonly Propertyとして公開する。
5. 生成物はFramework-neutral TypeScript ESMとし、React、Vue、Svelte、Inertia、Viteへ依存しない。Web Fetch互換境界はFramework所有のStructural Typeで表す。
6. `frontend:generate`と`frontend:check`を明示Commandとして提供する。Backendの`build:compile`はBackend Artifactを生成し、Frontend Source Treeを暗黙変更しない。
7. `#[Route]`を持つ全HTTP Operationを生成し、新しいOperation単位のFrontend Opt-in Attributeは追加しない。
8. Sensitive OperationValue PropertyはWrite-only Input Typeとして名前と型だけを含め、値、Default、Example、Log Helperへ出さない。Sensitive Outcome PropertyはFrontend Contract Build Errorとする。
9. Browserは`globalThis.fetch`とRelative URLを既定とし、SSR、Node、Testは呼出Optionで`fetch`と`baseUrl`を差し替えられる。Method、Binding、Body、Content-Typeは上書きさせない。
10. Retry、Polling、Cache、State Management、Form Helper、Frontend Framework Adapter、Vite Plugin、Diagnostics UIはPhase 15の初期Scopeに含めない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Frontend利用者はOperationValueと通信Optionを`.fetch()`へ渡すだけで、Inline／Deferred／Rejected／Failure／Transport Resultを型付きで扱える。
- HTTP送信を独自Clientへ委ねる場合は`.toRequest()`、NavigationやLink生成には`.url()`を使い、Operation Metadataを別定義しない。
- Generated RuntimeはFramework-neutralだが、Web Fetch互換のRequest／Response／Abort Contractを所有する。
- Backend-only Applicationは`frontend:generate`を実行しなければFrontend Treeを持たずに運用できる。
- Frontend Contract ManifestとGenerated TreeはBuild IDとDeterministic HashでDriftを検出する。
- Sensitive Inputは送信可能な型を維持するが、生成物へ実値を埋め込まない。Sensitive Outcomeは安全なResponse Contractが別途定義されるまで生成を拒否する。
- Generated APIはExperimental Compatibility Policyの対象であり、Public Readiness前のMinor Releaseで変更され得る。

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
- [Operation Frontend Bridge](../spec/67-operation-frontend-bridge.md)
- [Phase 15 Delivery Plan](../spec/68-phase-15-delivery-plan.md)
