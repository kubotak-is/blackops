# P23-003A1: Audit Catalog Foundation Report

Status: Accepted — Audit Catalog Foundation (BlackOps 1.3 unreleased)

Started At: 2026-08-28T10:22:45+09:00

Updated At: 2026-08-28T18:13:03+09:00

Accepted At: 2026-08-28T18:13:03+09:00

## Summary

P23-002E Acceptance後、P23-003Aをserial A1/A2へ分割した。D147で、Catalog専用
JCS/hash、exact minimum Framework catalog、evidence matrix、keyed `opaque_id`、
AuditReadContext、AuditRecord construction stages、observed safe projectionの
authorityを閉じた。

本A1はCatalog/JCS/security-access foundationだけを実装するTask Packetである。
Initial preflightはHistorical Changes Requestedである。指摘されたgate ordering、
request basis、JCS failure contract、opaque-key owner、Public API boundaryを
management/normative scopeで補正した。First corrected
exact-nine re-preflightはgeneral `P1=1/P2=1/P3=0`、focused
`P1=1/P2=0/P3=0`でChanges Requestedとなった。両laneのraw-cap precedence rootと、
general laneのAuditTypedReference boundary rootをProduction変更なしで補正した。
Second corrected exact-nine reviewはfocused Green `P1=0/P2=0/P3=0`、general
Changes Requested `P1=0/P2=1/P3=0`であった。General findingはnormative defectで
なく、byte preservationとtenant opaque-contextのdirect fixture不足だけである。
Task fixtureを限定補正したthird corrected exact-nineに対するfresh general Sol
re-reviewはGreen `P1=0/P2=0/P3=0`である。そのGreen後、Luna Max workerは
exact Files Allowed内のProduction implementationとIteration Gateを完了した。
Round 8までのbounded correctionを経て、Orchestratorのcomplete Final Gateと
固定Product freezeに対するfresh Sol Max implementation reviewはGreen
`P1=0/P2=0/P3=0`である。OrchestratorはP23-003A1をAcceptした。

## Changed Files

Orchestrator contract draftingでは次を変更・作成した。

- `develop/decisions/147-audit-catalog-and-safe-projection-boundary.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md`
- `develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/spec/README.md`

Production Source／Testはexact Files Allowed内で追加した。既存の無関係なdirty fileは変更していない。

今回のProduct filesは32 Source、12 PHP Test、2 Fixtureである。Dependency metadataは
direct `symfony/polyfill-intl-normalizer:^1.38`をcomposer.jsonへ追加した。

## Decisions and Assumptions

- Stable `1.2.0` current / BlackOps `1.3` unreleased proposalを維持する
- P23-003A1とA2は直列であり、partial runtime activationを行わない
- A1 owns Catalog-only RFC 8785 JCS/hash; B owns AuditRecord logical/chain hashes
- existing ProjectionBuildSet codecはAudit JCSへ再利用しない
- NFCは`ext-intl`追加でなくexisting transitive polyfillのdirect requirement化で保証する
- `BlackOps\Core\Audit`と`BlackOps\Internal\Audit`を使い、new Deptrac layerを作らない
- exact Framework catalogは30 definitions and one pinned catalog hashである
- keyed opaque IDはHMAC-SHA-256／dedicated exact 32-byte key roleを使う
- A1はkey provider、rotation、runtime adapterを実装せず、P23-003Cがdedicated
  provider／reference／availability／injectionを所有する
- pre-decision `AuditReadRequestBasis`とpost-decision `AuditReadContext`を分け、
  `.requested`を認可／lookup前に実装可能にする
- D/G runtime nonceは外部入力でなく、一requestごとの16-byte CSPRNG lower-hexである
- A1 Core/Internal Auditの`#[PublicApi]` marker allowlistはexactly emptyである
- pre-commit Final Gateでは到達不能なGit/Composer package exportを実行せず、
  explicit authorized candidate commit後のparent/release gateへ延期する
- Safe projectionとAuditRecord stage typesはA2へ残す
- raw complete Catalog capはexact ten semantic stagesの前段guardであり、over-capは
  他のdefectに関係なくzero decoder callで`jcs_bound_invalid`となる
- `AuditTypedReference {type,id}`の各memberはvalid UTF-8／NFC／no-control、
  1..256 UTF-8 bytesであり、trim／case-fold／silent normalizationをしない
- shared mixed-case／space／slash／colon preservation vector、各read consumer
  canary、tenant exact closed JCS／HMAC oracleをrequired fixtureへ追加する
- public Documentation、route、CLI、DB、runtimeは変更しない

## Commands and Results

Contract drafting中にread-only Source/spec inventory、independent Node catalog
oracle、`git diff --check`を実行した。Catalog oracleはD147／Taskに記録した
30 definitions、byte counts、hash、opaque ID goldenへ一致した。

Initial broad Sol Max preflightは`P1=1/P2=3/P3=1`、focused packaging/
architecture reviewは`P1=2/P2=2/P3=0`でChanges Requestedを返した。件数は
別review laneでありaggregateしない。両reviewが確認したrootは、pre-commit package
gate、pre-decision request basis、JCS escape/failure precedence、opaque purpose/key
 ownership、empty PublicApi inventory、executable focused/Deptrac gate、D147 status sync
である。Initial exact-nine freezeとそのreview evidenceはHistoricalである。

Historical exact-nine individual SHA-256はordered exactly:

```text
5dcdf316acaf61c95ee8cb6d029cfb9d35c7333046945adc7519aa1fc183bf80  develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
9d36cd6dfb63f477e4ac78e36a9d3e2f0fb789e9fcd423cf043e9058decc8394  develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
72ee5a1721c4ce0daad7ecb21dea80bd4c10705000f6f4590c2c4e7a4cb705b6  develop/spec/105-blackops-1-3-capability-contract.md
d7c6d9b69a6030cee2f478cea4ca41833c5f36f4cb5c7346a388f30c1d5c1382  develop/spec/106-phase-23-delivery-plan.md
41e743926826cbb3d2330a0c015e802036cde069e625378914d8ab9aeb18a2b0  develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md
b356da3789b008f30fcf0d54a0dd8f6ded695e46127a1c0e787ee8f748b8ba73  develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md
b41745a5f0a3724eb05fa2c4a14d3dc510b29a131a26fa17b7a2f55051e22f95  develop/STATE.md
cb234f646c65d38b335546eaad1ae79e281f7fbd529c08b853e8bb6809054400  develop/TODO.md
d17e8d7629fc0892acd32f3a0b45295aff57d93edc39e643f618160178b604aa  develop/spec/README.md
```

Historical ordered aggregateは
`5c591411813de86e2ee479ccc9d14181edadc7064122e888ef8c66d8efc42f79`である。

First corrected exact-nine freezeはfresh re-preflight後にHistoricalとなった。
Individual SHA-256はordered exactly:

```text
9f8101f7cd59f8c82410213a66b35fd80bbb3404517a857e4116b9f24aba77a8  develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
b3a233c57ce36b315becbe58895449d3a00e930c305c27dde1adb2abc3c952a3  develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
30614bb5cb0c4f94c1a96200132adb6fde56a335e6645b720a73ec650c427134  develop/spec/105-blackops-1-3-capability-contract.md
bbde69d5a682a32f3d48f6cc4316ddb872bd481bf4c8510bc009739d1a6f47e0  develop/spec/106-phase-23-delivery-plan.md
161e8e5debb13c5a2143a2abdb543d4051115aa3a0e816da22827822947c3f4f  develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md
f22c5e5d7098862a74e0b243c5e0d7246fc30e4950c50c5ba0d040a882c6a24d  develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md
ea16e41d45dc4c6594a85e200e1408d805cb9f6e1da8197aec969946dce19f39  develop/STATE.md
ab4a9b7d5d21bc8c688d464b734de42daaac063f14e79d53e0a0f1e2448d77be  develop/TODO.md
f8215cb66b1493b4086acf1b104587e2f5d032a3071b560c0206d5e246696b30  develop/spec/README.md
```

Historical ordered aggregateは
`4fbc2bd596bdcfed7240e58e64b0d90fad461a3ef1dc7ddb731d33e2fac35c64`である。
General re-preflight returned `P1=1/P2=1/P3=0`; focused re-preflight returned
`P1=1/P2=0/P3=0`. Start/end freeze、HEAD=main、staged0は両laneで一致した。

Second corrected exact-nine freezeもreview後にHistoricalとなった。Individual
SHA-256はordered exactly:

```text
8ac7a435a458110ef076428f814c6eb9105b4b80e9fb20a84151246db093559b  develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
790c4fbe098975a0efbdcbb5df8e4086a8f5ed41c271ced0fa0e28ae839db75b  develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
e166783fe445001ab2199d1cc0762aca75af033b268d23519124818587f962c1  develop/spec/105-blackops-1-3-capability-contract.md
0df40a4d6b4ad63b5d919d1d45a30365c50a44c08a348349aaf51b88923db285  develop/spec/106-phase-23-delivery-plan.md
4f2eae5f3f9f48b5e38ba4b1af8b52ea3210b12e198c41c6de498f6dc85ad772  develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md
23e24af78af5663601ed5972a7e01a232d601a5c1155788b5f18bcb99c381f1a  develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md
4c7a9344849fffe90d73ca4841438b70f54e80a8a111adf070a14ff8a27da255  develop/STATE.md
7addbddf5bd1ea3a502a56f6ca11e9a727cf3f2fb53db19a9be1d4a6c045181c  develop/TODO.md
171527f7288a06e070969fc857dc5a7c83a9d261c7cd7f5fd22bdd9e4477b494  develop/spec/README.md
```

Historical ordered aggregateは
`b88812135bbe38a926ab3c6c52239b355cbe24a4adcad549ee69c494e474691d`である。
Focused lane was Green `P1=0/P2=0/P3=0`; separate general lane returned
`P1=0/P2=1/P3=0`. Start/end freeze、HEAD=main、staged0、diff-checkは一致した。

Third corrected exact-nine individual SHA-256はordered exactly:

```text
962f1a95d8a08ea6f9726cf46f506d044a9e06aad3c2e0c720b311a7a46cc4d8  develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
1629ba4d6faea807359ce06cc3ad5037b11b93a301733cdfc707c74ce199db19  develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
ba27f0be63c28f0927016957fec489b651d4ae39ae6c177df99e5950024f4a2c  develop/spec/105-blackops-1-3-capability-contract.md
554a33fbc345fee836cf0ff5172b3a9490a1d7eda23917588772b518abf5d064  develop/spec/106-phase-23-delivery-plan.md
b48843de1163b41b1d727164fc60149081c023006a99e4e5c4281115ac23f84e  develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md
53512e8d9e9c5359b9315d2273c9210812ba63be2cbd6ff7d13893ae34cb1936  develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md
31ffd1500b675d3b80290ef288d722d8604fcb74fbf2c37f4c3fd51880382a14  develop/STATE.md
aebf4f5e398384f8324b5564d927fd231b0ad3de1908d6b4c0800473d73ce828  develop/TODO.md
4337555d19af3f4b6027bdd2567b757fd14ef728d16afd19bc5c61a8a6c0140c  develop/spec/README.md
```

Reviewed ordered aggregateは
`ab2b246e0f5201a2858611d9193c3ce47dd78b2a7e2981ba430a0afaca579663`である。
Fresh general closure review matched branch／HEAD=main／staged0／diff-check and
returned Green `P1=0/P2=0/P3=0`. このpreflight review時点ではProduction／test／
Dockerを未実行だった。後続のProduction、Iteration Gate、complete Final Gateの
current evidenceは以下へ記録する。

Node independent Framework catalog oracleはPASSし、unsigned 13878 bytes、hash
preimage 13916 bytes、complete 13959 bytes、stored 13960 bytes、hash
`cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83`を確認した。
Iteration Gateはfocused format、focused PHPUnit `21/141`、Composer strict、Mago
lint/baseline/analyze、Deptrac `949/949` violations `0` uncovered `0`、Node golden、
management-ID guard、diff-check、staged0までGreenである。

## Product Freeze (A1 Files 1-48)

Iteration GateはGreenであり、以下をTask Files AllowedのDependency、Source、Test、
Fixture順に固定した。Individual SHA-256と、その出力全体のSHA-256 aggregateは、
このfreeze後のProduct変更がないことを前提とする。

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
eafea8d1ec2af40a67a1274f04a03a13067a4f534b63c6700e2ab40793de9352  src/Core/Audit/AuditActionPhase.php
3470aaa492aad01b23e70c79c42712d952375b391b58f3355a4ced7bc78c9995  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
0d8f0321ca00979d8a47792cfcf370c1cacd89d3d10996d7e0dbe55e1d796c68  src/Core/Audit/AuditEventCatalog.php
0c9907b02eca618ce1206dfd3df625fc399c920a0fdfe5debef3b36b870198e6  src/Core/Audit/AuditEventCatalogLookup.php
2b871f1e8c0f480f65a13de67ef6ce2ab22141fd508b5372b6044fe860b23f2d  src/Core/Audit/AuditEventCategory.php
7d94b56272d928ab11af505b3dee3f21374fd719ec1539d7e3e8489f2116c0ff  src/Core/Audit/AuditEventDefinition.php
7407e76feb68f1ebfac602b1a81f352ae4e6b2b62f4c5853815cc8d8c67512ed  src/Core/Audit/AuditEvidenceClassification.php
0fa1b723a5203a02f047bb491178b49e94a94985cf61ca76b708ebe666386311  src/Core/Audit/AuditEvidenceFieldDefinition.php
e1e1bb1a666f1d222867125a86e5061d80f9d5d2f1280422a838e8e04f2364b3  src/Core/Audit/AuditEvidenceType.php
4a39542d93c5faf06fb5d6e446f0b0890b675f9566ea1fa2311abddaa0c76757  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
60221ac4e6a04fce85779eb4e109df4d40c08c870917e78d2db41c2a5134cc8d  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
9e9d4f4ef0f1756027f675cd982bd1910602c63fb59394516cf13a06e1505aa0  src/Core/Audit/AuditReadRequestBasis.php
3d93e29580d55e62ed4cbb49122ee4e39a43e1797cf1837b3d6b37fdb69791d6  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
1879c2b7412665c8fa6d3c454add48781c8db771f7dbbfcf1ea841f96f675a4f  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
d5fad007f75c88ea13625858f1cb6fe13b5baf01266c8c816e8f923b64adf81d  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
be7210e87e5775e2944d2125c4618286a92cf243e6284e6e286419418c7fbae2  src/Core/Audit/AuditValidationException.php
77fa814c16e4ee2b92fc84e81bcaa3a0119446a51d8c1c41c00e93571b54836b  src/Internal/Audit/AuditJcsCodec.php
3867dd13c6185db175bfcd6c5e0861f271e1b83a1d4a5a7d884c9b9ac51fb780  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
84b772f407ac1d5d7b10116046ac08f0fbe55d0b050befbc13ec9ae4ec6f849d  src/Internal/Audit/AuditStringValidator.php
c2ec7d28484ccc0253a3c38c3f3ce5a3fbc4fdb2df5d4c0a4cff53529b560b86  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
a192eadef18ea7c9eb8c9fadbd7f3a10938ba93063d6d52aaa203f21335afed6  src/Internal/Audit/AuditReadContextCodec.php
ad706c8edd099cfc841ad29316e751cd4c56ed34e92ec3e4f2c7cc9ce499c1eb  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
7644e8a4bb3a7d60cdb61b8a75bb915a8da862b9caa10d65ebc30731b0709ba4  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
91f645b3e91bb683f5f36f863df0df9fa747e14982a5b766d5931443e7f5df60  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
209b904bba650efe3916f9f0d6dfd4e5a7b2f089ea32292b917cb5b77837bfc9  tests/Core/Audit/AuditEventCatalogTest.php
86c42a8668b4ddd8d1bdf8bb3b741524007ac6a03fc87318debb70be1616df2e  tests/Core/Audit/AuditEventDefinitionTest.php
36f35dc1beeb537fe4043b456b888fdb220be08c50f809fc61bc29310b43f558  tests/Core/Audit/AuditReadRequestBasisTest.php
4ccbd64005ed7a9ec092d239e64a1982ef2bdff6ea0ceaafa18e97fac960a707  tests/Core/Audit/AuditReadContextTest.php
cc471696686fd08517cf5e82e2e4e14e34778265d9aa6774bea6087f5de45cb6  tests/Core/Audit/AuditValueTypesTest.php
482992f71483f8039f7c221a714abd91795aba2225a663d91c80d797dd904a4d  tests/Internal/Audit/AuditJcsCodecTest.php
52d3ff457405be23f02a8229e5afaa6623b99bb4d86af724a25a08e3f12d8162  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
36459ad67209ca2c7afd1c4d5b359e7eba088779e867560762f838d53d74dc3b  tests/Internal/Audit/AuditReadContextCodecTest.php
8223dffe78cc606677d434d9bffb803554dcc49df7f5c8c5c0b9619be09ef911  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
6c57e67f0e15e104c81de5a46056cdebbb388803c0c072f55d2d3ae3ebaa37fe  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
b02c6d1e2dcfd8305dd15cb88615379345d5d89839a1db03fe3156ee0b6e8d36  tests/Architecture/AuditPublicApiTest.php
f52ffff61cc312918e2c3993374c41884fee690bdb66f591c50d9529f9e08f2f  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
fc66e044ea36b8a9faccafc070ad6eb67fe45df8ec0343ae043005beb1065f5a  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Product freeze aggregate (ordered `sha256sum` output):
`4967e0de46b3ac1eada9ea876ae6a09a47334adcdb085d6a9710d15409817422`.

Iteration evidence: focused format PASS; focused PHPUnit `21 tests, 141 assertions`
PASS; Node catalog/requestRef golden PASS; Composer strict validation PASS; Mago lint
PASS and baseline up to date; Mago analyze `error: 0` (warnings 144/help 75); Deptrac
`949/949`, violations `0`, uncovered `0`; management-ID guard, diff-check, and staged
set guard PASS.

Complete Final Gateは同じProduct freezeでGreenとなった。Full format、Composer strict、
Mago lint／baseline／analyze、Deptrac `949/949`（violations `0`、uncovered `0`）、
version baseline、Documentation release source guard、management-ID guard、diff-check、
staged `0`はすべてPASSした。Full PHPUnit初回は既存
`OutboxRelayRuntimeTest::testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection`
がheartbeat実測`1`／期待`>=2`で一度だけFAILした。Productを変更せず同Testを単独実行
して`1 test / 4 assertions` PASS、続くfull PHPUnit再実行は
`2581 tests / 13335 assertions` PASSである。初回timing failureもEvidenceとして保持する。
Final Gate終端でProduct aggregateは引き続き
`4967e0de46b3ac1eada9ea876ae6a09a47334adcdb085d6a9710d15409817422`、
branch `agent/p23-001-blackops-1-3-feasibility`、HEAD／main
`5ad87557c08e20c3bcc6d77be83804001ba3eb80`、staged `0`である。

## Fresh Sol High Implementation Review

Fresh read-only reviewはstart／end freeze一致を確認し、Changes Requested
`P1=4/P2=2/P3=0`を返した。独立focused validation reviewも同じProduct rootを
再現した。Current correction rootsは次の6件である。

1. CoreとRFC 8785 codecで`requestRef`生成を重複し、valid `U+2028|U+2029`
   basisで異なるhashを作る。
2. Catalog／Evidence finite enumとsix-field descriptorがnormative wireを表現せず、
   distinct semantic action setをmultiset比較する。
3. Catalog identity／order／reserved provenanceとlookupのidentity/hash/complete-byte
   conflictがfail closedでない。
4. generic opaque-ID derivationがTenant／Evidence structured contextをraw scalarで
   迂回でき、NFCとsensitive-key境界も閉じない。
5. strict JCS numeric domainとexact ten-stage validation precedenceが不完全である。
6. Task Required Fixtures／exact PublicApi inventory／stored Catalog fixture／independent
   opaque oracleがmutation-sensitiveに閉じていない。

このreview後のProduct変更は上記Product freezeとFinal Gate evidenceをHistoricalに
する。Initial Final GateのOutbox heartbeat timing failureはunrelated evidenceとして
保持し、Product finding countへ含めない。

## Acceptance Criteria

- [x] P23-002E Accepted prerequisite確認
- [x] read-only contract/source/delivery research
- [x] P23-003A serial A1/A2 split
- [x] D147 exact Catalog／opaque-ID／projection boundary drafted
- [x] P23-003A1 Task Packet／initial Report drafted
- [x] initial exact management／normative freeze（Historical）
- [x] broad Sol Max preflight Changes Requested `P1=1/P2=3/P3=1`
- [x] focused packaging review Changes Requested `P1=2/P2=2/P3=0`
- [x] bounded management／normative correction
- [x] first corrected exact-nine freeze（Historical）
- [x] first corrected exact-nine re-preflight Changes Requested: general
  `P1=1/P2=1/P3=0`; focused `P1=1/P2=0/P3=0`
- [x] bounded raw-cap precedence／AuditTypedReference correction
- [x] second corrected exact-nine freeze
- [x] second corrected review: focused Green `P1=0/P2=0/P3=0`; general
  Changes Requested `P1=0/P2=1/P3=0`
- [x] typed-reference preservation／tenant opaque-context fixture correction
- [x] third corrected exact-nine freeze
- [x] fresh general Sol re-review Green `P1=0/P2=0/P3=0`
- [x] Luna High Production implementation
- [x] focused gate and exact Dependency／Source／Test／Fixture freeze
- [x] complete Final Gate
- [x] Historical fresh Sol High implementation review Changes Requested
  `P1=4/P2=2/P3=0`
- [x] bounded Luna High correction and new exact Product freeze
- [x] corrected complete Final Gate
- [x] fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`
- [x] Orchestrator A1 Acceptance

## Remaining Issues

P23-003A1内のRemaining Issueはない。

上位GoalではA2、parent P23-003A Acceptance、P23-003B..G、P23-004、P23-005、
P23-006が残る。Commit、stage、push、CI、deploy、publication、tag、releaseは
未承認・未実行である。

## Suggested Next Action

P23-003A2のTask Packetを作成し、Accepted A1を入力としてfresh Sol Max preflightへ
渡す。Commit／stage／runtime activationは行わない。

## Current Luna High Correction Checkpoint (2026-08-28T13:59:07+09:00)

The six fresh-review roots were corrected within the exact Files Allowed. The
request basis now has one Core-owned JCS implementation and literal U+2028/U+2029
coverage in every actor/execution/tenant/nonce path. Catalog decoding retains
object/list kind, performs closed shape before UTF8 and hydration, detects
decoded-equivalent duplicate keys, applies the ten stages in global order, and
separates byte bounds from semantic cardinality/value caps. Catalog lookup uses
sealed Framework provider object identity, validates application namespaces,
and fails closed for identity/bytes/hash conflicts. Opaque tenant/evidence
contexts are closed structured purposes with sensitive raw parameters and exact
PHP/Node goldens. PublicApi inventory covers all A1 types and Core signature
closure.

Verification results:

- focused PHPUnit: `51 tests, 920 assertions` PASS;
- focused format check, Composer strict, Mago lint, and Mago baseline: PASS;
- Node catalog/requestRef/opaque oracle: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`;
- management-ID guard, `git diff --check`, staged set guard: PASS;
- Mago analyze on the shared moving snapshot: `error:132`, `warning:188`,
  `help:89`; output includes unrelated snapshot-wide unresolved vendor test
  symbols and generated/untracked code, so no out-of-scope baseline/config edit
  was made.

The exact ordered 48-path Product SHA-256 output for this checkpoint is:

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
3470aaa492aad01b23e70c79c42712d952375b391b58f3355a4ced7bc78c9995  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
0eede10e221645e477115a92f97088641796b8f383ea1d1214df654151f3b279  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
62fa7d3ddfa9aad343dfdb7e2db2946f950c0f79e06f7d18feee5599a014e67d  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
4a39542d93c5faf06fb5d6e446f0b0890b675f9566ea1fa2311abddaa0c76757  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
05782a5f048569a27fb4e6e6b9e85e4a1f6468ba07606dbe9e761bf3f3f67926  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
1879c2b7412665c8fa6d3c454add48781c8db771f7dbbfcf1ea841f96f675a4f  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
a80a071f338513ec057181156aa01f8b776bb577b1110321ba6bbe2aadb0fc1b  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
f39438b334a62fb7412b935b62828e7da94bae0a73c5136b0bf840ab933f41ee  src/Internal/Audit/AuditJcsCodec.php
3867dd13c6185db175bfcd6c5e0861f271e1b83a1d4a5a7d884c9b9ac51fb780  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
0183e3d54763e43108130a90298ad6ba610d13d84a638f78d11d0d6683635f74  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
6e586a2a8dac607e9b34769c9535104e1a0d8e978b9aad507b065cbedb0f8795  src/Internal/Audit/AuditReadContextCodec.php
7f0b9c908a5d0ba3e01b89eb0a52f9fe79fab0ba9cff2483727b1b125063a43f  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
288bfa22f5cd82ec7ab637ee4d50859a1de1a582ea2f9411e87248eb463b9bbb  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
87e986c8796ad6bc1e4975e2f12512520fb40b8f1c69d7ade67968b6a6619c8b  tests/Core/Audit/AuditEventCatalogTest.php
0c74887ff1b6f7472fc21cca879aa94999adcccb0fbc9b4ef048081c0ed55655  tests/Core/Audit/AuditEventDefinitionTest.php
585d67a4881d0a61632325fec1465af7969c9cfa1e977375902d6977ee16ced1  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
db51d69a3a7b7fa165d1fea0b55714dc34a43b614ac64eda723013f69c5cd507  tests/Core/Audit/AuditValueTypesTest.php
ce9c0be1e3e43498f86614accef37343463e9af5d55a194a25f3909de6d022fb  tests/Internal/Audit/AuditJcsCodecTest.php
88dbb28e500dfd6575074e75fdf729caf750869f52efb90a24913c1803006e23  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
36459ad67209ca2c7afd1c4d5b359e7eba088779e867560762f838d53d74dc3b  tests/Internal/Audit/AuditReadContextCodecTest.php
586078e89bf9cdb8eeaabc6f87e54f93ff7531047f20545dbe1a21189aa4a12b  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
b63bebca7012abdf86f0fd6582cc6c9f84ce3faea968f63baa7a485d8e70b968  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
da0d689a68e70f4875264cde464056f5fb1fc354a6346a1f6868430f8c19d7e8  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered `sha256sum` output aggregate:
`b3f10afdfc00d8592f87f002ba24af5a8ce974bc676314cea9e1059fe684ca80`.

Remaining: corrected complete Final Gate from the beginning, fresh Sol High
re-review, and Orchestrator Acceptance. Mago analyze must be rerun by the
Orchestrator on the intended candidate environment; the shared moving snapshot
result above is not represented as Green.

Next Action: Orchestrator reviews this checkpoint and, if accepted, reruns the
complete Final Gate and requests fresh Sol High re-review. The worker performs
no commit, stage, push, or runtime activation.

## Current Luna High Correction Checkpoint (2026-08-28T14:50:15+09:00)

The correction continued within the exact Files Allowed. AuditJcsCodec now
parses objects into key/value pairs, preserving numeric-string member names
including nested `{"0":"x"}`; invalid UTF-8 is reported before decoded-key
duplicate collapse, while valid decoded duplicates and unpaired-surrogate
duplicates retain shape precedence. Catalog and ReadContext consumers unwrap
the internal object representation only after decoding. AuditTypedReference
constructor members are marked `#[SensitiveParameter]`, and an invalid tenant
trace canary proves exception text does not contain the rejected identifier.
Mutation-sensitive boundary fixtures now cover event/action/tuple/evidence counts,
evidence JCS bytes, declared string bytes, tenant reference bytes, semantic tuple
members, owner/category/delivery/evidenceVersion, and Node canonical/negative vectors.

Verification on this moving correction snapshot:

- focused PHPUnit: `64 tests, 979 assertions` PASS;
- focused format check: PASS;
- Composer strict validation: PASS;
- Mago lint and `mago lint --verify-baseline`: PASS;
- required source-only Mago analyze: exit `0`, `error:0` (warnings `75`, help `37`);
- Node catalog/requestRef/opaque golden: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`;
- `git diff --check` and staged-name guard: PASS / empty.

The new exact48 ordered individual SHA-256 manifest is:

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
3470aaa492aad01b23e70c79c42712d952375b391b58f3355a4ced7bc78c9995  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
0eede10e221645e477115a92f97088641796b8f383ea1d1214df654151f3b279  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
62fa7d3ddfa9aad343dfdb7e2db2946f950c0f79e06f7d18feee5599a014e67d  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
4a39542d93c5faf06fb5d6e446f0b0890b675f9566ea1fa2311abddaa0c76757  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
05782a5f048569a27fb4e6e6b9e85e4a1f6468ba07606dbe9e761bf3f3f67926  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
1879c2b7412665c8fa6d3c454add48781c8db771f7dbbfcf1ea841f96f675a4f  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
99a0a90cfacfbc4ab930a12aaa35617372886f92c813b4f632b579f73116a830  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
d5ece4103cf86cd4c2bef3b796e5b4faed03f2b0ef4f3e0f11403a167aa32805  src/Internal/Audit/AuditJcsCodec.php
d96d27e1ea3d6cfd93e712bf291fcc835cdaec40759efac38c475ab1846ebdb2  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
0183e3d54763e43108130a90298ad6ba610d13d84a638f78d11d0d6683635f74  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
35d0ee63b290f0edb80a0d0c2a8a4df19ac448589ebba76096e7a4a3feb1046f  src/Internal/Audit/AuditReadContextCodec.php
6f9547e3fce2252f772914cd6a311d62772d5bc75a32aa01ffdb46a19181f328  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
288bfa22f5cd82ec7ab637ee4d50859a1de1a582ea2f9411e87248eb463b9bbb  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
5067391814a1550ef7695241aa25070f5f812acf275ce550bbfa45f9e2351db8  tests/Core/Audit/AuditEventCatalogTest.php
1f8946433e6382b3ec0315166658327fed47d074ce78d35190746f67fb74e9c9  tests/Core/Audit/AuditEventDefinitionTest.php
585d67a4881d0a61632325fec1465af7969c9cfa1e977375902d6977ee16ced1  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
db51d69a3a7b7fa165d1fea0b55714dc34a43b614ac64eda723013f69c5cd507  tests/Core/Audit/AuditValueTypesTest.php
9508ff4a9345e20b116952f6464b9c3426d11574af85dc9a5436d6275d8a6a42  tests/Internal/Audit/AuditJcsCodecTest.php
3cfe805655696ed569ba6fe2467b47aae81a5c64266e6886e222e663b159f03a  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
36459ad67209ca2c7afd1c4d5b359e7eba088779e867560762f838d53d74dc3b  tests/Internal/Audit/AuditReadContextCodecTest.php
a537a15d4484c0d816ca8845269f9b68dbc14023074b5f05d50dab0e386a360e  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
b63bebca7012abdf86f0fd6582cc6c9f84ce3faea968f63baa7a485d8e70b968  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
39f6bf9b4e3c3c653876a8c457ffd8d9dc0047a136aa18904040785772c08e12  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered `sha256sum` output aggregate: `8c85e32e01efeea24ff86e31f55383dd60caca9240f96cbd93ba2de5476dffc1`.
This freeze is historical as soon as any listed Product file changes. No
commit, stage, push, runtime activation, CI, deployment, or publication was
performed.

## Luna High Correction Round 5 Checkpoint

Updated At: 2026-08-28T16:04:10+09:00

Round 5 corrected the direct-entrypoint validation roots within the exact
Files Allowed. `AuditTypedReference` now validates both members globally by
UTF-8, control, NFC, and byte-bound stages. Direct Catalog construction now
defers Application reserved-prefix semantics until the earlier global checks
complete. Structured Evidence HMAC context validates every string slot by
stage, so an invalid event/field UTF-8 value is not masked by a bad catalog
hash grammar. Required fixtures now include canonical-shaped raw over-cap,
opaque-id 70/71/72, reserved `version`, unsupported nested/list/float/
non-finite evidence, all read purposes and decisions, changed nonce, and a
valid wrong-domain requestRef.

Focused verification on this moving snapshot:

- PHPUnit: `79 tests, 1051 assertions` PASS;
- focused format: PASS;
- Composer strict validation: PASS;
- Mago lint and baseline: PASS;
- Audit-only Mago analyze: exit `0`, `error:0` (`warning:89`, `help:42`);
- Node catalog/requestRef/opaque golden: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`;
- management-ID guard, `git diff --check`, and staged-name guard: PASS / empty.

The corrected exact48 ordered manifest is:

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
3470aaa492aad01b23e70c79c42712d952375b391b58f3355a4ced7bc78c9995  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
0eede10e221645e477115a92f97088641796b8f383ea1d1214df654151f3b279  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
62fa7d3ddfa9aad343dfdb7e2db2946f950c0f79e06f7d18feee5599a014e67d  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
4a39542d93c5faf06fb5d6e446f0b0890b675f9566ea1fa2311abddaa0c76757  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
05782a5f048569a27fb4e6e6b9e85e4a1f6468ba07606dbe9e761bf3f3f67926  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
1879c2b7412665c8fa6d3c454add48781c8db771f7dbbfcf1ea841f96f675a4f  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
e8e373bdedec3bae53e061cd8b195cda106a43d92d3868d857894c044cb87dbb  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
bd323e9f0c96a86300db43ba21c4d9611b5743e79ea03f17e4557544f1b6c2ba  src/Internal/Audit/AuditJcsCodec.php
d96d27e1ea3d6cfd93e712bf291fcc835cdaec40759efac38c475ab1846ebdb2  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
0ba4c269a07a6e10320e86066d6f96d3341dc55acb624b9bd939e8ad617e2247  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
50be98e0fa9d32d026d1e1f555fb418c4b7ebe340992259f5b89af2c831222c1  src/Internal/Audit/AuditReadContextCodec.php
ed8a3479589b36115a004cf4f0fb1a9918e7b5f50c9c6cc686a44ba890ba9bd8  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
288bfa22f5cd82ec7ab637ee4d50859a1de1a582ea2f9411e87248eb463b9bbb  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
5067391814a1550ef7695241aa25070f5f812acf275ce550bbfa45f9e2351db8  tests/Core/Audit/AuditEventCatalogTest.php
58e602889cc4ce15d8579f9ccbb1c29529d19722806044641d94d3373a830d21  tests/Core/Audit/AuditEventDefinitionTest.php
585d67a4881d0a61632325fec1465af7969c9cfa1e977375902d6977ee16ced1  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
f2cb39c7aa005a9780b0b652bac4829593f9d15f57d87cdf3281b28e2c5ca3d6  tests/Core/Audit/AuditValueTypesTest.php
7fa4ea6aeb1f99f457b453660727cc131d78b81277b669ca6aeb9c922a953a2e  tests/Internal/Audit/AuditJcsCodecTest.php
a013434a97a70a8f6e74e7d61df2793c2b20962100e407b0addd374230287af2  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
39f0baf30a029be328fce9e278806ece1d36873cb0cb9590bda6428905eb203e  tests/Internal/Audit/AuditReadContextCodecTest.php
6f3d60095799d962ef672c39f2f51b6e6870f91e450f2b126779afdeda4acd30  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
b63bebca7012abdf86f0fd6582cc6c9f84ce3faea968f63baa7a485d8e70b968  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
39f6bf9b4e3c3c653876a8c457ffd8d9dc0047a136aa18904040785772c08e12  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered `sha256sum` output aggregate: `4f6e5e880531fe8c0904a515026f8f7622b25eb3ce9268fd53aa6aad18b2aa9b`.

The Round 4 `732a99bd...` freeze and its complete Final Gate are Historical
because this Round 5 changed Product files. No commit, stage, push, runtime
activation, CI, deployment, or publication was performed. Remaining work is
the corrected complete Final Gate, fresh Sol High implementation review, and
Orchestrator Acceptance.

## Luna High Correction Round 4 Checkpoint (2026-08-28T15:12:58+09:00)

Fresh Sol findings were corrected within the exact Files Allowed. ReadContext
now performs closed shape/type before UTF-8 and then applies the global
control/NFC/bounds/grammar/order/semantic/hash precedence; compound-invalid
fixtures cover missing plus invalid UTF-8, invalid purpose plus control/NFD
nonce, noncanonical bytes plus wrong requestRef, target injection, and
wrong-domain requestRef. Evidence validates scalar shape before text and
semantic stages, returns exact safe codes for all string/integer/boolean
presence/nullability rows, and defers version mismatch until after those
stages. AuditJcsObject direct pair encoding rejects duplicate keys including
nested pairs. The deferred-state fixture explicitly calls
`deferUtf8Validation()` and proves the same instance resets after decoding.

Verification on this moving correction snapshot:

- focused PHPUnit: `63 tests, 1006 assertions` PASS;
- focused format check: PASS;
- Composer strict validation: PASS;
- Mago lint and `mago lint --verify-baseline`: PASS;
- Audit-only source Mago analyze (`src/Core/Audit src/Internal/Audit`): exit `0`;
- Node catalog/requestRef/opaque golden: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`;
- `git diff --check` and staged-name guard: PASS / empty.

The corrected exact48 ordered individual SHA-256 manifest is:

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
3470aaa492aad01b23e70c79c42712d952375b391b58f3355a4ced7bc78c9995  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
0eede10e221645e477115a92f97088641796b8f383ea1d1214df654151f3b279  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
62fa7d3ddfa9aad343dfdb7e2db2946f950c0f79e06f7d18feee5599a014e67d  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
4a39542d93c5faf06fb5d6e446f0b0890b675f9566ea1fa2311abddaa0c76757  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
05782a5f048569a27fb4e6e6b9e85e4a1f6468ba07606dbe9e761bf3f3f67926  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
1879c2b7412665c8fa6d3c454add48781c8db771f7dbbfcf1ea841f96f675a4f  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
99a0a90cfacfbc4ab930a12aaa35617372886f92c813b4f632b579f73116a830  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
bd323e9f0c96a86300db43ba21c4d9611b5743e79ea03f17e4557544f1b6c2ba  src/Internal/Audit/AuditJcsCodec.php
d96d27e1ea3d6cfd93e712bf291fcc835cdaec40759efac38c475ab1846ebdb2  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
0183e3d54763e43108130a90298ad6ba610d13d84a638f78d11d0d6683635f74  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
50be98e0fa9d32d026d1e1f555fb418c4b7ebe340992259f5b89af2c831222c1  src/Internal/Audit/AuditReadContextCodec.php
975ea3b4edf1c114c93891be8d2d8b598e09872d43af6927f3cd65e962b64bde  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
288bfa22f5cd82ec7ab637ee4d50859a1de1a582ea2f9411e87248eb463b9bbb  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
5067391814a1550ef7695241aa25070f5f812acf275ce550bbfa45f9e2351db8  tests/Core/Audit/AuditEventCatalogTest.php
1f8946433e6382b3ec0315166658327fed47d074ce78d35190746f67fb74e9c9  tests/Core/Audit/AuditEventDefinitionTest.php
585d67a4881d0a61632325fec1465af7969c9cfa1e977375902d6977ee16ced1  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
db51d69a3a7b7fa165d1fea0b55714dc34a43b614ac64eda723013f69c5cd507  tests/Core/Audit/AuditValueTypesTest.php
7fa4ea6aeb1f99f457b453660727cc131d78b81277b669ca6aeb9c922a953a2e  tests/Internal/Audit/AuditJcsCodecTest.php
3cfe805655696ed569ba6fe2467b47aae81a5c64266e6886e222e663b159f03a  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
4b99698ec75de0308625de0edf648a2cf3fbb1acf434af81e2b92e2ad74f1933  tests/Internal/Audit/AuditReadContextCodecTest.php
d17a5b8254a5a252117af4558c1de27f474d8241b1aaecc5469857ff8c685c34  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
b63bebca7012abdf86f0fd6582cc6c9f84ce3faea968f63baa7a485d8e70b968  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
39f6bf9b4e3c3c653876a8c457ffd8d9dc0047a136aa18904040785772c08e12  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered manifest aggregate: `732a99bd703dd37602e023a86c1a0bd77bb4b1bbeea3a2e3a644f37f1ef14647`.
This is a moving correction snapshot; complete Final Gate and fresh Sol High
re-review remain pending. No commit, stage, push, runtime activation, CI,
deployment, or publication was performed.

## Luna High Correction Round 6 Checkpoint

Updated At: 2026-08-28T17:36:31+09:00

Round 6 closes the finite Sol validation-order audit within the exact Task
scope. The audit covered 25 contract rows and 38 public/direct callables across
the 32 A1 Source files. Product Source/Test/Fixture changes are frozen; only
this Task, Report, and STATE synchronization follows.

The corrected implementation covers whole-input shape before text validation,
direct-constructor precedence, token-aware surrogate handling, decoded-key
duplicate handling without invalid-UTF-8 substitution collisions,
object/list and numeric-string-key preservation, direct Catalog semantic
precedence before JCS caps, evidence shape and descriptor boundaries, sealed
Framework provider provenance, exact tenant/evidence HMAC context separation,
and Unicode requestRef slots. The remaining exact-cap proof uses real
RFC-8785-valid canonical JSON objects at the configured boundary lengths; no
non-JSON repeated-byte stand-in remains in the cap fixtures.

### Changed Product Files

The Product freeze is the exact ordered 48-file manifest below. Round 6 source
quality corrections touched `AuditReadRequestBasis`, `AuditSemanticTuple`,
`AuditEventCatalog`, `AuditEventDefinition`, `AuditJcsCodec`, and the bounded
catalog/JCS/lookup tests; all earlier Round 2-5 Product changes remain included
in the same manifest.

### Commands and Results

- `docker compose run --rm app mago format --check src/Core/Audit src/Internal/Audit tests/Core/Audit tests/Internal/Audit tests/Architecture/AuditPublicApiTest.php`: PASS;
- `docker compose run --rm app composer validate --strict`: PASS;
- `docker compose run --rm app vendor/bin/phpunit tests/Core/Audit tests/Internal/Audit tests/Architecture/AuditPublicApiTest.php`: `87 tests, 1090 assertions` PASS;
- same focused command including `tests/Architecture/PublicApiArchitectureTest.php`: `91 tests, 1102 assertions` PASS;
- `docker compose run --rm app mago lint src/Core/Audit src/Internal/Audit`: PASS, no issues;
- `docker compose run --rm app mago lint --verify-baseline`: PASS, baseline up to date;
- `docker compose run --rm app mago analyze --reporting-format=count src/Core/Audit src/Internal/Audit`: exit `0`, `error:0`, `warning:89`, `help:56`;
- `mise exec -- node tests/Fixtures/Audit/audit-event-catalog-golden.mjs`: PASS;
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered`: `949/949`, violations `0`, uncovered `0`;
- management-ID guard, `git diff --check`, and `git diff --cached --name-only`: PASS / empty.

### Exact Ordered 48-File SHA-256 Manifest

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
8c5ab03bcb4a19344d0a8ef45e1030d6d21a71eaddeb93b2d5ad3d58a2cce80b  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
37bdb8a73dded8e7399e63180c136555f40e1266878435364e1ab6d47a5308a6  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
b1dea6d544eb2862dcf7799cc3a1728e1fb87f47677bade2dad2247a875fcbc1  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
a2a4de43320394bd332d5ff4292b12a3cc813db15293769cbded0766993a2017  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
e90976400232df4e982184fafa9a65372eb1646811f692fdf03366862868b8f5  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
fb4d8d9cb839185270433939e634cacdc3f0c1df91403b240eb5df8d9bb36d22  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
e8e373bdedec3bae53e061cd8b195cda106a43d92d3868d857894c044cb87dbb  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
6c06e2731ed8b8ea00c3a5eade6b82b5cdedcf6444d30a02b7da8549049fd898  src/Internal/Audit/AuditJcsCodec.php
26b343a915323296ac93b58a2afc46a827e6c4456e6b7bd2f024916e82a52883  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
dd0006fac9d16fa773beec58e9e672ce117b4408166e1e5412cd4fff5a9a1f44  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
50be98e0fa9d32d026d1e1f555fb418c4b7ebe340992259f5b89af2c831222c1  src/Internal/Audit/AuditReadContextCodec.php
5b531a3e53dea9e34e6599e096cd64a8ce886c6dc501c00a8ffcce567d53c1cb  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
2f9cf493a6ffb1630a8c314f2a6a3ffb67699119ace3f4ce352837304cc6e0eb  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
d3b2ea641d2c506c643b4d3f48fc78b2a79a316345821f60b1c879b894687bd9  tests/Core/Audit/AuditEventCatalogTest.php
f86f4f7cbe053e420a733e47b4d7a2291923505b6dc1bfcb0fe49c75cd49a5ab  tests/Core/Audit/AuditEventDefinitionTest.php
4ab8538f7e589d9c6682001f40145e1371e47b2c2acf5cea7c1b7704a65f2a3f  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
7498540b54421d3744670fa903a3869f20d1a0679eb397bfc6f661c26616c4a4  tests/Core/Audit/AuditValueTypesTest.php
cf696a34f625030911cdabeda599a6f025372a6cf1388f313fb93739af8a9001  tests/Internal/Audit/AuditJcsCodecTest.php
2e9eea45032df077b641c86688b8b57e0e12c04215f0d1d398ffa0e342fc766e  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
39f0baf30a029be328fce9e278806ece1d36873cb0cb9590bda6428905eb203e  tests/Internal/Audit/AuditReadContextCodecTest.php
e44e6c8eb1917a9fd4eac4ece43b77a1934cd48b28e003cc6d99ef2e5552b181  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
cfe72e4c73e21290042a15fa29cd312da35505b727b6e6771cc28c5462deeecc  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
a390df5c65954d9d918ac232ce34de29fa398e75fff9fb089f6be097fe2147c3  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered `sha256sum` output aggregate: `f665a928c838a6db17587a28c2bf6ba5ee1fd8b8d15916fa6e18b77f78625926`.

### Remaining Issues

Within P23-003A1, the corrected complete Final Gate, fresh Sol High
implementation review, and Orchestrator Acceptance remain. Higher-level work
remains P23-003A2, parent P23-003A Acceptance, and P23-003B through P23-006.
No commit, stage, push, runtime activation, or A2/B-G work was performed.

### Suggested Next Action

The Orchestrator should re-freeze this exact manifest and run the complete Final
Gate from its first command, then request fresh Sol High implementation review.
After `P1=0/P2=0/P3=0`, the Orchestrator may explicitly accept A1.

## Luna High Correction Round 7 Checkpoint

Updated At: 2026-08-28T17:36:31+09:00

Round 7 closes the three residual global validation-order roots found by the
fresh Sol review. `AuditEventDefinition` completes all stage-7 order and
uniqueness checks before stage-8 tuple/action semantics. `AuditJcsCodec`
performs whole-tree shape, UTF-8, control, and NFC passes in that order.
`ImmutableAuditEventCatalogLookup` performs collection-wide shape, provenance
and identity semantics, canonical-size caps, and hash checks in stages
`1 -> 8 -> 9 -> 10`. Direct compound fixtures cover every corrected path.

Focused evidence on the frozen Product snapshot is Green:

- PHPUnit: `92 tests, 1098 assertions`;
- format, Composer strict, Mago lint, and baseline verification: PASS;
- Audit-only Mago analyze: exit `0`, `warning:90`, `help:56`, error `0`;
- Node golden: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`, allowed `5650`;
- management-ID guard, `git diff --check`, and staged-name guard: PASS / empty.

The Orchestrator complete Final Gate is also Green on this exact Product
freeze: full PHPUnit `2656 tests, 14304 assertions`, full Mago analyze exit `0`
with `warning:189`, `help:105`, and all other required commands PASS.

### Exact Ordered 48-File SHA-256 Manifest

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
8c5ab03bcb4a19344d0a8ef45e1030d6d21a71eaddeb93b2d5ad3d58a2cce80b  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
37bdb8a73dded8e7399e63180c136555f40e1266878435364e1ab6d47a5308a6  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
f187aab3a4f16134632e10efbe9aebf886784962a2a86df7c7b2ab398d6cb317  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
a2a4de43320394bd332d5ff4292b12a3cc813db15293769cbded0766993a2017  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
e90976400232df4e982184fafa9a65372eb1646811f692fdf03366862868b8f5  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
fb4d8d9cb839185270433939e634cacdc3f0c1df91403b240eb5df8d9bb36d22  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
e8e373bdedec3bae53e061cd8b195cda106a43d92d3868d857894c044cb87dbb  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
6914f1b4ee76ec513fbb5b75a086077cbbaf4106df4307949fcd47bd55b70999  src/Internal/Audit/AuditJcsCodec.php
26b343a915323296ac93b58a2afc46a827e6c4456e6b7bd2f024916e82a52883  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
dd0006fac9d16fa773beec58e9e672ce117b4408166e1e5412cd4fff5a9a1f44  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
50be98e0fa9d32d026d1e1f555fb418c4b7ebe340992259f5b89af2c831222c1  src/Internal/Audit/AuditReadContextCodec.php
5b531a3e53dea9e34e6599e096cd64a8ce886c6dc501c00a8ffcce567d53c1cb  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
4a4844567a8307ad22e2e314284f75dd96b60e56220e0c679a12e76239111715  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
d3b2ea641d2c506c643b4d3f48fc78b2a79a316345821f60b1c879b894687bd9  tests/Core/Audit/AuditEventCatalogTest.php
056422be9ecc651b20278142f7a45575219dfe6786a70c525a5813a8481c17d3  tests/Core/Audit/AuditEventDefinitionTest.php
4ab8538f7e589d9c6682001f40145e1371e47b2c2acf5cea7c1b7704a65f2a3f  tests/Core/Audit/AuditReadRequestBasisTest.php
dfe70030182ef0b4727c67f491d8b482bcb54e6040401f482b7da769df24a078  tests/Core/Audit/AuditReadContextTest.php
7498540b54421d3744670fa903a3869f20d1a0679eb397bfc6f661c26616c4a4  tests/Core/Audit/AuditValueTypesTest.php
16d77d5279f7aecb6ec91410895ebf085563a40944255b51da89c1507fc3b3f9  tests/Internal/Audit/AuditJcsCodecTest.php
2e9eea45032df077b641c86688b8b57e0e12c04215f0d1d398ffa0e342fc766e  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
39f0baf30a029be328fce9e278806ece1d36873cb0cb9590bda6428905eb203e  tests/Internal/Audit/AuditReadContextCodecTest.php
e44e6c8eb1917a9fd4eac4ece43b77a1934cd48b28e003cc6d99ef2e5552b181  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
ce676a215aabba81c6c39dcf0190703fe235fee80ff877bde401c1c61e5b353a  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
a390df5c65954d9d918ac232ce34de29fa398e75fff9fb089f6be097fe2147c3  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered manifest aggregate: `a792b250b7ecdd4033d1f5e7d1804ea4ac2f423fd501eedcabd99f3e3ffd89f2`.

### Remaining Issues

Within P23-003A1, fresh Sol High implementation review and Orchestrator
Acceptance remain. Higher-level P23-003A2 and later Audit delivery tasks remain
out of scope. No commit, stage, push, runtime activation, CI, deployment, or
publication was performed.

### Suggested Next Action

Run a fresh Sol High review against the exact Round 7 manifest. If it returns
`P1=0/P2=0/P3=0`, synchronize A1 Acceptance and unblock P23-003A2.

## Luna Max Correction Round 8 Checkpoint

Updated At: 2026-08-28T18:05:06+09:00

### Summary

Round 8 closes the final Lookup identity and ReadContext construction-boundary
findings within the exact A1 Files Allowed. Duplicate Catalog identity now
compares the complete canonical bytes, including the stored `catalogHash` and
terminal LF, during stage 8. An identical unsigned body carrying another valid
lowercase hash therefore fails with `semantic_invalid` before the stage-9 cap
and stage-10 hash checks. The direct mutation fixture fixes that precedence.

`AuditReadContext` remains a pure A1 value. Its direct fixture proves that the
constructor is private, the only public static factory is
`fromBasis(basis, decision)`, no external `requestRef` can be supplied, and
equal correlation references produce separate Allow and Deny contexts without
granting or collapsing authority. No runtime replay registry was added.

### Changed Files

Round 8 changed only these Product files:

- `src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php`
- `tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php`
- `tests/Core/Audit/AuditReadContextTest.php`

Task, Report, and STATE were synchronized as management-only changes. No
commit, stage, push, runtime activation, A2/B-G work, CI, deploy, publication,
tag, or release was performed.

### Commands and Results

- direct two-test PHPUnit: `16 tests/56 assertions` PASS;
- focused A1 plus PublicApi architecture PHPUnit: `98 tests/1124 assertions` PASS;
- scoped and full format checks: PASS;
- Composer strict: PASS;
- Mago lint: PASS, baseline up to date;
- full Mago analyze: exit `0`, `warning:189`, `help:105`, no errors;
- Node Framework catalog/requestRef/opaque oracle: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`, allowed `5650`;
- full PHPUnit: `2658 tests/14318 assertions`, exit `0`; existing
  deprecation/notices remain `1/2/13`;
- version baseline: PASS, published `1.2.0`, historical `1.1.0`;
- Website release source guard: PASS;
- management-ID guard: no matches;
- `git diff --check`: PASS;
- `git diff --cached --name-only`: empty.

### Exact Ordered 48-File SHA-256 Manifest

```text
b72d42f07444d974b7619c94fe93cfc36d8621d0830d62c084c5bec790ce143d  composer.json
75efc2f5af77e1d50215baedec2e68adbf4fa8b95f9bf04381907fecc9647b75  composer.lock
a6f25674ccfcba6f29e9de7f051e75fef002e44eb3c6e3dfb2510a027d5f457d  src/Core/Audit/AuditActionPhase.php
8c5ab03bcb4a19344d0a8ef45e1030d6d21a71eaddeb93b2d5ad3d58a2cce80b  src/Core/Audit/AuditCatalogHash.php
c38cf4a6fea0a476b5cac1672882228e4c01a389eab61ce66d32e3dee7c0256b  src/Core/Audit/AuditDelivery.php
37bdb8a73dded8e7399e63180c136555f40e1266878435364e1ab6d47a5308a6  src/Core/Audit/AuditEventCatalog.php
c63f35158b8aec4e86ebf7f7c443f37f099a982de17bb93c293a4e33793cdef1  src/Core/Audit/AuditEventCatalogLookup.php
ede439a64144c86fc635a8658af12f4005a43f18aa3be56e9f510eedff909f10  src/Core/Audit/AuditEventCategory.php
f187aab3a4f16134632e10efbe9aebf886784962a2a86df7c7b2ab398d6cb317  src/Core/Audit/AuditEventDefinition.php
57634d27e482000f8dcc8c592cab946b7a788a7028dbe13befabfbee6e9466a1  src/Core/Audit/AuditEvidenceClassification.php
a4fcd433ea1bbe88ea3def101fd27adb0a048f22f83bd2f82673e9b96ed88152  src/Core/Audit/AuditEvidenceFieldDefinition.php
382ce50fc2ee12c76c0963126ca3509e32186270002a1451a06bbb9578ac63d0  src/Core/Audit/AuditEvidenceType.php
a2a4de43320394bd332d5ff4292b12a3cc813db15293769cbded0766993a2017  src/Core/Audit/AuditOpaqueId.php
87e3fd368f454558400c4218a97f008f5e423396b233182564103b2490691b7d  src/Core/Audit/AuditOpaqueIdPurpose.php
43f86ee902789ebb3036dec2248562f4a0951c8c7a664be14eb001f5ce53b5bd  src/Core/Audit/AuditOutcomeStatus.php
addfab979d60ce0b16eb74ec43855fdf64f7287acc2b7173f987b0b08541171c  src/Core/Audit/AuditPolicyDecision.php
71cbf7d4e09759acc6b7f3c27d1f39c2b167cbc0a2247effa6ef092592a9eb29  src/Core/Audit/AuditReadAuthorizationDecision.php
e90976400232df4e982184fafa9a65372eb1646811f692fdf03366862868b8f5  src/Core/Audit/AuditReadRequestBasis.php
0e3154cecbdd92c2a12fd2c4b5c7b92d26924c2e799c0767815630c522d7dbc3  src/Core/Audit/AuditReadContext.php
be38e183cd80072ad429ab1a4381edcd8f4e267e8de6409ac52737315b37fbed  src/Core/Audit/AuditReadPurpose.php
fb4d8d9cb839185270433939e634cacdc3f0c1df91403b240eb5df8d9bb36d22  src/Core/Audit/AuditSemanticTuple.php
4292ce99113d8ddc08d791e227eccc4845e10712ee56d021c5481e637c9db629  src/Core/Audit/AuditSourceOwner.php
e8e373bdedec3bae53e061cd8b195cda106a43d92d3868d857894c044cb87dbb  src/Core/Audit/AuditTypedReference.php
b732cf2abbb3e9ae87c1d6042606d493a2e5e7054f309ab0095c07e68b0bbd0d  src/Core/Audit/AuditValidationFailure.php
84ab6ad07ce4de4e21d96c48c0912a7e121bdca667a8915e174ffa9c0d390628  src/Core/Audit/AuditValidationException.php
6914f1b4ee76ec513fbb5b75a086077cbbaf4106df4307949fcd47bd55b70999  src/Internal/Audit/AuditJcsCodec.php
26b343a915323296ac93b58a2afc46a827e6c4456e6b7bd2f024916e82a52883  src/Internal/Audit/AuditJcsObject.php
790746acb4fe0813b9404adf53da8a1f42885c6e2665d5983ebdc781e7b58f34  src/Internal/Audit/AuditLengthPrefix.php
1557bc52da89b1b25356522b03cd08a008d28b721ed10e2fe8929292fe9c3f74  src/Internal/Audit/AuditStringValidator.php
dd0006fac9d16fa773beec58e9e672ce117b4408166e1e5412cd4fff5a9a1f44  src/Internal/Audit/HmacAuditOpaqueIdDeriver.php
50be98e0fa9d32d026d1e1f555fb418c4b7ebe340992259f5b89af2c831222c1  src/Internal/Audit/AuditReadContextCodec.php
5b531a3e53dea9e34e6599e096cd64a8ce886c6dc501c00a8ffcce567d53c1cb  src/Internal/Audit/Catalog/AuditEventCatalogCodec.php
e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865  src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php
2792d7807de22da46af2e482c35d868ce675d631a3960d88fc703e43406762d7  src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
d3b2ea641d2c506c643b4d3f48fc78b2a79a316345821f60b1c879b894687bd9  tests/Core/Audit/AuditEventCatalogTest.php
056422be9ecc651b20278142f7a45575219dfe6786a70c525a5813a8481c17d3  tests/Core/Audit/AuditEventDefinitionTest.php
4ab8538f7e589d9c6682001f40145e1371e47b2c2acf5cea7c1b7704a65f2a3f  tests/Core/Audit/AuditReadRequestBasisTest.php
4a51a2e2d526084c0027282973a27d5184f6bc0090419b287f2246ed9a694980  tests/Core/Audit/AuditReadContextTest.php
7498540b54421d3744670fa903a3869f20d1a0679eb397bfc6f661c26616c4a4  tests/Core/Audit/AuditValueTypesTest.php
16d77d5279f7aecb6ec91410895ebf085563a40944255b51da89c1507fc3b3f9  tests/Internal/Audit/AuditJcsCodecTest.php
2e9eea45032df077b641c86688b8b57e0e12c04215f0d1d398ffa0e342fc766e  tests/Internal/Audit/AuditOpaqueIdDeriverTest.php
39f0baf30a029be328fce9e278806ece1d36873cb0cb9590bda6428905eb203e  tests/Internal/Audit/AuditReadContextCodecTest.php
e44e6c8eb1917a9fd4eac4ece43b77a1934cd48b28e003cc6d99ef2e5552b181  tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
8322aa26a4cd90fd23d0e32eb2239ed602c5c0b23f005500e57f23afd189fbc5  tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
b2f20f2329f0b05f1b72c2fe608fa3530136ec92224f3b6de5cd6340c26fcf50  tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
d94b1b3226edf36dea34c9d60e347d2b2c053c1f6544afb26f175b42d8a9d211  tests/Architecture/AuditPublicApiTest.php
ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d  tests/Fixtures/Audit/framework-audit-event-catalog-v1.json
a390df5c65954d9d918ac232ce34de29fa398e75fff9fb089f6be097fe2147c3  tests/Fixtures/Audit/audit-event-catalog-golden.mjs
```

Ordered manifest aggregate:
`9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`.
The ending Product aggregate after the complete Final Gate is identical.

### Acceptance Criteria

- Round 8 bounded Product correction: PASS;
- required direct mutation fixtures: PASS;
- focused Iteration Gate: PASS;
- exact Product freeze and complete Final Gate: PASS;
- Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased;
- fresh Sol Max implementation review: Green `P1=0/P2=0/P3=0`;
- Orchestrator Acceptance: complete.

### Remaining Issues

Within P23-003A1, no work remains. Higher-level work remains P23-003A2,
parent P23-003A Acceptance,
P23-003B..G, P23-004, P23-005, and P23-006.

### Suggested Next Action

Create and preflight P23-003A2 against Accepted A1. Keep the exact A1 Product
freeze unchanged and do not commit, stage, push, or activate runtime behavior.
