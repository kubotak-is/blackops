# P22-004H: PR #9 CI Contract Correction

Status: Documentation Review Passed, Commit Approved

## Goal

公開済みExperimental Stable `1.2.0`のimmutable Framework／Skeleton／Packagist／GitHub Releaseを変更せず、PR #9 head `f9e893974b29c9c81a7f206dbd2b187a600ed232`で判明した3件のP1を限定修正し、Documentation closeoutをall-Greenで統合可能にする。

## Trigger Evidence

- PR: `https://github.com/kubotak-is/blackops/pull/9`
- CI run: `31916870322`
- Failed runtime job: `95090063583`
- Documentation delivery run: `31916870338`
- Failed documentation build job: `95090063505`
- Runtime reproduction: `3332fd1..f9e8939`のrelease-runtime対象差分は`examples/quickstart/README.md`だけで、現ConsumerがDocumentationをruntime driftとして拒否した
- Documentation failure: Astro Fontsが`fonts.gstatic.com`のIBM Plex Sans URLを取得しHTTP `404`。同URLは2026-08-16T09:20:12Zのread-only HEAD確認でも`404`
- Initial final Documentation re-review: P1=3／P2=0／P3=0。Subsequent P2=3 guard correction is implemented, and corrected final re-review returned P1=0／P2=0／P3=1; exact Commit／PR #9 Push is permitted while merge remains prohibited until same-SHA Green

## In Scope

- Manual Recovery harness復元を、function境界内loop／checkout、release blob equality、EXIT trap、直接復元、復元後equality、trap解除の順でfail closedに固定する
- 空function、function外checkout、復元hash検査削除、復元前trap解除を静的negative fixtureが拒否する
- `examples/quickstart/README.md`だけをrelease-runtime Source判定から厳密に分離し、`src`、`composer.json`、QuickstartのREADME以外、`resources`、`migrations`のdrift拒否を維持する
- README-only差分PASSと実runtime差分FAILを静的またはdisposable fixtureで証明する
- Documentation Website buildからbuild-time外部Google Font URL依存を除去し、exact Blume `1.3.0`のlocal font variantsとRepository内licenseへ移行する
- clean cache相当のWebsite buildとartifact font解決を回帰検査する
- generated configはexactly two `fontProviders.local()` callsだけを許可し、non-local providerとremote `@font-face` URLを拒否する。Raw／emitted font assetsとUbuntu-Font-Licence-1.0 licenseはexpected SHA-256／titleで検証する
- PR #9 failure、Correction、再Review／CI状態をTask／Report／STATE／TODO／Specification／parent／P22-004Gへ同期する

## Out of Scope

- Framework／Skeleton Production PHP Sourceの変更
- 公開済み`1.2.0` Tag、GitHub Release、Packagist metadata、Skeleton remoteの変更または再発行
- `operation:inspect` root-owned journal limitationの修正
- Documentation Website production deployment
- CI failureのskip／waive／allow-failure、blind rerun
- Blume／Astroの無関係なupgradeまたはWebsite redesign

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/framework-update-runtime.sh`
- `tests/Consumer/version-baseline.sh`
- `docs/website/blume.config.ts`
- `docs/website/theme.css`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/public/fonts/**`
- `docs/website/public/licenses/**`
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/tasks/P22-004H-pr9-ci-contract-correction.md`
- `develop/orchestration/reports/P22-004H-pr9-ci-contract-correction.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Commit前にOrchestratorとDocumentation Reviewerの独立Reviewを受ける
- Runtime equalityの除外はexact `examples/quickstart/README.md`だけとし、directory全体や任意Markdownを除外しない
- Manual Recoveryはimmutable release checkoutを維持し、dispatch SHAからoverlayするのはreviewed Consumer harnessだけとする
- Restore guardはWorkflowの現在の正常動作だけでなく、壊れたfixtureを拒否して検査強度を証明する
- Font修正はbuild-time networkを不要にし、生成artifact内の参照先が存在することを検査する。外部Font URLを別URLへ置換するだけでは受入れない
- Font binaryを追加する場合、再配布LicenseをRepositoryへ含める
- 公開済みRelease evidenceをSource変更後のrelease evidenceとして再利用しない。今回の変更はcloseout deliveryだけで、再publicationしない
- Website production deploymentを実行しない
- WorkerはReview前にCommit／Pushしない

## Acceptance Criteria

- [x] Manual Recovery restore function内の全harness checkoutとrelease blob equality、trap設置／直接復元／復元後equality／trap解除が順序付きでguardされる
- [x] restore空化、function外checkout、復元hash削除、早期trap解除のnegative fixtureが失敗する
- [x] README-only差分はrelease-runtime equalityを壊さず、Quickstart runtime fileまたは他runtime Source差分は失敗する。Workflow／Runtime Consumerの広域・追加除外もnegative fixtureで失敗する
- [x] Framework annotated／peeled tag equalityとimmutable Source `3332fd1`は維持される（Workflow／Consumerの既存guardを保持）
- [x] Website buildは外部Google Font fetchなしで成功し、生成configがlocal provider/pathのみ、artifactがemitted local referencesとlicenseを検証する
- [x] Website test／check／build、version baseline、Mago format、PHP management-ID guard、diff／scope checkがPASSする
- [x] Orchestrator independently ran the full Framework update runtime Consumer and reported PASS（stable `e3df5576...`／candidate `3332fd1...`、11 migrations、both provider lanes）
- [x] P22-004H ReportとSTATE／TODO／Spec／parent／P22-004GがPR #9のfailureとcurrent correction stateへ同期する
- [x] Orchestrator ReviewがP1=0／P2=0を確認する
- [x] Documentation ReviewがP1=0／P2=0／P3=1を確認し、non-blocking wording P3をCommit前に修正する
- [ ] 新CommitをPR #9へPush後、CIとDocumentation deliveryが全Greenになるまでmergeしない
- [x] Completion handoffが残り工程または明示的noneと一つのNext Actionを記載する

## Required Commands

```bash
bash -n tests/Consumer/framework-update-runtime.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
mise exec -- pnpm --dir docs/website test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
git diff --check
```

長時間`framework-update-runtime.sh`の完全実行は、変更したrelease-runtime判定をdisposable focused fixtureで先に証明したうえで、OrchestratorがDocker状態と所要時間を評価して実行する。未実行の場合はReportへ理由を明記する。

## Expected Report

`develop/orchestration/reports/P22-004H-pr9-ci-contract-correction.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。Task完了と上位Goalの残工程を分離する。
