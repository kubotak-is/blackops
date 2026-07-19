# P16-006 Generated Wait Capability and Frontend CI Report

Status: Accepted

## Summary

全Generated HTTP Operation Objectへ、Terminal StateまでStatusを明示取得する`.wait(operationId, options)`を追加した。購読可能なStructural Abort Signalと正のSafe Integer `maxWaitMilliseconds`を必須化し、固定Deadline、Server `Retry-After`、Abort、Timeout、Clock／Timer／Fetch注入を共通Generated Runtimeへ実装した。

各in-flight Status RequestもDeadline Timer／Abort SignalとのRaceに含めるため、Injected Fetchが永遠に未解決でも`.wait()`は`poll_timeout`または`aborted`へ有限に到達する。Status RequestとRetry Sleepは成功、Abort、Timeout、Errorの全経路でInvocation所有TimerをClearし、Abort ListenerをRemoveする。

単発`.status()`の7 State Resultは変更せず、`.wait()`には別のOperation固有Wait Resultを生成した。Wait ResultはTerminal 4 StateとFailureだけを含み、Non-terminal 3 State、`invalid_wait_options`、`poll_timeout`が単発Statusまたは`.fetch()`へ混入しない。

Generated MarkerをSchema 4へ更新し、Permanent `tests/Frontend`のDOMなしStrict TypeScript／Node Runtime Evidenceを既存`pnpm test`とGitHub Actions Frontend Jobへ統合した。CI Workflow自体の変更は不要だった。

## Changed Files

### Production

- `src/Internal/Frontend/Generation/FrontendGenerationMarker.php`
- `src/Internal/Frontend/Generation/FrontendTypeScriptGenerator.php`

### Tests and Permanent Frontend Evidence

- `tests/Internal/Console/FrontendGenerateCommandTest.php`
- `tests/Internal/Frontend/Generation/FrontendOutputWriterTest.php`
- `tests/Internal/Frontend/Generation/FrontendTypeScriptGeneratorTest.php`
- `tests/Frontend/package.json`
- `tests/Frontend/types/narrowing.ts`
- `tests/Frontend/scripts/status-wait-runtime-test.mjs`

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/status-query.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-006-generated-wait-and-frontend-ci.md`

## Decisions and Assumptions

- `.wait()`は全HTTP Operationへ生成する。Execution StrategyでSurfaceを分けず、既知Operation IDを持つ任意OperationのStatusを待機できる。
- Orchestrator Reviewで有限Deadlineをin-flight Fetchにも適用することを明確化した。Request開始と同時にDeadline TimerをArmするため、Immediate TerminalでもRequest Deadline Timerは一度Armされ、Response完了時にClearされる。Retry Sleep Timerは0回である。
- 単発`.status()`へWait専用Codeを露出させない。`OperationStatusResult`／`OperationStatusTransportError`を維持し、`OperationWaitResult`／`OperationWaitTransportError`を別に生成する。
- Wait Resultは`completed`、`rejected`、`failed`、`dead_lettered`とFailureだけを含む。`accepted`、`running`、`retry_scheduled`はRuntime Loop内だけで扱う。
- Deadlineは開始Clockと`maxWaitMilliseconds`から一度だけ固定し、経過回数の加算では延長しない。Clockの非整数、負数、Unsafe値、逆行、Deadline Overflowを`invalid_wait_options`へ閉じる。
- Retry対象はStrict Decode済みNon-terminal 200だけである。401／404／410／500、Network、Malformed、Invalid ID／Optionを自動Retryしない。
- Timer／Clock／Fetch／Signal／Listener／Deadlineは各InvocationのLocal変数だけに保持し、Module Global Mutable Stateを追加しない。
- DOM `AbortSignal`、Browser Timer型、NodeJS Namespace、Frontend Frameworkへ依存しない。
- Quickstart、Skeleton、Guide、Website、Consumer E2E、HTTP／Public PHP API、Frontend Contract Manifest、CI Workflowは変更していない。

## Generated Wait Type／Narrowing Matrix

| Surface | Contract |
| --- | --- |
| `<Operation>WaitResult` | `OperationWaitResult<'literal.type', OperationOutcome>` |
| Terminal success | `completed`／`rejected`／`failed`／`dead_lettered` |
| Query failure | `authentication`／`unavailable`／`expired`／`internal` |
| Wait transport | Status transport Code + `invalid_wait_options`／`poll_timeout` |
| Non-terminal | Wait Result型から除外 |
| Required options | `signal`、`maxWaitMilliseconds` |
| Optional injections | `clock`、`timer`、`fetch`、既存Call Option |

Permanent TypeScriptはStatus 7 State、Wait Terminal／Failure、Operation固有Outcome、EmptyOutcomeをNarrowingする。SignalまたはDeadline省略、Wait Resultへの`accepted`代入、Statusへの`poll_timeout`代入、Fetchへの`invalid_wait_options`代入を`@ts-expect-error`で固定した。

## Polling／Retry-After／Deadline Matrix

| Situation | Behavior |
| --- | --- |
| Start | Status Requestを直ちに開始し、同時に残りDeadline TimerをArm |
| Terminal／Failure | Request TimerをClearして即時返却 |
| Non-terminal | Request TimerをClearし、`Retry-After * 1000`だけ待機 |
| Retry interval < Remaining | Sleep後に次Status Request |
| Retry interval >= Remaining | Remainingだけ待ち、追加Fetchなしで`poll_timeout` |
| in-flight Fetch未解決 | Deadline Timerで`poll_timeout` |
| ClockがDeadline到達 | 追加Fetchなしで`poll_timeout` |
| Clock逆行／Invalid | `invalid_wait_options` |

Fallback Interval、Retry Count、Exponential Backoff、Jitterを持たない。Deadlineは固定値を正本とし、Status／Sleep回数で延長しない。

## Abort／Cleanup Matrix

| Path | Fetch | Timer／Listener cleanup | Result |
| --- | --- | --- | --- |
| Pre-abort | 0 | 登録なし | `aborted` |
| addEventListener中の同期Abort | 0 | Listener Remove、Timerなし | `aborted` |
| Request Timer登録中の同期Abort | 1 | 取得HandleをClear、Listener Remove | `aborted` |
| Sleep Timer登録中の同期Abort | 1 | 取得HandleをClear、Listener Remove | `aborted` |
| in-flight Abort | 1 | Request Timer Clear、Listener Remove | `aborted` |
| Sleep中Abort | 1 | Sleep Timer Clear、Listener Remove | `aborted` |
| Deadline | 1以上 | Fired Timer Clear、Listener Remove | `poll_timeout` |
| Success／HTTP／Transport Error | 1 | Request Timer Clear、Listener Remove | Resultを即時返却 |

Listener／Timer登録、Clock、CleanupがThrowしてもRaw DetailをResultへ出さず、安全なWait Failureへ閉じる。Abort Reasonは一度も読み出さずResultへ保持しない。

## Error Immediate-stop Matrix

| Status／failure | Fetch count before return | Result |
| --- | --- | --- |
| 401 | 1 | `authentication` |
| 404 | 1 | `unavailable` |
| 410 | 1 | `expired` |
| 500 | 1 | `internal` |
| Network rejection | 1 | `network_error` |
| Malformed response | 1 | `unexpected_response` |
| Invalid ID | 0 | `invalid_operation_id` |
| Invalid Wait Option／Clock | 0または最初のClock不整合検知時 | `invalid_wait_options` |

いずれもRetryしない。

## Parallel Wait Isolation Evidence

Permanent Node Runtime Testは異なるSignal、Clock開始値、Timer、Fetch Sequenceを持つ二つの`.wait()`を同時実行した。一方をSleep中にAbortしても、他方のTimerとFetchは継続しCompletedへ到達する。双方のActive Timer／Listenerは0となり、Fetch回数とClock値は互いに混ざらない。

## Marker／Determinism／Drift Evidence

- Current Ownership MarkerはSchema 4。
- Owned Legacy Marker 1／2／3をCurrent 4へ置換できる。
- Current DecodeとTemporary Tree Validationは4だけを受理する。
- Unknown Marker、Non-marker、Symlink、Atomic Replace、Rollback Safetyの既存回帰を維持する。
- 同一Contractの二回生成でPath／Bytesが一致する。
- `frontend:check`は`.wait()`を含むExpected TreeをFreshと判定する。
- Timestamp、Absolute Path、Environment、Credential、Fixture ValueをGenerated Treeへ追加していない。

## Permanent Strict TypeScript／Node Runtime／CI Evidence

既存Pinned TypeScript 6.0.3、DOMなしStrict ES2022 ESM FixtureへStatus／Wait Evidenceを恒久追加した。`tests/Frontend/package.json`の既存`test` Scriptが、既存Runtime Testに続いて`status-wait-runtime-test.mjs`を実行する。GitHub Actions Frontend Jobは既に`pnpm --dir tests/Frontend run test`を呼ぶため、Workflow変更なしで新Evidenceを実行する。

Node Runtimeは7 State、Typed Outcome、EmptyOutcome、Retry秒からMillisecondsへの変換、Terminal、Deadline、never-resolving Fetch、Pre／in-flight／Sleep／同期Abort、HTTP／Network／Malformed即時停止、Invalid ID／Option、Default Runtime、Parallel Wait、Import Side Effect不在、`.fetch()`非自動Status、Freeze、全Cleanupを検証した。

## Sensitive／Raw Error Safety Evidence

- Network Exception、Malformed Body、Cleanup Exception、Abort ReasonをResultへ保持しない。
- `poll_timeout`／`aborted`へOperation ID、Elapsed、Deadline、Timer Handle、Signal Reasonを追加しない。
- Permanent Runtime ResultとGenerated Treeへ`raw-body`、`credential-secret`、`sensitive-value`、Default Fixture値が出ないことをGuardした。
- Operation Route Header／Value BindingをStatus／Wait Requestへ流用しない既存境界を維持する。

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
Result: OK (46 tests, 460 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1421 tests, 5634 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2526 / Warnings 0 / Errors 0。

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
Result: Already up to date。TypeScript 6.0.3／pnpm 11.12.0 Lockを維持。

docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
Result: Build成功、5 files生成、Generated Tree fresh。

mise exec -- pnpm --dir tests/Frontend run test
Result: DOMなしStrict TypeScript、既存Fetch Runtime、Status／Finite Wait Runtime、Module Shape全成功。

mise exec -- pnpm --dir tests/Frontend run clean
Result: 成功。Generated Tree、Build Artifact、Runtime Emitなし。

Management ID／Sensitive／Forbidden Policy／Tracking／git diff --check Guards
Result: 全成功。
```

## Acceptance Criteria

- [x] 全Generated HTTP Operation Objectが`.wait(operationId, options)`を持つ
- [x] Required Signalと正の有限Safe Integer `maxWaitMilliseconds`を強制する
- [x] 最初のStatus Queryを待機せず直ちに開始する
- [x] Terminalは直ちに返し、Non-terminalだけRetry-Afterに従う
- [x] Deadline到達は追加FetchせずSafe `poll_timeout`になる
- [x] never-resolving in-flight FetchもDeadline／Abortで有限に終了する
- [x] Pre-abort／Sleep中Abort／In-flight AbortがSafe `aborted`になる
- [x] 401／404／410／5xx／Network／MalformedをRetryしない
- [x] Timer／Clock／FetchをDOMなし構造型で注入できる
- [x] 全完了経路でTimer／ListenerをCleanupする
- [x] 複数`.wait()`がGlobal Mutable Stateなしで完全に独立する
- [x] `.status()`一回取得と`.fetch()`非自動Pollingを維持する
- [x] Existing Fetch／Status Result UnionをWait専用Codeで広げない
- [x] Wait Result型からNon-terminal Stateを除外する
- [x] Marker 4、Deterministic Bytes、Atomic Replace、Driftが成功する
- [x] Permanent Frontend Typecheck／Runtime EvidenceがCIの既存Frontend Jobで実行される
- [x] Raw Body、Credential、Sensitive、Exception、Abort Reasonを露出しない
- [x] HTTP／PHP Public API／Manifest Schema／Quickstart／Websiteを変更しない
- [x] Required Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

実装上のBlockerと仕様矛盾はない。Quickstart／SkeletonのStatus Authorizer例、Real HTTPでの202からTerminalまでのJourney、Guide／Website／Consumer E2E同期は後続P16-007の責務である。

## Suggested Next Action

P16-006をCommit／Pushし、その後P16-007 Consumer Experience and Closeoutへ進む。

## Orchestrator Review

Generated Wait／Status Result分離、in-flight Fetchを含む固定Deadline、Retry-After、Abort登録中の同期Race、Timer／Listener Cleanup、Parallel Wait分離を差分とPermanent Runtime Evidenceで確認した。Composer、Mago、対象46 tests／460 assertions、全1421 tests／5634 assertions、Deptrac、実生成Treeの`frontend:check`、DOMなしTypeScript／Node Runtime、Sensitive／Forbidden／Tracking／Management ID／Diff Guardを独立再実行し、すべて成功した。範囲逸脱と仕様矛盾はなくAcceptedとした。
