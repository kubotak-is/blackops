# P9-005: Continuous Integration and Root README

Status: Follow-up CI In Progress

## Goal

通常のPush／Pull RequestでMago、PHPUnit、Deptracを実行するGitHub Actions CIを追加し、Repository Root READMEをFramework利用者とContributorの入口として整備する。

## In Scope

- `main` Push／Pull Request用の独立CI Workflow
- Composer ValidationとDependency Install
- Mago Format Check／Lint／Analyze
- Full PHPUnit
- Deptrac
- 最小権限、重複Run Cancel、Docker Resource Cleanup
- Root READMEのCI Badge、概要、Install、Quickstart、Generator、Development、Documentation、Status、License
- Development SetupのWorker Model記載同期
- Workflow構文／実コマンド検証
- Report／STATE更新

## Out of Scope

- Release／Skeleton Publication Workflowの再設計
- Consumer E2Eの通常CI追加
- Branch ProtectionのRemote設定
- GitHub Actions Cache最適化
- Production Code変更
- Phase 10 Documentation Website

## Relevant Specifications and Decisions

- `develop/spec/40-mvp-delivery-plan.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/decisions/048-implementation-orchestration.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`

## Files Allowed to Change

- `.github/workflows/ci.yml`
- `README.md`
- `docs/internals/development-setup.md`
- `develop/orchestration/tasks/P9-005-continuous-integration-and-readme.md`
- `develop/orchestration/reports/P9-005-continuous-integration-and-readme.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、Reportへ記録する。

## Constraints

- CIはCredential／Token／Secretを要求しない
- Pull Requestから安全に実行できる`contents: read`最小権限とする
- RepositoryのDocker Compose／PHP 8.5／PostgreSQL 18環境を再利用する
- Release Tag Publicationと通常CIを分離する
- READMEはMain BranchのPhase 9実装とPackagist公開済み`1.0.0`を混同しない
- Production Readyを宣言しない
- Comment／DocBlockへ管理番号を書かない

## Acceptance Criteria

- [ ] `main` PushとPull RequestでCIが起動する
- [ ] CIがComposer Validation、Mago、Full PHPUnit、Deptracを実行する
- [ ] CIが最小権限、同一Ref重複Run Cancel、always Cleanupを持つ
- [ ] READMEからCI状態、Install、Quickstart、Generator、開発Command、詳細Documentationへ到達できる
- [ ] READMEが公開版とMain Branchの状態差を明示する
- [ ] Development SetupのWorker ModelがAGENTS／D077と一致する
- [ ] Workflow YAMLとCI内の実コマンドが成功する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/ci.yml").read_text())'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P9-005-continuous-integration-and-readme.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
