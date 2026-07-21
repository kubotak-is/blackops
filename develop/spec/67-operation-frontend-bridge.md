# Operation Frontend Bridge

## Goal

BackendのOperation／HTTP定義を正本として、Frontendから型付きでOperationを実行・参照できるFramework-neutral TypeScript ESMを生成する。

利用者はURL、HTTP Method、Path／Query／Header／Body Binding、成功Outcome、Deferred Acknowledgement、Validation／Rejected／Failure ShapeをFrontendへ手書きで複製しない。生成OperationはCallable／Thenableではなく、明示的なMethodを持つimmutableなOperation Objectとする。

## Source of Truth and Artifacts

生成Pipelineは次の一方向とする。

```text
PHP Operation Definition + OperationValue + Outcome
  + Operation Manifest + HTTP Manifest
    -> Frontend Contract Manifest
      -> Framework-neutral TypeScript ESM Tree
```

`build:compile`は既存Operation／HTTP／Container Artifactと同じApplication Build IDでFrontend Contract Manifestを生成する。Frontend Contract Manifestは言語中立なPHP配列Artifactとし、Credential、Environment Secret、Runtime Value、Default Value、Exampleを含めない。

Installed Applicationの`app.build`は次のArtifact Pathを持つ。

```php
return [
    'build' => [
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

Production HTTP／Worker RuntimeはFrontend Artifactを読み込まない。Backend-only Applicationも`build:compile`で小さなContract Artifactを生成するが、`frontend:generate`を実行しない限りTypeScript Source Treeを持たない。

各ArtifactはSchema Version、Application Build ID、Payloadを持つ。Operation／HTTP／Frontend ManifestのBuild ID不一致、Schema非対応、Payload破損はFrontend生成前にFail-fastする。Source ReflectionへFallbackしない。

## Eligible Operations

- `#[Route]`を持つ全HTTP Operationを生成する
- Routeを持たないConsole／Internal Operationは生成しない
- 新しい`#[Frontend]` Opt-in Attributeを追加しない
- Authorization、Authentication、CORS、CSRFは生成対象選択ではなくApplication／HTTP Runtimeが強制する
- Operation Type ID、Route、Definition Class、Value Class、Outcome Class、Execution StrategyがManifest間で一致しなければBuild Errorにする

一つのOperationにつき一つのRouteという既存HTTP Contractを維持する。複数Route、Custom Responderによる非標準Response、File／Stream ResponseはPhase 15の生成対象にしない。

## Contract Manifest

Frontend Contract ManifestはOperationごとに少なくとも次を保持する。

- Operation Type ID
- Definition ClassとShort Class Name
- HTTP MethodとPath Template
- Execution Strategyの安定識別子
- OperationValue Class
- Value Fieldごとの名前、Native Scalar Kind、Nullable、Required、Binding Source、Transport Name、Sensitive有無、Validation Rule Metadata
- Outcome Class、成功Mode、Public Fieldごとの再帰的なScalar／DTO／List Schema
- 生成Module Path、Export Name

Binding Sourceは`path`、`query`、`header`、`body`のいずれかとする。AttributeなしConstructor Parameterは同名Body Fieldとして扱う。Constructor Defaultを持つFieldはOptionalだが、Default実値はManifestとTypeScriptへ出さない。

Validation MetadataはField、Rule、安定Code、公開可能なRule Parameterだけを持つ。FrontendでPHP Validationを再実装せず、422 ViolationのField／Rule／Codeを型付けするために使用する。

## Supported Types

Phase 15の初期Contractは、現在のHTTP Binderが直接扱うNative Scalarを正本とする。

| PHP | Contract Scalar Kind | TypeScript |
| --- | --- | --- |
| `string` | `string` | `string` |
| `int` | `integer` | `number` |
| `float` | `float` | `number` |
| `bool` | `boolean` | `boolean` |
| Nullable | Base Kind＋`nullable=true` | `T \| null` |
| Constructor Defaultあり | `required=false` | Optional Property |
| `void`／`EmptyOutcome` | Outcome `mode=void` | `undefined` |

Frontend ContractはPHP `int`と`float`を同じ`number`へ正規化せず、`integer`と`float`を保持する。Generated TypeScript型はいずれも`number`だが、D101のPath／Query／Header Canonical EncodeとOutcome Decodeの整数性検査が異なるためである。Scalar Kindを変更する場合はFrontend Contract Artifact Schema Versionを上げ、旧ArtifactをFreshとして扱わない。

Unionは`null`を含むNullableだけを許可する。Untyped、`mixed`、Intersection、Scalar以外のUnion、Object、Resource、Callable、Arrayの要素型不明、Backed Enum、DateTime等は初期ScopeでBuild Errorにする。

PHP Native TypeだけからTypeScript Contractを確定できない型を`any`または`unknown`へ暗黙変換しない。Outcome OutputだけはD104により`OutcomeData` Nested DTO、Nullable DTO、`#[ListOf] list<DTO>`を追加する。Map、Scalar List、Enum、Date／TimeとOperationValueのNested／Array InputはUnsupportedのままとする。再帰Shapeの正本は[Structured Outcome Contract](73-structured-outcome-contract.md)とする。

## Generated Module Layout

既定出力RootはApplication Rootの`resources/js/blackops/`とする。生成Treeは次を持つ。

```text
resources/js/blackops/
├── index.ts
├── client.ts
├── types.ts
├── manifest.json
└── operations/
    ├── welcome/show-welcome.ts
    └── order/create-order.ts
```

Module DirectoryはOperation Type IDの最終Segmentを除くPrefix、File NameはOperation Short Class Nameのkebab-caseから決定する。Export NameはPHP Operation Short Class Nameと同じPascalCaseとする。

例：

| Type ID | PHP Operation | Module |
| --- | --- | --- |
| `welcome.show` | `ShowWelcome` | `operations/welcome/show-welcome.ts` |
| `order.create` | `CreateOrder` | `operations/order/create-order.ts` |
| `diagnostics.failure.trigger` | `TriggerFailure` | `operations/diagnostics/failure/trigger-failure.ts` |

同じModule Path、Export Name、Case-insensitive Pathへ複数Operationが解決される場合はBuild Errorにする。生成順、Field順、Import順、JSON Key順、改行は決定的にする。

## Operation Object API

生成ModuleはimmutableなOperation ObjectをNamed Exportする。

```ts
import { CreateOrder } from '@/blackops/operations/order/create-order';

const result = await CreateOrder.fetch(
  {
    reference: 'order-1042',
    amount: 1200,
  },
  {
    credentials: 'include',
    signal,
  },
);
```

Operation Objectは次を提供する。

```ts
CreateOrder.fetch(value, options);
CreateOrder.toRequest(value, options);
CreateOrder.url(urlParameters);

CreateOrder.type;
CreateOrder.method;
CreateOrder.path;
CreateOrder.strategy;
```

- `fetch()`はHTTP Requestを送信し、Operation固有のTyped Resultを返す
- `toRequest()`は送信せず、同じBinding ContractからFramework所有の`OperationRequest`を返す
- `url()`はPath／Query Bindingだけを受け取り、URL文字列を返す。Header／Body Fieldを要求しない
- `type`、`method`、`path`、`strategy`はReadonly Literal Propertyとする
- Callable Function、Class Constructor、Thenable、暗黙実行を提供しない

Path／QueryがないOperationの`url()`は引数なしとする。Required Path／Queryがある場合だけ、必要Fieldを持つ生成Typeを要求する。

## Request Binding

`toRequest()`と`fetch()`は同じ生成Bindingを使う。

- Pathは一SegmentごとにPercent Encodeし、Path Templateの対応Placeholderだけを置換する
- QueryはURL Encodingし、Optional未指定を省略し、`null`は明示Contractに従い文字列化せず省略する
- Header BindingはOperationValueからHeaderへ移し、Bodyへ重複させない
- Body BindingはJSON Objectへ集約する
- GET／HEADはBodyを生成しない
- BodyがあるRequestは`Content-Type: application/json`をFrameworkが設定する
- Constructor DefaultのOptional Fieldが未指定ならRequestへ含めず、Server側Defaultを使用する
- Method、Path、Binding由来Header、Body、Framework管理`Content-Type`はCall Optionで上書きできない

Path／Query／HeaderのScalarはD101のServer Bindingと同じCanonical文字列へ変換する。`string`はそのまま、`integer`はJavaScriptのSafe Integer、`float`は有限なNumberだけを10進／JSON Number表現へ変換し、`boolean`は小文字`true`／`false`を使用する。Nullableの`null`とOptionalの`undefined`はPathでは許可せず、Query／Headerでは送信を省略する。Unsafe Integer、NaN、Infinity、Runtime Type不一致、PHPの弱いCast、`1`／`0` Boolean Aliasを送信しない。JavaScriptで正確に表現できない64-bit IntegerはApplicationが`string`として宣言する。

Operation-owned HeaderとCall Option HeaderがCase-insensitiveに衝突する場合はOperation-owned Headerを使用する。Credential Headerの自動保存、Token取得、CSRF取得は行わない。

## Client Runtime and Options

共通Client Runtimeは各Operation Moduleへ通信処理を複製せず、Web Fetch互換Structural Typeだけに依存する。

```ts
type OperationCallOptions = {
  baseUrl?: string;
  headers?: Record<string, string>;
  credentials?: 'omit' | 'same-origin' | 'include';
  signal?: OperationAbortSignal;
  fetch?: OperationFetch;
};
```

- Browserは`globalThis.fetch`とRelative URLを既定にする
- SSR／Node／Testは呼出単位で`fetch`と`baseUrl`を差し替えられる
- `baseUrl`はHTTP／HTTPSのOriginとOptional Base Pathだけを許可し、Credential、Query、Fragmentを拒否する
- `lib.dom.d.ts`のWindow／ElementやFrontend Framework Typeへ依存しない
- Missing Fetch、Invalid Base URL、Network Failure、Abort、Unexpected ResponseをTyped Transport Errorへ変換する
- Retry、Backoff、Cache、Polling、State Managementを実装しない

Global Mutable Client ConfigurationはSSR Request間のCredential Leakを招くため提供しない。

## Bound Client Factory

Generated Root `index.ts`は全Operation／共通型と`createBlackOpsClient()`を決定的にExportする。FactoryはHTTP Frameworkへ依存せず、SvelteKit Server `event.fetch`、Native／Global Fetch、Test DoubleをApplication-owned CastやAdapterなしで受け取る。

```ts
const blackops = createBlackOpsClient({
  baseUrl: 'http://blackops:8080',
  fetch: event.fetch,
  headers: { Authorization: `Bearer ${token}` },
  credentials: 'same-origin',
});

const result = await blackops.CreateOrder.fetch(value, {
  idempotencyKey: 'order-1042',
});
```

- FactoryはBase URLとFetchを必須、Default HeaderとCredential Modeを任意とし、入力をCopy／Freezeする
- Bound CallはBase URLとFetchをOverrideできず、Header、Credential、SignalだけをCall単位で変更できる
- DefaultとCall HeaderはCase-insensitiveにMergeし、Call側を優先する
- OperationValue由来Header、Generated `Content-Type`、専用Option由来`Idempotency-Key`を任意Headerで上書きさせない
- POST／PUT／PATCH／DELETEの`.fetch()`／`.toRequest()`だけが`idempotencyKey`を受理する。1〜255文字の空白／Control Characterを含まないPrintable ASCIIだけを送信する
- GET／HEAD、`.status()`、`.wait()`、Raw `Idempotency-Key` HeaderはNetwork Call前に拒否する
- 不正Bindingは非同期APIでは`invalid_client_options`、同期`.url()`／`.toRequest()`では値を含まないSafe Errorにする
- Bound Client、各Bound Operation、Factory／Call SnapshotをFreezeし、並列Call間でHeader、Credential、Signal、Idempotency Stateを共有しない
- Existing Unbound Operation ObjectのSignatureとRuntimeを維持する

BackendのIdempotency Storage／Duplicate SuppressionはPhase 19の責務であり、このFactoryはHeader生成までを提供する。

## Typed Result

`fetch()`は例外を通常のHTTP制御フローへ使わず、`ok`と`kind`でNarrowingできるDiscriminated Unionを返す。

成功側：

- Inline Outcome: `{ ok: true, kind: 'completed', status: 200, data: TOutcome }`
- Inline Void: `{ ok: true, kind: 'completed', status: 204, data: undefined }`
- Deferred: `{ ok: true, kind: 'accepted', status: 202, data: DeferredAcknowledgement }`

失敗側：

- Protocol: `{ ok: false, kind: 'protocol', status: 400, error: ProtocolError }`
- Rejected: `{ ok: false, kind: 'rejected', status: 400 | 401 | 403 | 404 | 409, error: OperationRejection }`
- Validation: `{ ok: false, kind: 'validation', status: 422, error: ValidationRejection<TField> }`
- Internal: `{ ok: false, kind: 'internal', status: 500, error: InternalOperationError }`
- Transport: `{ ok: false, kind: 'transport', status: null, error: OperationTransportError }`

Operation IDはResponse Contractに存在する場合だけ返す。`operationId?: string`を架空値で補完しない。Transport Error Codeは少なくとも`missing_fetch`、`invalid_base_url`、`network_error`、`aborted`、`unexpected_response`を安定識別子として持つ。

Generated RuntimeはStatus、Content-Type、JSON Object、Discriminant、既知共通Field、Operation固有OutcomeのScalar／DTO／Listを全階層で検証する。不正または未知のResponse Shapeを成功型へCastせず`unexpected_response`へ変換する。Raw Body、Credential、Exception DetailをTransport Errorへ含めない。

## Sensitive Data

- Sensitive OperationValue FieldはFrontendから送信するWrite-only Inputとして名前と型を生成する
- Sensitive Inputの値、Constructor Default、Example、Fixture、Log HelperをContract Manifest／Generated Treeへ埋め込まない
- OperationValueを成功Result Typeへ混ぜない
- `#[Sensitive]`を持つOutcome Public PropertyはFrontend Contract Build Errorにする
- Reserved Sensitive Key Patternは生成時の警告／拒否に利用できるが、Attribute Metadataを正本とする
- Generated Error、Manifest、MarkerへAbsolute Source Path、Environment、Credentialを含めない

Frontend TypeはAccess Controlではない。Authentication、Authorization、Tenant、CORS、CSRF、Encryption、Browser Storage、RetentionはApplication責務である。

## Commands and Configuration

Canonical Commandは次とする。

```text
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

`frontend:generate`と`frontend:check`はFrontend Contract ManifestがMissing、Stale、Build ID不一致の場合にFailし、`build:compile`を暗黙実行しない。

Optional `config/frontend.php`は生成先だけを設定する。

```php
return [
    'output' => dirname(__DIR__) . '/resources/js/blackops',
];
```

Config欠落時は上記を既定とする。OutputはApplication Root配下の絶対Directoryでなければならず、Application Root自体、Filesystem Root、Symlink、Repository外Pathを拒否する。

## Atomic Generation and Drift

- 生成Treeは同じParent上のTemporary Directoryへ完全生成・検証後、Atomic Replaceする
- 初回生成時、非空かつBlackOps Markerを持たないDirectoryを上書き／削除しない
- MarkerはGenerator Schema Version、Application Build ID、Contract Hashを持つ
- `frontend:generate`は生成対象外Fileを残さず全Treeを置き換える
- 失敗時は既存の有効Treeを維持し、Temporary Directoryをcleanupする
- `frontend:check`は生成せず、Expected TreeとFile Path／Bytes／余剰Fileを比較する
- FreshはExit 0、Missing／DriftはExit 1、Invalid Config／Artifact／ContractはExit 2とする
- stdoutへ結果、stderrへ安全な診断を分離し、Source ValueやCredentialを表示しない

Generated TreeはCommitしても`.gitignore`してもよい。CIは`build:compile -> frontend:generate -> frontend:check -> TypeScript compile/test`を実行し、生成SourceのDriftとRuntime挙動を検証する。

## Deferred Scope

- Phase 16のStatus／Outcome APIとPollingは生成Clientへ組み込まない
- React／Vue／Svelte／Inertia Adapter、Hook、Form Helper、Vite Pluginは追加しない
- OpenAPI、Remote Schema Registry、NPM Package Publicationを追加しない
- Map、Scalar List、Enum、Date／Time、Nested／Array Input、Upload／Stream、Custom Responderは後続Decisionへ送る
- Generated Diagnostics UI、Retry、Cache、Offline Queue、State Managementを追加しない

## Traceability

- Decision: [D100 Phase 15 Operation Frontend Bridge](../decisions/100-phase-15-operation-frontend-bridge.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
- Core Model: [Core Model](01-core-model.md)
- Handler and Result: [Handler and Result](04-handler-and-result.md)
- HTTP: [HTTP Adapter](05-http.md)
- Manifest: [Operation Registry and Manifest](08-registry-and-manifest.md)
- Sensitive: [Sensitive Projection](25-sensitive-projection.md)
- Build Discovery: [Operation Authoring and Build Discovery](50-operation-authoring-and-build-discovery.md)
- Structured Outcome: [Structured Outcome Contract](73-structured-outcome-contract.md)
