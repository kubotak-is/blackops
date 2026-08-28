# P23-002A1 Task Report

Status: Accepted

Updated At: 2026-08-26T17:30:18+09:00

Source Freeze At: `2026-08-26T17:12:09+09:00`

Accepted At: `2026-08-26T17:30:18+09:00`

## Summary

P23-002A1のnon-activated foundationを実装した。Injected absolute locator、固定root／lock
names、closed canonical root／descriptor／projection wire、typed hashes、NFC／control／
bound／sort／duplicate／origin validation、verified-handle reader、shared lock、exclusive
publisher、root-last atomic replacement、directory fsync、deterministic bounded GCを実装し、
command／route／scheduleのsynthetic exact-three inputsだけで閉じた。A1はKernel、build、
configuration、manifest、real producersへ接続していない。

## Sol Review Checkpoint

Fresh Sol implementation review at `2026-08-26T16:44:55+09:00` returned Changes
Requested `P1=3/P2=1/P3=0`. The prior Final Local Gate Green and its
`604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed` Source/Test
aggregate are Historical after this review and are not reusable for the
corrected candidate.

Correction is limited to four roots and their direct fixtures: RFC 8785 literal
U+2028/U+2029 encoding plus command-description rejection; bounded directory
scan with an explicit limit and immediate sentinel stop; cleanup directory fsync
and actually-deleted sorted-prefix reporting on later failure; and reset of
per-publication deletion state for publisher reuse. D145, D146, Specifications
105/106, existing production paths outside BuildSet, and all activation/public
surfaces remain unchanged.

The bounded post-Gate Sol re-review returned `P1=0/P2=1/P3=0`; its only finding
was that the completed Final Gate remained unchecked in this Report and the
Task. Both management entries are corrected without changing Source/Test, so
the current Final Local Gate evidence remains valid.

The focused Sol management confirmation matched the corrected five-file
management freeze, unchanged Source/Test aggregate, staged-empty state, and
`git diff --check`, and returned Green `P1=0/P2=0/P3=0`. The Orchestrator
accepts P23-002A1 as the non-activated foundation for the remaining A/B/C
atomic slice.

## Current Final Local Gate and Source Freeze

The four Sol findings above are corrected within the existing 13 Source and 2
focused Test files. The current Final Local Gate is Green on the unchanged
15-file Source/Test aggregate
`fc4b98e429a01489e33db7f7f4fbb60ea5362591da24361a48e987d4d96a0d2b`:

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection/BuildSet` — PASS, `39 tests / 202 assertions`.
- `docker compose run --rm app mago format --check src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet` — PASS.
- Full PHPUnit — PASS, `2360 tests / 9757 assertions`, exit 0; Deprecations 1, PHPUnit Deprecations 2, Notices 13.
- Full format — PASS.
- Composer strict validation — PASS.
- Full Mago lint — PASS; baseline `186` excluded.
- `mago lint --verify-baseline` — PASS.
- Full Mago analyze — exit 0; errors `0`, `108 warnings/help`.
- Deptrac — PASS; Allowed `4849`, all violation categories `0`.
- Version baseline — PASS; published `1.2.0`, historical `1.1.0`.
- Website release source check — PASS.
- PHP management-ID guard over the same Source/Test directories — PASS, no matches.
- `git diff --check` — PASS.
- `git diff --cached --name-only` — empty; staged `0`.

The aggregate was computed as `sha256sum <15 files> | sha256sum`. The prior
failed lint-only gate and prior aggregates are Historical and are not reusable.
Per-file SHA-256 values are:

- `ProjectionBuildSetAbsentException.php` — `19cf1ec1dbb4b18b1c54482f5530ff96ca3c2c3fd88f32931ef5320d19ca10d2`
- `ProjectionBuildSetCodec.php` — `5569e509a64b24ab67644b7a98d63858b77e67d783ba8bb4db6d02d753c157dc`
- `ProjectionBuildSetException.php` — `f0e3306d8f93632791e00681c0b3db7e47aae1874d951d55e5601501a9e5fee8`
- `ProjectionBuildSetFilesystem.php` — `07b5f3d3dcd67ea5b9baa5b9d334dc24b3df6f779a02491218f7db29dbc0f408`
- `ProjectionBuildSetFilesystemException.php` — `d919d2ceb80641767580bea6f0c4b9c80fbf3a997d9aa0602f463df29d38b85b`
- `ProjectionBuildSetJsonObject.php` — `db05e94bb6a2fad2a4e1c9791dcdbcf4f86584163330732e5943b5bfac29a05c`
- `ProjectionBuildSetLocator.php` — `83bd874407fd52e8f4a24d178d0f84bda60dd74dbaf18a84e9043d8e0a700dd5`
- `ProjectionBuildSetProjection.php` — `147c4d3cc0a711f8c744ab5b302cb83924e0fa4cd9da65acb5c9a7e86100514c`
- `ProjectionBuildSetPublicationResult.php` — `cebb85a58461c2ddd3e6ad77b3813d5d554969c2ebec5a25f814af95efe2491e`
- `ProjectionBuildSetPublisher.php` — `2bb09eae0f0bfee2abd4a73360afcb52d9fa461f60c72a8bd6840c6ca34bbe13`
- `ProjectionBuildSetReadResult.php` — `b653a9205755a76b233184cad80a5e858a14b126d8b01a4afb10b76564150aeb`
- `ProjectionBuildSetReader.php` — `0d8cfc91a0c9c9277dde48a0de4085de688fbbdb6616cc7206f7781bf8dfe2d1`
- `ProjectionBuildSetValidationException.php` — `d16faabdf2e580793946ce3c02aee46dec259fea8195ab6232569fa2f6d38933`
- `ProjectionBuildSetCodecTest.php` — `bd80073cf007492cf76e9c0076637080710f7767589e162156e461a2dfbd3842`
- `ProjectionBuildSetPublisherTest.php` — `7fe9cfddb9583c7d0826078709d67468633d0101b92678f5ae366f7be30b82a1`

The only new Source/Test paths are those 15 files under the two allowed
BuildSet directories. Existing unrelated parent worktree paths remain outside
this correction scope and were not edited. The prior aggregate and prior Final
Gate attempt remain Historical. Website Browser, same-SHA CI, deploy, publication,
tag, release, commit, and stage actions remain unapproved or unexecuted.

## Changed Files

Production Code（新規13 files）:

- `src/Internal/Projection/BuildSet/ProjectionBuildSetAbsentException.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetException.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystem.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystemException.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetJsonObject.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetLocator.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetProjection.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetPublicationResult.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetPublisher.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReadResult.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php`

Focused tests（新規2 files）:

- `tests/Internal/Projection/BuildSet/ProjectionBuildSetCodecTest.php`
- `tests/Internal/Projection/BuildSet/ProjectionBuildSetPublisherTest.php`

Management（許可されたcurrent handoffのみ）:

- `develop/orchestration/tasks/P23-002A1-projection-build-set-foundation.md`
- `develop/orchestration/reports/P23-002A1-projection-build-set-foundation.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/spec/README.md`

D145、D146、Specifications 105/106はcurrent preflight authorityとして読み、今回の実装で
要求されたboundaryを満たすため変更していない。既存Production、docs、Website、config、
dependency、Kernel、build、public routeは変更していない。共有worktreeの既存変更は範囲外
として保持した。

## Decisions and Assumptions

- locatorは既存absolute non-symlink directoryを受け取るだけで、config／manifestから推測しない。
- readerはmissing lockを作成せず、publisherだけが`x+b` exclusive-createする。lockはempty regular
  fileとして再検証する。
- 各既存generationはverified regular handleでcanonical bytes／digest／basenameが完全一致した
  場合だけreuseし、既存targetはoverwriteしない。
- `lstat`→single `fopen`→pre-I/O `fstat`→second `lstat`のdevice／inode／type一致を採用し、
  PHP標準APIにないkernel `O_NOFOLLOW`はclaimしない。
- directory scanは256、GC deletionは32、temporary nonceはroleごと16回でbounded fail-closed。
- root rename後のcleanup failureはrollbackせず、`published=true, cleanupComplete=false`を返す。
- A1はsynthetic exact-three inputsだけを受け、real producer／activationは後続A2/B/Cへ残す。

## Commands and Results

Pre-implementation Fast Gateは、実装前のため対象directoryが存在せずNot Verifiedだった。
PHPUnitは`tests/Internal/Projection/BuildSet` missing、targeted formatは対象なし、guardは
対象なし、`git diff --check`だけPASSだった。この履歴を実装後Greenと混同しない。

Initial Source freeze candidateのFocused/Fast Gate:

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection/BuildSet` — PASS、32 tests / 167 assertions。
- `docker compose run --rm app mago format --check src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet` — PASS。
- PHP management-ID guard — PASS、matchesなし。
- `git diff --check` — PASS。
- `docker compose run --rm app mago lint src/Internal/Projection/BuildSet` — PASS、No issues found。
- `docker compose run --rm app mago lint src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet` — exit 0、targeted test側の既存severity warning/helpのみ。
- 新規Source／TestのPHP syntax check — PASS。
- Initial Source/Test aggregate SHA-256 — `a347c904aafc96bb785649baf69ac64ab7f3f779c188d882eea253702e749716`。

Focused matrixはhash golden、LF／BOM／CRLF／extra whitespace、closed unknown／missing／wrong
type／duplicate、NFC／control／separator、record／field cap+1、IANA timezone inventory、
root self-hash／path／kind／duplicate mutation、complete reader、shared lock lifetime、
missing-lock bootstrap、FIFO／symlink／handle replacement／lock replacement、mixed generation、
root-last checkpoints、pre-commit crash、entry 256/257、missing-lock 255/256、root-temp slot、
GC 32/33、unknown preservation、post-commit partial cleanupを含む。

Historical Final Local Gateは、前回Source/Test freezeのaggregate
`604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed`に対してGreenとなった。
以下の結果はSol-requested correction前のHistorical evidenceであり、現candidateの
acceptanceには再利用しない。

- Focused PHPUnit: `32 tests / 167 assertions` PASS。
- Full PHPUnit: `2353 tests / 9722 assertions`、exit 0（Deprecations 1、
  PHPUnit Deprecations 2、Notices 13）。
- Full format PASS、Composer strict validation PASS。
- Full Mago lint PASS（baseline `186` excluded）、`mago lint --verify-baseline` PASS。
- Full `mago analyze` exit 0、errors `0`（`108 warnings/help`）。
- Deptrac: Allowed `4849`、violations／skipped／uncovered／warnings／errorsはすべて `0`。
- `version-baseline.sh`: published `1.2.0`、historical `1.1.0`、PASS。
- Website release source check PASS。
- PHP management-ID guard PASS、`git diff --check` PASS、staged `0`。

The prior freeze had no Source／Test changes after its gate. Website full test／build／Browser
was not run by Task contract. Network、commit、stage、push、CI、deploy、publication、tag、
release also remain unapproved／unexecuted. Stable `1.2.0` remains current、BlackOps `1.3`
remains unreleased. This gate is Historical after the new Sol review.

Commit、stage、push、CI、deploy、publication、tag、releaseはNot Run／未承認。staged=0を維持する。

## Acceptance Criteria

- [x] Injected locator、fixed root／lock names、exact closed wire、canonical bytes、hash、bounds。
- [x] Readerのshared lock、verified handles、root＋全3 projectionのcomplete-set validation。
- [x] Publisherのexclusive lock、sequential temp、fsync／rename order、root-last commit、bounded GC。
- [x] symlink／FIFO／replacement、mixed generation、crash checkpoint、entry／cleanup bounds、unknown preservation。
- [x] A1 non-activation、既存Production／public／docs／configの範囲外変更なし。
- [x] Focused/Fast Gate。
- [x] Final Gate — Final Local Gate Green。
- [x] Sol implementation review `P1=0/P2=0/P3=0`。
- [x] Orchestrator Acceptance。

## Remaining Issues

Initial Sol implementation reviewのChanges Requested `P1=3/P2=1/P3=0`は
Historicalであり、四つのbounded findingとdirect fixtureはCorrection済み。Remainingは
P23-002A1内のRemaining Issueはない。上位GoalではP23-002A2、P23-002B、
P23-002C、後続Phase 23、P23-006が残る。既存worktreeの
範囲外baseline／failureは修正せず、今回のReportへ分離した。

## Suggested Next Action

P23-002A2 command/list layerのTask Packetを作成し、A1を再実装せず、
A/B/Cのatomic acceptance境界を維持してpreflight reviewへ渡す。
Commit、stage、push、CI、deploy、publication、tag、releaseは未承認のままとする。

## Historical Source Freeze Hash Manifest

Historical Aggregate: `604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed`

- `ProjectionBuildSetAbsentException.php` — `19cf1ec1dbb4b18b1c54482f5530ff96ca3c2c3fd88f32931ef5320d19ca10d2`
- `ProjectionBuildSetCodec.php` — `643e92205adc0390482b4d4192d1205f3eec139a3e08fa5e94beb44a9e289735`
- `ProjectionBuildSetException.php` — `f0e3306d8f93632791e00681c0b3db7e47aae1874d951d55e5601501a9e5fee8`
- `ProjectionBuildSetFilesystem.php` — `842b7ca3dddef4e5663909a2c4de676335b0f93b1934fcedfdc6f372a288ccf1`
- `ProjectionBuildSetFilesystemException.php` — `d919d2ceb80641767580bea6f0c4b9c80fbf3a997d9aa0602f463df29d38b85b`
- `ProjectionBuildSetJsonObject.php` — `db05e94bb6a2fad2a4e1c9791dcdbcf4f86584163330732e5943b5bfac29a05c`
- `ProjectionBuildSetLocator.php` — `83bd874407fd52e8f4a24d178d0f84bda60dd74dbaf18a84e9043d8e0a700dd5`
- `ProjectionBuildSetProjection.php` — `147c4d3cc0a711f8c744ab5b302cb83924e0fa4cd9da65acb5c9a7e86100514c`
- `ProjectionBuildSetPublicationResult.php` — `cebb85a58461c2ddd3e6ad77b3813d5d554969c2ebec5a25f814af95efe2491e`
- `ProjectionBuildSetPublisher.php` — `4992cc767426ab1ec2798691c67e8de888e070ebd2660e5233d1fc08c24a7442`
- `ProjectionBuildSetReadResult.php` — `b653a9205755a76b233184cad80a5e858a14b126d8b01a4afb10b76564150aeb`
- `ProjectionBuildSetReader.php` — `0d8cfc91a0c9c9277dde48a0de4085de688fbbdb6616cc7206f7781bf8dfe2d1`
- `ProjectionBuildSetValidationException.php` — `d16faabdf2e580793946ce3c02aee46dec259fea8195ab6232569fa2f6d38933`
- `ProjectionBuildSetCodecTest.php` — `6f2eff4ed75022b291a5a5e2429f4894def34896bce0552a5713b9b0273d239e`
- `ProjectionBuildSetPublisherTest.php` — `86801745225034fec5ce7cf76e54a582da195dcf56676805da79a3462be89ad6`
