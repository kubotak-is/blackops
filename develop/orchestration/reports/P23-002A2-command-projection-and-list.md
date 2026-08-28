# P23-002A2 Task Report

Status: Accepted — non-activated Command Projection and List

Updated At: 2026-08-26T21:03:51+09:00

Started At: 2026-08-26T17:40:29+09:00

Accepted At: 2026-08-26T21:03:51+09:00

## Summary

P23-002A1はAccepted。P23-002A2のTask Packetを作成し、command projection、
embedded Framework rows、`list`／`ls`、global reservation、Stable input compatibility、
`operation:list --strategy`を一つの非接続実装sliceとして定義した。

Phase 1 implementationとPhase 2のpure producerは実装済み。初回Sol xHigh preflightはChanges Requested
`P1=3/P2=1/P3=0`。実登録metadataの単一正本、producerの3系統input、validator
両entrypoint内でのglobal reservation、Fast Gateのpath coverageへ限定してPacketを
補正した。namespace互換はStableのresolution／diagnostic／exitと、BlackOps safe row
outputを混同しない表現へ直した。

Bounded re-preflightは初回4 rootをclosureし、target `list` alias `ls`をcurrent
Symfony defaultとall-field identicalにした矛盾だけをChanges Requested
`P1=1/P2=0/P3=0`として返した。29-row registryをtarget projection／activation
authorityへ限定し、current Symfony `list` aliases `[]`からtarget aliases `['ls']`
への唯一のfuture deltaをfixture化した。A2 runtimeは引き続き`ls`を登録しない。

Focused Sol confirmationはcorrected six-file freezeを照合し、Green
`P1=0/P2=0/P3=0`を返した。Phase 1のtarget/reservation registry、current
Kernel metadata consumption、validator両entrypointに続き、Phase 2ではpure
producerとexact three-source union fixtureをFiles Allowed内で実装した。
producerはexplicit／collision-filter済みdiscovered／Operationを別typed inputで受け、
embedded exact 29 rows、null description正規化、Operationのclosed defaults、
collision/boundsを検証する。reader／list output／operation filterは後続Phaseへ残す。

Phase 3AではProjectionBuildSetのtyped complete-set reader seamを実装した。
`read()`は既存の戻り値・例外・shared-lock/same-handle挙動を維持し、
`readStatus()`はcaller-injected expected application build IDとcommand／route／schedule
input hashesを比較して、閉じた`available|absent|malformed|unsupported|stale|build_mismatch`
resultを返す。全3 projectionを走査してから固定precedenceを適用するため、非選択projectionの
失敗と走査位置に依存しない。permission／I/O／unreadableはtyped filesystem exceptionの
まま伝播し、exception messageの解析やfallbackは行わない。

Historical pre-correction completion checkpoint: P23-002A2 implementation was
complete, the prior Source/Test set was frozen, and the prior Fast Gate was Green.
Renderer evidence was
`7 tests / 37 assertions`, Namespace `9 tests / 15 assertions`, final Projection
Command `34 tests / 267 assertions`, operation filter `7 tests / 43 assertions`,
and relevant Kernel combined `32 tests / 359 assertions`; the final focused Kernel
test hash changed only by added assertions, with production unchanged. The bounded
List/Renderer lint correction was behavior-preserving and the resulting focused
Projection Command/BuildSet run is `40 tests / 330 assertions`; focused lint and
format checks PASS.

## Changed Files

- `src/Internal/Projection/Command/CommandProjectionTarget.php`
- `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`
- `src/Internal/Projection/Command/CommandProjectionReservationRegistry.php`
- `src/Internal/Projection/Command/CommandProjectionProducer.php`
- `src/Internal/Projection/Command/CommandProjectionRenderer.php`
- `src/Internal/Projection/Command/CommandProjectionMarkdownCellEncoder.php`
- `src/Internal/Projection/Command/CommandProjectionNamespaceResolver.php`
- `src/Internal/Projection/Command/CommandProjectionListCommand.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReadLimitException.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryResult.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryStatus.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationReason.php`
- `src/Internal/Console/FrameworkCommandNames.php`
- `src/Internal/Console/ApplicationCommandCollisionValidator.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
- `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
- `tests/Internal/Projection/Command/CommandProjectionRendererTest.php`
- `tests/Internal/Projection/Command/CommandProjectionNamespaceResolverTest.php`
- `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
- `tests/Internal/Projection/Command/ApplicationOperationListCommandTest.php`
- `tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php`
- `tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `develop/orchestration/tasks/P23-002A2-command-projection-and-list.md`
- `develop/orchestration/reports/P23-002A2-command-projection-and-list.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`

## Decisions and Assumptions

- Stable `1.2.0`のSymfony-backed `list`はcurrentのまま維持する。
- A2 componentはP23-002CまでProduction Kernel／locator／publisherへ接続しない。
- six-state recoveryはA1 exception message解析を禁止し、additive typed seamで表す。
- 反復中はfocused testだけ、Final GateはSource freeze後に1回だけ行う。
- target projection／activation metadataはBlackOps 25 rows＋target default 4 rowsのexact
  29-row registryを単一正本とし、pre-release reservation inventoryとは分離する。
- current Symfony defaultとの比較では`help`／`completion`／`_complete`を全field一致、
  `list`だけをaliases `[]`から`['ls']`への明示的future deltaとして固定する。
- producerはexplicit、collision-filter済みdiscovered、Operation metadataを別入力で受け、
  3系統を欠落なくprojectionへ含める。
- global reservationはvalidatorの`merge()`と`validateOperationCommands()`内部で必須適用し、
  runtime loaderを直接変更しない。
- typed readerのoperational pathはRecoveryResultの第七状態にせず、
  `ProjectionBuildSetFilesystemException`をcallerへ伝播する。raw read capはmalformedへ
  型付けし、six-state statusだけをsuccess resultとして返す。
- status precedenceは`absent > malformed > unsupported > build_mismatch > stale`とし、
  command／route／scheduleを全て試行してから最上位の失敗を選ぶ。期待build IDと3種の
  input hashはreader callerが明示的に注入する。

## Commands and Results

- Phase 2 focused PHPUnit: `52 tests / 563 assertions` PASS（Phase 1 fixturesを含む）。
- Phase 2 focused Mago format `--check`: PASS。
- Phase 2 PHP management-ID guard: PASS。
- Phase 2 `git diff --check`: PASS。staged state: `0`。
- Ordered 11-file Phase 2 Source/Test aggregate:
  `f46934937acb0a075717fac1ea79d016c011ddfb86723945a76ee467cc0f9e24`。
- Phase 2 per-file hashes: producer
  `f9b5696ab988f373b1223e8ecf01397939c562142643d550d038beaf21faff3d`;
  reservation registry `377ddcff501fbb78c3e8fc3f62e6978445b95dde6b39f643824ab607da3ab059`;
  target `77d167e1a93cce646f68766bc2bd2a2e195a4f9e93d4bc17e711ce0e298e16f2`;
  target registry `5dc556bdeec1e9d3f2d8dcbfd64ff56119cc7f01ea9b43c7a6e0aa1bf02220bd`;
  validator `b46abf092dc80285271138bd50037906acf3f2c4efe92d70fb08599ec75ba486`;
  framework names `b5c3c21c3bafd72f9c5fdd06b791a8a8f89f85dd0a846533ec2f77fb7cc05747`;
  Kernel `8e2c417717cb3ce4465076ae1ad402c59ac962469d8642383a461dd34437fe28`;
  producer test `8050296e1121a3ea47bd33f2f4cf9c154f77d2928f6ac1feadfc98fd4592800d`;
  registry test `8e1dfbf2dedd1676bd2c61873796595f7e68b3e371b6f08ee658ca5146596689`;
  validator test `4411871868c253fe0e30ebb18ed272425d035ee00e3d0d23d7612b862952c799`;
  Kernel test `516964076bb3e8516522144fa13031c5a2e3413518f9d44566add1aa68d818d2`.
- Full Fast Gate／Final Gate／Build／Network／CI: Not Run。Phase 2 checkpointのため。
- `git diff --check`: PASS。
- staged state: `0`。
- Initial Sol xHigh preflight: Changes Requested `P1=3/P2=1/P3=0`。
- Bounded Sol xHigh re-preflight: Changes Requested `P1=1/P2=0/P3=0`。初回4 rootは
  closed。current／target `list` alias境界だけを補正済み。
- Focused Sol xHigh confirmation: Green `P1=0/P2=0/P3=0`。

### Phase 3A Commands and Results

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection/BuildSet` — PASS,
  `45 tests / 265 assertions`。
- `docker compose run --rm app mago format src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet` — PASS。
- `docker compose run --rm app mago format --check src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet` — PASS。
- PHP management-ID guard over `src tests --glob '*.php'` — PASS、management identifier matchなし。
- `git diff --check` — PASS。`git diff --cached --name-only`はempty、staged `0`。
- Phase 3A ordered Source/Test SHA-256 manifest is recorded below. Commit、stage、push、CI、
  deploy、publication、tag、releaseは未実行。

### Current post-correction checkpoint

- Current status: `Accepted — non-activated Command Projection and List`。
- P1 correction hashes: List
  `1293ba223a43c7edfa925606cf3ef34166101155f7ca93c2846131d4d41bf816`, Renderer
  `b5921d015a672feef961b906ff5eafd199eab9dd73c0c304c7989cff142b2fb6`, and
  ListTest `2c3f8528fe11c41b4134663db00f7530d248e548cb35face9845ce5ae5ab73d8`。
- Correction focused Projection Command: `35 tests / 320 assertions` PASS;
  focused lint, format, analyze error `0`, PHP management-ID, `git diff --check`,
  and staged `0` PASS. Tests are unchanged from the prior manifest except for
  the recorded correction assertion.
- New Fast Gate: PHPUnit `130 tests / 1166 assertions` PASS; focused format,
  PHP management-ID, `git diff --check`, and staged `0` PASS.
- New Final Gate: format PASS; Composer `validate --strict` PASS; Mago lint PASS
  with baseline `186` filtered; lint baseline current PASS; Mago analyze error `0`
  PASS; full PHPUnit `2408 tests / 10398 assertions` PASS; Deptrac `883/883`,
  `Allowed4932`, zero violations PASS; version baseline (`published1.2.0` /
  `historical1.1.0`) PASS; Website `release:check:source` PASS; PHP management-ID,
  `git diff --check`, and staged `0` PASS. Non-blocking PHPUnit evidence is
  Deprecations `1`, PHPUnit deprecations `2`, Notices `13`.
- Website full test/build/Browser/Network/CI remain Not Run per the Task because
  public Documentation Source is unchanged. Commit, stage, push, CI, deploy,
  publication, tag, and release remain unapproved and unexecuted.
- Human Table projection cells use `OutputFormatter::escape`; final bytes use
  `OUTPUT_RAW`. Decorated/undecorated txt/raw/json/xml/md adversarial tags remain
  literal, ANSI-free, valid JSON/XML, exit `0`, and final-LF terminated.
- The prior `0999e0ab7aa313cdee7ffe09593ae65b3ff78f74ee2db103eb7a52c4e8e504e9`
  gate/review and `1c1614e8a621c190869a543537747f736fc5b923ebc97099c59013a0d7253cda`
  freeze are Historical. Stable `1.2.0` remains current and BlackOps `1.3`
  remains unreleased. A2 list/ls remains unregistered and non-activated, with no
  partial publish, Production locator, build connection, public docs, or artifact
  change.
- The bounded Sol xHigh closure review matched the Source/Test aggregate
  `69a3bd57…` and management aggregate `ef232f92…`, and returned Green
  `P1=0/P2=0/P3=0`. Orchestrator Acceptance completed at
  `2026-08-26T21:03:51+09:00`.
- The current read-only Sol closure review returned Changes Requested
  `P1=0/P2=1/P3=0`; its sole P2 was management-root synchronization. No
  Source/Test or technical defect was identified. A fresh bounded read-only
  closure review is requested after this correction.

## Current ordered 30-file Source/Test manifest

The exact ordered manifest below reproduces aggregate
`69a3bd57cef3dcea92f2edf4bc4d57c50e7655ac8c2ef3bd0cfe22802cf30740` before and
after the Final Gate. The reproducible aggregate command is:

```bash
p23_files=(
  src/Internal/Application/ApplicationConsoleKernel.php
  src/Internal/Console/ApplicationCommandCollisionValidator.php
  src/Internal/Console/ApplicationOperationListCommand.php
  src/Internal/Console/FrameworkCommandNames.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystem.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetReadLimitException.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryResult.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryStatus.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php
  src/Internal/Projection/BuildSet/ProjectionBuildSetValidationReason.php
  src/Internal/Projection/Command/CommandProjectionListCommand.php
  src/Internal/Projection/Command/CommandProjectionMarkdownCellEncoder.php
  src/Internal/Projection/Command/CommandProjectionNamespaceResolver.php
  src/Internal/Projection/Command/CommandProjectionProducer.php
  src/Internal/Projection/Command/CommandProjectionRenderer.php
  src/Internal/Projection/Command/CommandProjectionReservationRegistry.php
  src/Internal/Projection/Command/CommandProjectionTarget.php
  src/Internal/Projection/Command/CommandProjectionTargetRegistry.php
  tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php
  tests/Internal/Application/ApplicationConsoleKernelTest.php
  tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php
  tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php
  tests/Internal/Projection/Command/ApplicationOperationListCommandTest.php
  tests/Internal/Projection/Command/CommandProjectionListCommandTest.php
  tests/Internal/Projection/Command/CommandProjectionNamespaceResolverTest.php
  tests/Internal/Projection/Command/CommandProjectionProducerTest.php
  tests/Internal/Projection/Command/CommandProjectionRendererTest.php
  tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php
)
sha256sum "${p23_files[@]}" | sha256sum
```

- `src/Internal/Application/ApplicationConsoleKernel.php` — `86ac7eabf4523c67e618c580085819453308d63d733c94ccbdc38fb1b7c7d107`
- `src/Internal/Console/ApplicationCommandCollisionValidator.php` — `b46abf092dc80285271138bd50037906acf3f2c4efe92d70fb08599ec75ba486`
- `src/Internal/Console/ApplicationOperationListCommand.php` — `9e7c5a00507b94ff97de0c9ae0675c9c31e34c2dfd4217feda6d8439fef45814`
- `src/Internal/Console/FrameworkCommandNames.php` — `b5c3c21c3bafd72f9c5fdd06b791a8a8f89f85dd0a846533ec2f77fb7cc05747`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php` — `7fb99aebda68ca30e4f4126168b389b1005dc702255784b454f81d3f627111d4`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystem.php` — `1f1bb2594e2e795d3f5f6bb13b028d80db106fa2d879ccd62b8da98adbee2a66`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReadLimitException.php` — `0a27d0c3c42a74b98d3a3a279a34fbc52e49cc80d2db49d54be0079f33531c5a`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php` — `c757db0ae18f5b2ef76f2f323f6757ed7c405809f05e8cb242f9a61ae93f025a`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryResult.php` — `b3a322ac53bc5ccd157cf787fbfe144a831e3b464d35a72bbbae8660e39649a4`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryStatus.php` — `607c474c243dd8f1335d734d69c8d9fd650f84cd0e403cf80c88fb85084e9483`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php` — `a5b2a15c420820d54177501e7ad13b38ec658d5db3b60640db0c8e2c00e883f8`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationReason.php` — `c91a68609b187c71c0493cf9d8a7210e0580ea31262c43a5fc4c5043453caed1`
- `src/Internal/Projection/Command/CommandProjectionListCommand.php` — `1293ba223a43c7edfa925606cf3ef34166101155f7ca93c2846131d4d41bf816`
- `src/Internal/Projection/Command/CommandProjectionMarkdownCellEncoder.php` — `807edac71781a03489c934d1c6815a78918aabc521a063d4309ba6c435d3ad1f`
- `src/Internal/Projection/Command/CommandProjectionNamespaceResolver.php` — `d4fe507e8d3fe0bc2f0f61a43d6761ca2a035fd0141af9848ce810a55d2bb17d`
- `src/Internal/Projection/Command/CommandProjectionProducer.php` — `f9b5696ab988f373b1223e8ecf01397939c562142643d550d038beaf21faff3d`
- `src/Internal/Projection/Command/CommandProjectionRenderer.php` — `b5921d015a672feef961b906ff5eafd199eab9dd73c0c304c7989cff142b2fb6`
- `src/Internal/Projection/Command/CommandProjectionReservationRegistry.php` — `377ddcff501fbb78c3e8fc3f62e6978445b95dde6b39f643824ab607da3ab059`
- `src/Internal/Projection/Command/CommandProjectionTarget.php` — `77d167e1a93cce646f68766bc2bd2a2e195a4f9e93d4bc17e711ce0e298e16f2`
- `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php` — `5dc556bdeec1e9d3f2d8dcbfd64ff56119cc7f01ea9b43c7a6e0aa1bf02220bd`
- `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — `402c3d0f297beb465cc06f35d38b86958fd07584b29dc83c867e5bf4e6f205d1`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php` — `516964076bb3e8516522144fa13031c5a2e3413518f9d44566add1aa68d818d2`
- `tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php` — `4411871868c253fe0e30ebb18ed272425d035ee00e3d0d23d7612b862952c799`
- `tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php` — `260618c1c56e4652b678b150424d9cef57d4343744c22c61cc9ef8252cb7be48`
- `tests/Internal/Projection/Command/ApplicationOperationListCommandTest.php` — `afde95caeb78a5e59c1655d1ef0a0ecbabd473cbc4c63107e04ca2b005dbbf81`
- `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php` — `2c3f8528fe11c41b4134663db00f7530d248e548cb35face9845ce5ae5ab73d8`
- `tests/Internal/Projection/Command/CommandProjectionNamespaceResolverTest.php` — `f0becdd4a144d3ac0c8ab41f15dcc80b394618e1db5eb0346dbef6eabb8b3d99`
- `tests/Internal/Projection/Command/CommandProjectionProducerTest.php` — `8050296e1121a3ea47bd33f2f4cf9c154f77d2928f6ac1feadfc98fd4592800d`
- `tests/Internal/Projection/Command/CommandProjectionRendererTest.php` — `fed5815f2b27f5e25dc8d9a1668914e6f5ef8bf8bb8b94c237b0c99993e05ff6`
- `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php` — `8e1dfbf2dedd1676bd2c61873796595f7e68b3e371b6f08ee658ca5146596689`

## Acceptance Criteria

- [x] Sol xHigh preflight Green。
- [x] Luna Max implementation complete within Files Allowed。
- [x] Command producer／embedded Framework rows／reservation closed contract。
- [x] `list`／`ls` six-state recovery, Stable input, and output wires covered。
- [x] `operation:list --strategy` no-filter compatibility and bounded filter covered。
- [x] Constructor／factory／Container／DB／Provider／handler／source sentinels are `0`。
- [x] Partial root publication, Production list activation, and public config changes are `0`。
- [x] Task Fast Gate Green and Source/Test freeze recorded。
- [x] Source-freeze Final Gate Green（current ordered 30-file freeze
  `69a3bd57cef3dcea92f2edf4bc4d57c50e7655ac8c2ef3bd0cfe22802cf30740` is Green）。
- [x] Sol xHigh implementation review Green（bounded closure verdict
  `P1=0/P2=0/P3=0`; the prior management-only `P1=0/P2=1/P3=0` is Historical）。
- [x] Orchestrator Acceptance。
- [x] Report／STATE／TODO／Spec README current checkpoint synchronization。

## Remaining Issues

No technical or external blocker remains within P23-002A2. The higher atomic
slice still requires P23-002B route and P23-002C schedule/final activation.
Stable `1.2.0` remains current, BlackOps `1.3` remains unreleased, and A2
replacement `list`／`ls` remains non-activated.

## Suggested Next Action

Begin P23-002B while preserving the A/B/C atomic publication boundary. Commit,
stage, push, CI, deploy, publication, tag, and release remain unapproved and
unexecuted.

## Phase 3A Source/Test Hash Manifest

Ordered aggregate (`sha256sum <9 files> | sha256sum`):
`45308ab3970aa86147e2371904c5c14544158b3396b067d748e7e4e10b8d4cc4`。

- `src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php` — `8b94e62a1ccd82946957f41e6a0f091c184b9e70f1ad57594156c18e38ab4076`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystem.php` — `1f1bb2594e2e795d3f5f6bb13b028d80db106fa2d879ccd62b8da98adbee2a66`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php` — `c757db0ae18f5b2ef76f2f323f6757ed7c405809f05e8cb242f9a61ae93f025a`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReadLimitException.php` — `0a27d0c3c42a74b98d3a3a279a34fbc52e49cc80d2db49d54be0079f33531c5a`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryResult.php` — `b3a322ac53bc5ccd157cf787fbfe144a831e3b464d35a72bbbae8660e39649a4`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryStatus.php` — `607c474c243dd8f1335d734d69c8d9fd650f84cd0e403cf80c88fb85084e9483`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php` — `a5b2a15c420820d54177501e7ad13b38ec658d5db3b60640db0c8e2c00e883f8`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationReason.php` — `c91a68609b187c71c0493cf9d8a7210e0580ea31262c43a5fc4c5043453caed1`
- `tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php` — `260618c1c56e4652b678b150424d9cef57d4343744c22c61cc9ef8252cb7be48`
