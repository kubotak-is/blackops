# P23-002E: About and Aggregate Outbox Status

Status: Accepted — About and Aggregate Outbox Status

Started At: 2026-08-28T02:20:34+09:00

Updated At: 2026-08-28T04:22:03+09:00

## Goal

BlackOps `1.3`の運用者向けCLIとして、`about`とaggregate-only
`outbox:status`を追加する。

`about`は、実際にComposerで導入されたFramework version、現在のPHP process、
公開済みProjectionBuildSetの状態を、固定項目だけで表示する。HTTP mode、
FrankenPHP version、Worker registry、Extension、OS、依存Package一覧は推測しない。

`outbox:status`は、Transactional Outboxの配信待ち、再試行、lease、dead-letterを
installation単位の件数と最古ageで示す。Outbox row、tenant、payload、context、
identifier、failure fingerprintを列挙しない。

## Release Boundary

- Release Authority tuple: Stable `1.2.0` current / BlackOps `1.3` unreleased proposal
- Proposed Capability IDs: `cli.about.v1`、`cli.outbox.status.v1`
- P23-002DはAcceptedであり、exact33 target／exact27 Framework namesが前提である
- 本TaskはStable `1.2.0`の公開Guide、Website、生成Artifact、version、tag、
  release authorityを変更しない
- `about`が表示するversionはComposer installed metadataを読む。Sourceへfuture
  `1.3.0`をhard-codeしない
- `1.3`のRelease、public Documentation、same-SHA CI、publicationはP23-006が所有する

### Documentation impact

- Public Source／route inventory: 変更なし。未公開CLIのProduction／internal contractだけを扱う
- 旧Version occurrence: 変更なし。Stable `1.2.0`とhistorical `1.1.0`の分類を維持する
- Source／Search／LLM artifact: 本Taskでは公開本文を変更しないため生成対象外。P23-006で
  positive／negative fixtureと全Artifactを検証する
- same-SHA CI／Documentation delivery／Production deploy: 本Taskでは実行しない

## Fixed Decisions

### 1. `about` authority and scope

Inputは`about [--json]`だけである。alias、positional argument、repeatable option、
tenant、filterはない。

Framework version authorityは
`Composer\InstalledVersions::getPrettyVersion('blackops/framework')`とする。
このAPIを直接利用するため、root `composer.json`はvirtual package
`composer-runtime-api:^2.0`を直接要求し、`composer.lock`を同期する。transitive
dependencyの要求へ依存しない。Package名は固定`blackops/framework`である。

Runtime metadataは現在Commandを実行しているprocessの`PHP_VERSION`と`PHP_SAPI`
だけである。scopeは固定`current_process`とする。HTTP deployment profile、
FrankenPHP／Caddy version、Classic／Worker support、Deferred process、Extension、OS、
hostname、installed Extension、dependency inventory、path、environment variableを
検出または表示しない。Process capability registryがないため、capability listも
作らない。

Build metadataは既存`ProjectionBuildSetStatusSource::withStatus()`をexactly once
呼び、statusだけを次のexact eightへ縮約する。

`available|absent|malformed|unsupported|stale|build_mismatch|
configuration_invalid|read_failed`

Application build ID、build-set hash、input hash、pathは表示しない。
`ProjectionBuildSetValidationException`は`configuration_invalid`、
`ProjectionBuildSetFilesystemException`は`read_failed`へ縮約する。
非`available` statusはhealth failureではなくinstalled stateなので、valid reportは
exit `0`である。Readiness判定は`diagnostics:check`の責務であり、`about`へ統合しない。

Commandはconcrete objectとしてKernelへ直接登録する。constructor、`list`、`help`、
invalid inputではComposer metadata／Projection sourceを呼ばない。Valid invocationは
Composer metadata sourceとProjection sourceをそれぞれexactly once呼び、DB、
Container、Provider、Handler、Network、Environmentを解決しない。

### 2. `about` bounded values and wire

Closed objectは次のfieldだけである。

| Field | Exact contract |
| --- | --- |
| `schemaVersion` | integer `1` |
| `package.name` | fixed `blackops/framework` |
| `package.version` | installed pretty version; ASCII 1..128 bytes; `^[A-Za-z0-9][A-Za-z0-9._+@/-]{0,127}$` |
| `runtime.scope` | fixed `current_process` |
| `runtime.phpVersion` | ASCII 1..64 bytes; `^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$` |
| `runtime.sapi` | lowercase ASCII 1..32 bytes; `^[a-z0-9][a-z0-9_-]{0,31}$` |
| `projectionBuildSet` | exact eight status |

Humanは次の固定順`key=value` UTF-8/LF wireである。

```text
schemaVersion=1
package.name=blackops/framework
package.version=1.2.0@dev
runtime.scope=current_process
runtime.phpVersion=8.5.0
runtime.sapi=cli
projectionBuildSet=available
```

VersionとPHP値は例であり、Productionではauthorityから取得したvalidated valueを
そのまま使う。JSONはclosed RFC 8785 JCS objectと一つのLFである。

```json
{"package":{"name":"blackops/framework","version":"1.2.0@dev"},"projectionBuildSet":"available","runtime":{"phpVersion":"8.5.0","sapi":"cli","scope":"current_process"},"schemaVersion":1}
```

Human／JSONは`OUTPUT_RAW`で一度だけstdoutへ書き、ANSI、BOM、CRLF、追加LFを
含めない。結果precedenceは次のとおりである。

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| valid report | exact Human or JSON | empty | `0` |
| invalid input | empty | `about.input_invalid\n` | `2` |
| missing／invalid／throwing metadata or unexpected report shape | empty | `about.metadata_unavailable\n` | `1` |

Raw exception、Composer reference、install path、dependency、environment、build ID／hashを
streamへコピーしない。Partial outputは禁止する。

### 3. `outbox:status` authority and scope

Inputは`outbox:status [--json]`だけである。alias、positional argument、tenant、
filter、paginationはない。local host／ContainerのBlackOps CLIを実行できる運用者を
authorityとし、scopeは固定`installation`とする。

Sourceはcurrent installationのPostgreSQL `outbox_records`だけを一つの固定aggregate
queryで読む。`operations`、Operation `dead_letters`、Outbox retry auditは読まない。
tenant、actor、record／Operation ID、Operation type、payload、context、fingerprint、
relay ID、attempt、connection nameをselect、materialize、emitしない。public HTTP route、
tenant selector、row listing、paginationを追加しない。

Commandはinput validation後に注入clockをexactly once読み、その`observedAt`をsource
factory／readの一回だけの呼出しへ渡す。Factoryはvalid execute内だけでDatabase
configuration、Framework connection、schemaを解決する。`ApplicationOutboxRuntime`は
Operation runtime、protection、sender、PCNTLを構成するため再利用しない。

### 4. Outbox buckets, ages, and integrity

Due timeは`COALESCE(next_attempt_at, available_at)`であり、現行claim predicateと一致する。
Equality with `observedAt`はreadyまたはexpiredである。

| Field | Predicate |
| --- | --- |
| `pendingReady` | `state=pending` and due `<= observedAt` |
| `pendingDelayed` | `state=pending` and due `> observedAt` |
| `retryReady` | `state=retry_scheduled` and due `<= observedAt` |
| `retryDelayed` | `state=retry_scheduled` and due `> observedAt` |
| `leasedActive` | `state=leased` and `lease_expires_at > observedAt` |
| `leasedExpired` | `state=leased` and `lease_expires_at <= observedAt` |
| `deadLettered` | `state=dead_lettered` |

`sent`はterminal successとして除外する。Outbox dead-letterは`outbox_records`の
`dead_lettered`だけであり、Operation dead-letterと統合しない。

Ageは次のexact threeだけである。

| Field | Source |
| --- | --- |
| `oldestReady` | ready pending／retry dueのminimum |
| `oldestLeasedExpired` | expired `lease_expires_at`のminimum |
| `oldestDeadLettered` | `dead_lettered_at`のminimum |

Ageは`floor(observedAt - source)`の非負秒で、対応countが0のときだけnullである。
count／ageはJSON-safe integer `0..9007199254740991`に限定する。

Aggregate rowはpublic seven counts、three minimum timestamps、次のvalidation-only
three anomaly countsからなるexact 13 aliasesである。

- `retryNullNextAttemptCount`
- `leasedNullLeaseCount`
- `deadLetterInvalidTimestampCount` (`dead_lettered_at IS NULL OR > observedAt`)

Anomaly countはすべて0でなければならない。Missing／extra alias、nondecimal、cap
overflow、future age、count/null mismatch、anomalyは`integrity_failed`である。
Sourceは`fetchAssociative`で一つのrowだけを読み、`fetchAll`、row loop、payload decodeを
使わない。Missing schema/tableはemptyではなく`storage_unavailable`である。現行indexの
まま正確性を実装し、O(N) aggregate scanを受容する。Migration、index、counter、
performance SLAは追加しない。

Three minimum aliasesのDBAL valueは`null`またはexactly 27-byte ASCIIの
`YYYY-MM-DDTHH:MM:SS.ffffffZ`だけである。SQLはfiltered `MIN`を
`AT TIME ZONE 'UTC'`と固定formatでこのtextへ変換し、session `TimeZone`／`DateStyle`へ
依存しない。Sourceはstring以外、offset／timezone abbreviation、fraction欠落／桁違い、
whitespace、invalid calendar valueを拒否し、`Y-m-d\TH:i:s.u\Z`でparseした値が入力と
exact round-tripすることを確認してからageを計算する。

### 5. `outbox:status` wire

Humanは次の固定順である。

```text
schemaVersion=1
scope=installation
observedAt=2026-08-27T15:00:00.000000Z
counts.deadLettered=0
counts.leasedActive=0
counts.leasedExpired=0
counts.pendingDelayed=0
counts.pendingReady=1
counts.retryDelayed=0
counts.retryReady=0
agesSeconds.oldestDeadLettered=null
agesSeconds.oldestLeasedExpired=null
agesSeconds.oldestReady=0
```

JSONはclosed JCS＋LFである。

```json
{"agesSeconds":{"oldestDeadLettered":null,"oldestLeasedExpired":null,"oldestReady":0},"counts":{"deadLettered":0,"leasedActive":0,"leasedExpired":0,"pendingDelayed":0,"pendingReady":1,"retryDelayed":0,"retryReady":0},"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}
```

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| success | exact Human or JSON | empty | `0` |
| input invalid | empty | `outbox.input_invalid\n` | `2` |
| database/schema configuration invalid | empty | `outbox.configuration_invalid\n` | `2` |
| connection/query/missing table | empty | `outbox.storage_unavailable\n` | `1` |
| aggregate shape/value invalid | empty | `outbox.integrity_failed\n` | `1` |

Successは`OUTPUT_RAW`、ANSIなし、一つのfinal LFである。Failureはstdoutを空にし、
raw exception、SQL、DSN、path、schema、credential、tenant、row valueを出さない。

### 6. Runtime inventory and atomic activation

Target metadataへ次のexact framework rowsを追加する。

- `about`: `Show installed BlackOps package, runtime, and projection status.`、aliasなし、hidden=false
- `outbox:status`: `Show aggregate transactional outbox status.`、aliasなし、hidden=false

P23-002Dのexact33 target／exact27 Framework namesは、本Task完了後にexact35／
exact29となる。既存reservation registryは両名を含むためexact13のままである。
Default visible listはexact32からexact34、Command projection producerのcurrent
fixtureはexact36からexact38となる。両Commandを同時にactivateし、片方だけの
current completionは認めない。

`worker:list|worker:ls`、Worker registry、`dead-letter:list`、row listing、public routeを
追加しない。Application canonical name／alias collisionは既存global registryで
owner/name/actionable renameを返し、silent overrideを禁止する。

## Parallel Worker Ownership

Sol Max preflightがGreenになるまでProduction Sourceを変更しない。

Green後はLuna Max workerを次の順で用いる。

1. About component worker: Aboutのnew Source／direct testsだけを所有する
2. Outbox component worker: Outbox／PostgreSQLのnew Source／direct testsだけを所有する
3. Integration worker: 1と2の完了後にComposer／Deptrac／Application／Registry／existing testsを所有する

1と2は並列実行できる。共有existing file、Task、Report、STATE、TODO、Specは同時編集
しない。WorkerはReview前にcommit、stage、pushしない。

## In Scope

- 上記two commands、bounded values、renderers、typed failures
- Composer installed-version sourceとdirect virtual runtime API requirement
- existing ProjectionBuildSet statusのsafe contraction
- PostgreSQL one-row Outbox aggregate source
- Kernel activation、target exact35、Framework names exact29、reservation exact13、visible list exact34
- safe input／error contraction、no-leakage、no-resolution fixtures
- focused iteration gates、one exact freeze、one complete Final Gate、one fresh Sol Max review

## Out of Scope

- Outbox row listing、pagination、search、tenant filter、user authorization API
- `dead-letter:list`、Operation dead-letterとの統合、retry mutation
- Worker／process capability registry、HTTP runtime profile discovery
- public HTTP route、automatic health/readiness route
- Outbox schema migration、index、counter table、query performance SLA
- Stable `1.2.0` Guide／Website／generated artifacts／release notes
- Commit、Stage、Push、PR、remote CI、Deploy、Publication、Tag、Release

## Files Allowed

### Production Source／Dependency Metadata

1. `composer.json`
2. `composer.lock`
3. `deptrac.yaml`
4. `src/Internal/About/AboutMetadata.php`
5. `src/Internal/About/AboutMetadataSource.php`
6. `src/Internal/About/ComposerAboutMetadataSource.php`
7. `src/Internal/About/AboutReport.php`
8. `src/Internal/About/AboutRenderer.php`
9. `src/Internal/Console/AboutCommand.php`
10. `src/Core/Outbox/OutboxStatusFailure.php`
11. `src/Core/Outbox/OutboxStatusSnapshot.php`
12. `src/Core/Outbox/OutboxStatusSource.php`
13. `src/Core/Outbox/OutboxStatusSourceException.php`
14. `src/Internal/Outbox/OutboxStatusRenderer.php`
15. `src/Internal/Console/OutboxStatusCommand.php`
16. `src/Transport/PostgreSql/PostgreSqlOutboxStatusSource.php`
17. `src/Internal/Application/ApplicationConsoleCommandFactory.php`
18. `src/Internal/Application/ApplicationConsoleKernel.php`
19. `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`

### Tests

20. `tests/Internal/About/AboutMetadataTest.php`
21. `tests/Internal/About/ComposerAboutMetadataSourceTest.php`
22. `tests/Internal/About/AboutReportTest.php`
23. `tests/Internal/About/AboutRendererTest.php`
24. `tests/Internal/Console/AboutCommandTest.php`
25. `tests/Core/Outbox/OutboxStatusSnapshotTest.php`
26. `tests/Internal/Outbox/OutboxStatusRendererTest.php`
27. `tests/Internal/Console/OutboxStatusCommandTest.php`
28. `tests/Transport/PostgreSql/PostgreSqlOutboxStatusSourceTest.php`
29. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
30. `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php`
31. `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
32. `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
33. `tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php`
34. `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
35. `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
36. `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
37. `tests/Consumer/version-baseline.sh`

### Management／Normative Documentation

38. `develop/orchestration/tasks/P23-002E-about-and-outbox-status.md`
39. `develop/orchestration/reports/P23-002E-about-and-outbox-status.md`
40. `develop/STATE.md`
41. `develop/TODO.md`
42. `develop/spec/README.md`
43. `develop/spec/105-blackops-1-3-capability-contract.md`
44. `develop/spec/106-phase-23-delivery-plan.md`
45. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`

上記以外を変更しない。追加fileが必要なら実装を広げず、ReportへBlockerとして返す。

## Required Fixtures

### About

- installed `1.3.0|v1.3.0|1.2.0@dev|1.3.x-dev|dev-main|dev-feature/x` positive
- Composer rootを別Package、`blackops/framework`をdependency entryとする
  `InstalledVersions` datasetで上記versionを検証し、global stateを`finally`で必ず復元する
- version／PHP／SAPI empty、cap、cap+1、control、space、`=`、non-ASCII、throw／missing
- Projection exact eight、known exception contraction、unexpected result/throw
- Human／JCS exact bytes、decorated/undecorated parity、one LF、no ANSI
- unknown option、positional、non-Boolean `--json`でmetadata／Projection zero call
- constructor／list／helpでComposer／Projection／DB／Container／Provider／Handler zero call
- no build ID/hash/path/reference/dependency/environment/extension/host/runtime-profile canary

### Outbox

- empty、each bucket、mixed、ready/expired equality、sent-only zero
- pending due COALESCE、retry delayed/ready、leased active/expired、dead-letter
- multiple tenant canary rowsをinstallation aggregateへ含めるがtenantをselect／emitしない
- one clock、source factory/read once、UTC microseconds
- future age、count/age cap/cap+1、nullability mismatch
- minimum timestampのexact UTC microsecond text positiveと、offset／timezone abbreviation／
  fraction欠落・桁違い／whitespace／非string／invalid calendar value negative
- retry null next-attempt、leased null lease、dead-letter null/future timestamp
- extra/missing source column、nondecimal result、false row
- configuration、connection、query、missing table failure
- Human／JCS exact bytes、stdout/stderr separation、one LF、no ANSI
- invalid inputでdatabase factory zero calls
- fixed one-row SQL、no Operation/retry-audit/row/payload/context/tenant/identifier/fingerprint/relay access

### Integration and regression

- target exact35、Framework names exact29、reservation exact13、visible list exact34、producer exact38
- command projection/list/build goldensにexactly two new rows
- canonical activation、no aliases、application canonical／alias collision fail-closed
- `list`／`help`でmetadata／Projection／DB zero calls
- no public HTTP route、no Worker registry、no Stable Guide／Artifact occurrence
- `composer-runtime-api:^2.0` direct requirement、lock／strict validate、Composer Deptrac classification

## Iteration Gate

各component workerは自分のnew Source／Testだけをformatし、direct PHPUnitを実行する。
Integration workerは変更したexisting testsと全P23-002E focused testsを実行する。

Source freeze前にだけ、許可された全PHP fileへ次を実行する。

```bash
docker compose run --rm app mago format --check <allowed-php-files>
docker compose run --rm app vendor/bin/phpunit <focused-test-files>
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Final Gate

Production Source／Testをexact ordered manifestとaggregateへfreezeした後、一度だけ
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
- [x] About／Outbox fixed contractがSpec／Decision／Taskで一致する
- [x] Luna Max parallel componentsとintegrationがFiles Allowed内で完了する
- [x] `about` exact wire／metadata bounds／Projection states／no-leakage fixturesがGreen
- [x] `outbox:status` exact aggregate／bounds／anomaly／no-leakage fixturesがGreen
- [x] exact35／exact29／exact13／exact34／exact38とcollision／no-route regressionがGreen
- [x] exact Source／Test freeze上でcomplete Final GateがGreen
- [x] fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`
- [x] Report／STATE／TODO／Spec indexのRemaining／Next Actionが同期する
- [x] OrchestratorがAcceptanceを明示する

## Remaining Issues

Initial Sol Max preflightは`P1=0/P2=2/P3=0`で、minimum timestamp internal wireと
About target descriptionの二点を指摘した。両方をcontract上で限定補正済みである。

P23-002E内の残作業はない。Fresh Sol Max implementation reviewはGreen
`P1=0/P2=0/P3=0`で、Orchestratorはexact37とcomplete Final Gate evidenceを受理する。

上位BlackOps `1.3` GoalにはP23-003 Audit、P23-004 Worker／Classic、P23-005
Documentation、P23-006 release gateが残る。Commit、stage、push、CI、deploy、
publication、tag、releaseは未承認である。

## Suggested Next Action

P23-003 Audit deliveryをbounded researchとSol Max preflightから開始する。
