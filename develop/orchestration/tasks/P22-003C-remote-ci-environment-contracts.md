# P22-003C: Remote CI Environment Contract Correction

Status: Accepted

## Goal

Draft PR #3でexact candidate `577cc224e0628ccbb9d91027ca214a4625a5228a`へ初めて得たsame-SHA GitHub Actions evidenceが露出した3つのCI environment contract gapを、Runtime behavior／Public API／Guide本文を変更せずに閉じる。Correction CommitをP22-003 replacement candidateとし、完全なLocal Gateとsame-SHA Remote CIを最初から再取得できる状態へ戻す。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/decisions/140-release-quality-tooling-baseline.md`
- P22-003 Task／Report
- Draft PR #3, CI run `31693960894`, Documentation delivery run `31693960994`

## Remote Failure Evidence

1. `Stable 1.1 to candidate 1.2 runtime consumer` failed while creating the annotated local `1.2.0` tag because the disposable Framework Git repository had no tagger identity.
2. `Mago, PHPUnit, and Deptrac` failed in `version-baseline.sh` because its default shallow checkout did not contain annotated Stable tag `1.1.0`.
3. CI Website and Documentation delivery failed the same `guide-code.test.mjs` PHP lint because the test prepended `<?php` to complete Outbox examples that already begin with `<?php`.

Community Board clean install／full-stack and Frontend passed. Documentation deployment jobs were skipped. The direct `main` Push was rejected without remote mutation by the PR-required Repository Rule and must not be retried.

## In Scope

- Runtime ConsumerがRepository／global identityに依存せず、disposable candidate repositoryだけでdeterministic annotated local tagを作成するbounded identity contract
- CI Quality checkoutがStable annotated tagを含むfull historyを取得する設定
- Guide PHP syntax testがopening tagを持つcomplete exampleと持たないfragmentの両方を一つだけのopening tagでlintするnormalization
- Focused regression／static verification、Task Report、STATE checkpoint

## Out of Scope

- Production PHP、Runtime behavior、Public API、Database Schema、Migration、Guide本文の変更
- Workflow job／quality commandの削除、skip、allow-failure、retry、severity downgrade
- Stable tag、Candidate version、Mago baseline、Deptrac rules、package export contractの変更
- PR merge、`main` Push再試行、Tag、Release、Packagist、Skeleton publication、Documentation deploy
- P22-003 AcceptanceまたはP22-004開始

## Files Allowed to Change

- `.github/workflows/ci.yml`
- `tests/Consumer/framework-update-runtime.sh`
- `docs/website/tests/guide-code.test.mjs`
- `develop/orchestration/reports/P22-003C-remote-ci-environment-contracts.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Orchestrator Review前にCommitしない
- Git identityはdisposable Framework repository／annotated tag operationへ限定し、global configやuser credentialを変更しない
- CI Quality jobはStable `1.1.0` annotated tagをversion guardが直接検証できるcheckoutを使う
- PHP example normalizationは既存opening tagを削除・重複せず、tagなしfragmentだけへ補う
- Failureをskip／retry／allow-failureへ変えない
- Secret／GitHub Token／Credential値をLog、Report、Repositoryへ書かない
- Source-changing correction後、`577cc224`のLocal Gate evidenceを新Candidateへ再利用しない

## Acceptance Criteria

- [x] Global／system Git identityが利用できない環境でもRuntime Consumerがannotated local candidate tagを作成して全journeyを完走する
- [x] Identity設定がdisposable repositoryへ限定され、global Git configを変更しない
- [x] CI Quality checkoutが`fetch-depth: 0`でStable tag historyを取得する
- [x] `version-baseline.sh`がcurrent repositoryで成功し、Quality jobから同guardを実行する
- [x] Guide PHP syntax testがopening tagあり／なし双方を正規化して全79 website testsを成功させる
- [x] CI／Documentation deliveryのfailed stepをskip、retry、allow-failureへ変更しない
- [x] Shell syntax、Mago format、management-ID guard、`git diff --check`が成功する
- [x] ReportとSTATEが実行結果、未実行Command、残課題を正確に記録する
- [x] WorkerはCommit／Push／PR mutationを行わない

## Required Commands

```bash
bash -n tests/Consumer/*.sh
bash tests/Consumer/version-baseline.sh
GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null bash tests/Consumer/framework-update-runtime.sh
mise exec -- pnpm --dir docs/website test
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Workerは長時間Runtime Consumerを実行できなかった場合、未実行理由をReportへ記録する。Orchestratorはreview後のCorrection Commitを新Candidateとして、P22-003の完全なLocal Gateを別途最初から実行する。

## Expected Report

`develop/orchestration/reports/P22-003C-remote-ci-environment-contracts.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
