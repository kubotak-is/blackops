# P22-003D: Runtime Consumer Executable Git Mode Correction

Status: Accepted — Correction Commit Approved

## Goal

Draft PR #3のexact candidate `96383e1bbe1a0914d1eddc9e1dea160042804f7c`に対するsame-SHA CI run `31719526793`で露出したRuntime ConsumerのGit mode contract gapを、Script本文、Quality guard、Runtime behavior、Public APIを変更せずに閉じる。Reviewed correction CommitをP22-003 replacement candidateとし、complete Local Gateとsame-SHA Remote CIを最初から再取得できる状態へ戻す。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- P22-003 Task／Report
- P22-003C Task／Report
- Draft PR #3, CI run `31719526793`, Quality job `94512672260`

## Remote Failure Evidence

- Quality checkoutはfull historyとStable tagを取得し、Composer strictとexact Framework package exportまでPASSした。
- `version-baseline.sh`は`tests/Consumer/framework-update-runtime.sh`へ`test -x`を要求し、`Runtime consumer must be executable`でexit 1した。
- Git indexは対象fileをmode `100644`として記録している。
- WSL2 working treeはmode `0755`を表示するが、Repositoryの`core.filemode=false`によりLocal GateではGit mode差分として検出されなかった。
- Community Board clean install／full-stack、Frontend、Runtime Consumer、Website、Documentation artifact／PR previewはPASSした。Main production deployはskipped。

## In Scope

- `tests/Consumer/framework-update-runtime.sh`のGit index modeを`100644`から`100755`へ変更する
- Script blob／本文が不変であることを検証する
- Focused Git mode／version guard／shell syntax／Report／STATE checkpoint

## Out of Scope

- Script本文、Production PHP、Runtime behavior、Public API、Workflow、Quality command、Version guardの変更
- Failureのskip、retry、allow-failure、severity downgrade
- PR branch update、workflow rerun、PR merge、`main` Push、Tag、Release、Packagist、Skeleton publication、Deploy
- P22-003 AcceptanceまたはP22-004開始

## Files Allowed to Change

- `tests/Consumer/framework-update-runtime.sh`（Git mode only; blob/content must remain unchanged）
- `develop/orchestration/reports/P22-003D-runtime-consumer-executable-mode.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Orchestrator Review前にCommitしない
- `git diff --raw`が対象fileのmode-only `100644 -> 100755`を示すこと
- `git hash-object`／index blob SHAがcorrection前後で同一であること
- `version-baseline.sh`の実行可能要件を弱めない
- Source-changing correction後、`96383e1`以前のLocal／Remote evidenceを新Candidate Acceptanceへ再利用しない
- Secret／Credential値をLog、Report、Repositoryへ書かない

## Acceptance Criteria

- [x] Runtime ConsumerのGit index modeが`100755`である
- [x] Runtime Consumerのblob／本文が`96383e1`から不変である
- [x] `version-baseline.sh`が成功する
- [x] 全Consumer shell syntax、management-ID guard、`git diff --check`が成功する
- [x] Diffがmode-only correctionとTask Report／STATEに限定される
- [x] ReportとSTATEがRemote failure、commands、未実行項目、complete gate restartを正確に記録する
- [x] WorkerはCommit／Push／PR mutationを行わない

## Required Commands

```bash
git ls-files -s tests/Consumer/framework-update-runtime.sh
git diff --raw -- tests/Consumer/framework-update-runtime.sh
git hash-object tests/Consumer/framework-update-runtime.sh
git rev-parse 96383e1:tests/Consumer/framework-update-runtime.sh
bash -n tests/Consumer/*.sh
bash tests/Consumer/version-baseline.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-003D-runtime-consumer-executable-mode.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
