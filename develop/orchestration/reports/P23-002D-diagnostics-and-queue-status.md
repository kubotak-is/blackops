# P23-002D: Diagnostics Check and Aggregate Queue Status Report

Status: Accepted — Diagnostics Check and Aggregate Queue Status

Started At: 2026-08-27T23:47:45+09:00

Updated At: 2026-08-28T02:09:28+09:00

Completed At: 2026-08-28T02:09:28+09:00

## Summary

P23-002C Acceptance後に、`diagnostics:check`とaggregate-only
`queue:status`のcurrent Spec、Source、PostgreSQL schema、OperationalHealth契約を
read-onlyで調査した。

実装前に未確定だった次の境界をTask Packetとcurrent normative Sourceへ固定する。

- readiness queryの明示的なApplication authority
- arbitrary readiness Reportを固定6項目へ縮約するbounded normalizer
- Projection shared lockとreadiness／DB executionの分離
- host-operator／installation scopeのQueue aggregate
- mutually exclusive counts、exact ages、one-row SQL、no tenant／row leakage
- Human／JCS JSON／stderr／exitのexact wire
- Diagnostics／Queue並列componentと後続integrationの非競合ownership

Stable `1.2.0`はcurrentのままであり、BlackOps `1.3`はunreleased proposalである。

Fresh Sol Max re-preflight Green後、Luna Max workerがDiagnostics、Queue、
integrationをTask Packet内で実装した。Orchestratorの独立focused gateは
`129 tests / 1,419 assertions`でGreenとなり、Production Source／Test exact33を
aggregate `b55fa437777074244908480a4c211d2ba0f48d8412b18d6f321abffafc0e3641`
へfreezeした。公開Builder inventory、age cap+1、multi-tenant regression fixtureを補正した
current exact34 aggregateは
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`で、
complete Final GateはGreenである。

## Changed Files

Management／normative files:

- `develop/orchestration/tasks/P23-002D-diagnostics-and-queue-status.md`
- `develop/orchestration/reports/P23-002D-diagnostics-and-queue-status.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`

Production Source／TestはTask Packetのexact34だけを変更した。順序付きmanifestは
`Frozen Source／Test Candidate`に記録する。

## Initial Sol Max Preflight

The exact8 candidate and aggregate
`cfbb1264547c924587f1c76a681653d998fcf23f87c68633c21b65b92edd5714`
matched at review start. The review was deliberately stopped after it confirmed
two P1 architecture roots; no longer-running review of the superseded candidate
is reused as a final verdict.

1. Transport did not permit Internal dependencies, but the Task placed the
   Queue source port/value/failure types under `src/Internal/Queue` for use by
   `PostgreSqlQueueStatusSource`.
2. Application and InternalApplication did not permit Observability, but the
   Task required `OperationalHealthQuery` typing in `ApplicationBuilder` and
   `ApplicationConfigurationSnapshot` without allowing `deptrac.yaml`.

The bounded correction moves only Queue ports/value/failure types to
`src/Core/Queue`, keeps the renderer Internal, and adds `deptrac.yaml` to Files
Allowed for exactly `Application -> Observability` and
`InternalApplication -> Observability`. It also makes concrete direct Command
registration and pre-dependency Symfony input contraction explicit. Production
Source remains unchanged.

## Fresh Sol Max Re-preflight

The corrected exact8 candidate matched aggregate
`f29f3deb2896cdb067b58927139f7d3c512bdd8ce20674035b5393ce8f674a34` at
review start. Branch／HEAD／main、staged `0`、all eight individual hashes also
matched. Fresh bounded Sol Max review returned Green `P1=0/P2=0/P3=0`.

The reviewer confirmed both prior architecture roots are closed, the two
Deptrac edges are bounded and acyclic, Diagnostics and Queue contracts are
implementable within the declared Files Allowed, component ownership is
disjoint, and Stable `1.2.0`／unreleased `1.3` remain separated. Product
implementation and Product gates were not run by this read-only review.

## Luna Max Implementation and Orchestrator Review

Diagnostics componentは、Projection status callbackからsmall enumだけを返して
shared lockを解放した後に、登録済みreadiness queryをexactly once呼ぶ。
exact-six normalizer、fixed Human／JCS wire、typed Projection failure contraction、
safe input error、no-leakage fixtureを実装した。direct resultは
`18 tests / 137 assertions` Greenだった。

Queue componentは、Core port／snapshot／failure、Internal Command／renderer、
PostgreSQL one-row exact13 aggregate sourceを実装した。tenant／identifier／payload／
Outboxをselectまたはemitせず、one clock、count／age／nullability／future／cap、
typed configuration／storage／integrity failureを固定した。direct resultは
`20 tests / 212 assertions` Greenだった。

Integrationは、Builder／Snapshot readiness authority、concrete two-command Kernel
activation、valid execution内だけのQueue DB source factory、exact33 target／exact27
Framework names／exact13 reservations、projection/list/build/collision regression、
no public routeを接続した。Luna Max focused resultとOrchestratorの独立再実行はともに
`129 tests / 1,419 assertions` Greenだった。

## Frozen Source／Test Candidate

Current exact34 candidateは、下記Historical exact33 manifestのpath orderを保持し、
次の二つのhashを置換する。

```text
d00c4b1e6a3d75e75d904fc5b4727250b4c9b4ebfdfaa10061a814be6fd2151f  tests/Core/Queue/QueueStatusSnapshotTest.php
defa14c2eee09a014c135eb80c96fe748ce7a2a78ceb4add86d93b735fcc14fa  tests/Transport/PostgreSql/PostgreSqlQueueStatusSourceTest.php
```

その後、末尾へ次の1行を追加する。

```text
aa5d80380b1d81816edee96137bbb2a3d4661911ba2786f4393aacf8c5b9afd7  tests/Application/ApplicationTest.php
```

このexact orderで`sha256sum <34 paths> | sha256sum`を実行したcurrent aggregateは
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`である。
Current focused gateは`136 tests / 1,465 assertions` Green。以下のexact33
candidateとその途中Final GateだけをHistoricalとする。

This exact33 candidate is Historical. Complete Final Gate stopped at full
PHPUnit because the existing public Builder method inventory test was outside
the initial Files Allowed and did not include `withOperationalHealthQuery`.
The aggregate and manifest remain recorded below as failure evidence; they are
not reusable for the corrected candidate.

次のexact orderで
`sha256sum <33 paths> | sha256sum`を実行したaggregateは
`b55fa437777074244908480a4c211d2ba0f48d8412b18d6f321abffafc0e3641`である。

```text
86121119b9e270078a554d95c2bb6effdf6115bc46fa2e23fbdbb1ced36e5a57  src/Application/ApplicationBuilder.php
e82a9aea1e7f1842232ef90e844dbcebd34bfcc71d82f850ca35e770c09f517b  src/Internal/Application/ApplicationConfigurationSnapshot.php
7b891de5b90a09285e1795ac8e293a526b5d31dd16721c0fbcd844e0a517d0a7  src/Internal/Application/ApplicationConsoleCommandFactory.php
6a17a7d9956e42a01d851422c3626f049bacba98ee535c0eff659e9e72a4cafc  src/Internal/Application/ApplicationConsoleKernel.php
b3bff870c1689c364f9c08cd5964238230171c8558fca34ee540670d59d095ee  src/Internal/Console/DiagnosticsCheckCommand.php
b41186f502a8fc6f624f8c65a8482a29859a049c72b63721592d6d8b69f02de3  src/Internal/Console/QueueStatusCommand.php
a8b4035b035b77bba3498e1c92d3e497d62e9731dd8beda57dbb0d29cc52ef6f  src/Internal/Diagnostics/SystemDiagnosticsCheck.php
c9d1a75a520657fdb9c4820e75c7339bf7d407d34b22ab514983039e504f0ffb  src/Internal/Diagnostics/SystemDiagnosticsReport.php
fc9768a3fe9af3863a62aa7ebc825e090312e2588ae0cf29e82359e197370eba  src/Internal/Diagnostics/SystemDiagnosticsNormalizer.php
9038be45074d18e17fbdd0b3e67ed9c484f7472458c0864db05640ed5dcbd684  src/Internal/Diagnostics/SystemDiagnosticsRenderer.php
85dd9d17cbe6c74426bf227183b3a933368fb7263620dba37dbbb40916eed49a  src/Core/Queue/QueueStatusFailure.php
542aca1b080494b72fc764ff7d1801b8dd5f18ded829c3bd4d335a9921896d0f  src/Internal/Queue/QueueStatusRenderer.php
0add28e10b104cd0c5feffe8ac046f5eb704b2b004b255a65d372c9de96798e3  src/Core/Queue/QueueStatusSnapshot.php
874704223a5f26ba5090b08d75fe57a3b8ac9ce6e35facc941b2fafa68ab3084  src/Core/Queue/QueueStatusSource.php
a634bd3a2334fe8e267d6b61c8d034657551188c43964733e4215723d098a742  src/Core/Queue/QueueStatusSourceException.php
f577c4f45a911fd0e8841c4b32edbdd8e605c488b74db99972b7ac52ddd4138a  src/Transport/PostgreSql/PostgreSqlQueueStatusSource.php
6f28cc6ce35f8a59b401301561d923b17c6ade89815560e2380c6455fa4df0fa  src/Internal/Projection/Command/CommandProjectionTargetRegistry.php
031bc6cd46336a89aa3f114989766d77458314c347694330edc89d6438b1ef8f  deptrac.yaml
689cbdc3a699c5339767224570000723f0a29cd2f88f9b3fb2207455fb851713  tests/Internal/Application/ApplicationBuilderTest.php
aeb63753c0d15d3afeb178419bbd297bc5adda478bb9851f3ba0621c855aa443  tests/Internal/Application/ApplicationConsoleKernelTest.php
e7ea360cd4a57ffd8d8e48c5e026c15e95d81f9f02809c1c75dd0addcf11d72f  tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php
a78affc47a72d06d85b16ef6416d2ac4933fa3e13cd2eb8d9eba8285887907b0  tests/Internal/Application/ApplicationHttpConfigurationTest.php
03cc08bc288e0233dd370c06c9b6f51ca18d87b3eb7412eb72d3603096ef7936  tests/Internal/Console/ApplicationBuildCompileCommandTest.php
bb8beafe813b358ac5b9784a1f9f168f0572cef1cecee5d671347f09bcdf4f8f  tests/Internal/Console/DiagnosticsCheckCommandTest.php
e69ec75877e651ee74879a991b068f0aeea89625dd01d1467b91700dccc9ea49  tests/Internal/Console/QueueStatusCommandTest.php
5d0e0b9bb32b3160db3cc7d0bac18d868e2c299c4c80f3a40b07e39ebf0f3dac  tests/Internal/Diagnostics/SystemDiagnosticsNormalizerTest.php
f4f806627b9b1f184e28856114c7530fe8eae88ec973b922ecae6a79b7fa2c7a  tests/Internal/Diagnostics/SystemDiagnosticsRendererTest.php
cd109d92857fa96515f0fcb064424347fb58e1c5bc7738ad549b8ede736c9457  tests/Core/Queue/QueueStatusSnapshotTest.php
ac74173f636c3d7780bd8b1b68c97587ae19e724df33df3bbadf287a9b149a09  tests/Internal/Queue/QueueStatusRendererTest.php
ec49f6c1d7f4b6f46bb6a20a8ca33ff509956fcb978f1ba543a91726ad5aac6f  tests/Internal/Projection/Command/CommandProjectionListCommandTest.php
cd3887b0d8d2061432cf8376623617b98a68806fc72c175e30916a0ed93c9ecc  tests/Internal/Projection/Command/CommandProjectionProducerTest.php
5e6adf521e5d378102ce7ea8080dd04d58007e5cf88574f19579a6a370d8dabb  tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php
0ef0a36086f1e30c993a766b8dda3f448672dac86c85f85b211f4c632f13c740  tests/Transport/PostgreSql/PostgreSqlQueueStatusSourceTest.php
```

## Decisions and Assumptions

1. `ApplicationBuilder::withOperationalHealthQuery()`を1.3の唯一のreadiness
   registration authorityとする。暗黙Container探索はしない。
2. Query未登録、throw、不正shapeを固定safe codeへ縮約する。
3. Diagnostics JSONとQueue JSONはRFC 8785 JCS＋一つのLFとする。
4. QueueはOS／Container CLI accessを持つhost operator向けで、scopeは
   `installation`に固定する。
5. Queue queryはtenantを含む全Operationを集計するが、tenant列、identifier、
   payloadをselectまたはemitしない。
6. 現行indexのO(N) aggregate scanを正確性実装として受容し、migration／SLAは
   Out of Scopeとする。
7. Public HTTP route、Stable Guide、release、commit、pushは本Taskに含めない。
8. Queue port/value/failureはCore、PostgreSQL implementationはTransport、
   renderer/CommandはInternalに分ける。
9. Application／InternalApplicationからObservabilityへの依存は、公開Builder
   authorityに必要な二つの明示Deptrac edgeだけを追加する。

## Commands and Results

Research、implementation、focused verificationを実行した。

- current AGENTS／STATE／P23-002C／Spec README／D145／Specs 105/106を確認
- OperationalHealth fixed six、CLI adapter、Projection Status Sourceを確認
- Application Builder／Snapshot／Kernel／Command Factoryのcompositionを確認
- PostgreSQL `operations` state／timestamp／index／tenant metadataを確認
- Outboxが別lifecycleでP23-002E所有であることを確認

Fresh Sol Max re-preflightはGreen `P1=0/P2=0/P3=0`。

- exact32 PHP `mago format --check`: PASS
- focused PHPUnit: PASS、`129 tests / 1,419 assertions`、既存Notice `2`
- `mago lint`: PASS
- `mago lint --verify-baseline`: PASS
- `mago analyze --reporting-format=count`: PASS、errors `0`、warnings `90`、help `48`
- Production/Test management-ID scan: PASS、occurrence `0`
- `git diff --check`: PASS
- staged files: `0`

Complete Final Gateはfull PHPUnitで停止した。全体結果は`2512 tests / 11,350
assertions / 1 failure`で、failureは
`ApplicationTest::testPublicBootstrapTypesAreMarkedAndExposeRequiredFluentShape`
のexpected method inventoryだけだった。Final Gate後半、fresh Sol Max
implementation review、Browser、Network、CIは未実行である。

Bounded correction後のdirect testは`6 tests / 43 assertions` Green、corrected
exact34 focused PHPUnitは`135 tests / 1,462 assertions` Greenである。Corrected
complete Final Gateは次の全項目をPASSした。

- `mago format --check src tests`
- `composer validate --strict`
- `mago lint`／`mago lint --verify-baseline`
- `mago analyze --reporting-format=count`: errors `0`、warnings `90`、help `48`
- full PHPUnit: `2512 tests / 11,351 assertions`、failure `0`
- Deptrac: `904` dependencies、violations／uncovered／errors `0`
- version baseline: `published=1.2.0 historical=1.1.0`
- `pnpm --dir docs/website run release:check:source`
- Production/Test management-ID scan: occurrence `0`
- `git diff --check`、staged `0`
- ending exact34 aggregate: `72721ec6a380981fd210960a65acbb02591725f6d538a61571188b0ed354114d`

Taskに当初記録した`docker compose run --rm website ...`はroot Composeの
`postgres|app` service setと一致せず`no such service: website`で実行不能だった。
Product failureではないため、CIとAccepted P23-002Cが使うcanonical pnpm commandへ
Taskを訂正し、同じsource guardがGreenであることを確認した。

## Acceptance Criteria

- [x] Sol Max preflight Green
- [x] Luna Max component／integration implementation complete
- [x] focused gates Green
- [x] frozen candidate complete Final Gate Green
- [x] fresh Sol Max implementation review Green
- [x] Orchestrator Accepted

## Fresh Sol Max Review — Changes Requested

Review-before exact34 matched aggregate
`72721ec6a380981fd210960a65acbb02591725f6d538a61571188b0ed354114d`。
Verdictは`P1=0/P2=1/P3=0`。Productionの
`QueueStatusSnapshot::ageForCount()`はage MAX+1を正しく拒否する一方、current
fixtureはcountのcap+1しか直接固定していない。age range checkだけを削除しても
existing bound testsがGreenとなるため、Task Required Fixturesの
`count/age cap/cap+1`を満たすdirect regression evidenceが不足する。

Bounded correctionは既存allowed
`tests/Core/Queue/QueueStatusSnapshotTest.php`へage MAX+1 rejectionを1 case追加する
だけである。Production Sourceは変更しない。Current exact34とFinal Gateは
review-before Historical evidenceとし、correction後にnew aggregateをfreezeする。

Luna Maxはこのdirect rejection fixtureだけを追加した。Direct PHPUnitは
`5 tests / 7 assertions`、Orchestrator focused gateは
`136 tests / 1,463 assertions` Green。Production Sourceは不変で、current exact34
aggregateは`db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`。

## Corrected Current Final Gate

同一current exact34でcomplete Final Gateを実行し、次をすべてPASSした。

- `mago format --check src tests`
- `composer validate --strict`
- `mago lint`／`mago lint --verify-baseline`
- `mago analyze --reporting-format=count`: errors `0`、warnings `90`、help `48`
- full PHPUnit: `2,513 tests / 11,352 assertions`、failure `0`
- Deptrac: `904` dependencies、violations／uncovered／errors `0`
- version baseline: `published=1.2.0 historical=1.1.0`
- `pnpm --dir docs/website run release:check:source`
- Production/Test management-ID scan: occurrence `0`
- `git diff --check`、staged `0`
- ending exact34 aggregate:
  `db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`

## Historical Fresh Sol Max Closure Review — Changes Requested

The reviewer matched branch／HEAD／main、staged `0`、and ordered exact34 aggregate
`db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`.
Verdict is `P1=0/P2=1/P3=0`.

Production SQL correctly performs an installation-wide one-row aggregate and
does not select tenant fields. The remaining evidence gap is that the real
PostgreSQL integration fixture seeds every row with the same tenant. A
tenant-scoped source/view could therefore pass without proving that two tenants
contribute to the combined result. The bounded correction changes only
`tests/Transport/PostgreSql/PostgreSqlQueueStatusSourceTest.php`, seeds at least
two tenant canaries whose rows affect the totals/age, and retains no-leakage
assertions. The current freeze and Final Gate are review-before Historical.

Luna Max changed only the allowed PostgreSQL source test. It seeds `tenant-a`
and `tenant-b`; both contribute to totals, and `tenant-b` determines bounded
age results. Direct PHPUnit is `8 tests / 148 assertions`; Orchestrator focused
gate is `136 tests / 1,465 assertions`. Production is unchanged. Current
exact34 aggregate is
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`.

## Final Candidate Complete Final Gate

The complete Final Gate on current exact34 is Green:

- `mago format --check src tests`
- `composer validate --strict`
- `mago lint`／`mago lint --verify-baseline`
- `mago analyze --reporting-format=count`: errors `0`、warnings `90`、help `48`
- full PHPUnit: `2,513 tests / 11,354 assertions`、failure `0`
- Deptrac: `904` dependencies、violations／uncovered／errors `0`
- version baseline: `published=1.2.0 historical=1.1.0`
- `pnpm --dir docs/website run release:check:source`
- Production/Test management-ID scan: occurrence `0`
- `git diff --check`、staged `0`
- ending exact34 aggregate:
  `6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`

## Fresh Sol Max Closure Review — Green

The reviewer matched branch `agent/p23-001-blackops-1-3-feasibility`,
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`, and ordered
exact34 aggregate
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`.
Verdict is Green `P1=0/P2=0/P3=0`.

The two-tenant PostgreSQL fixture makes both tenants affect counts and makes
`tenant-b` determine bounded age results, so a source restricted to either
tenant now fails. Production remains unchanged, no delta regression was found,
and Stable `1.2.0`／unreleased `1.3` boundaries remain intact.

## Remaining Issues

None within P23-002D. Higher-goal work remains P23-002E、P23-003 Audit、P23-004
Worker/Classic、P23-005 Documentation、and P23-006 release gate. Commit、stage、
push、CI、deploy、publication、tag、release remain unexecuted.

## Suggested Next Action

Begin P23-002E `about`／aggregate-only `outbox:status` research and Task Packet
preflight. Implementation requires a new packet and Sol Max preflight Green.
