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
- D: 生成Operation自体を`async` Callableにし、第1引数へOperationValue、第2引数へ通信Optionを渡す。生成関数が共通Client Runtimeを内部利用して`fetch`し、Typed Resultを直接返す。Request Descriptorを利用者の通常APIへ露出しない

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

await httpは何者？
Bまでやる場合どうなる？
----
Requestとfetchで分離せずに、内部的にfetchを持ったオブジェクトを提供することは可能？
```
import { ShowWelcome } from '@/blackops/operations/welcome/show-welcome';

const result = await ShowWelcome({}, {});
```
※第1引数にOperationValueの引数、第二引数でクライアントに渡せるオプション

[/ANSWER]

### Response to Review Comment: Direct Callable Operation

可能である。Userが提示したAPIをOption Dとして追加し、レビュー後の推奨をDへ変更する。

```ts
import { ShowWelcome } from '@/blackops/operations/welcome/show-welcome';

const result = await ShowWelcome(
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

const result = await CreateOrder(
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
- 各Operationが通信処理を重複実装せず、生成Tree内の共通Client Runtimeを呼ぶ
- Inline 200／204、Deferred 202、Rejected 4xx、Validation 422、Internal 500をOperation固有のDiscriminated Unionとして返す
- Network FailureとAbortもResult Unionへ安全なTransport Errorとして含め、未型付けの`fetch`例外を通常制御フローへ漏らさない
- Retry、Polling、Cache、State Managementは持たない

`OperationFetch`と`OperationAbortSignal`はWeb Fetch互換の最小Structural Typeとして生成する。`lib.dom.d.ts`の`Window`、UI Element、React等へ依存しない。Q2のAは「UI DOM／Frontend Frameworkに依存しない」という意味を維持しつつ、Option Dが必要とするWeb Fetch互換契約だけを共通Runtime境界にする。

生成名はPHP OperationのShort Class Nameを維持し、`ShowWelcome`、`CreateOrder`のようなPascalCase Callableとする。TypeScriptでは大文字関数がComponentと誤認される可能性はあるが、Backend Operationとの一対一対応とImport時の発見性を優先する。File PathはOperation TypeではなくFeature／Operation名から決定的に生成し、衝突をBuild Errorにする。

Option Dは内部的にはBと同じTyped Client責務を持つが、利用者がDescriptor生成とClient実行を二段階で書く必要がない。Link URLやForm Actionだけを得るHelperは初期Scopeへ含めず、まずOperation実行に絞る。

### Revised Recommendation

Dを推奨する。

Userが求める`await ShowWelcome(value, options)`を最短のCanonical APIにできる。Q2のFramework-neutral ESM、Q3の明示生成、Q4の全HTTP Operation、Q5のSensitive境界とも矛盾しない。BlackOpsがHTTP Result変換まで所有するためAよりPublic Contractは広がるが、Bの`createBlackOpsClient().execute(descriptor)`より利用側の定型処理を減らせる。

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
- C: Phase 15からNode PackageとVite Pluginを提供し、Vite Dev／BuildがPHP Commandを自動実行する。Project CLIの明示生成は補助とする

### Recommendation

Aを推奨する。

Backend-only ApplicationへNode／Frontend Directoryを要求せず、生成の入口はFramework Update後も現在のProject CLIから解決される。Vite固有Process管理、Windows／WSL Path、PHP起動、Watch、Stale Route Cacheに相当する問題をPhase 15へ持ち込まない。Drift CheckをCIへ置けば、生成物をCommitするApplicationと`.gitignore`するApplicationの両方を支援できる。

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
- ModuleはOperationごとに分割し、PHP Operation Short Class Nameと同じPascalCase CallableをNamed Exportする
- Callableの第1引数はOperationValue型の単一Object、第2引数はFramework-neutralな`OperationCallOptions`とする
- Callableは共通Client Runtimeを内部利用して`fetch`し、Path／Query／Header／Bodyを構築してOperation固有のTyped Resultを直接返す
- Browserは`globalThis.fetch`／Relative URLを既定とし、SSR／Node／Testは`fetch`／`baseUrl`を呼出単位で差し替えられる
- Inline Outcome、204、Deferred 202、Validation 422、業務Rejected、Internal 500、Transport ErrorのDiscriminated Unionを生成する
- HTTP Routeを持つOperationだけを対象とし、新しいOperation単位Opt-in Attributeを要求しない
- Sensitive ValueはWrite-only Input Typeへ残すが、値／Default／Exampleを生成しない。Sensitive OutcomeはBuild Errorにする
- Request Descriptorの通常Public API、Link／Form Helper、React／Vue／Svelte／Inertia Adapter、Vite Plugin、Retry、Generated Polling ClientはPhase 15の初期Scopeに含めない

## Decision

[DECISION]

Awaiting Question 1 Option D final confirmation. Questions 2 through 5 are answered A.

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
