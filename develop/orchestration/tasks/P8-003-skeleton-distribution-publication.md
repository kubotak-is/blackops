# P8-003: Skeleton Distribution Publication

Status: Ready for Review

## Goal

Framework Release Tagから`examples/quickstart/`を決定的にSplitし、同一TagでPublic `kubotak-is/blackops`の`main`へPublishできるGitHub Actions Workflow、Local Validation、Credential／Packagist運用境界を実装する。

## In Scope

- Bare SemVer Release Tag Validation
- Framework Tag／Skeleton Framework Constraint整合Guard
- Committed Quickstartからの決定的なGit Subtree Split
- Distribution Root Allowlist／Metadata／Generated State Validation
- Split Commitと同一Version Tag生成
- Local Publication Dry Run
- GitHub Actions Release Workflow
- Deploy Key Secret `SKELETON_DEPLOY_KEY`接続
- `kubotak-is/blackops` `main`へのCommit／Tag Push
- Existing Remote Branch／Tagのfail-closed検証
- Local create-project／Consumer／Full Quality Gate
- Packagist GitHub連携境界Documentation
- External Repository／Deploy Key／Secret／Packagist状態のOrchestrator検証

## Out of Scope

- Framework Main RepositoryのGitHub Remote作成またはHistory Push
- Packagist API TokenのRepository Secret登録
- Packagist APIの直接呼出
- Framework Release Notes／Stable 1.0.0 Release
- Remote `composer create-project`によるP8-004 Closeout
- Distribution Repositoryへの手動機能Commit

## Relevant Specifications and Decisions

- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/decisions/073-skeleton-distribution-publication-boundary.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/52-phase-8-delivery-plan.md`

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `docs/guide/README.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/installed-application-status.md`
- `docs/internals/mvp-e2e.md`
- `docs/internals/development-setup.md`
- `docs/internals/skeleton-publication.md`
- `docs/internals/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-003-skeleton-distribution-publication.md`
- `develop/orchestration/reports/P8-003-skeleton-distribution-publication.md`
- `develop/STATE.md`

許可外修正が必要なら実装を広げずReportのBlockerへ記録する。Credential、Private Key、Token、Generated Split TreeをRepositoryへ保存しない。

## Distribution Contract

- Repository: `git@github.com:kubotak-is/blackops.git`
- Public URL: `https://github.com/kubotak-is/blackops`
- Default Branch: `main`
- Visibility: Public
- Package: `blackops/skeleton`
- Source: `examples/quickstart/`
- Secret: `SKELETON_DEPLOY_KEY`
- Packagist: GitHub Tag連携。WorkflowからAPIを呼ばない

Distribution `main`とRelease TagはWorkflow生成物だけを受け取る。Remote Repositoryへ直接機能変更しない。

## Release Trigger and Version Contract

- GitHub Actionsはbare SemVer Tag pushで起動する
- Versionは`MAJOR.MINOR.PATCH`だけを受理する
- Skeleton TagはFramework Trigger Tagと完全一致する
- `examples/quickstart/composer.json`の`blackops/framework` ConstraintがRelease Major／Minorを許容する
- Composer Metadataに`version`／`repositories`を追加しない
- Existing Remote Tagが同一Split Commit以外を指す場合は失敗する
- Remote `main`がCurrent ReleaseのPrevious SplitからFast-forward不能なら失敗し、force pushしない

## Split and Validation Contract

Local Publication ScriptはVersionとSource Refを受け、Temporary Directoryだけで次を行う。

1. Source Refとbare SemVerを検証する
2. `git subtree split --prefix=examples/quickstart`相当のSplit Commitを生成する
3. Split Rootへ`composer.json`、`README.md`、`bin/setup`等があることを確認する
4. Package Name／Type／PHP／Framework Constraint／Post-createを検証する
5. `composer.lock`、`vendor/`、`.env`、Generated Artifact、Path Repository、Version Field、Repository内部Fileがないことを検証する
6. Split RootでComposer strict validationを行う
7. 同一Version TagをSplit Commitへ作成できることを検証する
8. Temporary Clone／Worktree／TagをCleanupし、Main Working TreeとDocker Resourceを変更しない

Dry RunはRemote Push、Secret参照、Packagist Mutationを行わない。

## Workflow Contract

Workflowは次の順でfail closedに実行する。

1. Full CheckoutとRelease Tag Validation
2. Composer／Mago／Full PHPUnit／Deptrac
3. Quickstart Consumer E2EとSkeleton Local Create-project Smoke
4. Publication Dry RunとSplit Root Validation
5. `SKELETON_DEPLOY_KEY`を一時SSH Fileへ展開し、Permissionを限定
6. GitHub Host Keyを検証済みKnown Hostsへ設定
7. Remote `main`／同名TagをFetchしてFast-forward／Idempotencyを検証
8. Split Commitを`main`へPush
9. 同じbare SemVer TagをSplit CommitへPush
10. Job終了時に一時Key、Clone、ArtifactをCleanup

Workflow LogへSecret、Private Key、Credentialを出力しない。Packagist APIは呼ばない。

## External Orchestrator Actions

Local実装受入後、Orchestratorが次を実施またはBlockerとして返す。

1. Public `kubotak-is/blackops`をDefault Branch `main`で作成
2. Repository専用Ed25519 Deploy Key Pairを一時領域で生成
3. Public KeyをDistribution RepositoryへWrite-enabled Deploy Keyとして登録
4. Private KeyをMain Repository Secret `SKELETON_DEPLOY_KEY`へ登録
5. Local Private Keyを削除し、Repositoryへ未混入であることを確認
6. Packagist `blackops/skeleton`をDistribution URLへ接続し、GitHub連携を有効化

GitHub／Packagist認証がない場合、Local実装を完了して明確なBlockerで停止する。

## Acceptance Criteria

- [x] Bare SemVer以外を拒否する
- [x] Framework Tag／Skeleton Constraint不整合を拒否する
- [x] Quickstartだけの決定的なSplit Commitを生成する
- [x] Distribution Root Metadata／Allowlist／Generated State Guardが成功する
- [x] Split CommitへFrameworkと同じTagを作成できる
- [x] Local Dry RunがWorking Tree／Docker／External Stateを変更しない
- [x] WorkflowがFull Quality／Consumer／Create-project Gate後だけPushする
- [x] WorkflowがDeploy Key Secretを一時利用しLog／Artifactへ残さない
- [x] Remote Branch／Tag Divergenceをforceせず拒否する
- [x] Packagist API Token／直接API CallがWorkflowにない
- [x] Public Repository／Deploy Key／Secret／Packagist境界がDocumentedである
- [x] Mago、Full PHPUnit、Deptrac、Composer、Consumer、Publication Guardが成功する
- [x] Report／Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
! rg -n 'packagist.*(token|api)|PACKAGIST|api\.packagist' .github tests docs --glob '*.yml' --glob '*.yaml' --glob '*.sh' --glob '*.md'
! rg -n 'BEGIN (OPENSSH|RSA|EC) PRIVATE KEY|github_pat_|ghp_' . --hidden --glob '!.git/**' --glob '!develop/**'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P8-003-skeleton-distribution-publication.md`へ次を記録する。

- Summary
- Split／Version／Constraint Evidence
- Workflow／Credential Boundary Evidence
- Local Publication Smoke Evidence
- External Repository／Deploy Key／Packagist Status
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
