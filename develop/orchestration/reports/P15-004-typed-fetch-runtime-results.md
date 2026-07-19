# P15-004 Typed Fetch Runtime and Results Report

Status: Accepted

## Summary

P15-003のFramework-neutral TypeScript ESM生成を拡張し、各frozen Operation Objectへ`.fetch()`を追加した。

Generated `types.ts`はDOM／Node／Frontend Frameworkへ依存しないStructural Fetchと、Operation共通のAcknowledgement／Failure／Transport Typeを持つ。Operation ModuleはValue、Outcome、Validation Field、成功Modeに限定されたResult Unionを生成し、`.fetch(value, options)`を共通`client.ts` Runtimeへ接続する。

共通RuntimeはPer-call Injected FetchをBrowser `globalThis.fetch`より優先し、Request構築、Fetch送信、Response Shape Snapshot、Raw Text読取、Strict Decodeを行う。HTTP Statusだけで成功型へCastせず、Media Type、JSON Object、Exact Key、Discriminant、Category、Operation固有Outcome Scalar／Validation Fieldを検証する。Raw Body、Credential、Exception Detail、Sensitive ValueはResultへ含めない。

Generated Runtime追加に伴いOwnership Marker Schemaを2へ上げた。既存Schema 1 Treeから更新不能にならないよう、既存OutputのOwnership判定だけKnown Legacy 1を受理し、Temporary／New Treeの検証はCurrent Schema 2だけに限定した。

## Changed Files

### Production

- `src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- `src/Internal/Frontend/Generation/FrontendOutputWriter.php`
- `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`

### Tests

- `tests/Internal/Console/FrontendGenerateCommandTest.php`
- `tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`
- `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P15-004-typed-fetch-runtime-results.md`

HTTP Responder、Public PHP API、Migration、Database Schema、Quickstart／Skeleton／Guide／Website、CI、常設TypeScript Toolchainは変更していない。

## Decisions and Assumptions

- `OperationRequestOptions`は送信機能を持たないまま維持し、`.fetch()`だけが`fetch?: OperationFetch`を追加した`OperationCallOptions`を受ける。
- `.fetch()`は`.toRequest()`と同じ`buildOperationRequest()`を内部使用する。Method、Binding Header、Body、`Content-Type`をCall Optionから上書きするSurfaceは追加していない。
- Invalid Base URLだけをInternal Error Classで識別して`invalid_base_url`へ変換する。Value Runtime Type、Header、Body Serialize等のProgrammer Errorは再throwし、Transport Errorへ偽装しない。
- Injected Fetchが指定された場合はそれだけを使用し、存在しない場合だけ`globalThis.fetch`をRuntimeへBindして使用する。Client／Base URL／CredentialのGlobal Mutable Stateは持たない。
- 実HTTP ResponderとTask Contractに矛盾はない。HTTP 400は`status: error`のProtocolと`status: rejected`／`category: business_rule`の業務拒否を区別する。HTTP 401はAuthentication Middlewareの`status: error`／`category: unauthorized`とOperation Responderの`status: rejected`を両方受理する。403／404／409はOperation ResponderのRejected Shapeだけを受理する。
- Response Objectは直接信用せず、Getter AccessをCatch境界内でPlain Snapshotへ固定する。Throwing Proxy／Getter、Non-string Content-Type、Malformed Structural Responseは`unexpected_response`になる。
- 204はContent-Typeを要求せず、`text()`が厳密な空文字を返した場合だけInline Void Completedにする。`text()` Throw／Rejectは`network_error`であり、Raw Detailを保持しない。
- Generated Marker Schema 2はGenerated Runtime Contractの変更を表す。`decode()`はCurrent 2限定、Output Ownership用`decodeOwned()`だけ1／2を受理し、未知Versionを拒否する。

## Generated Type and Narrowing Matrix

| Operation Contract | Generated Success Type | Failure Types |
| --- | --- | --- |
| Inline＋Outcome | `{ ok: true; kind: 'completed'; status: 200; data: TOutcome }` | Protocol／Rejected／Validation／Internal／Transport |
| Inline＋Void | `{ ok: true; kind: 'completed'; status: 204; data: undefined }` | Protocol／Rejected／Validation／Internal／Transport |
| Deferred | `{ ok: true; kind: 'accepted'; status: 202; data: DeferredAcknowledgement }` | Protocol／Rejected／Validation／Internal／Transport |

各Operationは次を生成する。

- `<Operation>Value`: Write-only Sensitive Inputを含むRequest Input Type
- `<Operation>Outcome`: Artifact Field名／Scalar Kind／Nullableだけから作るOutcome Type。Voidは`undefined`
- `<Operation>Field`: OperationValue Constructor Parameter名のString Literal Union。空Valueは`never`
- `<Operation>Result`: Strategy／Outcome Modeに対応する成功Branch一つと共通Failure Branch

`ok`、`kind`、`status`はLiteral Discriminantであり、Inline Outcomeへ204／202、Inline Voidへ200／202、Deferredへ200／204を含めない。

## HTTP Response Decode Matrix

| HTTP | Required Shape | Generated Result |
| --- | --- | --- |
| 200 | JSON Object、Artifact Outcome FieldのExact Set、Scalar／Nullable一致 | Inline Outcome Completed |
| 204 | Raw Bodyが空文字 | Inline Void Completed |
| 202 | JSON、`status=accepted`、Non-empty `operationId`／`acceptedAt`、Exact Keys | Deferred Accepted |
| 400 Protocol | JSON、`status=error`、Non-empty `code`、Exact Keys | Protocol |
| 400 Business | JSON、`status=rejected`、`category=business_rule`、Non-empty `code`、Optional `operationId` | Rejected |
| 401 Middleware | JSON、`status=error`、`category=unauthorized`、Non-empty `code`、Operation IDなし | Rejected |
| 401／403／404／409 Operation | JSON、`status=rejected`、Status対応Category、Non-empty `code`、Optional `operationId` | Rejected |
| 422 | JSON、Validation固定Discriminant、Non-empty `operationId`、既知Field／Non-empty Rule／CodeのViolation Array | Validation |
| 500 | JSON、`status=error`、`code=internal_error`、Optional `operationId` | Internal |

204以外はCase-insensitiveな`application/json` Media Typeを要求し、Charset Parameterを許可する。Unknown Status、Invalid JSON、Array／Scalar JSON、Unknown Key、Missing Key、Unknown Validation Field、Unsafe Integer、Non-finite Float、型不一致は`unexpected_response`になる。Operation IDはResponseに存在する場合だけResultへコピーする。

## Fetch and Transport Error Matrix

| Boundary | Code／Behavior |
| --- | --- |
| Invalid Base URL | `invalid_base_url` |
| Injected／Default Fetchなし | `missing_fetch` |
| Fetch Throw／Rejected、Signal未Abort | `network_error` |
| Fetch Throw／Rejected時にSignal `aborted=true` | `aborted` |
| Response `text()` Throw／Rejected | `network_error` |
| Malformed Response Getter／Shape／Decode | `unexpected_response` |
| Value／Header／Body Programmer Error | Throwを維持しTransportへ変換しない |

Transport Resultは`code`だけを持ち、URL、Header、Request Value、Credential、Exception Name／Message／Stack、Raw Response Bodyを含めない。Retry、Backoff、Polling、Cacheは行わない。

## Sensitive and Raw Body Boundary

- Sensitive Value Fieldは既存どおりValue TypeとBinding Metadataの名前／型だけへ残し、値、Default、Example、Sensitive FlagをGenerated Treeへ書かない。
- Outcome DecoderはArtifact Outcome Fieldだけを扱い、OperationValueをSuccess Resultへ混ぜない。
- Raw Bodyは`decodeOperationResponse()`へ渡すLocal Stringだけであり、成功／失敗／Transport Resultへ保持しない。
- Fetch／Body-read ExceptionはCatchするが、Name、Message、StackをResultへコピーしない。
- Operation RejectionはCategory、Code、実在するOptional Operation IDだけ、ValidationはSafe Violationだけを返す。

## Determinism and P15-003 Regression Evidence

- 同一Frontend Contractから二回生成したFile Path／Bytesが一致する既存Testを維持した。
- `.url()`、`.toRequest()`、Readonly Metadata、D101 Scalar、Empty Body `{}`、Protected Header、Base URLの既存Assertionが成功した。
- Generated Moduleごとの送信処理は`fetchOperation()`呼出だけであり、共通Runtimeを複製しない。
- Generator Schema 1 TreeをSchema 2へ置換でき、Unknown Version、Non-marker、Symlink、Rollback Safetyを拒否／維持するTestが成功した。
- Working TreeへGenerated TypeScript Fixtureを固定せず、Temporary Command Treeの禁止Surface Assertionを維持した。
- 一時生成Treeは既存`docs/website/node_modules/.bin/tsc`を使い、`--strict --target ES2022 --module ES2022 --moduleResolution bundler --noEmit`で型検査後に削除した。常設Toolchain／CIは追加していない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Console/FrontendGenerateCommandTest.php
Result: OK (32 tests, 282 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1320 tests, 5026 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2262 / Warnings 0 / Errors 0。

docs/website/node_modules/.bin/tsc --noEmit --strict --target ES2022 \
  --module ES2022 --moduleResolution bundler <temporary generated tree>
Result: 成功。Temporary Treeは削除済み。

Management Comment ID、Generated Sensitive／Forbidden Runtime Surface、git diff --check Guard
Result: 成功。Generated Fixtureは固定せず、Command TestのTemporary Treeへ同等Assertionを置いた。
```

初回Target Testは新しい禁止文字列AssertionがStructural型名`OperationFetchResponse`／`OperationAbortSignal`自身へ誤一致した。禁止対象をDOM固有型へ限定して修正し、Production Contractは変更していない。初回Mago LintはGeneratorのMethod数／Halsteadを報告し、Response Mode Helperの統合と既存Renderer Methodへの限定Expectで解消した。

## Acceptance Criteria

- [x] Operation Objectが`.fetch()`とOperation固有Promise Resultを持つ
- [x] Browser既定FetchとPer-call Injected Fetchを同じ共通Runtimeで選択する
- [x] `.toRequest()`／`.url()`／Readonly MetadataのP15-003 Contractが回帰しない
- [x] Inline Outcome 200、Inline Void 204、Deferred 202をStrategy／Outcome ModeどおりDecodeする
- [x] Protocol、Rejected、Validation、InternalをStatusとJSON Shapeから厳密Decodeする
- [x] Missing Fetch、Invalid Base URL、Network、Abort、Unexpected ResponseをTransport Codeで区別する
- [x] Operation固有Outcome Scalar、Nullable、Validation FieldをRuntime検証する
- [x] Resultが`ok`／`kind`／`status`でNarrowingできるDiscriminated Unionである
- [x] Operation IDをResponseに存在する場合だけ保持する
- [x] Raw Body、Credential、Exception Detail、Sensitive ValueをGenerated Artifact／Resultへ含めない
- [x] Callable／Thenable、Global Mutable Client、Retry／Polling／Framework依存を追加しない
- [x] Deterministic Bytes、Marker、Output Safety／Rollbackが回帰しない
- [x] HTTP Responder、Public PHP API、Migration、Database Schemaを変更しない
- [x] Required PHP Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

P15-004を妨げるBlockerはない。

`frontend:check`、常設TypeScript Compile／Node Runtime Fixture、CI連携はP15-005、Quickstart／Skeleton／Guide／Consumer E2EはP15-006のScopeである。Deferred Status／Outcome取得、PollingはPhase 16まで追加しない。Documentation WebsiteのPublication／Deployは実行していない。

## Orchestrator Review

Accepted。

Orchestratorは実HTTP ResponderとGenerated DecoderのShapeを照合し、Protocol 400、Business Rejection、Authentication Middleware 401、Operation Rejection 401／403／404／409、Validation 422、Internal 500が一致することを確認した。Generated Treeは一時領域でDOMなしStrict TypeScript Compileを行い、生成JavaScriptでInline Outcome、Inline Void、Deferred、全Failure分類、Scalar／Nullable、Unknown Key、Malformed Structural Response、Injected／Global／Missing Fetch、Invalid Base URL、Network／Abort、Programmer Error再throw、Raw Secret非露出を含む44 Runtime Assertionを実行して成功した。

Orchestrator再実行のTargetは32 tests／282 assertions、Full PHPUnitは1320 tests／5026 assertionsで成功した。Composer Root／Quickstart、Mago format／lint／analyze、Deptrac（Violations 0／Warnings 0／Errors 0）、Management ID、Runtime Import、Generated Fixture不在、TypeScript／Migration追加、`git diff --check`の各Guardも成功した。Known Legacy Marker 1だけをOwnership判定で受理し、新規TreeをCurrent Marker 2限定で検証する境界を確認した。

Task Packetに残っていた改名前のD094参照は、実在する`develop/decisions/094-stable-1-1-release-contract.md`へ補正した。仕様またはProduction Codeの変更ではない。

## Suggested Next Action

P15-004をTask単位でCommit／Pushし、P15-005 Drift and Frontend Build IntegrationのTask Packet作成へ進む。
