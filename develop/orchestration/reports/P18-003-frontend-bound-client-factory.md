# P18-003: Frontend Bound Client Factory Report

## Summary

Generated Frontend TreeへRoot `index.ts`とFramework-neutralな`createBlackOpsClient()`を追加した。ApplicationはBase URL、SvelteKit Server／Native Fetch、Default Header、Credential ModeをFactoryで一度だけBindingし、ReadonlyかつRuntimeでFrozenされたOperation Objectから`.fetch()`、`.status()`、`.wait()`、`.toRequest()`、`.url()`を利用できる。

既存の個別Operation ModuleとUnbound APIは維持した。Factory／Call OptionのSnapshot分離、Case-insensitive Header Merge、Operation-owned Header保護、Mutation専用Idempotency Key、Safe Transport FailureをPermanent Frontend FixtureとQuickstart実HTTP Consumerで固定した。Frontend Contract Manifest Schemaは変更せず、Generated Tree MarkerだけをVersion 6へ更新した。Community Board、外部Publication／Deploy、Commitは変更／実行していない。

## Changed Files

- Frontend Generation: `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`、`src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- PHPUnit: `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`、`tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`、`tests/Internal/Console/FrontendGenerateCommandTest.php`
- Permanent Frontend Fixture: `tests/Frontend/package.json`、`tests/Frontend/types/narrowing.ts`、`tests/Frontend/scripts/module-shape-test.mjs`、`tests/Frontend/scripts/bound-client-runtime-test.mjs`
- Installed Consumer: `examples/quickstart/tests/Frontend/real-http.ts`、`examples/quickstart/tests/Frontend/typecheck.ts`、`tests/Consumer/quickstart-e2e.sh`
- Documentation／Specification: `develop/spec/67-operation-frontend-bridge.md`、`docs/guide/configuration.md`、`docs/guide/first-operation.md`、`docs/guide/mvp-sample.md`、`docs/guide/security.md`、`docs/internal/installed-application-status.md`
- Website／Orchestration: `docs/website/tests/reader-experience.test.mjs`、`develop/TODO.md`、`develop/STATE.md`、本Report

`FrontendGenerateCommandTest.php`の生成File数／Inventory同期と、Website Reader Experience TestのP18-002由来Public API Count 149から150への同期は、Orchestratorが承認した機械的Scope Extensionである。

## Generated Public TypeScript Contract

Root `index.ts`はOperation ModuleをModule Path、Export Nameの決定的順序でImport／Exportし、Operation固有のValue／Outcome／Result／Status／Wait型と共通Public型をNamed Exportする。既存の個別Module Importはそのまま利用できる。

`createBlackOpsClient()`はRequiredな`baseUrl`と`fetch`、Optionalな`headers`と`credentials`を受け取り、全OperationをShort Name Propertyとして持つ`BlackOpsClient`を返す。Root ImportだけではNetwork、Timer、Global Fetch Capture、Source Scanを実行しない。Client、各Bound Operation、保持するOption／Header／Request SnapshotはCopyしてFreezeし、既存Operation ObjectをMutationしない。

Generated Treeは`index.ts`追加に伴いMarker Schema Version 6をCurrentとした。Version 1から5はOwnership確認とAtomic Cleanupにだけ受理し、Fresh判定にはVersion 6だけを使う。Frontend Contract Manifestは既存Schema Version 3のままである。

## Fetch Compatibility and Type Evidence

Generated Public TypeはDOM、SvelteKit、Application PackageをImportしないStrict ES2022 Structural Contractである。SvelteKit Server `event.fetch`相当の関数、Native／Global Fetch、Test DoubleをApplication-owned Cast／WrapperなしでRequired Factory Optionへ直接代入できることを`tests/Frontend/types/narrowing.ts`で検証した。

Bound CallはFactoryでBindingしたFetchとBase URLだけを使い、Call単位の`baseUrl`／`fetch` Overrideを型で公開しない。Runtimeで余分なPropertyとして渡してもNetwork前に`invalid_client_options`へ閉じる。Quickstart E2EではGenerated Root Factoryから実HTTP `/welcome`へ到達し、Typed Outcomeを取得した。

## Binding／Header／Idempotency Matrix

| Surface | Factory Default | Call Override | Protection／Validation |
| --- | --- | --- | --- |
| Base URL | Required | 不可 | Invalid URLはNetwork 0のSafe Failure。同期`.url()`／`.toRequest()`は値を含まないSafe Error |
| Fetch | Required | 不可 | Missing／Invalid FetchはNetwork 0の`invalid_client_options` |
| Header | Optional | 可 | Call側がCase-insensitiveに勝つ。HTTP tokenでないNameとControl Characterを含むValueは拒否 |
| Credential | Optional | 可 | Valid Modeだけを受理し、Requestごとに分離 |
| Signal | なし | 可 | Requestごとに伝播し、並列Call間で共有しない |
| Idempotency Key | なし | POST／PUT／PATCH／DELETEの`.fetch()`／`.toRequest()`だけ | 1から255文字の空白／Control CharacterなしPrintable ASCII。Raw Header指定、GET／HEAD／Status／WaitはNetwork前に拒否 |

Generated `Content-Type`、Generated `Idempotency-Key`、Operation Value由来Header、Method、Path、BodyはDefault／Call Headerから上書きできない。

## Isolation and Sensitive Evidence

Permanent Runtime FixtureでFactory入力後のObject／Header Mutation、Call後のOption Mutation、並列RequestのHeader／Signal／Credential／Idempotency Key分離を検証した。Client、Bound Operation、Request SnapshotはFrozenで、同じClientのCall間にMutable Stateを保持しない。

Invalid Base URL、Fetch、Header、Credential、Idempotency Key、禁止OverrideはNetwork Call 0で安定した`kind: 'transport'`／`code: 'invalid_client_options'`を返す。Transport Result、同期Error、JSON Stringification、Generated TreeへBase URL、Credential、Header Value、Idempotency Key、Raw Error、Sensitive Input Valueを反射しない。

## Compatibility and Marker Evidence

既存Unbound `.fetch()`／`.status()`／`.wait()`／`.toRequest()`／`.url()`のType NarrowingとRuntime Fixtureは継続して成功した。Bound Factoryは追加APIであり、既存Operation Objectを変更しない。

Generator／Writer TestでRoot `index.ts`の決定的Export、Marker 6、旧Marker 1から5のOwnership Cleanup、Fresh／Drift／Unexpected Extra Fileを検証した。Frontend Generateは6 files、QuickstartのGenerated Smoke Operationを含む実HTTP Consumerは8 filesを生成し、Fresh CheckとCleanupに成功した。

## Commands and Results

- `docker compose run --rm app mago format --check src tests`: success
- `docker compose run --rm app mago lint`: success
- `docker compose run --rm app mago analyze`: success
- `docker compose run --rm app vendor/bin/phpunit --testsuite frontend`: suiteがRepositoryに存在せず、No tests executed。Task Packetの置換規定により同責務のFocused PHPUnitを実行し、OK (31 tests, 364 assertions)
- `docker compose run --rm app vendor/bin/phpunit`: OK (1507 tests, 5998 assertions)
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`: success、0 violations、0 skipped、0 uncovered、0 warnings／errors、2556 allowed
- Root／Quickstart `composer validate --strict`: valid
- Permanent Frontend `build:compile`、`frontend:generate` (6 files)、`frontend:check`: success
- Permanent Frontend `pnpm test`: Strict Typecheck、Runtime Compile、Injected Fetch、Status／Wait、Bound Client、Module Shapeがsuccess
- `bash tests/Consumer/quickstart-setup.sh`: success
- `bash tests/Consumer/quickstart-e2e.sh`: success。Generated Root Bound Clientによる実HTTP Journeyを含む
- `bash tests/Consumer/skeleton-create-project.sh`: success
- `bash tests/Consumer/framework-update-generators.sh`: success
- Website `pnpm test`: 初回41／42。P18-002で追加済みEnvironmentに対するstale Public API Countだけが失敗し、承認済みScope Extensionで同期後42／42 success
- Website `pnpm build`: success。31 routes／30 public pages、Artifact／Navigation／Search／Site Check成功。既存Vite chunk-size warningだけを確認
- Management ID Guard、Community Board Scope Guard、`git diff --check`: success
- Generated／Build／Dependency Artifact: cleanup済み

## Decisions and Assumptions

Task Packetが参照する`develop/spec/70-deferred-status-and-outcome-api.md`はRepositoryに存在せず、同じ正本に相当する`develop/spec/69-deferred-status-and-outcome-api.md`を参照した。仕様との矛盾はなかった。

Fetch互換はPublic型を広いStructural Callableとして受け、Generated Internal Adapter内で既存Transport Shapeへ接続した。`any`、DOM型、SvelteKit Import、Global Mutable Stateは追加していない。

## Acceptance Criteria

- [x] Generated RootがFactory、全Operation、Operation固有型、共通型を決定的にExportする。
- [x] SvelteKit Server FetchとNative／Global FetchをAdapterなしでBindingできる。
- [x] Bound Operationから`.fetch()`、`.status()`、`.wait()`、`.toRequest()`、`.url()`を利用できる。
- [x] Base URL、Default／Call Header、Credential、SignalをContractどおり合成する。
- [x] Mutation専用Idempotency KeyとGET／HEAD／Status／Wait／Raw Header拒否を固定した。
- [x] Factory／Call／並列Request間のIsolationとRuntime Freezeを検証した。
- [x] Invalid BindingをNetwork前のSafe Failureへ閉じ、Sensitive Valueを公開しない。
- [x] Existing Unbound APIとFrontend Contract Manifest Schemaを維持した。
- [x] Marker 6、旧Ownership Cleanup、Atomic Generation、Fresh Checkを同期した。
- [x] Permanent Frontend FixtureとQuickstart実HTTP ConsumerがFactoryを使用する。
- [x] Required Quality／Consumer／Website／Guardを実行し、Repositoryに存在しないPHPUnit Suiteだけ同責務Commandへ置換した。
- [x] Community BoardのSource／Generated Tree／Manual Wrapperを変更していない。
- [x] Documentation Website／Community Boardを外部公開していない。
- [x] WorkerはCommitしていない。

## Remaining Issues

Active Implementation Blockerはない。Backend側Idempotency Storage／Duplicate SuppressionはTask PacketどおりPhase 19 Scopeであり、本TaskはRequest Header Contractだけを提供する。Community BoardのManual Wrapper移行はP18-007へ据え置く。

## Suggested Next Action

OrchestratorがGenerated Public TypeScript Contract、SvelteKit Fetch互換、Header／Idempotency／Sensitive境界、Marker Migration、全GateをReviewする。Accept後はPhase 18 Delivery Planに従い次のTask Packetを作成する。

## Orchestrator Review

Accepted。Generated `index.ts`とFactoryの実出力、Bound／Unbound API、Structural Fetch、Header／Credential／Signal／Idempotency合成、Safe Failure、Marker Migration、Documentation／Consumer差分をReviewした。Production Scope逸脱、Community Board差分、Sensitive Value露出、仕様矛盾はない。

独立再検証はFocused PHPUnit 51 tests／486 assertions、Permanent Frontend Build／Generate 6 files／Fresh Check、DOMなしStrict TypeScript、Injected Fetch／Status-Wait／Bound Client／Module Shape Runtime、Mago Format、Deptrac 0 violations、Management ID Guard、Diff Checkが成功した。独立Frontend検証で作成したGenerated／Build／Dependency ArtifactはCleanupした。

Task Packet内の存在しないSpec 70参照は、Workerが実際に参照した正本`develop/spec/69-deferred-status-and-outcome-api.md`へ修正した。Public Contract変更ではない。
