# P15-003 Operation Object and Request Generation Report

Status: Accepted

## Summary

Frontend Contract Manifest Schema Version 2だけを入力として、Framework-neutralなTypeScript ESM Treeを決定的に生成する`frontend:generate`を実装した。

生成Treeは共通`types.ts`／`client.ts`、Ownership Marker `manifest.json`、HTTP OperationごとのModuleを持つ。各ModuleはPascalCaseのfrozen Operation ObjectをNamed Exportし、Readonly Literal `type`／`method`／`path`／`strategy`、`.url()`、`.toRequest()`を提供する。HTTP送信、Response Decode、Typed Result、Drift Checkは追加していない。

OutputはApplication Root配下だけに限定し、Non-marker Directory、Symlink／Symlink Ancestor、Root自身、Application外Path、Traversalを拒否する。Temporary TreeのWrite／Read-back／Marker検証後だけBackup Renameで置換し、置換Failureでは旧Treeを復元する。

## Changed Files

### Production

- `src/Internal/Application/ApplicationFrontendConfiguration.php`
- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/FrontendGenerateCommand.php`
- `src/Internal/Frontend/Generation/FrontendContractHasher.php`
- `src/Internal/Frontend/Generation/FrontendGeneratedTree.php`
- `src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- `src/Internal/Frontend/Generation/FrontendOutputWriter.php`
- `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`

### Tests and Fixtures

- `tests/Fixtures/Frontend/FrontendContractFixture.php`
- `tests/Internal/Console/FrontendGenerateCommandTest.php`
- `tests/Internal/Frontend/Generation/ApplicationFrontendConfigurationTest.php`
- `tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`
- `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P15-003-operation-object-request-generation.md`

Public PHP API、Migration、Database Schema、Quickstart／Skeleton Frontend Source、Guide／Website、CIは変更していない。

## Decisions and Assumptions

- `config/frontend.php`はOptionalとし、欠落時は`<application-root>/resources/js/blackops`を使う。明示`output`は絶対PathかつApplication Root配下だけを受理する。
- `frontend:generate`は`app.build.frontend_manifest`と`app.build.application_build_id`を検証し、Source Discovery、Reflection、`build:compile`を呼ばない。
- Canonical Contract HashはFrontend Contract Codecの言語中立PayloadをOperation Module順へ正規化し、JSON Key順を維持したUTF-8 JSONからSHA-256で計算する。Build ID、時刻、Source Metadata、Object IdentityをHash入力へ含めない。
- Operation Value Property名はPHP Constructor Parameter名を維持し、Transport AliasはBinding Descriptorだけに使う。Sensitive Inputは名前と型を通常のInput Typeへ含めるが、Sensitive Flag、値、Default、Exampleを生成しない。
- `url()`はPath／Queryだけを扱い、該当Fieldがない場合だけ引数なしにする。`toRequest()`はValue Fieldが空でもReadonly empty objectを第一引数に要求する。
- Operation-owned Header名は値がOptional／Nullableで省略されても先にCase-insensitive予約し、Call Headerから注入または上書きできない。`Content-Type`も常に予約する。
- TypeScript Compiler／Node Runtime FixtureはTask Scope外である。生成Sourceの構文境界、2-space indentation、Binding／Base URL GuardはPHP Unit Testで固定し、実TypeScript Compile／RuntimeはP15-005へ残す。

## Generated Tree and Determinism Evidence

Fixture Contractから次の4 Fileを生成した。

```text
client.ts
manifest.json
operations/order/create-order.ts
types.ts
```

同一Artifactを二回生成し、File Path順と全Bytesが完全一致した。Operation Module順は入力順に依存せずModule PathでSortし、MarkerはGenerator Schema Version 1、Application Build ID、64文字のCanonical Contract Hashだけを保持する。

Generated Moduleは`CreateOrderValue`、Path／Queryだけの`CreateOrderUrlParameters`、frozen `CreateOrder`を持つ。空Value Operationは`Readonly<Record<never, never>>`を要求し、Path／QueryなしOperationの`url()`だけを引数なしで生成する。Generated SourceへPHP Class名、Absolute Source Path、Credential、Default、Example、Runtime Value、`.fetch()`、`Promise`、Frontend Framework依存を含めない。

Working TreeへGenerated Fixtureを固定せず、`FrontendGenerateCommandTest`がTemporary ApplicationへTreeを生成し、全Fileを読み戻して機密値と禁止Surfaceの同等Assertionを行う。

## Request Binding Matrix

| Binding／Option | Generated Behavior | Evidence |
| --- | --- | --- |
| Path | Required FieldをNative Kind検査し、Canonical文字列化後に一Segmentずつ`encodeURIComponent`。Placeholder集合とPath Binding集合を生成前に再検証し、Runtimeでも未解決Placeholderを拒否 | Generator Test |
| Query | Transport NameをKeyへ使用。Optional `undefined`とNullable `null`だけを省略し、Non-nullable `null`は拒否 | Generator Source Contract Test |
| Header | Transport Nameへ移しBodyへ含めない。値をCanonical Encode後にCR／LF検査。全Binding Header名を値の有無より先に予約 | Generator Source Contract Test |
| Body | Transport NameをJSON Keyへ使用。Optional未指定をPropertyから省略し、Nullable `null`を維持。Body Bindingが一つ以上定義されたOperationは、全Optional Field未指定でも`{}`をJSON Serialize | Generator Source Contract Test |
| Content Type | Body Bindingが一つ以上定義されたら実値の有無にかかわらず`application/json`を設定し、Call Headerからの上書きを拒否 | Generator Source Contract Test |
| Integer | `Number.isSafeInteger()`を要求し、有限Safe Integerだけを10進文字列化 | Generator Source Contract Test |
| Float | `Number.isFinite()`を要求し、NaN／Infinityを拒否 | Generator Source Contract Test |
| Boolean | Native Booleanだけを受け、小文字`true`／`false`へ変換 | Generator Source Contract Test |
| String | Native Stringだけを受け、値をCastせず使用。HeaderではCR／LFを拒否 | Generator Source Contract Test |
| Base URL | HTTP／HTTPS Scheme、ASCII Host／IPv6、0..65535 Port、Optional Pathだけを許可。Credential、Malformed Authority／Port／IPv6、Query、Fragment、Whitespace、不正Percent Escapeを拒否 | Generator Base URL Contract Test |
| Credentials／Signal | `OperationRequest`へ値を変換せずOptionalで渡す | Generated `client.ts` Assertion |
| GET／HEAD | Body Bindingを生成前に再拒否 | Invalid Metadata Test |

Method、Path、Body、Binding Headerは`OperationRequestOptions`へ存在せず、Call Optionから上書きできない。DOM `Request`／`RequestInit`／`AbortSignal`へ依存しないStructural Typeだけを生成する。

## Output Safety and Rollback Matrix

| Case | Result | Evidence |
| --- | --- | --- |
| Config欠落 | `resources/js/blackops`をDefaultにする | Configuration Test／Command Test |
| Application Root自身／外部／Relative | Config Error | Configuration Test |
| File Path／Symlink／Symlink Ancestor | ConfigまたはWriter Error | Configuration／Writer Test |
| Non-empty Non-marker Directory | 内容を維持して拒否 | Writer Test |
| Empty Directory／Valid Marker Tree | Temporary Treeから全置換 | Writer Test |
| Module Traversal／Invalid Module | Generation前に拒否 | Generator Test |
| Temporary Write／Read-back／Marker | 全検証後だけOutput Rename | Writer Unit Boundary |
| Replacement Rename Failure | Backupを元Pathへ復元し旧Bytes／Markerを維持 | Injected Rename Failure Test |
| Success／Restorable Failure | Temporary／Backup Directoryを残さない | Writer Test |

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P15-003 required targets>
Result: OK (42 tests, 247 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1313 tests, 4927 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2262 / Warnings 0 / Errors 0。

Management Comment ID、Runtime Frontend Artifact Import、Public PHP API、Migration、Committed TypeScript／JavaScript、git diff --check Guard
Result: 成功。
```

Orchestrator Reviewで、初回Generated RuntimeがBody Bindingの定義ではなくBody Fieldの実値が一つ以上ある場合だけJSON Bodyを作る不整合を検出した。`hasBody`をBinding Descriptorの`source === 'body'`で確定し、全Optional Body Fieldが`undefined`でも`body: '{}'`と`Content-Type: application/json`を生成するRegression Testを追加した。GET／HEAD Body拒否とOptional Property省略は維持し、Correction後にTarget、Full、Composer、Mago、Deptrac、全Guardを再実行した。

最初のDocker Format実行はSandbox内からDocker APIへ接続できず失敗した。承認済みDocker Compose実行へ切り替え、全Required Commandを最終Codeで成功させた。

初回TargetはTest側のValue TypeとURL Typeの重複Field件数期待が一件ずれて1 Failureだった。生成ContractではなくAssertionを修正し、最終Targetを成功させた。Magoは初回にComplexityと型精度を検出し、責務を説明する限定Expect、明示型、Named Argument、制御フロー修正後にLint／Analyzeとも成功した。

## Acceptance Criteria

- [x] Optional Config欠落時にDefault Outputを使い、明示Outputを検証する
- [x] `frontend:generate`がFrontend Contract ManifestだけからTreeを生成する
- [x] `manifest.json`がSchema、Build ID、Canonical Contract Hashを持つ
- [x] 同じContractから二回生成したFile Path／Bytesが一致する
- [x] Operation Objectが`.url()`、`.toRequest()`、Readonly Metadataを持つ
- [x] Value／URL Parameter TypeがRequired／Optional／Nullable／Sensitiveを正しく表す
- [x] Path／Query／Header／Body BindingがHTTP Contractと一致する
- [x] JSON Body、`Content-Type`、Header Conflict、Base URLを安全に処理する
- [x] `integer`／`float`／`boolean`をD101形式へ変換し、Unsafe Integer／NaN／Infinityを拒否する
- [x] Non-marker Directory、Symlink、Application外Path、Traversalを拒否する
- [x] Generation Failureで既存有効Treeを保持しTemporary／BackupをCleanupする
- [x] Source Reflection、Credential、Default実値、Example、Absolute Source Pathを生成物へ含めない
- [x] `.fetch()`、Result Decode、Drift Check、Frontend Framework依存を追加しない
- [x] Public PHP API、Migration、Database Schemaを変更しない
- [x] Required PHP Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

P15-003を妨げるBlockerはない。

`.fetch()`、Response Decode、Typed Result UnionはP15-004、`frontend:check`、TypeScript Compile／Node Runtime Fixture、CI連携はP15-005、Quickstart／Skeleton／Guide／Consumer E2EはP15-006のScopeである。Documentation WebsiteのPublication／Deployは実行していない。

Restore Rename自体がFilesystem障害で失敗した場合、旧Treeを失わないためBackup Directoryを意図的に残す。通常の生成Failureと復元可能な置換FailureではTemporary／Backupを全Cleanupする。

## Orchestrator Review

Orchestratorは生成されたTypeScript 4 FileをRepository外の一時Directoryへ展開し、既存TypeScript CompilerのStrict Modeで型検査した。生成JavaScriptを実行し、Path／Query Canonical Encode、Base URL結合、Body JSON、Operation-owned Headerと`Content-Type`の上書き防止、Request／Header／Operation ObjectのFreeze、Unsafe Integer、NaN、不正Header、Credential／Query／不正Port付きBase URLの拒否を確認した。

初回Reviewで、Body Bindingが定義されていても全Optional Fieldが未指定ならBodyを省略する仕様差を検出した。Correction後は`body: '{}'`と`Content-Type: application/json`を生成し、Nullableで省略されたOperation-owned HeaderもCall Optionから注入できないことを実行確認した。

独立再検証はTarget 42 tests／247 assertions、Full 1313 tests／4927 assertionsで成功した。Composer Root／Quickstart、Mago format／lint／analyze、Deptrac（Violations 0／Warnings 0／Errors 0）、Management Comment ID、Runtime非接続、Public PHP API、Migration、TypeScript／JavaScript追加、`git diff --check` Guardも成功したため、P15-003をAcceptedとした。

## Suggested Next Action

P15-004 Typed Fetch Runtime and ResultsのTask Packetを確定し、`.fetch()`、Response Decode、Inline／Deferred／Rejected／Failure／Transport Typed Resultへ進む。
