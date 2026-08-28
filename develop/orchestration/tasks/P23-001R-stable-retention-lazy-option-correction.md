# P23-001R: Stable Retention Lazy Option Correction

Status: Accepted — Stable 1.2 five-option Retention contract restored

Updated At: 2026-08-26T14:14:17+09:00
Accepted At: 2026-08-26T14:14:17+09:00

## Current Correction Checkpoint

Initial read-only Sol Documentation Review returned Changes Requested:
`P1=0/P2=1/P3=0` (Historical input). The P2 root was that
`assertProductFramingArtifactContract` aggregated CLI and Retention artifact
surfaces before requiring the five-option current truth. Removing the current
boundary, fallback, or option from one HTML/Search/raw/LLM surface could pass
when another surface supplied the marker; production Retention Search/LLM
content exists only inside `search-all`／`llm-full-all`, so CLI content could
also substitute for the Retention page.

The bounded correction changes only
`docs/website/scripts/product-framing-contract.mjs` and
`docs/website/tests/product-framing-contract.test.mjs`. CLI HTML/raw/search/LLM
surfaces are now validated independently after visible normalization, and the
Retention HTML/raw/search/LLM lanes bind to their own fixture aliases or the
production page/raw/search-all/llm-full-all alternatives. Search JSON is parsed
fail-closed for exactly one `/database/retention` string-content route; the LLM
artifact is parsed fail-closed for exactly one correctly sourced `# Retention`
segment. Negative fixtures cover all eight lanes and the current boundary,
omission fallback, and idempotency option; production-shaped aggregate
positive and missing/wrong/duplicate route/segment cases are included.

The correction closes that technical root. After the exact-five-file management
sync, the final read-only Sol Documentation re-review returned
`P1=0/P2=0/P3=0`; Orchestrator review also confirmed the exact scope, hashes,
post-correction evidence, and frozen paths. P23-001R is Accepted. Stable
`1.2.0` remains current and BlackOps `1.3` remains unreleased.

Started At: 2026-08-26T13:00:33+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

Branch: `agent/p23-001-blackops-1-3-feasibility`

## Goal

Stable `1.2.0`で公開済みの`retention:plan`と`retention:purge`について、
実Commandが持つ`--idempotency-record-days`を外側のLazy Command Definition
にも追加する。`help`と実行時の入力Bindingを一致させ、Project Rootから5つの
Retention期間を明示できる状態へ戻す。

このTaskは既存Stable契約のBounded Correctionである。BlackOps `1.3`機能、
AuditRecord、Command名、Retention Policyの意味は追加または変更しない。

## Why

`RetentionPlanCommand`と`RetentionPurgeCommand`は
`--idempotency-record-days`を定義してPolicyへ渡す。一方、
`ApplicationConsoleKernel`のLazy wrapperは4つの期間Optionだけを公開するため、
Project RootのConsoleではSymfonyの入力Bindingが実Command生成前に第5Optionを
拒否する。Stable `1.2.0`の公開Guideと内部Retention契約は5対象を説明しており、
現行Guideはこの実装欠落へ合わせて一時的に4Optionへ狭められている。

## Release and Documentation Boundary

- Release Authority tuple: current Stable `1.2.0`; Framework tag `1.2.0`,
  direct ref `00e8c5875047a3c47acbebfe57f75b0e581d18b9`, peeled source
  `3332fd1dd0738fc7e79750facd93d49a59054ecf`; Skeleton tag `1.2.0`,
  direct ref `fedcfda5f39caf320ad67196e8ced459176cedb1`, peeled source
  `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- Capability ID: `framework-core`.
- Public Source inventory: `docs/guide/project-cli.md` and
  `docs/guide/retention.md` only.
- Public routes remain `/reference/project-cli` and `/database/retention`;
  H1、Slug、Navigation、Redirectは変更しない。
- Before classification: the current four-option wording is a temporary
  Stable defect description and must be removed from current Source and
  generated reader surfaces. It is not a historical allowlist entry.
- After classification: both commands accept five explicit period Options;
  omitting `--idempotency-record-days` preserves the existing fallback to the
  longest of the four base periods or Application configuration.
- BlackOps `1.3` remains unreleased. No capability, tag, version, Package,
  Skeleton, release authority tuple, or roadmap state changes.

## Files Allowed

1. `src/Internal/Application/ApplicationConsoleKernel.php`
2. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
3. `docs/guide/project-cli.md`
4. `docs/guide/retention.md`
5. `docs/website/scripts/product-framing-contract.mjs`
6. `docs/website/tests/product-framing-contract.test.mjs`
7. `docs/website/tests/reader-experience.test.mjs`
8. `tests/Consumer/version-baseline.sh`
9. `develop/orchestration/tasks/P23-001R-stable-retention-lazy-option-correction.md`
10. `develop/orchestration/reports/P23-001R-stable-retention-lazy-option-correction.md`
11. `develop/STATE.md`
12. `develop/TODO.md`
13. `develop/spec/README.md`

Existing worktree changes outside these paths belong to the accepted P23-001
snapshot and must remain byte-identical. Generated Website artifacts are not
edited directly.

## Out of Scope

- Retention calculations、default/fallback semantics、Purge transaction、Audit
  behavior、Scheduler behaviorの変更
- AuditRecordまたはBlackOps `1.3` implementation
- Command rename、new alias、new JSON wire、configuration schema change
- Dependency、lockfile、Migration、Skeleton、Example、Release Authority change
- Commit、stage、push、PR、CI、deploy、publication、tag、release
- Full Website testの反復実行。Source確定前はfocused testsだけを使う

## Implementation Requirements

1. 共通Lazy retention definitionへ`idempotency-record-days`を
   `InputOption::VALUE_REQUIRED`として追加し、Plan／Purgeの両方へ同じ定義を渡す。
2. Fresh Console Kernelから両CommandのHelpに第5Optionが一度だけ現れることを
   testする。
3. Fresh Console Kernel経由で両Commandへ第5Optionを渡したとき、Unknown Option
   にならず実CommandへBindingされることをtestする。Planは安全なread-only fixture、
   Purgeは`--dry-run` fixtureを使い、Purge mutationを起こさない。
4. Option省略時の既存fallbackと、直接Command testの既存5対象semanticsを保持する。
5. CLI ReferenceとRetention How-toを5Optionへ同期する。読者が明示Optionと省略時
   fallbackを区別できる日本語にする。
6. Website Source／Artifact guardを、5Optionをrequired current truth、旧4Option限定
   claimをnegative fixtureとしてfail closedにする。
7. version baselineを新しいStable correctionへ同期する。Stable `1.2.0` currentと
   BlackOps `1.3` unreleasedの境界を変えない。
8. Production Code／TestのCommentやDocBlockへTask／Spec／Decision IDを書かない。

## Verification Strategy

反復中は次のfocused gateだけを使う。Sourceが確定した後にFinal Gateを一度だけ行う。

### Fast Gate

```bash
docker compose run --rm app vendor/bin/phpunit \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Console/RetentionPlanCommandTest.php \
  tests/Internal/Console/RetentionPurgeCommandTest.php
mise exec -- pnpm --dir docs/website exec node --test --test-isolation=none \
  --test-name-pattern='Retention|retention' \
  tests/product-framing-contract.test.mjs \
  tests/reader-experience.test.mjs
mise exec -- pnpm --dir docs/website run release:check:source
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
git diff --check
```

### Final Gate

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run release:check:artifact
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

`build`がsandbox listener制約だけで停止した場合は、同一Commandのapproved rerunを
行い、製品Failureと環境制約を区別する。Full Website testは、focused test、check、
build、Artifact gateで対象Surfaceが閉じない場合だけ追加実行する。

## Acceptance Criteria

- [x] `retention:plan`と`retention:purge`の外側Definitionが5期間Optionを公開する。
- [x] 両CommandのHelpとKernel経由Bindingを回帰testが証明する。
- [x] Plan／Purgeの既存Retention semanticsと省略時fallbackが変わらない。
- [x] `project-cli.md`と`retention.md`が5Optionとfallbackを正確に説明する。
- [x] Source、HTML、Search、raw Markdown、LLM Artifactのcurrent truthが一致し、
  旧4Option限定claimはnegative fixtureで拒否される。
- [x] Fast GateとFinal GateがPASSし、未実行項目はReportでNot Verifiedになる。
- [x] Documentation ReviewerがStable公開契約をread-onlyで確認し、
  `P1=0/P2=0/P3=0`を返す。
- [x] Report、STATE、TODO、Spec READMEが実際の結果、Remaining、Next Actionへ同期する。

## Historical Worker Handoff Before Guard Correction

Luna Max workerはFiles Allowedだけを変更し、Fast GateとSource確定後のFinal Gateを
実行した。PHPUnitは30 tests／192 assertions、Retention focused Website testsは
3/3、Source／Artifact／version baseline、Mago、Website check/buildをPASSとして
Reportへ記録した。最初のformat checkは許可済みkernel testの整形だけを検出したため
bounded patchで修正し、Final Gateの再実行をPASSした。Review前にcommit、stage、
push、CI、deploy、releaseは行っていない。

## Final Correction Handoff

The guard correction is complete in the exact two implementation/test files;
the current management synchronization is limited to this Task, Report,
STATE, TODO, and Specification README. Post-correction evidence is retained
by hash: focused Retention tests `5/5`, Source guard PASS, Website check `41`
pages, build `42` pages with static artifact PASS and site `41` PASS, Artifact
guard PASS, version baseline `published=1.2.0`／`historical=1.1.0`, and
`git diff --check` PASS. PHPUnit `30/192` and Mago are guard-only evidence from
before this correction and are not claimed as rerun. Generated artifacts were
not edited; commit, stage, push, CI, deploy, publication, tag, and release were
not performed.

## Remaining Steps

P23-001R内の残工程はない。Full Website test、Browser、same-SHA CI、deploy、
publication、tag、release、commit、stageはTask範囲外のためNot Verified／未実行。
上位BlackOps `1.3` GoalにはSpecification 106のordered Phase 23 implementation、
P23-002A/B/C atomic projection slice、後続Task、P23-006 release gateが残る。

## Suggested Next Action

Specification 106に従い、P23-002A/B/C atomic projection sliceのうち
P23-002A Task Packetを開始する。Commit、stage、push、CI、deploy、releaseは
別途明示的に許可されるまで行わない。
