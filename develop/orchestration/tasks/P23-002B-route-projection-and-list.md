# P23-002B: Route Projection and List

Status: Accepted — non-activated Route Projection and List

Updated At: 2026-08-27T17:56:07+09:00

Started At: 2026-08-26T21:25:10+09:00

Accepted At: 2026-08-27T17:56:07+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

Branch: `agent/p23-001-blackops-1-3-feasibility`

## Goal

Accepted P23-002A1/A2の非接続ProjectionBuildSet componentへ、compiled HTTP
route rows、framework-fixed Status route、`route:list`／`route:ls`のHuman／JSON
readerを追加する。Bはroute componentだけを閉じ、partial rootをpublishせず、
Production locator、`build:compile`、Console Kernelへ接続しない。P23-002Cが
schedule componentとall-three producerをまとめて接続し、唯一のatomic activationを行う。

## Reader Outcome

利用者は、Handler、Middleware、Container、Database、Provider、Application Sourceを
起動せず、compiled HTTP method／path／execution strategy／Operation type／ownerを
確認できる。成功時だけroute rowsを表示し、complete ProjectionBuildSetが欠落、破損、
unsupported、stale、build mismatchの場合は安全にexit `2`、operational read failureは
exit `1`となる。

このOutcomeは未Release BlackOps `1.3` proposalである。Stable `1.2.0`のcurrent
runtime、公開Guide、generated artifactは変更しない。

## Source of Truth

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task Packet
4. `develop/spec/README.md`
5. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`
6. `develop/spec/105-blackops-1-3-capability-contract.md`
7. `develop/spec/106-phase-23-delivery-plan.md`
8. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
9. Accepted P23-002A1/A2 Task／Report and current Source／Tests

D146はfilesystem／locator／A/B/C ownershipのより具体的な正本である。矛盾は
Production Codeだけで解決せず、Reportへexact locationとBlockerを返す。

## Preflight Correction Checkpoint

Initial Sol xHigh preflight returned Changes Requested `P1=3/P2=1/P3=0`。
実装前に次の4 rootを正本へ限定補正した。

1. Command固有の`applicationProjectionStatus`契約をroute JSONへ誤適用しない。
   Route成功JSONはclosed `{schemaVersion:1,routes:[...]}`だけで、Human／JSON成功時の
   stderrはemptyとする。
2. Accepted A2 exact 29 target rowsへ、Bがcanonical `route:list`、alias `route:ls`、
   description `List compiled HTTP routes.`、`hidden=false`を加えてexact 30にする。
   current runtime `FrameworkCommandNames`は25のまま。Cがschedule rowを加えて31にし、
   そこで初めてactivateする。
3. `ProjectionBuildSetReader`へcallback-shaped typed-status seamを加え、shared lockを
   complete-set validation、selected-row rendering、output writeまで保持する。A2 listも
   同seamへ移す。同一exact 30-row inputに対するlock seam単独の変更ではbytes／exitを
   変えない。Bが行う29→30 rowの追加では、command projectionとBlackOps-owned listの
   goldenへ`route:list` exact 1 rowを追加する。未登録のStable Symfony `list` bytesは
   変更しない。
4. Human columns／null cell／formatter escaping／`OUTPUT_RAW`／JSON JCS／final LF／
   failure stderrをexact wireとして固定する。

Fresh Sol xHigh re-preflightはChanges Requested `P1=1/P2=1/P3=0`を返した。
上記の29→30 row差分とlock seam単独の不変条件を分離し、Fast GateをFiles Allowedの
全TestとKernel nonactivation fixtureへ拡張した。Implementationはclosure
re-preflight Green後にだけ開始する。

Closure Sol xHigh re-preflightはexact9 freeze
`991150cdbb60a004657c5c940639ab76f7d0d4826bd27738f6419af40fb5e1ab`へ
Green `P1=0/P2=0/P3=0`を返した。Luna Max implementationを開始してよい。

## Luna Max implementation checkpoint

Status: `Accepted — non-activated Route Projection and List`.
The bounded Final-Gate contract correction preserves the Stable GET
`/operations/` composite response by separating the runtime status-dispatch
matcher (which accepts the empty suffix) from the nonempty one-segment collision
predicate. The request handler uses the runtime matcher; the compiler and route
producer retain the collision predicate. The public composite regression asserts
the exact unavailable 404 JSON, cache header, content type, and body.

The bounded P2 fixture correction adds producer/codec duplicate, record-cap, and
closed-row rejection fixtures; non-selected command and schedule corruption;
non-Boolean `--json` input; and an executable manifest-class purity sentinel.
Existing exact30 canonical/alias and all-fallback output fixtures remain in the
Fast Gate. The renderer and list command retain the explicitly documented
validated route-record type assertions.
BlackOps-owned command/list goldens advance from 29 to 30; current runtime names
remain exact25 and `route:list`／`route:ls` remain absent from the Application
Console Kernel. A callback-thrown `ProjectionBuildSetAbsentException` now
propagates without a second callback invocation, while lock unlock/close remains
exactly once. Route Human command output is explicitly identical for decorated
and undecorated output and contains no ANSI bytes.

The prior Sol implementation review verdict Changes Requested `P1=1/P2=2/P3=0`
and the `3ebe65581101fe1e6e732194be40c86ddb532da6dc04a95cbbfe6c0aa41cf02c`
Source/Test freeze with its Final Gate evidence are Historical/superseded because
this correction changed Source/Test. The earlier `a1c17de` and `58a3f2e` freezes
remain Historical for their already recorded lint/analyze failures. Current
correction-focused HTTP/Route PHPUnit is `80 tests / 396 assertions` PASS and the
Task Fast Gate at `2026-08-27T09:58:07+09:00` is Green: `128 tests / 1221
assertions` PASS;
canonical `mago lint` PASS (`No issues found` after baseline filtering 186);
canonical `mago lint --verify-baseline` PASS (`Baseline is up to date`);
canonical `mago analyze` PASS with `0 errors`, `78 warnings`, and `34 help`
messages (112 existing non-error issues); allowed-path Mago format check PASS
(`All files are already formatted`); management-ID guard PASS;
`git diff --check` PASS; staged-file check PASS (`0`). No baseline was generated
or updated.

The accepted ordered 41-file Source/Test freeze aggregate is
`174bc94c42b733a7611885f2edc4ab78aad1628c02a3bdd4ed5a48eb2d3df6fa`. It covers
the accepted A1/A2 Source/Test set plus the bounded B additions; the exact
ordered manifest and per-file hashes are recorded in the Task Report.

The prior Final Gate evidence is retained only as Historical for the superseded
`3ebe655` freeze. The one current Final Gate on `174bc94c...` is Green: format,
Composer strict, lint/baseline, analyze with errors `0`, PHPUnit `2435 tests /
10739 assertions`, Deptrac violations/skipped/uncovered `0`, version baseline,
Website release-source guard, management-ID, diff, staged0, and ending freeze
all PASS.

Fresh Sol xHigh implementation review matched the exact Source/Test and
management hashes and returned Green `P1=0/P2=0/P3=0`. The Orchestrator accepts
P23-002B without activating the new commands or publishing a partial build set.

## In Scope

- compiled `HttpOperationManifest`からOperation route rowsをpureに生成する
- built-in `Inline`／`Deferred`を`inline`／`deferred`へ、その他のcompiled strategy
  class-stringを`custom:<FQCN>`へ変換する。class existence／Container resolutionは行わない
- framework-fixed `GET /operations/{operationId}` rowを同じroute projectionへ結合する
- canonical／renamed placeholder／static segmentのreserved GET collisionをrejectし、
  POST同pathとGET nested pathを許可する
- `OperationStatusHttpContract`をmethod／template／collision identityの単一Sourceとし、
  current compiler／request handler／B producerで共有する
- disconnected `route:list` commandとexact alias `route:ls`
- Human／JSON renderer、complete-set failure mapping、safe diagnostics
- A1 reader callback seamとA2 listのlock-held rendering migration
- command target metadata exact 30への拡張。ただしcurrent runtime namesは25のまま

## Out of Scope

- `ApplicationConsoleKernel`への`route:list`／`route:ls`登録
- `build:compile`接続、publisher呼出、Production locator／configuration
- partial ProjectionBuildSet root／route-only generationのpublication
- schedule projection、`schedule:list`／`schedule:ls`
- public Guide／Website／Search／LLM／release authority／version／tag
- Provider／Container／DB／Handler／Middleware／Source／runtime fallback
- Commit、Stage、Push、PR、remote CI、Deploy、Publication、Tag、Release

## Files Allowed

### Production Source

1. `src/Http/Status/OperationStatusHttpContract.php`
2. `src/Http/Status/OperationStatusRequestHandler.php`
3. `src/Http/Routing/HttpRouteCompiler.php`
4. `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php`
5. `src/Internal/Projection/Command/CommandProjectionListCommand.php`
6. `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`
7. `src/Internal/Projection/Route/RouteProjectionProducer.php`
8. `src/Internal/Projection/Route/RouteProjectionRenderer.php`
9. `src/Internal/Projection/Route/RouteProjectionListCommand.php`

### Tests

10. `tests/Http/Status/OperationStatusRequestHandlerTest.php`
11. `tests/Http/OperationRequestHandlerTest.php`
12. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
13. `tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php`
14. `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
15. `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
16. `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
17. `tests/Internal/Projection/Route/RouteProjectionProducerTest.php`
18. `tests/Internal/Projection/Route/RouteProjectionRendererTest.php`
19. `tests/Internal/Projection/Route/RouteProjectionListCommandTest.php`

### Management／Normative Documentation

20. `develop/orchestration/tasks/P23-002B-route-projection-and-list.md`
21. `develop/orchestration/reports/P23-002B-route-projection-and-list.md`
22. `develop/STATE.md`
23. `develop/TODO.md`
24. `develop/spec/README.md`
25. `develop/spec/105-blackops-1-3-capability-contract.md`
26. `develop/spec/106-phase-23-delivery-plan.md`
27. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
28. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`

上記以外を変更しない。追加fileが必要なら実装を広げずReportへBlockerとして返す。

## Exact Route Contract

### Producer

- Inputはalready-compiled `HttpOperationManifest`と`applicationBuildId`だけ。
- `routes[method][path]=typeId`が参照する`operations[typeId].strategy`だけを読む。
- operation rowはexact
  `{method,path,strategy,operationType,origin:"operation_manifest"}`。
- fixed rowはexact
  `{method:"GET",path:"/operations/{operationId}",strategy:null,operationType:null,origin:"framework_fixed"}`。
- strategy wireは`Inline::class -> inline`、`Deferred::class -> deferred`、その他は
  `custom:`＋先頭backslashなしclass-string。autoload、`class_exists`、reflectionをしない。
- Codecがclosed fields、bounds、sort、duplicate、NFC、custom grammarを最終検証する。
- GET `/operations/{operationId}`、GET `/operations/{id}`、GET `/operations/current`
  はfixed route collision。POST同path、GET `/operations/{id}/outcome`は非collision。
- Handler／outcome／value／dispatcher dataをrowへ出さない。

### Target Metadata and Nonactivation

- `CommandProjectionTargetRegistry::all()`はexact 30 rows。
- 新rowはcanonical `route:list`、aliases `['route:ls']`、description
  `List compiled HTTP routes.`、hidden `false`、originはcommand projection上`framework`。
- `FrameworkCommandNames::all()`とcurrent Kernel registrationsはexact 25のまま。
- `route:ls`を別canonical rowにしない。
- BではKernel registration、publisher、locator、build connectionが0件。

### Reader and Command

- `route:list [--json]`とalias `route:ls [--json]`だけを受ける。
- Unknown option／unexpected argument／invalid Booleanはexit `2`、stdout empty、
  stderr exact `Input error.\n`。
- expected build/input identityがinvalidならexit `2`、stdout empty、stderr exact
  `Route projection input is invalid.\n`。
- Reader callbackのshared lock内でroot＋command＋route＋scheduleを全検証し、
  route rowsをselect／render／writeする。
- availableだけexit `0`。absent／malformed／unsupported／stale／build mismatchは
  exit `2`、stdout empty、stderr exact
  `Route projection is unavailable or invalid.\n`。
- permission／I/O／unreadableはexit `1`、stdout empty、stderr exact
  `Projection build-set read failed safely.\n`。
- exception message、filesystem path、class、SQL、credentialを出さない。
- Canonical／aliasのstdout、stderr、exitはidentical。

### Human and JSON

- Human exact headers: `Method`, `Path`, `Strategy`, `Operation Type`, `Origin`。
- `strategy=null`／`operationType=null`はempty cell。
- 全cellを`OutputFormatter::escape`し、complete bytesを`OUTPUT_RAW`でwriteする。
- decorated／undecorated Human bytesはidentical、ANSIなし、UTF-8/LF、final LF exact1。
- JSONはclosed `{schemaVersion:1,routes:[...]}`、RFC 8785 JCS＋final LF exact1、
  BOM／CRLF／追加fieldなし。`applicationProjectionStatus`を含めない。
- Route rowsはCodecの`(path,method,origin,operationType,strategy)` unsigned-byte順。
- success stderrはHuman／JSONともempty。

## Required Fixtures

### Producer／Fixed Route

- empty Operation routesでもfixed row exact1
- inline／deferred／custom route rows＋deterministic sort
- unreferenced Operation metadataを出さない
- missing referenced type、invalid strategy wire、duplicate/cap/closed-field failure
- canonical／renamed placeholder／static GET collision rejection
- POST same path／GET nested path positive
- current compilerとrequest handlerが同じ`OperationStatusHttpContract`をconsume
- constructor／factory／Provider／Container／DB／Handler／Middleware／Source sentinel 0

### Target Registry

- exact30 rows、route canonical exact1、alias exact1、description／hidden exact
- A2 exact29からB exact30へのintentional golden update。availableと全fallbackで
  canonical `route:list` exact1、alias `route:ls`、別canonical aliasなし
- current Framework names／Kernel registrations exact25
- Reservation registry exact13 unchanged
- no `schedule:list` target before C

### Lock-held Reader

- callback event occurs after shared lock acquisition and before unlock
- callback throw still unlocks/closes exactly once
- all six statuses and combined precedence remain unchanged
- 同一exact30 inputに対するlock-seam前後で、A2 list available/fallback
  Human/raw/JSON/XML/Markdown bytesとexitが不変
- Bの29→30 row追加後goldenは、全format／全fallbackで`route:list`をexact1含む
- non-selected command/schedule mismatch invalidates route

### Route Command／Renderer

- canonical/alias Human and JSON exact parity
- exact Human headers/null cells/final LF
- JCS JSON decode、closed keys、no status、final LF
- path with recognized Symfony tags/open/close/style remains literal for decorated true/false,
  no ANSI/throw; JSON literal parity
- available empty route set still contains fixed row
- all five nonavailable statuses exit2/no stdout/exact stderr
- operational failure exit1/no stdout/safe exact stderr
- invalid input/expected identity exit2/no stdout/exact stderr
- no exception/path/secret/class/handler/middleware field leakage
- no fallback to HTTP artifact/source/runtime

## Fast Gate

反復中は次だけを実行する。

```bash
docker compose run --rm app vendor/bin/phpunit \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Projection/Route \
  tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php \
  tests/Internal/Projection/Command/CommandProjectionListCommandTest.php \
  tests/Internal/Projection/Command/CommandProjectionProducerTest.php \
  tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/Status/OperationStatusRequestHandlerTest.php
docker compose run --rm app mago format --check \
  src/Http/Status/OperationStatusHttpContract.php \
  src/Http/Status/OperationStatusRequestHandler.php \
  src/Http/Routing/HttpRouteCompiler.php \
  src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php \
  src/Internal/Projection/Command/CommandProjectionListCommand.php \
  src/Internal/Projection/Command/CommandProjectionTargetRegistry.php \
  src/Internal/Projection/Route \
  tests/Http/Status/OperationStatusRequestHandlerTest.php \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php \
  tests/Internal/Projection/Command/CommandProjectionListCommandTest.php \
  tests/Internal/Projection/Command/CommandProjectionProducerTest.php \
  tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php \
  tests/Internal/Projection/Route
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

## Final Gate

Source/Test freeze後に1回だけ実行する。Sourceが変われば旧evidenceはHistoricalとし、
新freezeで最初からやり直す。

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
pnpm --dir docs/website run release:check:source
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

Website full test／build／Browserはpublic Documentation Source不変のため実行しない。
未実行項目はReportへ理由付きで記録する。

## Acceptance Criteria

- [x] First Sol xHigh re-preflight Changes Requested `P1=1/P2=1/P3=0` recorded
- [x] Closure Sol xHigh re-preflight `P1=0/P2=0/P3=0`
- [x] Luna Max implementation within exact Files Allowed
- [x] Route producer＋fixed descriptor＋collision contract Green
- [x] Target registry exact30／current runtime names exact25／nonactivation Green
- [x] Lock-held callback seam＋A2 list regression Green
- [x] `route:list`／`route:ls` Human／JSON／exit／stderr exact wires Green
- [x] Constructor／factory／Provider／Container／DB／Handler／Middleware／Source sentinels 0
- [x] Fast Gate Green and ordered Source/Test freeze proposed
- [x] One post-correction Final Gate Green
- [x] Fresh Sol xHigh implementation review `P1=0/P2=0/P3=0`
- [x] Report／STATE／TODO／Spec README synchronized; D146 checkpoint updated
- [x] Orchestrator Acceptance

## Remaining Steps

None within P23-002B. The higher atomic slice still requires P23-002C
schedule/final activation.

## Suggested Next Action

Begin the P23-002C Task Packet and Sol preflight. P23-002C owns schedule and the
only all-three atomic activation; preserve P23-002B as non-activated.
Commit、stage、push、CI、deploy、publication、tag、releaseは未承認／未実行。
