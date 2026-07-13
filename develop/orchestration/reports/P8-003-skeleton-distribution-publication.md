# P8-003 Skeleton Distribution Publication Report

## Summary

Frameworkのbare SemVer Release Tagから`examples/quickstart/`だけを決定的にSplitし、同一Versionの`blackops/skeleton`としてPublic Distribution RepositoryへPublishするLocal ValidationとGitHub Actions Workflowを実装した。

Local Dry RunはVersion／Source Ref、反復Subtree Split、Distribution Root、Composer Metadata、Framework Constraint、Generated State不在、同一Tag生成、Working Tree／Docker／Temporary State Cleanupを検証する。WorkflowはFull Quality、Consumer E2E、Create-project、Publication Dry Runを通過した後だけ専用Deploy Keyを展開し、Remote `main`と同名TagのDivergenceをforceせず拒否する。

External GitHub／Packagist Mutationはworkerから実施していない。D076によりMain Framework Repositoryを`kubotak-is/blackops`、生成専用Distribution Repositoryを`kubotak-is/blackops-skeleton`へ分離した。OrchestratorがPublic Distribution Repository、Write-enabled Deploy Key、Main Repository Actions Secretを設定し、一時Key Fileを削除した。Main HistoryをPushし、UserがPackagistへ`blackops/framework`を登録した。初回ReleaseとSkeleton登録は未実施。

## Split／Version／Constraint Evidence

- `bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD`が成功した。
- Source Commit: `be08eaa403aaf07f14f900d99f722b7431cb7f29`
- 反復Split Commit: `da573f3190e5e855a9c09e275980c6ddc5cce028`
- 2回の`git subtree split --prefix=examples/quickstart`が同じCommitを返した。
- Distribution Root Allowlist、必須`composer.json`／`README.md`／`bin/setup`／`bin/blackops`／`bootstrap/app.php`、Executable Bitを検証した。
- Composer Name `blackops/skeleton`、Type `project`、PHP `>=8.5`、Framework `^1.0`、Post-create `@php bin/setup`を検証した。
- `repositories`／`version`、`composer.lock`、`vendor/`、`.env`、Symlink、生成済みBuild／Logがないことを検証した。
- `v1.0.0`はbare SemVer違反として拒否した。
- Release `2.0.0`はSkeleton Constraint `^1.0`と不整合として拒否した。
- Temporary Clone内のFramework Tagと同名Tagが存在する場合は、それだけを削除してSplit CommitへLocal Tagを再作成する。Main RepositoryのTagは変更しない。

## Workflow／Credential Boundary Evidence

- `.github/workflows/publish-skeleton.yml`は全Tag Pushを受け、最初にbare SemVerとTag／HEAD一致を検証する。
- CheckoutはFull Historyかつ`persist-credentials: false`である。
- 固定Concurrency GroupによりSkeleton Publicationを直列化する。
- Composer／Mago／Full PHPUnit／Deptrac、Quickstart E2E、Create-project、Publication Dry Runの後だけCredential Stepへ進む。
- Secret名は`SKELETON_DEPLOY_KEY`で、Runner一時FileへMode `0600`で書き、`if: always()` CleanupでKeyとPublication Treeを削除する。
- GitHub Host KeyはTLS検証を限定したGitHub Metadata APIから取得し、SSHは`StrictHostKeyChecking=yes`を使用する。
- Remote Tagが別Commitの場合と、Remote `main`がSplit CommitのAncestorでない場合は失敗する。
- Branch／Tag上書きとForce Pushを実装していない。同じSplit Commitが存在する再実行は不足分だけをPushする。
- Workflow、Test、DocumentationにPackagist Token、Packagist API Endpoint、Private Keyを含めないGuardが成功した。
- Workflow YAMLはLocal PyYAML Parserで読み取り可能であることを確認した。GitHub Actions上のLive RunはExternal Repository／Secret未設定のため未実行である。
- Workflowの`SKELETON_REMOTE`は生成専用`git@github.com:kubotak-is/blackops-skeleton.git`であり、Main Framework RepositoryへSkeleton SplitをPushしない。

## Local Publication Smoke Evidence

```text
bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed.

bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
Result: Skeleton publication dry run passed.
Source: be08eaa403aaf07f14f900d99f722b7431cb7f29
Split: da573f3190e5e855a9c09e275980c6ddc5cce028
```

Publication Dry Runは実行前後のMain Working Tree StatusとDocker Container／Image／Network／Volume IDを比較し、Temporary Rootを明示削除した後に不在を確認した。

## External Repository／Deploy Key／Packagist Status

| External state | Status |
| --- | --- |
| Main Framework `kubotak-is/blackops` | Public `main`へLocal HistoryをforceなしでPush済み。Remote SHA `8b2af584aeab2de8ecade4ad2741741d8db408bc` |
| Distribution `kubotak-is/blackops-skeleton` | Created; Public・Empty・Admin権限を確認 |
| Distribution Default Branch `main` | Empty RepositoryのためBranch未生成。初回Workflow Push後に`main`を検証する |
| Write-enabled Deploy Key | Registered; ID `157115254`、verified、`read_only=false` |
| Main Repository Secret `SKELETON_DEPLOY_KEY` | Registered; secret name only verified |
| Temporary Deploy Key Files | Private／Public Keyとも`/tmp`から削除済み |
| Packagist `blackops/framework` | Registered; Composer Metadataで`dev-main`がMain HEAD `ed9d8e345faca150644bab753e7b4d76d3243b78`を参照することを確認 |
| Initial Split Commit／Tag Push | Not executed |
| Packagist `blackops/skeleton` GitHub integration | Not configured |

Private Keyは表示またはRepositoryへ保存せず、Actions Secret登録後に一時Fileを削除した。Repository内のPrivate Key／Token Signature Guardも成功した。

Main RepositoryのLocal `master`を`main`へ改名し、GitHubの`LICENSE` Initial Commit `9c213ddd214c`を`--allow-unrelated-histories`で取り込んだ。Merge Commit `8b2af584aeab` を`origin/main`へ通常Pushし、Trackingを設定した。Force Pushは使用していない。

PackagistのComposer Metadataで`blackops/framework`の`dev-main`取得を確認した。Stable Versionはまだ存在しない。Framework Release TagからSkeletonを生成した後に`blackops/skeleton`を登録する必要がある。

## Changed Files

- `.github/workflows/publish-skeleton.yml`
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

Orchestratorが更新したD073、D076、Spec 46、Spec README、Task Packetにはworkerから変更を加えていない。

## Decisions and Assumptions

- Main Framework Repositoryは`kubotak-is/blackops`、Distribution Repositoryは`kubotak-is/blackops-skeleton`とする。
- 初回Public Stable Versionは`1.0.0`とし、FrameworkとSkeletonへ同じbare SemVer Tagを付ける。
- SkeletonのFramework Constraint `^1.0`は維持する。
- GitHub Framework Release NotesはP8-003に含めない。
- Distribution Repositoryは空Repositoryとして作成し、README／License等の初期Commitを置かない。初回`main`はWorkflowが生成する。
- Local Dry RunはRemote、Secret、Packagistへ接続しない。
- FrameworkとSkeletonのVersionは同じbare SemVerで、Skeleton ConstraintはRelease Major／Minorの`^M.N`と完全一致させる。
- Release WorkflowからPackagist APIを呼ばず、Distribution RepositoryのGitHub連携へTag検出を委ねる。
- Existing Remote Branch／Tagは生成Historyと一致する場合だけ前進させる。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Both Composer files are valid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All commands completed with no issues.

docker compose run --rm app vendor/bin/phpunit
Result: OK (721 tests, 2374 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 361 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1546 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed.

bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
Result: Skeleton publication dry run passed.

bash tests/Consumer/skeleton-publication.sh v1.0.0 HEAD
Result: Expected failure; bare SemVer validation rejected the version.

bash tests/Consumer/skeleton-publication.sh 2.0.0 HEAD
Result: Expected failure; Framework constraint mismatch was rejected.

Packagist API／Token guard
Private Key／Token signature guard excluding development instruction text
Management ID guard
Force-push guard
Result: No forbidden matches.

python3 PyYAML parse of .github/workflows/publish-skeleton.yml
Result: Workflow YAML parsed.

git diff --check
Result: No output.
```

### D076 Repository Naming Follow-up

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Both Composer files are valid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All commands completed with no issues.

bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
Result: Skeleton publication dry run passed. Source a45ca120f03eb776e75e16b9a7bb56e9207698c3, split da573f3190e5e855a9c09e275980c6ddc5cce028.

Workflow YAML parse, exact SKELETON_REMOTE, stale Distribution URL, Packagist API／Token, Private Key／Token signature, management ID guards
Result: Workflow parsed; exact Remote matched `git@github.com:kubotak-is/blackops-skeleton.git`; no forbidden matches.

git diff --check
Result: No output.
```

Repository参照とDocumentationだけの変更であり、PHP Production CodeとTest Contractは変更していない。Full PHPUnit、Deptrac、Quickstart Consumer E2E、Create-project Smokeは直前のP8-003受入結果を維持し、このFollow-upでは再実行していない。

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

## Remaining Issues

- DistributionのDefault Branch `main`は初回Workflow Push後に確認する必要がある。
- D078で初回Stable `1.0.0`を承認した。Framework Tag Push、Workflow Live Run、Distribution検証は未実施である。
- 初回Skeleton Push後、Packagist `blackops/skeleton`を`kubotak-is/blackops-skeleton`のGitHub連携へ登録する必要がある。
- External状態設定後にWorkflowの初回Live RunとRemote Branch／Tagを検証する必要がある。
- Remote `composer create-project`はP8-004で検証する。

## Orchestrator Review

WorkflowのQuality Gate順序、Checkout Credential無効化、Deploy Keyの一時File限定、Known Hosts検証、Remote Branch／Tagの冪等性とfail-closed分岐、Force Push不在を差分で確認した。Local ScriptはMain Working TreeではなくTemporary CloneだけにSplit Commit／Tagを作る。

OrchestratorがComposer Strict Validation、Mago Format Check、Publication Dry Run、Shell Syntax、Workflow YAML Parse、Management ID／Credential／Packagist API／Force Push Guard、`git diff --check`を再実行し、すべて成功した。Publication Dry RunはSource `be08eaa403aaf07f14f900d99f722b7431cb7f29`からSplit `da573f3190e5e855a9c09e275980c6ddc5cce028`を再現した。`actionlint`と`shellcheck`はLocal Environmentになく未実行だが、GitHub Actions Live RunとともにExternal設定後の検証項目とする。Local実装は受け入れた。

D076追従ではWorkflow、Documentation、Report、CheckpointのDistribution参照だけを`kubotak-is/blackops-skeleton`へ変更する。Local Split／Runtime Code／Test Contractは変更していない。

OrchestratorはD076追従後にComposer Strict Validation、Mago Format Check、Publication Dry Run、Workflow YAML Parse、Remote／Credential／Packagist API／Force Push Guardを再実行し、成功した。Source `09ea106d1fbe1b268451fc8f3673019906af3382`から同じSplit `da573f3190e5e855a9c09e275980c6ddc5cce028`が生成された。Workflow／Documentationの追従差分を受け入れた。

## Suggested Next Action

D078をCommit／Pushした後、Framework `1.0.0` TagでWorkflowを実行し、Distribution `main`とTagを確認する。生成後の`blackops/skeleton`をPackagistへ登録し、P8-004 Remote Create-project Smokeへ進む。
