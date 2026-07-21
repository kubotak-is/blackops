# P18-003: Frontend Bound Client Factory

Status: Ready

## Goal

Generated Frontend TreeへFramework-neutralな`createBlackOpsClient()`を追加し、ApplicationがBase URL、SvelteKit Server／Global Fetch、Default Header、Credential Modeを一度だけBindingできるようにする。既存のUnbound Operation Objectを互換として維持しながら、Bound Client上の全Operationを`blackops.CreateOrder.fetch()`、`.status()`、`.wait()`、`.toRequest()`、`.url()`で利用できるようにし、ApplicationごとのTransport Wrapper重複を減らす。

## In Scope

- Generated Root `index.ts`
- Framework-neutralな`createBlackOpsClient()`とReadonly Bound Client型
- 全HTTP OperationのShort Name PropertyとBound Operation Object
- SvelteKit Server `fetch`とGlobal Fetchの直接Binding
- Base URL、Default／Call Header、Credential、SignalのBindingと分離
- MutationだけのIdempotency Key Call Option
- Case-insensitive Header MergeとGenerated／Operation-owned Protected Header
- Invalid BindingのSafe Transport Resultと同期DescriptorのSafe Exception
- Factory／Client／Bound Operation／Option SnapshotのCopyとFreeze
- Existing Unbound Operation Module API互換
- Generator Marker Schema更新、Atomic Replace、Old Marker Cleanup、Fresh Check
- Permanent Frontend Fixture、Quickstart Frontend Consumer、Guide／Internal Documentation
- Report、TODO、STATE同期

## Out of Scope

- Community Boardの`operationFetch`、Manual Wrapper、BFF Source移行（P18-007）
- SvelteKit固有Import、Hook、Store、Form Action、Cookie、Redirect、Error Projection
- Browser向けGlobal Mutable ClientまたはSingleton
- Backend側Idempotency Storage／Duplicate Suppression（Phase 19）
- Retry、Backoff、Cache、Offline Queue、Polling Strategy変更
- Frontend Contract Manifest Schema／Payload変更
- Operation／DTO／Outcome Naming規則変更
- PHP Environment、Console、Session Auth
- Documentation Website Publication／Deploy

## Relevant Specifications and Decisions

- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/70-deferred-status-and-outcome-api.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Files Allowed to Change

### Frontend Generation

- `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`
- `src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- `src/Internal/Frontend/Generation/FrontendGeneratedTree.php` only if the new root file requires a mechanical tree-shape adjustment
- `src/Internal/Frontend/FrontendContractCompiler.php` only if existing Short Name collision diagnostics require a non-semantic assertion adjustment
- `src/Internal/Frontend/FrontendNamingCompiler.php` only if deterministic import path calculation cannot reuse the existing API

Do not change the Frontend Contract Manifest payload or schema. Do not add Runtime Service, Container binding, Global State, SvelteKit dependency, or Application-specific credential policy.

### Tests and Permanent Fixture

- `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`
- `tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`
- `tests/Internal/Frontend/Generation/FrontendTreeCheckerTest.php`
- `tests/Internal/Frontend/FrontendContractCompilerTest.php` only for Short Name collision evidence
- Existing or new focused fixtures under `tests/Internal/Frontend/**`
- `tests/Frontend/package.json`
- `tests/Frontend/tsconfig.json`
- `tests/Frontend/tsconfig.runtime.json`
- `tests/Frontend/types/**`
- `tests/Frontend/scripts/**`
- `tests/Frontend/fixture/**` except generated／build／dependency artifacts retained after verification

Permanent Fixtureは`lib.dom.d.ts`なしのStrict ES2022 TypeScriptを維持する。SvelteKit本体をTest Dependencyに追加せず、SvelteKit Server `fetch`の必要なStructural Shapeを型Fixtureで再現する。

### Installed Consumer

- `examples/quickstart/tests/Frontend/**`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication-dry-run.sh` and `tests/Consumer/skeleton-publication-workflow.sh` only if tracked Quickstart Frontend Test inventory changes mechanically

QuickstartのApplication-owned Frontend Testから生成`index.ts`とBound Clientを一つの実HTTP Journeyで利用する。Community BoardのSource、Generated Tree、Manual Wrapper、Packageは変更しない。

### Documentation and Orchestration

- `docs/guide/first-operation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/security.md`
- `docs/guide/configuration.md` only to clarify that Runtime binding is not stored in PHP configuration
- `docs/internal/installed-application-status.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/74-application-ergonomics.md` only for non-semantic clarification discovered during implementation
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P18-003-frontend-bound-client-factory.md`

Website Content Pipelineは`docs/guide/`を正本とする。Source変更により既存Website Testが必要なら実行するが、Publication／Deployはしない。

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Generated Layout and Public TypeScript Contract

生成Treeへ`index.ts`を追加する。

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

Root ModuleはOperation Moduleと共通型を決定的な順序でImport／Exportし、少なくとも次を提供する。

```ts
import { createBlackOpsClient } from './resources/js/blackops';

const blackops = createBlackOpsClient({
  baseUrl: 'http://blackops:8080',
  fetch: event.fetch,
  headers: { Authorization: `Bearer ${token}` },
  credentials: 'same-origin',
});

const result = await blackops.CreateOrder.fetch({
  reference: 'order-1042',
  amount: 1200,
});
```

- `baseUrl`と`fetch`はFactoryのRequired Optionとする
- `headers`と`credentials`はOptional Defaultとする
- Client Property名はOperationの既存Export Short Nameを使う
- Property順とImport順はModule Path、次にExport Nameの決定的順序とする
- Factory戻り値と各Bound Operation ObjectはReadonlyかつRuntimeでFrozenとする
- Root ModuleをImportしただけではNetwork Call、Global Fetch Capture、Timer、Source Scanを実行しない
- Root Moduleから既存Operation、Operation固有型、共通Public型もNamed Exportし、個別Module Importは引き続き利用できる
- Client Property名のCase-insensitive Collisionは既存Build CompilationでFail-fastする。黙ってSuffixやAliasを付けない

## Binding and Isolation Contract

Factoryは入力OptionをCopyして保持し、利用者が元のObjectやHeader Recordを後から変更してもClientへ反映しない。各CallでもDefaultとCall Optionから新しいRequest Snapshotを作る。

- Factory Default: Base URL、Fetch、Header、Credential Mode
- Call Override: Header、Credential Mode、Signal
- Mutation Callだけ: `idempotencyKey`
- Wait Call: 既存のDeadline、Clock、Timer等を維持しながら上記Bound Optionを合成する
- Call Headerは同名Default HeaderをCase-insensitiveに上書きする
- OperationValue Header、Method、Path、Body、Generated `Content-Type`、Generated `Idempotency-Key`はDefault／Call Headerから上書きできない
- `Idempotency-Key`をRaw Header Recordとして指定した場合は拒否し、専用`idempotencyKey` Optionだけを受理する
- POST／PUT／PATCH／DELETEの`.fetch()`と`.toRequest()`だけが`idempotencyKey`を型として受理する
- GET／HEAD、`.status()`、`.wait()`、`.url()`には`idempotencyKey`を公開しない。Runtimeで余分なPropertyとして渡された場合もNetwork Call前に拒否する
- Idempotency Keyは1から255文字の空白／Control Characterを含まないPrintable ASCIIだけを受理する
- 同じClientの並列Call間でHeader、Signal、Credential、Idempotency Key、Abort Stateを共有／Mutationしない

Header NameはHTTP tokenとして空でない文字列だけを受理し、Header ValueはCR、LF、NULまたは他のControl Characterを含む場合に拒否する。既存のWeb Fetch Structural Typeを維持し、DOM型をImportしない。

## Fetch Compatibility

- SvelteKit Serverの`event.fetch`をApplication-owned Type Cast／WrapperなしでFactoryへ渡せる
- Native／Global FetchとTest Doubleを同じFactory Optionで受理する
- Generated SourceはSvelteKit、DOM `Request`／`Response`／`Headers`型をImportしない
- Factory生成時にGlobal Mutable Defaultを設定しない
- Bound CallはFactoryへ渡されたFetchを使用し、Unbound Callは既存どおりCall単位FetchまたはGlobal Fetchを使用する
- Bound Call Optionから`baseUrl`または`fetch`をOverrideできない

TypeScriptのFunction Parameter Varianceを`any`、DOM型、Application-owned Adapterで回避しない。Generated Internal GenericまたはStructural AdapterでSvelteKit／Native Fetchの直接代入を成立させ、外部へ返すResultは既存の厳密なTyped Resultを維持する。

## Failure and Sensitive Contract

- Bound `.fetch()`、`.status()`、`.wait()`はMissing／Invalid Fetch、Invalid Base URL、Invalid Header、Invalid Credential、Invalid Idempotency KeyをNetwork Call前の`kind: 'transport'`へ変換する
- 新しい安定Codeが必要なら`invalid_client_options`を一つだけ追加し、Raw Option種別以外の値やThrowable Detailを含めない
- Bound `.toRequest()`／`.url()`は既存の同期Return型を維持する。不正Bindingは値を含まない既存Safe `Error`境界でFailする
- Existing Unbound Missing Fetch、Invalid Base URL、Network、Abort、Unexpected Response Codeを変更しない
- Factory Option、Credential、Header Value、Base URL、Idempotency Key、Raw Response、Thrown ErrorをTransport Result、Manifest、Marker、Generated Comment、String化へ反射しない
- Generated Root、Fixture、Docsへ実CredentialやPrivate Environment値を埋め込まない
- Browser Bundle Guardは`Authorization`というPublic Header名の一般記述ではなく、Credential値、Private Base URL、Application Session Tokenが出ないことを確認する

## Compatibility and Marker

- Existing individual Operation Module、`.fetch()`、`.status()`、`.wait()`、`.toRequest()`、`.url()`のSignatureとRuntime挙動を維持する
- Bound Clientは既存Operation ObjectをMutationしない
- Generated Tree Shape変更としてMarker Schema Versionを上げる
- Current MarkerだけをFreshとして受理し、旧Version 5 MarkerはOwnership確認とAtomic Cleanupには受理する
- Existing Frontend Contract Manifest Schema Versionは変更しない
- `frontend:generate`、`frontend:check`、Atomic Replace、Non-marker Guard、Unexpected Extra File Detectionを維持する

## Required Evidence

Permanent Fixtureで少なくとも次を固定する。

- Root Named Export、決定的なOperation Property順、Import時Side Effectなし
- SvelteKit-compatible Fetch Structural TypeとDOMなしStrict Typecheck
- Inline／DeferredのBound `.fetch()`
- Bound `.status()`と`.wait()`の既存Outcome Narrowing
- Absolute URL、Default／Call HeaderのCase-insensitive Merge、Credential Override、Signal伝播
- Operation-owned HeaderとGenerated Content-TypeのProtection
- POST Idempotency Header、Invalid Key、GET／Status／Wait拒否
- Factory入力後Mutation、Call後Mutation、並列CallのIsolation
- Missing／Invalid Fetch、Invalid Base URL／Header／CredentialがNetwork Call 0でSafe Result
- Result／Error／Generated TreeにCredential、Base URL、Raw Error、Sensitive Input Valueがない
- Existing Unbound Runtime／Type Narrowing回帰なし
- Marker Version、Old Marker Cleanup、Fresh／Drift／Extra `index.ts` Evidence
- Quickstart実HTTPで生成RootのBound Clientを一度利用する

## Acceptance Criteria

- [ ] Generated `index.ts`が`createBlackOpsClient()`と全Operationを決定的にExportする
- [ ] SvelteKit Server `fetch`とGlobal FetchをAdapterなしで型安全にBindingできる
- [ ] Clientから全OperationのBound `.fetch()`／`.status()`／`.wait()`／`.toRequest()`／`.url()`を利用できる
- [ ] Default／Call Header、Credential、Signal、Base URLがContractどおり合成される
- [ ] Mutationだけが専用Idempotency Keyを受理し、GET／Status／WaitとRaw Header指定をNetwork前に拒否する
- [ ] Factory／Call／並列Request間でMutable Stateが漏れず、生成ObjectがFrozenである
- [ ] Invalid BindingがSafe Failureとなり、Credential、Base URL、Raw Errorを公開しない
- [ ] Existing Unbound APIとFrontend Contract Manifestが回帰しない
- [ ] Marker Schema、Atomic Generation、Fresh Check、Old Ownership Cleanupが同期される
- [ ] Permanent Frontend FixtureとQuickstart実HTTP Consumerが新Factoryを使用する
- [ ] Mago Format／Lint／Analyze、PHPUnit、Deptrac、Frontend、Quickstart、Skeleton、Framework Update、Website、Management ID Guard、Diff Checkが成功する
- [ ] Community BoardのSource／Generated Tree／Manual Wrapperを変更しない
- [ ] Documentation Website／Community Boardを外部公開しない
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --testsuite frontend
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend test
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website test
mise exec -- pnpm --dir docs/website build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Consumer Script名またはPHPUnit Suite名がRepository内で異なる場合は、同じ責務の既存Commandへ置き換えてReportへ記録する。Generated／Build／Dependency ArtifactはTask完了前にCleanupする。Full Commandが環境理由で実行できない場合は未実行理由を記載し、代替で成功扱いにしない。

## Expected Report

`develop/orchestration/reports/P18-003-frontend-bound-client-factory.md` に次を記録する。

- Summary
- Changed Files
- Generated Public TypeScript Contract
- Fetch Compatibility and Type Evidence
- Binding／Header／Idempotency Matrix
- Isolation and Sensitive Evidence
- Compatibility and Marker Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
