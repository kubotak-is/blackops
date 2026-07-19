# P15-003: Operation Object and Request Generation

Status: Ready

## Goal

P15-002のFrontend Contract Manifestだけを入力として、Framework-neutralなTypeScript ESM Treeを安全かつ決定的に生成する。

各HTTP OperationをPascalCaseのimmutable Operation Objectとして公開し、`.url()`、`.toRequest()`、Readonly `type`／`method`／`path`／`strategy`を提供する。HTTP送信とResponse Decodeはまだ実装せず、P15-004が同じRequest Runtimeを使って`.fetch()`を追加できる境界を作る。

## In Scope

- Optional `config/frontend.php`とDefault Output `resources/js/blackops`
- Application Root配下だけを許可するFrontend Output Configuration
- `frontend:generate` Project CLI CommandとLazy Registration
- Frontend Contract ManifestのLoad、Build ID、Schema、Contract Hash
- Deterministic TypeScript ESM Tree
- 共通`types.ts`／`client.ts`、Operation Module、Ownership Marker `manifest.json`
- Operation固有Value／URL Parameter Type
- immutable Operation ObjectとReadonly Literal Metadata
- `.url()`によるPath／Query相対URL生成
- `.toRequest()`による未送信`OperationRequest`生成
- Path Segment Encode、Query Encode、Header／Body Binding、Optional／Nullable境界
- JSON BodyとFramework管理`Content-Type`
- Call HeaderとOperation-owned HeaderのCase-insensitive Conflict解決
- Base URL検証、Credential／Query／Fragment拒否
- Temporary Tree、Safe Replace、Failure Rollback／Cleanup
- Non-marker Directory、Symlink、Application外Pathの拒否
- Internal Architecture Documentation、Report、STATE同期

## Out of Scope

- `.fetch()`、Response Decode、Typed Result Union
- `frontend:check`、Drift Exit Contract
- TypeScript Compiler／Node Runtime Fixture、GitHub Actions変更
- Retry、Backoff、Polling、Cache、State Management
- React／Vue／Svelte／Inertia Adapter、Form Helper、Vite Plugin
- Typed Collection、Nested DTO、Enum、Date／Time、Upload／Stream
- Public PHP API、Attribute、Migration、Database Schema
- Quickstart／Skeleton Frontend Source、Guide／Website、Publication／Deploy

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/094-experimental-versioning-and-release-surface.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/decisions/101-http-scalar-binding-coercion.md`

D101はA／A／Aで確定し、P15-003AのServer側Scalar BindingとP15-003BのFrontend Contract Schema Version 2はAcceptedである。Generated RuntimeはArtifactのNative Scalar Kindから同じCanonical変換を実装する。

## Files Allowed to Change

### Production

- New `src/Internal/Frontend/Generation/**`
- New `src/Internal/Application/ApplicationFrontendConfiguration.php`
- New `src/Internal/Console/FrontendGenerateCommand.php`
- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- P15-003で必要な既存`src/Internal/Frontend/*.php`のDecode／Invariant強化だけ

### Tests and Fixtures

- New `tests/Internal/Frontend/Generation/**`
- New `tests/Internal/Console/FrontendGenerateCommandTest.php`
- New P15-003専用`tests/Fixtures/Frontend/**`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Application/ApplicationConsoleCommandFactoryTest.php`（存在し、必要な場合だけ）
- `tests/Architecture/PublicApiBoundaryTest.php`（Public API非追加Guardが必要な場合だけ）

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P15-003-operation-object-request-generation.md`

変更可能Fileの追加が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Generated Tree Contract

Default OutputはApplication Rootの`resources/js/blackops`とする。

```text
resources/js/blackops/
├── client.ts
├── types.ts
├── manifest.json
└── operations/
    ├── welcome/show-welcome.ts
    └── order/create-order.ts
```

`manifest.json`はOwnership Markerを兼ね、少なくともGenerator Schema Version、Application Build ID、Frontend Contract Hashを持つ。JSON Key、File Path、Import、Export、Field、Operation、改行を決定的にし、同じContractから二回生成した全File Bytesを一致させる。生成日時、Absolute Path、Environment、Credential、Runtime Valueを含めない。

Frontend Contract HashはCanonical Contract Payloadから計算し、PHP `serialize()`、Object Identity、Source File Metadataへ依存させない。

## TypeScript Contract

`types.ts`はDOMやFrontend Frameworkに依存しない最小Typeを公開する。

```ts
export type OperationCredentials = 'omit' | 'same-origin' | 'include';

export type OperationAbortSignal = Readonly<{
  aborted: boolean;
  reason?: unknown;
}>;

export type OperationRequestOptions = Readonly<{
  baseUrl?: string;
  headers?: Readonly<Record<string, string>>;
  credentials?: OperationCredentials;
  signal?: OperationAbortSignal;
}>;

export type OperationRequest = Readonly<{
  url: string;
  method: string;
  headers: Readonly<Record<string, string>>;
  body?: string;
  credentials?: OperationCredentials;
  signal?: OperationAbortSignal;
}>;
```

P15-004は`OperationRequestOptions`を拡張した`OperationCallOptions`とStructural Fetch／Response Typeを追加する。P15-003でDOM `Request`／`RequestInit`／`AbortSignal`を参照しない。

各Operation ModuleはValue Type、Path／QueryだけのURL Parameter Type、Named Operation Objectを生成する。Value FieldはPHP Constructor Parameter名をProperty名に使い、Transport AliasをType Property名に使わない。Sensitive Inputも名前と型だけを通常のWrite-only Input Typeへ含める。

```ts
export type CreateOrderValue = Readonly<{
  reference: string;
}>;

export const CreateOrder = Object.freeze({
  type: 'order.create',
  method: 'POST',
  path: '/orders',
  strategy: 'inline',
  url(parameters: CreateOrderUrlParameters): string,
  toRequest(value: CreateOrderValue, options?: OperationRequestOptions): OperationRequest,
});
```

Value Fieldがない場合も`.toRequest()`の第1引数はReadonly empty objectを要求し、OperationごとにCall Signatureを変えない。Path／Query Fieldがない`.url()`だけは仕様どおり引数なしとする。Callable、Thenable、Class Constructor、Mutable Metadataを生成しない。

## Request Binding Contract

- `.url()`はRelative Path＋Queryだけを返し、Base URLを受け取らない
- `.toRequest()`は同じURL BindingへOptional `baseUrl`を適用する
- PathはPlaceholder対応Fieldを必須とし、値を一Segment単位で`encodeURIComponent`相当へEncodeする
- QueryはTransport NameをKeyとしてEncodeし、Optional `undefined`と`null`を省略する
- Header BindingはOperation-owned Headerへ移し、Bodyへ含めない
- Body BindingはConstructor Parameter名ではなくTransport NameをJSON Object Keyに使う
- GET／HEADはBodyを生成しない
- Optional未指定FieldはQuery／Header／Bodyへ含めず、Server側Defaultを使用する
- Body Fieldが1つ以上存在する場合はJSON ObjectをSerializeし、`Content-Type: application/json`を設定する
- Call HeaderはOperation-owned Headerと`Content-Type`をCase-insensitiveに上書きできない
- Method、Path、BodyはCall Optionから指定できない
- `credentials`と`signal`は値を変換せず`OperationRequest`へ渡す
- Header名／値へ改行を含む場合は安全なGeneration／Runtime Errorにする

Non-body ScalarはArtifact Kindに従ってD101のCanonical文字列へ変換する。`integer`は`Number.isSafeInteger()`を満たす有限整数だけを許可し、`float`は`Number.isFinite()`を満たす値だけを許可する。JavaScriptで正確に表現できない64-bit Integerを丸めて送信せず、ApplicationはそのようなIdentifierを`string`として宣言する。`boolean`は小文字`true`／`false`、`string`は値をそのまま使用する。Pathの`null`／`undefined`、Runtime Type不一致、Unsafe Integer、NaN／Infinityは送信Requestを作らず安全なRuntime Errorにする。

`baseUrl`はHTTP／HTTPS OriginとOptional Base Pathだけを許可する。Credential、Query、Fragmentを拒否し、Trailing SlashとOperation Relative Pathを決定的に結合する。Global Mutable Client Configurationを追加しない。

## Output Safety and Atomicity

- Application RootとOutputの絶対Pathを正規化し、OutputがRoot自身、Filesystem Root、Application外なら拒否する
- Outputまたは既存AncestorがSymlinkなら拒否する
- 初回生成時、非空かつ有効なBlackOps `manifest.json`を持たないDirectoryを上書き／削除しない
- Temporary TreeはOutputと同じParentへ作り、全File Write／Read-back／Marker検証後だけ置換する
- 既存Generated Tree置換時はBackup Renameを使い、失敗時に旧Treeを復元する
- 成功時と失敗時のTemporary／Backup DirectoryをCleanupする
- Source FileはOutput Root外へ出ず、Contract Module Pathを再検証してTraversalを拒否する
- Console stdoutは生成先とFile数の安全なSummaryだけとし、Credential、Value、Absolute Source File Pathを表示しない

## Acceptance Criteria

- [ ] Optional Config欠落時にDefault Outputを使い、明示Outputを検証する
- [ ] `frontend:generate`がFrontend Contract ManifestだけからTreeを生成する
- [ ] `manifest.json`がSchema、Build ID、Canonical Contract Hashを持つ
- [ ] 同じContractから二回生成したFile Path／Bytesが一致する
- [ ] Operation Objectが`.url()`、`.toRequest()`、Readonly Metadataを持つ
- [ ] Value／URL Parameter TypeがRequired／Optional／Nullable／Sensitiveを正しく表す
- [ ] Path／Query／Header／Body BindingがHTTP Contractと一致する
- [ ] JSON Body、`Content-Type`、Header Conflict、Base URLを安全に処理する
- [ ] `integer`／`float`／`boolean`をD101形式へ変換し、Unsafe Integer／NaN／Infinityを拒否する
- [ ] Non-marker Directory、Symlink、Application外Path、Traversalを拒否する
- [ ] Generation Failureで既存有効Treeを保持しTemporary／BackupをCleanupする
- [ ] Source Reflection、Credential、Default実値、Example、Absolute Source Pathを生成物へ含めない
- [ ] `.fetch()`、Result Decode、Drift Check、Frontend Framework依存を追加しない
- [ ] Public PHP API、Migration、Database Schemaを変更しない
- [ ] Required PHP Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Console/FrontendGenerateCommandTest.php \
  tests/Internal/Application/ApplicationConfigurationLoaderTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'credential|local-example|sensitive-|default-must-not-appear' tests/Fixtures/Frontend/generated
! rg -n '\bfetch\s*\(|Promise<|react|vue|svelte|inertia|vite' tests/Fixtures/Frontend/generated --glob '*.{ts,json}'
git diff --check
```

Generated FixtureをWorking Treeへ固定しない設計の場合、最後のGenerated Content GuardはTest内Temporary Treeへ同等Assertionを置き、ReportへEvidenceを記録する。

## Expected Report

`develop/orchestration/reports/P15-003-operation-object-request-generation.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Generated Tree and Determinism Evidence
- Request Binding Matrix
- Output Safety／Rollback Matrix
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
