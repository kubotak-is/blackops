# P9-005: Continuous Integration and Root README Report

Status: Remote Verified - Checkout Follow-up Pending

## Summary

通常の`main` PushとPull RequestでFramework Quality Gateを実行する`.github/workflows/ci.yml`を追加した。CIはRepositoryと同じDocker Compose環境をBuildし、Composer Dependency Install／Validation、Mago Format Check／Lint／Analyze、Full PHPUnit、Deptracを順に実行する。

Workflow権限は`contents: read`だけとし、同一Workflow／Refの古いRunをCancelする。成功／失敗にかかわらずDocker Compose ResourceをCleanupする。Release TagとSkeleton Publicationは既存の独立Workflowへ残した。

Root `README.md`を追加し、CI／Packagist／PHP／License Badge、Framework概要、公開版とMain BranchのStatus差、公式Install、Quickstart、Phase 9 Project CLI、開発品質Command、Documentation導線、Licenseを記載した。Development Setupに残っていた旧Worker Model表記もAGENTS／D077へ同期した。

## Changed Files

- `.github/workflows/ci.yml`
- `README.md`
- `docs/internals/development-setup.md`
- `develop/orchestration/tasks/P9-005-continuous-integration-and-readme.md`
- `develop/orchestration/reports/P9-005-continuous-integration-and-readme.md`
- `develop/STATE.md`

## Decisions and Assumptions

- 通常CIは`main` Pushと`main`向けPull Requestを対象とする。
- Mago／PHPUnit／Deptracを一つのJob内の独立Stepとして実行し、一度のImage Build／Dependency Installを共有する。
- PHPUnitがPostgreSQL Integration Testを含むため、Host PHP Setupではなく既存Docker Compose環境を正本とする。
- Consumer／Create-project／Framework Update SmokeはRelease Workflowに残し、通常CIの時間をFramework Quality Gateへ限定する。
- Packagist Stable `1.0.0`とMain Branch Phase 9の差をREADMEで明示し、未公開機能をStableで利用可能とは記載しない。
- Production Codeは変更していないためImplementation Workerへの委譲対象外とした。

## Commands and Results

```text
docker compose build app
Result: blackops/framework:dev image built successfully.

docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress
Result: Lock file verified; dependencies installed; autoload generated.

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Both Composer packages are valid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted; no lint or analysis issues.

docker compose run --rm app vendor/bin/phpunit
Result: OK (771 tests, 2544 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 368 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1578 / Warnings 0 / Errors 0.

docker compose down --volumes --remove-orphans
Result: Test container and Compose network removed successfully.

Workflow YAML parse, README relative link target checks, required CI command／trigger／permission／cleanup checks, stale Worker Model guard, management ID guard, git diff --check
Result: All passed.
```

GitHub-hosted CIはWorkflowを含むCommitのPush後に確認する。

## GitHub Actions Evidence

Commit `2d356bc`のPushでGitHub-hosted Run `29233792270`が起動し、1分15秒で成功した。

- Build development image: success
- Install dependencies: success
- Validate Composer packages: success
- Run Mago: success
- Run PHPUnit: success
- Run Deptrac: success
- Clean up Docker resources: success

Run: `https://github.com/kubotak-is/blackops/actions/runs/29233792270`

成功Runに`actions/checkout@v4`のNode.js 20非推奨Annotationがあった。公式`actions/checkout` Latest Releaseが`v7.0.0`であることをGitHub APIから確認し、通常CIを`actions/checkout@v7`へ更新した。Follow-up Runで警告解消と全Gate成功を再確認する。

## Acceptance Criteria

- [x] `main` PushとPull RequestでCIが起動するWorkflow定義を持つ
- [x] CIがComposer Validation、Mago、Full PHPUnit、Deptracを実行する
- [x] CIが最小権限、同一Ref重複Run Cancel、always Cleanupを持つ
- [x] READMEからCI状態、Install、Quickstart、Generator、開発Command、詳細Documentationへ到達できる
- [x] READMEが公開版とMain Branchの状態差を明示する
- [x] Development SetupのWorker ModelがAGENTS／D077と一致する
- [x] Workflow YAMLとCI内の実コマンドがLocalで成功する
- [x] GitHub-hosted RunnerでCI Workflowが成功する

## Remaining Issues

`actions/checkout@v7`へ更新したFollow-up CommitのGitHub-hosted CIを確認する必要がある。

## Suggested Next Action

Checkout更新をCommit／Pushし、Follow-up GitHub Actions CIを監視する。成功後にTask／Report／STATEをAcceptedへ更新する。
