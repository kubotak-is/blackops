# P23-002C: Schedule Projection and Atomic Activation

Status: Accepted — Schedule Projection and Atomic Activation

Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted

Updated At: 2026-08-27T23:20:37+09:00

Started At: 2026-08-27T18:13:04+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

Branch: `agent/p23-001-blackops-1-3-feasibility`

## Goal

Acceptedで非接続のP23-002A1/A2/Bへ、compiled Application Schedule rowsと
`schedule:list`／`schedule:ls`を追加する。

同じTaskでcommand、route、scheduleの3 producerを`build:compile`へ接続し、
complete `ProjectionBuildSet v1`だけをpublishする。

Console KernelはBlackOps-owned `list`／`ls`、`route:list`／`route:ls`、
`schedule:list`／`schedule:ls`を同じcomplete rootへ接続する。

## Reader Outcome

Framework利用者は、Provider、Container、Database、clock、due evaluation、
Occurrence claim、maintenance schedulerを起動せず、build時にcompileされた
Application Scheduleのname、Operation type、cron、timezone、execution strategyを
確認できる。

`list`はprojectionが未生成でもexact 31 target rowsを表示し、
`build:compile`を発見できる。

`route:list`と`schedule:list`はcomplete rootだけを受理し、partial generationへ
fallbackしない。

このOutcomeは未Release BlackOps `1.3` proposalである。

Stable `1.2.0`のtag、公開Guide、Website、Search、LLM artifact、Release Authorityは
このTaskで変更しない。

## Source of Truth

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task Packet
4. `develop/spec/README.md`
5. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`
6. `develop/spec/105-blackops-1-3-capability-contract.md`
7. `develop/spec/106-phase-23-delivery-plan.md`
8. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
9. Accepted P23-002A1/A2/B Task／Report and current Source／Tests

D146はProduction locator、reader identity、publisher、A/B/C activationの
より具体的な正本である。

矛盾はProduction Codeだけで解決せず、Reportへexact locationとBlockerを返す。

## Implementation Start Condition

Production実装は、次の正本補正を含むTask PacketへSol xHigh preflightが
Green `P1=0/P2=0/P3=0`を返した後にだけ開始する。

Initial Sol xHigh preflightはChanges Requested `P1=2/P2=2/P3=0`を返した。
そのfindingはProduction実装前にこのPacketと正本へ限定補正した。
Fresh bounded Sol xHigh re-preflightはexact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`
を照合し、Green `P1=0/P2=0/P3=0`を返した。
Production implementationのStart Conditionは満たされた。

## Final-Gate correction checkpoint

The first Orchestrator Final Gate on the exact32 Source/Test aggregate
`0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5` is
Historical/superseded. Canonical `mago lint` reported exactly two new
cyclomatic-complexity errors: `ApplicationBuildCompileCommand` complexity 16
over the limit 15 at line 56, and
`ApplicationProjectionBuildSetStatusSource` complexity 28 over the limit 15 at
line 20. At that Historical checkpoint, no later Final Gate command was run.

The narrow current-code correction adds only
`@mago-expect lint:cyclomatic-complexity` class DocBlocks to those two allowed
Production files; runtime behavior and the fixed contract are unchanged.
Correction-focused format, canonical lint, and impacted PHPUnit pass.

The prior Orchestrator refroze the corrected exact32 Source/Test subset as
`be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766` and
restarted the complete Final Gate from its first command. Format, Composer
strict validation, canonical lint and baseline verification, analyze with zero
errors, full PHPUnit, Deptrac, version baseline, Website release-source,
management-ID, diff, staged-path, and ending-freeze checks are Green. The first
failed Final Gate and `0cce05bb...` remain Historical evidence only.

Fresh Sol xHigh implementation review of the corrected `be0b7c...` candidate
returned Changes Requested `P1=0/P2=2/P3=1`. The bounded correction adds only
the two allowed direct integration fixture files. Kernel inventory/alias
parity and build publication/failure-generation fixtures now close the review
findings. The `be0b7c...` exact32 freeze and its Final Gate are
Historical/superseded. The replacement worker-proposed exact32 aggregate is
`15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`; the
Orchestrator refroze it and completed the current Final Gate below.

## Current Final-Gate checkpoint

The current exact32 Source/Test freeze is `15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`.
The complete Final Gate is Green: format, Composer strict, canonical lint
(baseline filtered 186; no issues), baseline verification, analyze (exit 0,
errors 0, warnings 80, help 39), PHPUnit (2472 tests / 10972 assertions;
deprecations 1, PHPUnit deprecations 2, notices 13), Deptrac (violations,
skipped, uncovered, warnings, errors all 0; allowed 5003), version baseline,
release-source, PHP management-ID, diff-check, staged0, and ending exact32
invariance. The prior `be0b7c...` Final Gate is Historical/superseded.
Fresh Sol Max closure review independently matched the exact32 and exact9
freezes, closed the prior findings, and returned Green `P1=0/P2=0/P3=0`.
The Orchestrator accepts P23-002C. BlackOps `1.3` remains unreleased.

初期read-only boundary researchは、実装前Blockerとして次の4 rootを確認した。

1. Accepted component seamはcaller-injected exact 3 input hashesを要求するが、
   Production KernelがSource、Provider、Container、既存manifestを読まずにその値を
   算出するauthorityがない。
2. Injected locatorはexisting directoryを要求するが、Production pathと
   configuration欠落時の`list` recoveryが未定義だった。
3. Schedule成功JSONとexit `0|1|2`以外に、Human wire、formatter safety、
   final LF、input／artifact／operational diagnosticsが固定されていなかった。
4. exact 25 `FrameworkCommandNames`、exact 31 activation target、
   `build:compile`のpublication order、post-commit cleanup resultの扱いが
   一つの実装へ閉じていなかった。

このPacketは以下のFixed Contractで4 rootを閉じる。

## In Scope

- already-compiled `OperationRegistry`からschedule rowsをpureに生成する
- `Inline`／`Deferred`／custom strategyの単一wire mapper
- disconnected schedule Human／JSON rendererとcommand
- target metadata exact 30からexact 31への更新
- Production root、root-only runtime status source、missing-config recovery
- `build:compile`のall-three producer／publisher接続
- Console Kernelでの3 reader familyのatomic activation
- current Kernel compositionで起きるexplicit registration constructionと、
  list command自身が追加で起動しない処理の区別
- focused iteration test、one Final Gate、fresh Sol implementation review

## Out of Scope

- Scheduleのdue evaluation、Occurrence claim、misfire、overlap、actor、tenant
- Framework maintenance `scheduler:*`の列挙
- Provider、Container、Database、Handler、clockをlist時に起動するfallback
- `worker:list`／`worker:ls`、P23-002D以降のcommand
- public Guide／Website本文／Search／LLM／release note／version／tag
- Commit、Stage、Push、PR、remote CI、Deploy、Publication、Tag、Release

## Files Allowed

### Production Source

1. `src/Internal/Application/ApplicationBuildConfiguration.php`
2. `src/Internal/Application/ApplicationConsoleKernel.php`
3. `src/Internal/Application/ApplicationConsoleCommandFactory.php`
4. `src/Internal/Application/ApplicationProjectionBuildSetStatusSource.php`
5. `src/Internal/Console/ApplicationBuildCompileCommand.php`
6. `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php`
7. `src/Internal/Projection/BuildSet/ProjectionBuildSetStatusSource.php`
8. `src/Internal/Projection/BuildSet/ProjectionExecutionStrategyWire.php`
9. `src/Internal/Projection/Command/CommandProjectionListCommand.php`
10. `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`
11. `src/Internal/Projection/Route/RouteProjectionProducer.php`
12. `src/Internal/Projection/Route/RouteProjectionListCommand.php`
13. `src/Internal/Projection/Schedule/ScheduleProjectionProducer.php`
14. `src/Internal/Projection/Schedule/ScheduleProjectionRenderer.php`
15. `src/Internal/Projection/Schedule/ScheduleProjectionListCommand.php`

### Tests

16. `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
17. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
18. `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php`
19. `tests/Internal/Application/ApplicationProjectionBuildSetStatusSourceTest.php`
20. `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
21. `tests/Internal/Projection/BuildSet/ProjectionBuildSetPublisherTest.php`
22. `tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php`
23. `tests/Internal/Projection/BuildSet/ProjectionExecutionStrategyWireTest.php`
24. `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
25. `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
26. `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
27. `tests/Internal/Projection/Command/ApplicationOperationListCommandTest.php`
28. `tests/Internal/Projection/Route/RouteProjectionProducerTest.php`
29. `tests/Internal/Projection/Route/RouteProjectionListCommandTest.php`
30. `tests/Internal/Projection/Schedule/ScheduleProjectionProducerTest.php`
31. `tests/Internal/Projection/Schedule/ScheduleProjectionRendererTest.php`
32. `tests/Internal/Projection/Schedule/ScheduleProjectionListCommandTest.php`

### Management／Normative Documentation

33. `develop/orchestration/tasks/P23-002C-schedule-projection-and-atomic-activation.md`
34. `develop/orchestration/reports/P23-002C-schedule-projection-and-atomic-activation.md`
35. `develop/STATE.md`
36. `develop/TODO.md`
37. `develop/spec/README.md`
38. `develop/spec/105-blackops-1-3-capability-contract.md`
39. `develop/spec/106-phase-23-delivery-plan.md`
40. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
41. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`

上記以外を変更しない。

追加fileが必要なら実装を広げず、ReportへBlockerとして返す。

Implementation中に、Accepted A2 fixture
`ApplicationOperationListCommandTest`が`ls`未登録を固定しており、Cの意図した
activation後は必ず失敗することを確認した。
OrchestratorはProduction契約を広げず、この既存回帰test 1件だけをFiles Allowedへ
追加した。変更前hash
`afde95caeb78a5e59c1655d1ef0a0ecbabd473cbc4c63107e04ca2b005dbbf81`
を記録し、`ls` activation assertionだけをCのexact31 contractへ同期する。
この限定scope correctionはFinal Gate前の実装中補正であり、既存Preflight Greenを
Production設計の再審査へ戻さない。Fresh Sol implementation reviewが最終差分を確認する。

## Fixed Implementation Contract

### Production locator and absent recovery

- Production rootは、valid `app.build.container`のabsolute pathに対する
  `dirname(container)`である。
- `ApplicationBuildConfiguration::projectionBuildSetRoot`をこの計算の単一authorityとし、
  Status Source、Kernel、`build:compile`が同じpropertyを使う。
- P23-002Cがこのpathを明示的に選ぶ。
  A1/A2/Bが暗黙に推測した扱いにはしない。
- 新しいpublic configuration keyは追加しない。
- Directoryはexisting absolute non-symlink directoryであり、D146のentry cap、
  verified-handle、lock、unknown-entry rulesをそのまま適用する。
- `app.build`全体が存在しない場合、またはconfigured build directoryが存在しない場合、
  runtime status sourceはfilesystemへ書かずtyped `absent`を返す。
- Presentな`app.build`がarrayでない場合、required `container`／
  `application_build_id`が欠ける場合、値がinvalidな場合、またはexisting directoryが
  symlink／special／unresolvableの場合はconfiguration input failureである。
  Artifactの`malformed`とは混同せず、`ProjectionBuildSetValidationException`として
  commandのexact input diagnosticへ写像する。
- Valid locator取得後のlock／root／generationに対するpermission／I/O／unreadable
  failureはtyped operational exceptionのまま伝播する。
- Readerはmissing directory／lock／rootを作らない。
- `build:compile`は既存artifact writeの成功後に同じdirectoryが存在することを要求し、
  その時点でも存在しなければpublicationを開始せずfailureにする。

### Production input identity

- `ProjectionBuildSetStatusSource`はcommandが使う唯一のread seamであり、
  `withStatus(callable, selectedKey)`がcallback終了までshared lockを保持する。
- `ApplicationProjectionBuildSetStatusSource`だけがApplication configurationから
  Production locatorとconfigured build IDを解決する。
- Production readerの唯一のartifact inputは`ProjectionBuildSet v1` rootである。
- Production status sourceはconfigured `application_build_id`だけを外部期待値とする。
- Root descriptorのexact 3 `inputHash`と各projectionの`inputHash`／recomputed hashを
  shared lock内で照合する。
- Production commandはSource、Provider、Container、DB、HTTP Manifest、Operation
  Manifest、command manifestを読んで期待hashを再計算しない。
- Accepted component test用のcaller-injected exact 3 hashes seamは維持できる。
  Production commandはそのseamを使用しない。
- ReaderのProduction seamは
  `withPublishedStatus(expectedApplicationBuildId, callback, selectedKey)`である。
  Existing `withReadStatus(expectedApplicationBuildId, exact3, callback,
  selectedKey)`のbytes／exit semanticsはcomponent regressionとして維持する。
- `stale`はroot descriptorとprojectionのpublished input identity mismatchだけを意味する。
  Working Source drift detectionはclaimしない。
- Shared lockはcomplete-set validation、selected-row rendering、output writeまで保持する。

### Schedule producer

- Inputはalready-compiled `OperationRegistry`と`applicationBuildId`だけである。
- `schedule !== null`のmetadataだけを選ぶ。
- Rowはexact `{name,operationType,cron,timezone,strategy}`である。
- `name`／`cron`／`timezone`は`OperationScheduleMetadata`、`operationType`は
  `OperationMetadata::typeId`、`strategy`はdeclared strategy class-stringから取る。
- Shared strategy mapperは`Inline::class -> inline`、`Deferred::class -> deferred`、
  その他を`custom:<canonical-FQCN-without-leading-backslash>`へ変換する。
- Mapperはautoload、`class_exists`、reflection、factory、Containerを起動しない。
- Codecがclosed fields、bounds、cron／timezone grammar、sort、duplicate、input hashを
  最終検証する。
- Maintenance scheduler task、due state、Occurrence、actor、tenantをrowへ出さない。

### Target and runtime inventory

- `CommandProjectionTargetRegistry::all()`はexact 31 rowsになる。
- 新rowはcanonical `schedule:list`、aliases `['schedule:ls']`、description
  `List compiled application schedules.`、`hidden=false`、command projection上の
  originは`framework`である。
- `FrameworkCommandNames::all()`はexisting lazy Framework commandのexact 25を維持する。
- Empty configured Applicationのactivated canonical target inventoryはexact 31である。
  Aliasを別canonical rowにしない。
- KernelはBlackOps-owned `list`でSymfony default `list`を置換し、`ls`をaliasとして
  activateする。
- Kernelは`route:list`／`route:ls`、`schedule:list`／`schedule:ls`をactivateする。
- Reservation registry exact 13は変更しない。
- Explicit command instance／class-stringはcurrent Kernel composition boundaryで
  constructされ得る。
  New list commandはcomposition後にfactory、Container、Provider、Sourceを追加で
  resolveしない。

### Schedule command and exact output

- `schedule:list [--json]`とalias `schedule:ls [--json]`だけを受ける。
- Unknown option／unexpected argument／non-Boolean `--json`はexit `2`、stdout empty、
  stderr exact `Input error.\n`である。
- Human headersはexact `Name`, `Operation Type`, `Cron`, `Timezone`, `Strategy`である。
- 全cellを`OutputFormatter::escape`し、complete bytesを`OUTPUT_RAW`でwriteする。
- Decorated／undecorated Human bytesはidentical、ANSIなし、UTF-8/LF、final LF exact1である。
- JSONはclosed `{schemaVersion:1,schedules:[...]}`、RFC 8785 JCS＋final LF exact1、
  BOM／CRLF／追加field／`applicationProjectionStatus`なしである。
- RowsはCodecの`(name,operationType)` unsigned UTF-8 orderを使う。
- Success stderrはHuman／JSONともemptyである。
- availableだけexit `0`である。
- absent／malformed／unsupported／stale／build mismatchはexit `2`、stdout empty、
  stderr exact `Schedule projection is unavailable or invalid.\n`である。
- Permission／I/O／unreadableはexit `1`、stdout empty、stderr exact
  `Projection build-set read failed safely.\n`である。
- Invalid configured build identityはexit `2`、stdout empty、stderr exact
  `Schedule projection input is invalid.\n`である。
- Canonical／aliasのstdout、stderr、exitはidenticalである。

### Complete build publication

- `build:compile`は同じin-memory compilationからcommand、route、scheduleの3
  projectionsを生成する。
- Command producerはexplicit、collision-filtered discovered、Operation commandの
  accepted 3 inputsを使う。
- Route producerはalready-compiled HTTP Manifestを使う。
- Schedule producerはalready-compiled Operation Registryを使う。
- Existing container／Operation／HTTP／Frontend／command artifactのwriteがすべて
  成功した後、publisherをexact 3 projectionsで1回だけ呼ぶ。
- Publisherは3 generation filesを完成させ、rootを最後にcommitする。
- このatomicityはcomplete ProjectionBuildSetだけを対象とする。
  Runtime container／Operation／HTTP／Frontend／command artifactを含むApplication
  build全体のtransactionやrollbackはclaimしない。
- Root commit前のfailureでは`Build artifacts written.`を出さず、old rootまたは
  absent rootを維持するが、先に成功した既存artifact writeはrollbackしない。
- Producer failureとPublisher root commit前failureは既存のtyped exceptionを
  propagateし、success／cleanup diagnosticを生成しない。
- `ApplicationBuildCompileCommand`はconstructorからoptionalなexact-root
  `ProjectionBuildSetPublisher`を注入できるcomponent seamを持ち、default Production
  pathでは上記single rootから1 instanceだけを生成する。
- `published=true, cleanupComplete=true`はexisting success stdout
  `Build artifacts written.\n`、empty stderr、exit `0`である。
- `published=true, cleanupComplete=false`は同じsuccess stdout、stderr exact
  `Projection build-set cleanup is incomplete.\n`、exit `1`である。
  Published rootをrollbackしたとは主張しない。
- Command-only、route-only、schedule-only rootをpublishしない。

## Required Fixtures

### Schedule producer and wire

- zero schedule produces empty schedule records
- inline／deferred／custom rows and deterministic sort
- scheduled／unscheduled mixed registry
- duplicate／cap／cap+1／closed-row／cron／timezone／strategy negatives
- constructor／factory／Provider／Container／DB／clock／handler sentinel 0
- Route and Schedule use the same strategy mapper with byte-identical wires

### Production status source

- missing config and missing build directory return `absent` without write
- invalid present config and symlink／special／unresolvable directory raise the
  typed configuration-input validation failure
- `ApplicationBuildConfiguration`、Status Source、Kernel、build commandのrootはidentical
- unrelated existing build-directory entries are retained and count toward
  the exact 256／257 directory-entry boundary
- valid root uses configured build ID and published descriptor identities
- descriptor/projection input mismatch is `stale`
- working Source changes alone are not read or classified
- all-three non-selected mismatch invalidates command／route／schedule
- callback occurs under shared lock; callback throw unlocks/closes once

### Runtime activation

- target registry exact31, schedule canonical exact1, alias exact1
- `FrameworkCommandNames` exact25 and reservation exact13
- empty Application activates exact31 canonical target inventory
- list／route／schedule commands share the same status source instance
- `list`／`ls`, `route:list`／`route:ls`, `schedule:list`／`schedule:ls` parity
- no separate alias canonical rows
- missing root list recovery includes `build:compile`, route, schedule exact1 each
- route／schedule missing root exit2 and no runtime fallback
- explicit prebuilt/class-string construction count does not increase when list executes
- discovered lazy factory、Container、Provider、DB、Source sentinel 0 at list execution

### Build integration

- exact 3 producer inputs and one publisher call
- projection root is absent before first build and complete after success
- a fresh Kernel after build reads the same published build through all three
  command families
- existing artifact write failure calls publisher 0 times
- producer or publisher pre-commit failure leaves old/absent root and no success line
- already-written non-projection artifacts are not rolled back on pre-commit failure
- same build ID plus retained old root does not claim working Source drift detection
- existing component exact-three status seam retains its direct behavior
- cleanupComplete true/false exact stdout／stderr／exit
- second build switches one complete root to another with no mixed generation
- command target 30 to 31 intentional golden update
- route bytes remain unchanged for identical source input
- no partial publication entrypoint from A/B component

### Schedule command

- canonical／alias Human and JSON exact parity
- exact headers／final LF／JCS keys／no status field
- Symfony tag-like values remain literal for decorated true／false; no ANSI／throw
- empty schedule set success
- all five nonavailable statuses exit2／stdout empty／exact stderr
- operational failure exit1／stdout empty／exact stderr
- invalid input and build identity exact exit2 diagnostics
- no path、class、handler、tenant、credential、exception leakage

## Iteration Gate

実装中は変更したfocused testとFiles Allowedのformatだけを実行する。

```bash
docker compose run --rm app vendor/bin/phpunit \
  tests/Internal/Application/ApplicationHttpConfigurationTest.php \
  tests/Internal/Application/ApplicationProjectionBuildSetStatusSourceTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php \
  tests/Internal/Projection/BuildSet/ProjectionBuildSetPublisherTest.php \
  tests/Internal/Projection/BuildSet/ProjectionExecutionStrategyWireTest.php \
  tests/Internal/Projection/Command \
  tests/Internal/Projection/Route \
  tests/Internal/Projection/Schedule
docker compose run --rm app mago format --check \
  src/Internal/Application/ApplicationBuildConfiguration.php \
  src/Internal/Application/ApplicationConsoleKernel.php \
  src/Internal/Application/ApplicationConsoleCommandFactory.php \
  src/Internal/Application/ApplicationProjectionBuildSetStatusSource.php \
  src/Internal/Console/ApplicationBuildCompileCommand.php \
  src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php \
  src/Internal/Projection/BuildSet/ProjectionBuildSetStatusSource.php \
  src/Internal/Projection/BuildSet/ProjectionExecutionStrategyWire.php \
  src/Internal/Projection/Command/CommandProjectionListCommand.php \
  src/Internal/Projection/Command/CommandProjectionTargetRegistry.php \
  src/Internal/Projection/Route \
  src/Internal/Projection/Schedule \
  tests/Internal/Application/ApplicationHttpConfigurationTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php \
  tests/Internal/Application/ApplicationProjectionBuildSetStatusSourceTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Internal/Projection
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

反復中にfull PHPUnit、Deptrac、Website build、Browserを繰り返さない。

## Final Gate

Source／Testのexact ordered freezeを記録してから1回だけ実行する。

Sourceが変われば旧evidenceをHistoricalとし、新freezeで最初からやり直す。

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

Public Documentation Sourceを変更しないため、Website full test／build／Browserは
このTaskで実行しない。

未実行項目はReportへ理由を記録する。

## Release Documentation Impact

- Release Authority tupleはStable `1.2.0`／Historical `1.1.0`のままである。
- Capability IDsは未Release P23-002A/B/C projection command sliceだけである。
- Public Source／route inventoryは変更しない。
- Stable `1.1.0`／`1.2.0` occurrence分類とhistorical allowlistを変更しない。
- `release:check:source`で1.3 capabilityのGuide／Search／LLM漏出を拒否する。
- CI、Documentation delivery、Production deployはこのTaskのscope外である。

## Acceptance Criteria

- [x] Sol xHigh preflight Green `P1=0/P2=0/P3=0`
- [x] Luna Max implementation within corrected exact 41-file Files Allowed
- [x] Production locator／root-only identity／absent recovery implemented and focused Green
- [x] Schedule producer／strategy mapper／exact wire implemented and focused Green
- [x] Target exact31／Framework exact25／reservation exact13 implemented and focused Green
- [x] Complete build publication and cleanup result implemented and focused Green
- [x] Kernel atomic activation and no partial fallback implemented and focused Green
- [x] Current exact32 Source/Test freeze and Final Gate recorded
  (pre-correction `0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5`
  and corrected `be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`
  freezes are Historical after the fixture correction; current freeze is
  `15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`)
- [x] One Final Gate Green after current Source freeze
- [x] Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`
- [x] Report／STATE／TODO／Spec README／D145／D146／Specs105/106 synchronized
- [x] Orchestrator Acceptance

## Remaining Steps

P23-002C内の残作業はない。

Higher GoalではP23-002D/E、P23-003以降、P23-006 release documentation、
same-SHA CI／releaseが残る。

## Suggested Next Action

P23-002Dの`diagnostics:check`／aggregate-only `queue:status` preflightへ進む。
P23-002E以降はSpecification 106の順序を維持する。

Commit、stage、push、CI、deploy、publication、tag、releaseは未承認／未実行である。
