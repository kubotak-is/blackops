# Composer Skeleton Publication

## Source and Distribution Boundary

`examples/quickstart/` は、Installed Application ExampleとComposer Project Package `blackops/skeleton` のSource of Truthである。

Main Framework RepositoryはPublic `https://github.com/kubotak-is/blackops.git` とする。Distribution RepositoryはPublic `https://github.com/kubotak-is/blackops-skeleton.git`、Default Branch `main` とする。

開発、Review、Consumer E2EはMain Framework Repositoryで行う。Release時に `examples/quickstart/` の内容だけを自動Splitし、`composer.json` がRepository RootにあるRead-only Distribution RepositoryへPushする。

Packagistの `blackops/skeleton` PackageはDistribution Repositoryを参照する。Main Framework RepositoryのSubdirectoryを直接Packagist Package Sourceとして登録しない。

Distribution Repositoryへ機能変更を直接Commitしない。修正はMain Repositoryの `examples/quickstart/` へ行い、Release Pipelineから再生成する。

## Version Policy

FrameworkとSkeletonは同じRelease Version Tagを使用する。

例えばFramework `1.2.0` Releaseでは、Skeleton Split Commitにも `1.2.0` Tagを付ける。SkeletonのComposer Metadataは同じMajor／Minor系列のFrameworkをRequireする。

```json
{
  "require": {
    "blackops/framework": "^1.2"
  }
}
```

Release Automationは次を失敗条件とする。

- FrameworkとSkeletonのTagが一致しない
- SkeletonのFramework ConstraintがRelease Major／Minorを許容しない
- Split結果のRootに正しい `blackops/skeleton` Composer Metadataがない
- Split結果にRepository内部だけで成立するPath Repositoryが残る

Skeleton独自VersionまたはFrameworkと異なるRelease Cycleは採用しない。必要性が生じた場合はVersion Policyを再決定する。

初回Public Stable Versionは`1.0.0`とする。Framework Repositoryの`1.0.0` TagをTriggerとして、Skeleton Split Commitにも同じTagを付ける。SkeletonのFramework Constraintは`^1.0`とする。

## Lock File Ownership

SkeletonのSourceとDistributionへ `composer.lock` を含めない。

`composer create-project` はSkeletonのFramework ConstraintとPlatform RequirementからDependencyを解決し、生成Application内へ新しい `composer.lock` を作成する。以後、そのLock Fileは生成ApplicationがVersion管理する。

Consumer E2E用に作成された一時Lock Fileを `examples/quickstart/` の配布Sourceへ戻してはならない。

## Generated Project Identity

生成Applicationは次のIdentityを維持する。

- Composer Package Name: `blackops/skeleton`
- PHP Root Namespace: `App\`

Install時にTarget Directory名からComposer Nameを生成しない。Vendor／Package NameとPHP Namespaceを対話入力せず、Source FileのNamespace置換も行わない。

生成後にApplicationをComposer Packageとして公開する利用者は、自身の責任でComposer Nameを変更する。

## Post-create Contract

Root Packageの `post-create-project-cmd` は次だけを行う。

1. `.env` が存在しない場合に `.env.example` をCopyする
2. `var/build/` と `var/log/` 等のLocal生成Directoryを準備する
3. Docker Compose Build、Migration、Artifact Compile、HTTP起動等の次の手順を表示する

処理は再実行可能であり、既存 `.env` と既存利用者Fileを上書きしない。

Post-create処理は次を行わない。

- SecretまたはApplication Key生成
- Network Access
- Docker Daemon AccessまたはContainer起動
- Database接続またはMigration
- Build Artifact生成
- Worker／Scheduler／Retention実行

`composer create-project --no-scripts` でもApplication SourceとComposer Autoloadは成立しなければならない。READMEは `.env` CopyとLocal Directory準備の手動手順を記載する。

## Release Pipeline

Release Pipelineは次の順で行う。

1. Framework Quality SuiteとQuickstart Consumer E2Eを実行する
2. Framework Release TagとSkeleton Framework Constraintを検証する
3. `examples/quickstart/` をDistribution RepositoryへSplitする
4. Split CommitへFrameworkと同じVersion Tagを付ける
5. Distribution RepositoryへCommitとTagをPushする
6. Split結果からInstall Smoke Testを実行する
7. Packagistへ新しいTagを反映する

Push、Packagist Credential、TokenはRepositoryへ保存しない。Release PipelineのSecret Storeを使用する。

Cross-repository PushはDistribution RepositoryだけへWrite可能なDeploy Keyを使用し、Private KeyはMain Repository Secret `SKELETON_DEPLOY_KEY` で管理する。PackagistはDistribution RepositoryのGitHub連携でTag Pushを検知し、Release WorkflowからPackagist APIを呼ばない。

## Verification

- Main Repository内のConsumer E2EがFrameworkとQuickstartをAtomicに検証する
- Split結果のRootで `composer validate --strict` が成功する
- Split結果に `composer.lock` とLocal Path Repositoryがない
- `composer create-project` 相当の通常Installが成功する
- `--no-scripts` Installが成功し、Manual Setup手順で起動準備できる
- Post-createを再実行して既存 `.env` が変更されない
- Install中にDocker、Database、Migration、Buildが暗黙実行されない

## Traceability

- Decision: [D065 Composer Skeleton Publication](../decisions/065-composer-skeleton-publication.md)
- Repository Naming: [D076 Framework and Skeleton Repository Naming](../decisions/076-framework-and-skeleton-repository-naming.md)
- Initial Stable Version: [D078 Initial Stable Release Version](../decisions/078-initial-stable-release-version.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Installed Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
