# P16-005 Generated Status Capability Report

Status: Accepted

## Summary

全Generated HTTP Operation Objectへ、一回だけFramework標準Status Resourceを取得する`.status(operationId, options)`を追加した。Generated ModuleはOperation TypeをLiteralで保持する固有Status Resultを公開し、7 Lifecycle State、Operation固有Completed Outcome、EmptyOutcome、Retry Hint、401／404／410／500、Transport FailureをStrict Discriminated UnionへDecodeする。

Status取得はCanonical lowercase UUIDv7を送信前に検証し、既存の呼出単位`baseUrl`、Headers、Credentials、Signal、Injected Fetchだけを再利用する。Operation Route Bindingは使わず、GETを一回だけ実行する。`.fetch()`の挙動は変更せず、`.wait()`、Polling、Retry、Timer、Global Mutable Clientを追加していない。

Generated Runtime Surfaceの変更に合わせてOwnership MarkerをSchema 3へ上げた。Ownership判定ではLegacy 1／2を受理し、Current TreeとTemporary Treeは3だけを受理する。

## Changed Files

### Production

- `src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`

### Tests

- `tests/Internal/Console/FrontendGenerateCommandTest.php`
- `tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`
- `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/status-query.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-005-generated-status-capability.md`

## Decisions and Assumptions

- `.status()`は全HTTP Operationに生成する。Inline Operationも既知Operation IDのStatusを参照できるため、Execution Strategyで生成Surfaceを分けない。
- Status Transport Errorを既存`.fetch()` Transport Errorと分け、`invalid_operation_id`で既存Unionを広げない。
- Operation Moduleは既存Frontend ContractのOutcome Mode／FieldだけからStatus Decoder Contractを生成する。Frontend Contract Manifest ShapeとSchemaは変更しない。
- Error StatusとTerminal StateはPolling Hintを持たない。Non-terminal StateだけCanonical正整数`Retry-After`を必須とする。
- WireのOperation ID／TypeをRequested／Generated値で補完しない。双方の完全一致を確認した後だけGenerated Resultへ写す。
- HTTP Error、Network Error、Malformed ResponseはRaw Body、URL、Header、Credential、Operation ID、Exception DetailをResultへ保持しない。
- Error Responseに対する`Retry-After`の有無はServer Contract上の成功条件ではない。Terminal Found Stateの不在条件は厳密に検査する。
- Public PHP API、HTTP Resource、Frontend Fixture Source、CI、Quickstart、Website、Frontend Contract Manifestは変更していない。

## Generated Type／Narrowing Matrix

| Surface | Generated type／behavior |
| --- | --- |
| Module alias | `<Operation>StatusResult = OperationStatusResult<'literal.type', Outcome>` |
| Found | `ok: true`、`kind`と`data.state`が同じ7 State Literal |
| Non-terminal | `retryAfterSeconds`を必須化 |
| Terminal | `retryAfterSeconds`を型とRuntimeの双方から除外 |
| Completed | Operation固有Outcome。Void Operationは`undefined` |
| Query failure | `ok: false`でHTTP classificationをLiteral化 |
| Transport failure | `status: null`と安全な固定Code |

Temporary TypeScriptは全BranchをExhaustive Narrowingし、Literal Operation Type、Schema Version、HTTP Status、固定Error Code、Outcome ScalarをCompile時に確認した。Generated Objectに`.wait`がないことも`@ts-expect-error`で固定した。

## Status Request and URL Matrix

| Input／option | Result |
| --- | --- |
| Canonical lowercase UUIDv7 | `/operations/{id}`へ一回だけGET |
| Invalid／uppercase／非v7 ID | Fetch 0回、`invalid_operation_id` |
| Base URLなし | Relative Status Resource Path |
| Origin／Base Pathあり | Base Path末尾へStatus Resourceを結合 |
| Invalid Base URL | Fetch 0回、`invalid_base_url` |
| Per-call Headers | Case-insensitiveに先勝ちで重複除去 |
| `Content-Type`指定 | Status GETから除去 |
| Operation Route Header Binding | Status Requestへ流用しない |
| Credentials／Signal | 呼出単位でそのまま伝播 |
| Injected Fetch | Global Fetchより優先 |
| Fetch不在 | `missing_fetch` |

RequestはBodyを持たず、Module Import時に通信しない。`.fetch()`のDeferred 202もStatus Requestを自動実行しない。

## 7 State Decode Matrix

| State | Exact additional body | Result |
| --- | --- | --- |
| `accepted` | なし | Non-terminal Found |
| `running` | Positive Safe Integer `attempt` | Non-terminal Found |
| `retry_scheduled` | `attempt`、UTC Microseconds `retryAt` | Non-terminal Found |
| `completed` | Exact `outcome` Object | Terminal Found |
| `rejected` | Non-empty `error.category`／`error.code` | Terminal Found |
| `failed` | `error.code=operation_failed` | Terminal Found |
| `dead_lettered` | `error.code=operation_dead_lettered` | Terminal Found |

全StateでSchema Version 1、Requested Operation ID、Generated Operation Type、Exact Keysを要求する。Unknown State、余剰／欠落Field、不正Attempt、不正UTC日付／Microseconds、不一致Identityは`unexpected_response`になる。Result、Data、Outcome、ErrorはFreezeする。

## Retry-After Matrix

| Header／state | Result |
| --- | --- |
| Non-terminal `1`以上のCanonical整数 | Safe IntegerへDecode |
| Missing、`0`、負数、符号、空白、小数、HTTP Date | `unexpected_response` |
| Unsafe Integer | `unexpected_response` |
| 重複値の結合表現 | `unexpected_response` |
| TerminalでHeaderなし | Found |
| TerminalでHeaderあり | `unexpected_response` |

## Authentication／Unavailable／Expired／Internal Matrix

| HTTP | Required exact body | Result kind |
| --- | --- | --- |
| 401 | `status=error`、`category=unauthorized`、非空`code` | `authentication` |
| 404 | `status=error`、`code=operation_unavailable` | `unavailable` |
| 410 | `status=error`、`code=operation_expired` | `expired` |
| 500 | `status=error`、`code=internal_error` | `internal` |

Unknown Field、Code不一致、403、422、その他Statusは`unexpected_response`へ分類し、Raw Bodyを保持しない。

## Transport／Safe Failure Matrix

| Failure | Safe code |
| --- | --- |
| Invalid UUIDv7 | `invalid_operation_id` |
| Fetch不在 | `missing_fetch` |
| Invalid Base URL | `invalid_base_url` |
| Fetch rejection | `network_error` |
| Aborted Signalを伴うFetch／Body failure | `aborted` |
| JSON／Media Type／Shape／Identity／Header不整合 | `unexpected_response` |

一時Runtime TestはNetwork Error Object、Abort reason、Raw Response、Credential、URLをResultへ露出しないことを確認した。

## Typed Outcome and EmptyOutcome Evidence

OutcomeありOperationでは既存Phase 15のScalar／Nullable Decoderを再利用し、Exact Field SetとRuntime Scalarを検査する。Temporary TypeScript／Node Testは文字列、整数、Nullable Fieldを含むOutcomeのNarrowingとFreezeを確認した。

OutcomeなしOperationはCompleted Wire `outcome: {}`だけを受理し、Generated Resultの`outcome`を`undefined`へ変換する。余剰Fieldを持つObjectは`unexpected_response`になる。

## Marker／Determinism／Drift Evidence

- Current MarkerはSchema 3を生成する。
- Owned Legacy Marker 1／2はCurrent 3へ安全に置換できる。
- Unknown Marker、Non-marker、Symlink、Atomic Replace／Rollbackの既存Testを維持する。
- 同一Contractから二回生成したPath／Bytesが一致する。
- `frontend:check`はSchema 3 Expected Treeに対してFreshを返す。
- Generated TreeへTimestamp、Absolute Path、Environment、Credential、Fixture Valueを追加していない。

## Strict TypeScript／Runtime Evidence

Temporary Sourceを`/tmp/blackops-p16-005`へ作成し、既存Pinned TypeScript 6.0.3 ToolchainでDOMなしStrict ES2022 ESM Compileと生成JavaScript Runtime Testを実行した。

Runtime Testは7 State、Exact Keys、Typed／Empty Outcome、ID／Type／Schema mismatch、Attempt、UTC Microseconds、Retry-After、401／404／410／500、Invalid IDのFetch 0回、Injected／Global／Missing Fetch、Base URL、Header、Credential、Signal、Network／Abort、Malformed、single fetch、no auto-status、no import-side-effect、Freezeを確認した。Temporary Source、Generated Tree、Build Artifact、Runtime Emitは最終Cleanup済みである。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Console/FrontendGenerateCommandTest.php \
  tests/Internal/Console/FrontendCheckCommandTest.php
Result: OK (45 tests, 414 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1420 tests, 5588 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2526 / Warnings 0 / Errors 0。

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
Result: 成功。Pinned Lockfileを維持。

docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
Result: Build成功、5 files生成、Generated Tree fresh。

mise exec -- pnpm --dir tests/Frontend run test
Result: Strict TypeScript、Runtime assertions、Module shape全成功。

Temporary DOM-less Strict TypeScript／Node Runtime Status Evidence
Result: Compile成功。`P16-005 generated status runtime assertions passed.`

mise exec -- pnpm --dir tests/Frontend run clean
Result: 成功。Generated Tree、Build Artifact、Runtime Emitなし。

Management ID／Sensitive／Polling／Framework Adapter／Tracking／git diff --check Guards
Result: 全成功。
```

最初のfixture `build:compile`は、事前Cleanup後に`tests/Frontend/fixture/var/build` Directoryが存在しなかったためArtifact書込前に停止した。Directoryを作成して同じCommandを再実行し、以後のCanonical Chainは全成功した。Production CodeまたはGenerated Contractの失敗ではない。

## Acceptance Criteria

- [x] 全Generated HTTP Operation Objectが`.status(operationId, options)`を持つ
- [x] `.status()`が一回だけGETし、`.fetch()`は自動Status取得しない
- [x] Canonical UUIDv7を送信前に検証し、Invalid IDでFetchしない
- [x] 7 StateをState別Exact ShapeとTyped OutcomeでDecodeする
- [x] Operation ID／Type／Schema Version不一致をUnexpected Responseにする
- [x] Non-terminalだけ正整数`Retry-After`を要求し、HintをResultへ返す
- [x] Rejected／Failed／Dead Letteredを`ok: true`のTerminal Statusとして返す
- [x] 401／404／410／500を区別したTyped Failureにする
- [x] Missing Fetch／Invalid Base URL／Network／Abort／Invalid ID／MalformedをTransport Codeで区別する
- [x] Raw Body、Credential、Sensitive、Exception DetailをResult／Generated Artifactへ含めない
- [x] `.fetch()`／`.toRequest()`／`.url()`／Readonly Metadataが回帰しない
- [x] `.wait()`、Polling、Retry、Timer、Global Mutable Clientを追加しない
- [x] Marker 3、Deterministic Bytes、Atomic Replace、Driftが成功する
- [x] Temporary Generated TreeのStrict TypeScript／Runtime Evidenceが成功しCleanupされる
- [x] HTTP／Public PHP API／Manifest Schema／Frontend Fixture／CI／Quickstart／Websiteを変更しない
- [x] Required PHP Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

実装上のBlockerと仕様矛盾はない。`.wait()`、Status専用Permanent Frontend Fixture／CI Assertion、Quickstart／Skeleton／Guide／Website／Consumer E2E同期は後続Taskの責務である。

## Orchestrator Review

Operation固有Status Result、7 StateのLiteral Narrowing、Canonical UUIDv7 Preflight、Base Path付きURL、Operation ID／Type／Schema照合、Retry-After、UTC実在日付、Typed／Empty Outcome、401／404／410／500、Safe Transport、Freeze、Marker 1／2から3へのOwnership移行を確認した。既存`.fetch()`のResult Unionと自動実行境界は変更されず、`.wait()`／Polling／Timer／Global Mutable Stateは追加されていない。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、対象45 tests／414 assertions、全1420 tests／5588 assertions、Deptrac 0違反／0警告／0エラーが成功した。Frontend Fixtureのbuild→generate→check、DOMなしStrict TypeScript、Node Runtime、Module Shape、cleanupも成功し、生成Artifactは残っていない。Public PHP／HTTP、Manifest Schema、Frontend Fixture Source、CI、Quickstart、Websiteの範囲逸脱と仕様矛盾はなくAcceptedとした。

## Suggested Next Action

P16-005をCommit／Pushする。その後、P16-006でAbort可能かつ有限なGenerated `.wait()`とPermanent Frontend CI Evidenceを実装する。
