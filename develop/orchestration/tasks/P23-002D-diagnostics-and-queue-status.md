# P23-002D: Diagnostics Check and Aggregate Queue Status

Status: Accepted — Diagnostics Check and Aggregate Queue Status

Started At: 2026-08-27T23:47:45+09:00

Updated At: 2026-08-28T02:09:28+09:00

Completed At: 2026-08-28T02:09:28+09:00

## Goal

BlackOps `1.3`の運用者向けCLIとして、`diagnostics:check`と
aggregate-only `queue:status`を追加する。

`diagnostics:check`は、公開済みのreadiness契約と、現在選択されている
ProjectionBuildSetの状態を、固定件数かつ秘密情報を含まない一つのReportへ
まとめる。

`queue:status`は、Deferred Operationの待機・実行・監督・dead-letter状態を
installation単位の件数と最古ageだけで示す。Operation row、tenant、payload、
reason、identifierを列挙しない。

## Release Boundary

- Release Authority tuple: Stable `1.2.0` current / BlackOps `1.3` unreleased proposal
- Proposed Capability IDs: `cli.diagnostics.check.v1`、`cli.queue.status.v1`
- P23-002A1/A2/B/CはAccepted。P23-002Cのexact31 activationが前提である
- 本TaskはStable `1.2.0`の公開Guide、Website、生成Artifact、version、tag、
  release authorityを変更しない
- `1.3`のRelease、public Documentation、same-SHA CI、publicationはP23-006が所有する

## Fixed Decisions

### 1. Readiness dependency authority

`ApplicationBuilder::withOperationalHealthQuery(?OperationalHealthQuery)`を
BlackOps `1.3`の明示的な登録口とする。

- 登録されたobjectは`ApplicationConfigurationSnapshot`へ保持する
- Container、Service Provider、Database、Operation Runtimeから暗黙に探索しない
- 未登録は`readiness.query_unavailable=fail`であり、例外やclass名を出さない
- 複数回の呼出しはlast-call-wins、`null`は登録をclearする
- 既存Builder invocationは引数default `null`によりbehavior／bytesを変えない
- `list`、`help`、不正入力ではQueryを呼ばない
- Queryはvalid `diagnostics:check`ごとにexactly once呼ぶ
- ProjectionBuildSetのshared lockを解放した後にだけQueryを呼ぶ

既存の`OperationalHealthQuery`、`OperationalHealthKind::Readiness`、固定6 codeを
再利用する。`OperationDiagnosticsQuery`はOperation ID、row、Journal、Outcomeを
読む別Capabilityであり、再利用しない。

この公開依存は意図したArchitecture changeである。`deptrac.yaml`のApplicationと
InternalApplicationへ`Observability`を追加し、それ以外のlayer edgeは変更しない。

### 2. Diagnostics normalization

Query Reportを直接renderしない。次の条件をすべて満たす場合だけ、固定順へ
正規化する。

- kindは`readiness`
- codeは次のexact sixで、重複、欠落、追加がない
  1. `compiled_artifact`
  2. `runtime_configuration`
  3. `database`
  4. `migration_compatibility`
  5. `storage_key_provider`
  6. `runtime_services`
- 各statusはtyped `pass|fail`
- Report全体statusは6 checkのreductionと一致する
- input順とinput `checkedAt`は出力へ使わない

Normalizing前に`count(checks) === 6`を確認し、不一致なら要素を走査しない。
`compiled_artifact`はApplicationが登録したreadiness callbackの結果であり、
Framework-owned ProjectionBuildSet publication statusとは別row／別責務である。

Query throwは`query_failed`、shape不正は`query_invalid`へ縮約する。
この二状態と`query_unavailable`では、readiness rowはその1件だけとする。

Projection familyはexactly one rowで、codeは
`available|absent|malformed|unsupported|stale|build_mismatch|
configuration_invalid|read_failed`である。`available`だけがpassで、それ以外は
failである。`ProjectionBuildSetValidationException`は
`configuration_invalid`、`ProjectionBuildSetFilesystemException`は
`read_failed`へ縮約する。Projection Status callbackはexactly onceで、callback
終了後までreadinessを呼ばない。

最大row数は7である。全rowがpassの場合だけoverall `pass`／exit `0`とし、
一つでもfailならoverall `fail`／exit `1`とする。

### 3. Diagnostics wire

Inputは`diagnostics:check [--json]`だけとする。alias、positional argument、
repeatable option、tenant、Operation ID、filterはない。

Humanは次の固定順`key=value` UTF-8/LF wireである。

```text
schemaVersion=1
status=pass
checkedAt=2026-08-27T15:00:00.000000Z
projectionBuildSet=available
readiness.compiled_artifact=pass
readiness.runtime_configuration=pass
readiness.database=pass
readiness.migration_compatibility=pass
readiness.storage_key_provider=pass
readiness.runtime_services=pass
```

縮約時は6 readiness行を、exactly oneの
`readiness.query_unavailable|query_failed|query_invalid=fail`へ置き換える。

JSONはRFC 8785 JCSと一つのLFであり、closed shapeは次のとおりである。

```json
{"checkedAt":"2026-08-27T15:00:00.000000Z","checks":[{"code":"available","family":"projection_build_set","status":"pass"},{"code":"compiled_artifact","family":"readiness","status":"pass"},{"code":"runtime_configuration","family":"readiness","status":"pass"},{"code":"database","family":"readiness","status":"pass"},{"code":"migration_compatibility","family":"readiness","status":"pass"},{"code":"storage_key_provider","family":"readiness","status":"pass"},{"code":"runtime_services","family":"readiness","status":"pass"}],"schemaVersion":1,"status":"pass"}
```

Commandに注入したclockはvalid invocationの開始時にexactly once読み、UTC six
fractional digitsへ正規化する。Query自身の時刻は採用しない。

Input不正はstdout空、stderr exact `diagnostics.input_invalid\n`、exit `2`で
ある。Valid invocationはexit `0|1`、stderr空、HumanまたはJSON Reportをstdoutへ
`OUTPUT_RAW`で一度だけ書く。ANSI、BOM、CRLF、追加LFは許可しない。

Kernelは両P23-002D Commandをconcrete commandとして直接登録する。
`LazyFrameworkCommand`でwrapしない。各Command constructorはQuery／DBを実行せず、
`run()`が`Symfony Console ExceptionInterface`をcatchして上記safe input errorへ
縮約する。QueueのDB source factoryはvalid `execute()`内でだけ呼ぶ。

### 4. Queue authority and scope

`queue:status`はlocal host／ContainerのBlackOps CLIを実行できる運用者だけを
authorityとする。Application principalやtenant selectorを新設しない。

- scopeは固定`installation`
- current installationの`operations` table全体を集計する
- tenant、actor、Operation ID、Operation type、payload、reason、attempt、lease ownerを
  select、materialize、emitしない
- public HTTP route、自動readiness route、pagination、row listing、aliasは追加しない
- tenant/user-facing surfaceはOut of Scopeで、別のauthorization decisionを要する
- `outbox_records`はP23-002E所有なので常に除外する

### 5. Queue buckets and ages

Commandは注入clockをexactly once読み、その`observedAt`を全比較とageに使う。
次のcountは相互排他的である。

| Field | Predicate |
| --- | --- |
| `acceptedReady` | `state=accepted` and `available_at <= observedAt` |
| `acceptedDelayed` | `state=accepted` and `available_at > observedAt` |
| `retryReady` | `state=retry_scheduled` and `available_at <= observedAt` |
| `retryDelayed` | `state=retry_scheduled` and `available_at > observedAt` |
| `runningActive` | `state=running` and `lease_expires_at > observedAt` |
| `runningExpired` | `state=running` and `lease_expires_at <= observedAt` |
| `supervising` | `state=supervising` |
| `deadLettered` | `state=dead_lettered` |

`completed|rejected|failed`は除外する。`running`でleaseがnullのrowは
integrity failureであり、どちらのbucketにも黙って落とさない。

Ageは次の4 fieldだけである。

| Field | Source |
| --- | --- |
| `oldestReady` | readyとなる`available_at`のminimum |
| `oldestRunningExpired` | expiredとなる`lease_expires_at`のminimum |
| `oldestSupervising` | supervising rowの`updated_at`のminimum |
| `oldestDeadLettered` | dead-lettered rowの`updated_at`のminimum |

Ageは`floor(observedAt - source)`の非負秒で、対応countが0のときだけnullである。
count／ageはJSON-safe integer `0..9007199254740991`に限定する。future timestamp、
cap超過、欠落、余分なcolumn、非decimal、count/null不整合はintegrity failureである。

Sourceは一つの固定aggregate queryから一つのrowだけを読む。`fetchAll`、row loop、
payload decode、tenant predicate／columnは使わない。missing schema/tableはemptyではなく
storage unavailableである。現行indexのまま正確性を実装し、O(N) aggregate scanを
このTaskでは受容する。新index、counter table、migration、性能SLAは追加しない。

内部query rowは、8 count、4 filtered minimum timestamp、内部検証専用の
`runningNullLeaseCount`からなるexact 13 aliasである。余分／欠落aliasを拒否し、
`runningNullLeaseCount`は常に0を要求する。この内部fieldはpublic wireへ出さない。
`QueueStatusSource::read(DateTimeImmutable $observedAt)`はvalidated
`QueueStatusSnapshot`だけを返し、storage failureとintegrity failureはtyped exceptionで
区別する。

### 6. Queue wire

Inputは`queue:status [--json]`だけである。

Humanは次の固定順である。

```text
schemaVersion=1
scope=installation
observedAt=2026-08-27T15:00:00.000000Z
counts.acceptedDelayed=0
counts.acceptedReady=1
counts.deadLettered=0
counts.retryDelayed=0
counts.retryReady=0
counts.runningActive=0
counts.runningExpired=0
counts.supervising=0
agesSeconds.oldestDeadLettered=null
agesSeconds.oldestReady=0
agesSeconds.oldestRunningExpired=null
agesSeconds.oldestSupervising=null
```

JSONは次のclosed JCS＋LFである。

```json
{"agesSeconds":{"oldestDeadLettered":null,"oldestReady":0,"oldestRunningExpired":null,"oldestSupervising":null},"counts":{"acceptedDelayed":0,"acceptedReady":1,"deadLettered":0,"retryDelayed":0,"retryReady":0,"runningActive":0,"runningExpired":0,"supervising":0},"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}
```

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| success | exact Human or JSON | empty | `0` |
| input invalid | empty | `queue.input_invalid\n` | `2` |
| database/schema configuration invalid | empty | `queue.configuration_invalid\n` | `2` |
| connection/query/missing table | empty | `queue.storage_unavailable\n` | `1` |
| aggregate shape/value invalid | empty | `queue.integrity_failed\n` | `1` |

Failureはexception message、SQL、DSN、path、schema名、credential、tenant、row dataを
含まない。Human／JSONは`OUTPUT_RAW`、ANSIなし、一つのfinal LFである。

### 7. Runtime inventory

Target metadataへ次を追加する。

- `diagnostics:check`: description `Check application readiness and compiled artifacts.`、aliasなし、hidden=false、origin=framework
- `queue:status`: description `Show aggregate deferred-operation queue status.`、aliasなし、hidden=false、origin=framework

P23-002Cのexact31 target／exact25 Framework namesは、P23-002D完了後に
exact33／exact27となる。reservation registryは既に両名を含むためexact13のままで
ある。両CommandはKernelへ同時にactivateし、片方だけのcurrent completionは認めない。

## Parallel Worker Ownership

Sol Max preflightがGreenになるまでProduction Sourceを変更しない。

Green後は、Luna Max workerを次の順で用いる。

1. Diagnostics component worker: Diagnosticsのnew Source／direct testsだけを所有する
2. Queue component worker: Queue／PostgreSQLのnew Source／direct testsだけを所有する
3. Integration worker: 1と2の完了後に既存Application／Registry／integration testsを所有する

1と2は並列実行できる。共有existing file、Task、Report、STATE、TODO、Specは
同時編集しない。WorkerはReview前にcommit、stage、pushしない。

## In Scope

- 上記two commands、bounded values、renderers、typed failures
- `ApplicationBuilder` readiness registrationとSnapshot保持
- PostgreSQL one-row aggregate source
- Kernel activation、target exact33、Framework names exact27、reservation exact13
- safe input／error縮約、no-leakage、lock-separation fixture
- focused iteration gates、one exact freeze、one complete Final Gate、one fresh Sol Max review

## Out of Scope

- Operation／Queue row listing、pagination、search、tenant filter、user authorization API
- `worker:list`／`worker:ls`、Job registry、`queue:list`
- Outbox aggregation、`about`、`outbox:status`、dead-letter listing
- public HTTP route、automatic health/readiness route
- queue schema migration、index、counter table、query performance SLA
- Stable `1.2.0` Guide／Website／generated artifacts／release notes
- Commit、Stage、Push、PR、remote CI、Deploy、Publication、Tag、Release

## Files Allowed

### Production Source

1. `src/Application/ApplicationBuilder.php`
2. `src/Internal/Application/ApplicationConfigurationSnapshot.php`
3. `src/Internal/Application/ApplicationConsoleCommandFactory.php`
4. `src/Internal/Application/ApplicationConsoleKernel.php`
5. `src/Internal/Console/DiagnosticsCheckCommand.php`
6. `src/Internal/Console/QueueStatusCommand.php`
7. `src/Internal/Diagnostics/SystemDiagnosticsCheck.php`
8. `src/Internal/Diagnostics/SystemDiagnosticsReport.php`
9. `src/Internal/Diagnostics/SystemDiagnosticsNormalizer.php`
10. `src/Internal/Diagnostics/SystemDiagnosticsRenderer.php`
11. `src/Core/Queue/QueueStatusFailure.php`
12. `src/Internal/Queue/QueueStatusRenderer.php`
13. `src/Core/Queue/QueueStatusSnapshot.php`
14. `src/Core/Queue/QueueStatusSource.php`
15. `src/Core/Queue/QueueStatusSourceException.php`
16. `src/Transport/PostgreSql/PostgreSqlQueueStatusSource.php`
17. `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`
18. `deptrac.yaml`

### Tests

19. `tests/Internal/Application/ApplicationBuilderTest.php`
20. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
21. `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php`
22. `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
23. `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
24. `tests/Internal/Console/DiagnosticsCheckCommandTest.php`
25. `tests/Internal/Console/QueueStatusCommandTest.php`
26. `tests/Internal/Diagnostics/SystemDiagnosticsNormalizerTest.php`
27. `tests/Internal/Diagnostics/SystemDiagnosticsRendererTest.php`
28. `tests/Core/Queue/QueueStatusSnapshotTest.php`
29. `tests/Internal/Queue/QueueStatusRendererTest.php`
30. `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
31. `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
32. `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
33. `tests/Transport/PostgreSql/PostgreSqlQueueStatusSourceTest.php`
34. `tests/Application/ApplicationTest.php`

### Management／Normative Documentation

35. `develop/orchestration/tasks/P23-002D-diagnostics-and-queue-status.md`
36. `develop/orchestration/reports/P23-002D-diagnostics-and-queue-status.md`
37. `develop/STATE.md`
38. `develop/TODO.md`
39. `develop/spec/README.md`
40. `develop/spec/105-blackops-1-3-capability-contract.md`
41. `develop/spec/106-phase-23-delivery-plan.md`
42. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`

上記以外を変更しない。追加fileが必要なら実装を広げず、ReportへBlockerとして返す。

## Required Fixtures

### Diagnostics

- artifact exact eight codeとpass/fail mapping
- missing config/root=`absent`、present invalid config、I/O failure
- exact-six readiness all-pass、各code単独fail
- unregistered、throw、wrong kind、unknown、duplicate、missing、extra、aggregate mismatch
- artifact callback中はreadiness未実行、callback後exactly once
- fixed clock exactly once、input Report time無視
- Human／JSON exact bytes、decorated/undecorated parity、one LF、no ANSI
- unknown option、positional、non-Boolean `--json`を依存未解決でsafe input errorへ縮約
- secret、credential、DSN、SQL、path、class、tenant、row、Operation ID、payload、build ID/hash canary absent

### Queue

- empty、each bucket、mixed、ready/expired equality
- retry delayed/ready、payload-purged dead-letter、excluded terminal states、outbox-only zero
- multiple tenant canary rowsをinstallation aggregateへ含めるがtenant値をselect／emitしない
- one clock call、UTC microseconds、future age、count/age cap/cap+1、nullability mismatch
- running null lease、extra/missing source column、nondecimal result
- configuration、connection、query、missing table failure
- Human／JSON exact bytes、stdout/stderr separation、one LF、no ANSI
- invalid inputでdatabase factory zero calls
- fixed SQL one-row fetch、no row/payload/tenant/identifier column、no Outbox access

### Integration and regression

- target exact33、Framework names exact27、reservation exact13
- command projection/list/build goldens include exactly two new rows
- canonical command activation、no aliases、application canonical/alias collision fail-closed
- `list`／`help`でhealth query／DB／container／provider／handler zero resolution
- no public HTTP route、no Job registry、no Stable Guide／Artifact occurrence
- public `ApplicationBuilder` fluent method inventory includes
  `withOperationalHealthQuery`

## Iteration Gate

各component workerは、自分のnew Source／Testだけをformatし、direct PHPUnitを実行する。
Integration workerは変更したexisting testsと全P23-002D focused testsを実行する。

Source freeze前にだけ、許可された全PHP fileへ次を実行する。

```bash
docker compose run --rm app mago format --check <allowed-php-files>
docker compose run --rm app vendor/bin/phpunit <focused-test-files>
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Final Gate

Production Source/Testをexact ordered manifestとaggregateへfreezeした後に、一度だけ
complete Final Gateを実行する。Source変更後は先行evidenceを再利用しない。

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered
bash tests/Consumer/version-baseline.sh
pnpm --dir docs/website run release:check:source
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --cached --name-only
```

Final Gate後はexact freezeを変えず、fresh Sol Max implementation reviewを一度だけ
実施する。Management-only status syncはProduct Gateを再実行しない。

## Acceptance Criteria

- [x] Sol Max preflight Green `P1=0/P2=0/P3=0`
- [x] DiagnosticsとQueueのfixed contractがSpec／Decision／Taskで一致する
- [x] Luna Max parallel component implementationとintegrationがFiles Allowed内で完了する
- [x] `diagnostics:check` exact wire／normalization／lock separation／no-leakage fixturesがGreen
- [x] `queue:status` exact aggregate／scope／wire／failure／no-leakage fixturesがGreen
- [x] exact33／exact27／exact13とprojection/list/build/collision regressionがGreen
- [x] no public route、no Job registry、Stable `1.2.0`／unreleased `1.3`境界がGreen
- [x] one complete Final Gateがfrozen candidateでGreen
- [x] fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`
- [x] Report／STATE／TODO／Spec indexをcurrent evidenceへ同期する

## Current Checkpoint

Diagnostics／Queue componentとintegrationは完了し、initial Orchestrator focused gateは
`129 tests / 1,419 assertions`でGreenだった。Source／Test exact33をaggregate
`b55fa437777074244908480a4c211d2ba0f48d8412b18d6f321abffafc0e3641`
へfreezeしてcomplete Final Gateを開始したが、full PHPUnitは`2512 tests`中1件、
`tests/Application/ApplicationTest.php`の公開Builder method inventoryが
`withOperationalHealthQuery`を含まないため失敗した。Production実装のfailureではなく
Files Allowed／regression fixtureの漏れである。このtestをexact34へ追加し、旧freezeと
途中Gate evidenceはHistoricalとした。Luna Max correctionとOrchestrator direct testは
`6 tests / 43 assertions` Green、corrected exact34 focused gateは
`135 tests / 1,462 assertions` Greenである。current aggregateは
`72721ec6a380981fd210960a65acbb02591725f6d538a61571188b0ed354114d`。
このexact34でcomplete Final Gateを最初から再実行し、format、Composer strict、
lint／baseline、analyze errors `0`、PHPUnit `2512 tests / 11,351 assertions`、
Deptrac `904` dependencies／violations `0`、version baseline、Website
release-source、management-ID、diff、staged0、ending aggregate一致がGreenとなった。
Fresh Sol Max closure review matched branch／HEAD／main、staged `0`、and exact34
aggregate. It confirmed the multi-tenant fixture closes the prior counterexample,
Production remains unchanged, and returned Green `P1=0/P2=0/P3=0`.
root Composeには`website` serviceがなく、CIとAccepted P23-002C evidenceが使う
canonical `pnpm --dir docs/website run release:check:source`へGate commandを訂正した。
Fresh Sol Max implementation review found `P1=0/P2=1/P3=0`: Productionはage
MAX+1を正しく拒否するが、Required Fixtureはcount cap+1だけを直接固定し、age
cap+1 regressionを固定していなかった。Review-before exact34とFinal Gateは
Historicalとした。Luna Maxは既存allowed
`tests/Core/Queue/QueueStatusSnapshotTest.php`だけへdirect rejection fixtureを追加し、
Orchestrator focused gateは`136 tests / 1,463 assertions` Greenとなった。Current
exact34 aggregateは
`db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`である。
この同一freezeでcomplete Final Gateを実行し、format、Composer strict、lint／baseline、
analyze errors `0`、PHPUnit `2,513 tests / 11,352 assertions`、Deptrac `904`
dependencies／violations `0`、version baseline、Website release-source、
management-ID、diff、staged0、ending aggregate一致がGreenとなった。
Fresh Sol Max closure review matched the same freeze and returned
`P1=0/P2=1/P3=0`. Production SQL is finding-free, but the required real
PostgreSQL fixture seeds only one tenant and therefore does not directly prove
installation-wide aggregation across multiple tenants. This freeze and Gate are
review-before Historical evidence. Luna Max changed only
`tests/Transport/PostgreSql/PostgreSqlQueueStatusSourceTest.php`, seeding two
tenant canaries whose rows contribute to totals and age. Direct test is
`8 tests / 148 assertions`; Orchestrator focused gate is
`136 tests / 1,465 assertions`. Current exact34 aggregate is
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`.
The complete Final Gate on this same freeze is Green: format、Composer strict、
lint／baseline、analyze errors `0`、PHPUnit `2,513 tests / 11,354 assertions`、
Deptrac `904` dependencies／violations `0`、version baseline、Website
release-source、management-ID、diff、staged0、ending aggregate all pass.

## Remaining Steps

P23-002D内の残り工程はない。上位BlackOps `1.3` GoalにはP23-002E、P23-003
Audit implementation、P23-004 Worker/Classic track、P23-005 Documentation、
P23-006 release gateが残る。Commit、stage、push、CI、deploy、publication、tag、
releaseは未実行である。

## Next Action

P23-002E `about`／aggregate-only `outbox:status`のresearchとTask Packet
preflightを開始する。Production実装はPacketとSol Max preflight Green後にLuna Maxへ
委譲する。
