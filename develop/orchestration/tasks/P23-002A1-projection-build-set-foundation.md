# P23-002A1: ProjectionBuildSet Foundation

Status: Accepted

Updated At: 2026-08-26T17:30:18+09:00

Started At: 2026-08-26T14:25:34+09:00

Accepted At: 2026-08-26T17:30:18+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

Branch: `agent/p23-001-blackops-1-3-feasibility`

## Current Acceptance Checkpoint

Fresh Sol implementation review at `2026-08-26T16:44:55+09:00` is retained as
Historical Changes Requested `P1=3/P2=1/P3=0`. All four bounded findings are
now corrected: RFC 8785 literal U+2028/U+2029 encoding with one-line command
description rejection, bounded `opendir`／`readdir` sentinel scanning,
directory fsync after a successful cleanup prefix, and per-publication
deletion-state reset. Direct fixtures cover the independent Unicode exact-three
hash goldens, sentinel stop and scan limits, partial cleanup ordering and typed
prefix, and Publisher reuse.

The bounded post-Gate Sol re-review found no Source/Test defect and returned
`P1=0/P2=1/P3=0` solely because the completed Final Gate remained unchecked in
this Task and its Report. Those two management entries are now corrected. The
Source/Test aggregate is unchanged, so the existing Final Local Gate evidence
remains current and is not rerun.

The focused Sol management confirmation matched all five management hashes,
the unchanged Source/Test aggregate, staged-empty state, and `git diff --check`,
and returned Green `P1=0/P2=0/P3=0`. The Orchestrator accepts this bounded A1
foundation. Stable `1.2.0` remains current, BlackOps `1.3` remains unreleased,
and A1 remains non-activated until the A/B/C atomic slice completes.

The current Final Local Gate is Green on the unchanged 15-file Source/Test
aggregate `fc4b98e429a01489e33db7f7f4fbb60ea5362591da24361a48e987d4d96a0d2b`.
Focused PHPUnit is `39 tests / 202 assertions` PASS; full PHPUnit is `2360 tests /
9757 assertions` exit 0 with Deprecations 1, PHPUnit Deprecations 2, and Notices
13; full format, Composer strict validation, full Mago lint (baseline `186`
excluded), baseline verification, full Mago analyze (exit 0, errors `0`, `108`
warnings/help), Deptrac (Allowed `4849`, all violation categories `0`), version
baseline (`1.2.0` / `1.1.0`), Website release source, management-ID guard,
`git diff --check`, and staged `0` all PASS. The new Source/Test scope is exactly the 13 files under
`src/Internal/Projection/BuildSet/` and the 2 focused tests under
`tests/Internal/Projection/BuildSet/`; existing parent worktree changes outside
that scope remain untouched. The prior failed lint-only gate and prior
aggregates are Historical and are not reused.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. No
activation, public surface, documentation, dependency, generated artifact,
commit, stage, push, CI, deploy, publication, tag, or release action was taken.

## Goal

P23-002A/B/C atomic projection sliceの最初の小単位として、
`ProjectionBuildSet v1`のshared wire、injected locator、verified-handle
filesystem boundary、shared/exclusive lock、complete-set reader、root-last
publisher、bounded GCを実装する。

このTaskではcommand／route／scheduleのsynthetic complete inputsだけを使い、
Production `build:compile`、Console Kernel、public configurationへ接続しない。
Stable `1.2`のSymfony-backed `list`と公開挙動は変えない。

## Why

P23-001で確定したAの責務は大きく、list互換、projection wire、publisher、
filesystem safety、reservation、operation filterを一度に実装すると、失敗原因と
review対象が混ざる。さらに、accepted proseのkernel-level nofollow表現はPHP標準
APIでは直接実装できず、production locatorも未確定だった。

D146でfilesystem境界とlocator ownershipを先に閉じる。A1はwire／atomicityだけを
focused testで証明し、A2がcommand/list層を載せる。B/Cは同じcodec／reader／
publisherを再利用し、Cだけがcomplete three-producer rootをactivateする。

Initial Sol preflightは`P1=2/P2=2/P3=0`だった。Intermediate symlink、
generation/temp/GC namespace、first-lock bootstrap、current-only field tableの
4 rootをD146／Specifications 105/106とこのPacketへ一括補正した。Fresh Sol
preflightは`P1=0/P2=0/P3=0`でGreenとなり、Production implementationへ進める。

Fresh Sol implementation review at `2026-08-26T16:44:55+09:00` returned
Changes Requested `P1=3/P2=1/P3=0`. The prior Final Local Gate Green is now
Historical evidence and is not reusable after this correction. Correction is
bounded to four current-scope roots: RFC 8785 line-terminator encoding and
description rejection, bounded directory scan sentinel observation, cleanup
fsync/prefix result handling, and per-publication deletion-state reset, with
direct focused fixtures only.

## Source of Truth

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task Packet
4. `develop/spec/README.md`
5. `develop/spec/105-blackops-1-3-capability-contract.md`
6. `develop/spec/106-phase-23-delivery-plan.md`
7. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
8. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`

仕様と実装が矛盾する場合はSourceだけで解決せず、ReportへBlockerとして返す。

## Release and Documentation Boundary

- Release Authority tuple: Stable `1.2.0` current。変更しない。
- Capability ID: future `framework-core`／BlackOps `1.3` proposal。
- Public Source／route inventory: 変更なし。
- `docs/guide/**`、Documentation Website Source／Search／LLM／generated
  artifactsは変更しない。
- Version、tag、Package、Skeleton、public configuration、command name、H1、
  Slug、Navigation、Redirectを変更しない。
- A1のcodeは非接続componentであり、Stableまたは1.3のcurrent capabilityとして
  公開しない。

## Files Allowed

1. New files only under `src/Internal/Projection/BuildSet/`
2. New files only under `tests/Internal/Projection/BuildSet/`
3. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
4. `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`
5. `develop/spec/105-blackops-1-3-capability-contract.md`
6. `develop/spec/106-phase-23-delivery-plan.md`
7. `develop/orchestration/tasks/P23-002A1-projection-build-set-foundation.md`
8. `develop/orchestration/reports/P23-002A1-projection-build-set-foundation.md`
9. `develop/STATE.md`
10. `develop/TODO.md`
11. `develop/spec/README.md`

既存worktreeの他pathはUser／accepted P23-001／P23-001Rの変更であり、
byte-identicalに保つ。既存Production fileの追加変更が必要なら実装を広げず、
ReportへBlockerとexact pathを記録する。

## Out of Scope

- `ApplicationBuildCompileCommand`、`ApplicationConsoleKernel`、Public
  `ConsoleKernel`、Application configurationへの接続
- command projection producer、embedded Framework rows、`list`／`ls` output、
  namespace／format互換、reservation registry
- `operation:list --strategy` filter
- route／schedule producerまたはcommand
- public docs、Website、Example、Skeleton、dependency、lockfile、Migration
- native extension、FFI、shell helper、platform-specific binary
- commit、stage、push、PR、CI、deploy、publication、tag、release

## Fixed Implementation Contract

### Locator and fixed names

- `ProjectionBuildSetLocator`はabsolute existing dedicated directoryだけを
  受け入れる。A1はconfig keyや既存manifest pathから導出しない。
- Root filenameは`projection-build-set.json`、lock filenameは
  `.blackops-projection-build-set.lock`。
- Descriptor pathはsingle basenameだけを許可し、exact
  `command|route|schedule` prefix＋raw SHA-256へbindする。subdirectory、slash、
  backslash、dot segmentを拒否する。
- Locator directoryはnon-symlink directoryでなければならない。callerが
  permissions／ownership／immutabilityを保証するtrusted installation boundaryである。
- Readerはlock missingをtyped `absent` failureとして返し、lockを作成しない。
  Publisherだけが初回publication時に`x+b` exclusive-createでlockを初期化できる。
  既存lockはtruncateしない。初回read-only scanはlock用slotを予約して既存
  255 entriesまで、under-lock scanはlockを含む256までを受理する。後段failure時は
  empty fixed lockだけが残り得るが、projection/root/temp/deletion mutationは0とする。

### Wire

- Rootはclosed
  `{schemaVersion,kind,applicationBuildId,buildSetHash,projections}`。
- `schemaVersion=1`、`kind=projection_build_set`、projection keysはexact
  `command|route|schedule`。mappingはexact `command ->
  blackops_command_projection`、`route -> blackops_route_projection`、
  `schedule -> blackops_schedule_projection`。
- Descriptorはclosed `{kind,inputHash,path,sha256}`。kindはexact
  `blackops_command_projection|blackops_route_projection|blackops_schedule_projection`。
- Projection fileはclosed
  `{schemaVersion,kind,applicationBuildId,inputHash,records}`。
- `inputHash`の`sourceRecords`はstored `records`とbyte-equivalentな同じclosed／
  normalized／sorted arrayであり、別representationを持たない。
- Command recordはexact `{name,aliases,description,origin,hidden}`、originは
  `framework|application`。Route recordはexact
  `{method,path,strategy,operationType,origin}`、originは
  `operation_manifest|framework_fixed`。Schedule recordはexact
  `{name,operationType,cron,timezone,strategy}`でoriginを持たない。
- Specification 105のcurrent A1 tableをhard-codeする。Command name/aliasは
  1..256 bytes、aliases 0..32、description 0..256。Route methodは1..16 ASCII、
  pathは1..2048、nullable operationTypeは1..256、nullable strategyは1..1024。
  Schedule name/typeは1..256、cronはvalid 5-fieldかつ1..256、timezoneは
  UTC/IANAかつ1..128、strategyは1..1024。exact null/origin、NFC/control、sort、
  duplicate、separator、cap/cap+1をHistorical proseへ依存せず検証する。
- Stored bytesはRFC 8785相当のsupported closed scalar domainをcanonical key
  orderでencodeしたUTF-8 JCS＋exact one LF。floatはwireに存在せず拒否する。
- `ENC`、`buildSetHash`、`inputHash`、raw `sha256`をSpecification 105どおり
  実装する。
- Rootは64 KiB inclusive、各projectionは16 MiB inclusive、recordsは0..4096。

### Filesystem and locking

- D146のtrusted-root／pre-open `lstat`／single `fopen`／pre-I/O `fstat`＋
  post-open `lstat` device/inode/type equalityを実装する。kernel
  `O_NOFOLLOW`をclaimしない。
- Generation basenameはexact `^(command|route|schedule)-[0-9a-f]{64}\.json$`、
  tempはexact
  `^\.blackops-projection-build-set-(command|route|schedule|root)-[0-9a-f]{32}\.tmp$`。
  16-byte nonceのexclusive-createはroleごとに最大16回試行する。
- Existing generationはverified regular＋exact canonical bytes/digest一致だけreuseし、
  mismatch／symlink／specialはroot publish前にfailする。既存targetを上書きしない。
- Readerはlockのshared `flock`取得後にrootと全3 fileをopen/validateし、callback
  またはtyped resultの利用完了までlockを保持する。non-selected fileも必須。
- Publisherはexact3 typed inputsを要求し、exclusive `flock`取得前にtemp／
  generation／rootを書かない。
- Projection filesをfsync＋atomic rename、directory fsyncした後、root pathを最後に
  atomic replace＋directory fsyncする。既存root参照generationは上書きしない。
  ここをcommit pointとする。
- Missing/symlink/directory/FIFO/socket/device/replacement、partial kind、
  mixed build、wrong digest/input/self-hash、root-before-fileをfail closedにする。

### Bounded GC

- `MAX_DIRECTORY_ENTRIES=256`、`MAX_GC_DELETIONS=32`をinclusive boundとする。
  `.`／`..`以外のroot／lock／unknown entryもscan countへ含め、257th sentinelだけで
  overflowを検出する。
- 最初のtemp write前に`current entries + absent generation targets +
  (root absent ? 1 : 0) <= 256`と、existing-root replacement用slotを含む
  `projected committed + (root exists ? 1 : 0) <= 256`を要求する。GC後の削減を
  publication成立条件に使わず、temp write/renameはsequentialに行う。
- 新root publish後に削除対象となるrecognized stale generation/temp集合を、
  最初のwrite前に算出しunsigned-byte sortする。bound超過はbootstrap lock以外の
  mutation前にfailする。
- Unknown fileは削除しない。new current rootが参照する3 fileとlock/rootは削除しない。
- GCはexclusive lock内だけでcurrent root publish後に行い、削除後directory fsyncする。
  Post-commit GC failureは`published=true,cleanupComplete=false`を返し、rollbackしない。

## Test Matrix

最低限、次をnew focused testsで証明する。

1. root／3 projection exact positive、hash golden、LF/BOM/CRLF、cap/cap+1。
2. exact kind/field/origin、unknown/missing/duplicate/wrong type、sort、NFC、
   control、separator、alias count、record count、field cap/cap+1。
3. command origin、route origin、schedule no-originの交差negative。
4. root self-hash、descriptor raw digest、input hash、build ID、single-basename、
   prefix/hash binding、slash/backslash、swapped/duplicate kind/path、non-selected mismatch。
5. missing root、unreadable I/O、static symlink、directory、FIFO/special fixture、
   pre/post stat replacement、outside-root read/write sentinel。special fixtureは
   blocking open前にrejectする。
6. shared-reader/exclusive-publisher exclusion、lock replacement、reader lock lifetime。
7. complete three-input publish、partial-input mutation zero、existing exact reuse、
   existing mismatch/symlink/special fail、old-root/new-root only、root-last crash points、
   fsync/rename order、post-commit GC failure result。
8. GC current-ref preservation、recognized/near-miss table、unknown preservation、
   lock absent/present × entry `255/256/257`、projected committed count cap/cap+1、
   delete `32/33`、failure directory listing/hash。
9. No Provider、container、DB、handler、source reflection、existing manifest readerを
   呼ばないsentinel。

Test seamはfilesystem operation order／crash pointを観測できるbounded internal
interfaceとし、Production CodeへTask IDやtest-only branchを埋め込まない。

## Verification Strategy

反復中はFocused Gateだけを使い、Source freeze後にFinal Gateを1回行う。

### Fast Gate

```bash
docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection/BuildSet
docker compose run --rm app mago format --check src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src/Internal/Projection/BuildSet tests/Internal/Projection/BuildSet --glob '*.php'
git diff --check
```

### Final Gate

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/version-baseline.sh
mise exec -- pnpm --dir docs/website run release:check:source
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

### Historical Final Gate Result

The prior Final Local Gate was Green on the then-frozen Source/Test aggregate
`604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed`:

- Focused PHPUnit: `32 tests / 167 assertions` PASS.
- Full PHPUnit: `2353 tests / 9722 assertions`, exit 0; Deprecations 1,
  PHPUnit Deprecations 2, Notices 13.
- Composer strict validation and full format PASS.
- Full Mago lint PASS with baseline `186` excluded; baseline verification PASS.
- Full Mago analyze exit 0 with 0 errors (`108 warnings/help`).
- Deptrac: Allowed `4849`; violations, skipped, uncovered, warnings, and errors
  all `0`.
- Version baseline: published `1.2.0`, historical `1.1.0`, PASS.
- Website release source check, PHP management-ID guard, and `git diff --check`
  PASS; staged count `0`.

No Source or Test files changed after the final freeze. Website full test/build/
Browser, network, commit, stage, push, CI, deploy, publication, tag, and release
remain Not Verified or unexecuted by scope and instruction. Stable `1.2.0`
remains current and BlackOps `1.3` remains unreleased. This entire gate record is
Historical after the Sol-requested correction and must be rerun from the new
Source/Test freeze.

Website full test／build／Browserはpublic SourceとWebsite codeが不変なので実行しない。
実行しなかったCommandはReportへNot Verifiedと理由を記載する。

## Acceptance Criteria

- [x] D146とSpecifications 105/106のcurrent authorityを実装境界として維持する。
- [x] Injected locatorとfixed root/lock namesが実装され、config／manifestから推測しない。
- [x] Exact root／descriptor／3 projection wire、canonical bytes、hashes、boundsを
  positive/negative testsが閉じる。
- [x] Readerがshared lock下でroot＋全3 filesを同一handle検証し、partial setを受理しない。
- [x] Publisherがexclusive lock取得後だけwriteし、3 files→directory fsync→root-last→
  directory fsync→bounded GCの順を守る。
- [x] Static symlink/special/replacement、mixed generation、crash point、GC boundを
  focused testsがfail closedにする。
- [x] Existing Production/public/docs/Stable behaviorはA1の許可範囲外を変更せず、A1 componentは
  Kernel／build／configへ未接続である。
- [x] Focused/Fast GateがPASSし、未実行項目がReportでNot Verifiedになっている。
- [x] Final GateがPASSし、未実行項目がReportでNot Verifiedになる。
- [x] Sol xHighがread-only reviewで`P1=0/P2=0/P3=0`を返す。
- [x] Report／STATE／TODO／Spec READMEが実結果、残り工程、Next Actionへ同期する。

## Worker Handoff

Sol preflight reviewがGreenになった後、Luna Max workerはFiles Allowedだけを変更し、
Fast Gate、Source freeze、Final Gateの順に実行する。Review前にcommit／stage／push／
CI／deploy／releaseを行わない。範囲外変更が必要なら実装を広げずReportへ返す。

## Remaining Steps

P23-002A1の残りはない。上位GoalではP23-002A2、P23-002B、P23-002C
atomic activation、後続Phase 23 Task、P23-006が残る。

## Suggested Next Action

P23-002A2 command/list layerのTask Packetを作成し、A1を再実装せず、
A/B/Cのatomic acceptance境界を維持してpreflight reviewへ渡す。
Commit、stage、push、CI、deploy、publication、tag、releaseは未承認のままとする。
