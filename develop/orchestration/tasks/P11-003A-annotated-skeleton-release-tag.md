# P11-003A: Annotated Skeleton Release Tag

Status: Accepted

## Goal

Skeleton Distributionの新規Release Tagをannotated tagとして生成・公開し、Tag ObjectとPeeled Split Commitを正しく監査できるようLocal Consumer TestとGitHub Actions Publication Workflowを修正する。公開済みLegacy `1.0.0` lightweight tagは移動せず、同じSplit Commitを指すRecoveryだけを維持する。

## In Scope

- Local Skeleton Publication Testのannotated tag生成
- Local TestでTag Object Type、Tag Message、Peeled Split Commitを検証
- Publication Workflowでannotated Skeleton Tag Refを生成・Push
- Remote annotated tagのTag Object／Peeled Commit監査
- New Releaseで同名lightweight tagをSuccessとして扱わないFail-closed境界
- 公開済みLegacy Skeleton `1.0.0` lightweight tagのImmutable Recovery例外
- Manual DispatchとTag Triggerで同じTag Validation／Recovery Contractを使用
- Workflow／ShellのRegression Guard
- Internal Publication Documentation更新
- ReportとSTATE更新

## Out of Scope

- Framework／Skeleton `1.1.0` Tag Push
- Skeleton Distribution Repository更新
- Packagist Mutation
- GitHub Release作成
- Release Version、Compatibility Policy、Skeleton Source変更
- Production Code、Public API、利用者向けDocumentation変更
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/reports/P11-003-release-candidate-gate.md`

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/**`（Publication WorkflowのRegression Guardが別Fileとして必要な場合のみ）
- `docs/internal/skeleton-publication.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/mvp-e2e.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-003A-annotated-skeleton-release-tag.md`
- `develop/orchestration/reports/P11-003A-annotated-skeleton-release-tag.md`
- `develop/STATE.md`

範囲外Fileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。

## Contract

- New Skeleton Release Tagはannotated tag objectである
- Annotated TagのPeeled Commitは決定的なSkeleton Split Commitと一致する
- WorkflowはCommit Objectを`refs/tags/<version>`へ直接Pushしない
- Existing annotated tagはPeeled Commitが同じ場合だけIdempotent Successとする
- Existing annotated tagのPeeled Commitが異なる場合はTagを移動せず失敗する
- Existing lightweight tagは新規Release Contract違反として失敗する
- 例外は公開済みSkeleton `1.0.0`だけとし、Direct Commitが期待Split Commitと一致するManual Recoveryを許可する
- Legacy `1.0.0` Tagをannotatedへ置換、削除、移動しない
- Tagger Identity／MessageはFramework CredentialやUser Local Configへ依存しない
- Private Key展開前にLocal Quality／Consumer／Publication Gateを完了する

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Tag Object IDの再現性をRelease Contractにしない。Peeled Split Commitの決定性をContractとする
- Existing Remote RefはDirect Tag Refと`^{}` Peeled Refを分けて読む
- Credential、Token、Private KeyをSource、Report、Logへ保存しない
- External Stateを変更しない。Local／Temporary Bare Repositoryだけで検証する
- Force Push、Tag Delete、Tag Moveを実装しない
- Workflow修正でLegacy `1.0.0` Manual Recoveryを壊さない
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [x] Local Publication Testがannotated tag objectを生成する
- [x] Local TestがTag Object Type `tag`、所定Message、Peeled Split Commit一致を検証する
- [x] Workflowがannotated Tag RefをSkeleton RemoteへPushする
- [x] Existing annotated tagはPeeled Commit一致時だけIdempotent Successとなる
- [x] Existing annotated tagのPeeled Commit不一致をFail-closedで拒否する
- [x] New ReleaseのExisting lightweight tagをFail-closedで拒否する
- [x] Legacy Skeleton `1.0.0` lightweight tagだけは同一Split CommitならImmutable Recoveryとして許可する
- [x] Legacy `1.0.0` Tagを移動／削除／置換するPathがない
- [x] Manual DispatchとTag Triggerが同じContractを通る
- [x] Workflow／Shell Regression Guardが成功する
- [x] Composer、Mago、PHPUnit、Deptrac、関連Consumer Gateが成功する
- [x] Internal Documentation、Report、STATEが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash -n tests/Consumer/skeleton-publication.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
! rg -n -- '--force|force-with-lease|push.*[[:space:]]-f([[:space:]]|$)' .github/workflows/publish-skeleton.yml tests/Consumer/skeleton-publication*.sh
! rg -n 'BEGIN (OPENSSH|RSA|EC) PRIVATE KEY|github_pat_|ghp_|gho_' . --hidden --glob '!.git/**' --glob '!develop/**'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

Workflow YAML Parse、Tag Object／Peeled Commit／Legacy `1.0.0` Recovery／New lightweight rejectionのRegression CommandもReportへ記録する。

## Expected Report

`develop/orchestration/reports/P11-003A-annotated-skeleton-release-tag.md`へ次を記録する。

- Summary
- Annotated Tag Contract Evidence
- Remote Tag Inspection／Recovery Evidence
- Legacy 1.0.0 Compatibility Evidence
- Workflow／Credential Boundary Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
