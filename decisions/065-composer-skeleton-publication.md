# D065: Composer Skeleton Publication

Status: Awaiting Answer

## Context

D063は `examples/quickstart/` をExampleと `blackops/skeleton` の共通Source of Truthとし、公式Install Commandを次に限定した。

```bash
composer create-project blackops/skeleton my-app
```

D064はFeature-first Layout、Application-owned Dotenv、Docker Compose Quickstartを確定した。Phase 8の実装前に、Monorepo内SourceをComposer Packageとして公開する境界、Version、Lock File、生成後Project Identity、Post-create Side Effectを決定する。

Composerの `create-project` はPackageを指定Directoryへ取得し、Dependency Installを行う。`post-root-package-install` と `post-create-project-cmd` はRoot PackageのScriptとして実行される。Dependency Packageに定義されたScriptはRoot Projectへ伝播しない。

PackagistはPackage RepositoryのRootに `composer.json` がある構成を基本とする。このRepositoryではFramework Rootの `composer.json` が `blackops/framework` を表すため、`examples/quickstart/` の `blackops/skeleton` を同じRepository URLのSubdirectory Packageとして直接登録しない。

## Question 1: Publication Repository Boundary

`examples/quickstart/` をPackagistから取得できるRepository Rootへどう公開するか。

### Options

- A: Main RepositoryのTagから `examples/quickstart/` を自動Splitし、Read-onlyの `blackops/skeleton` Distribution Repositoryへ同じTagをPushする
- B: Release時にZIP Artifactを生成し、Composer Artifact Repositoryとして独自配信する
- C: Skeletonを別Repositoryへ手動Copyし、独立して開発／Releaseする

### Recommendation

Aを推奨する。

Source of TruthをMain Repositoryに保ち、Packagistが期待するRepository RootにSkeletonの `composer.json` を置ける。FrameworkとSkeletonのAtomic Testを維持し、手動CopyによるDriftを避けられる。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 2: Version Relationship

FrameworkとSkeletonのVersionをどう対応させるか。

### Options

- A: Framework Release Tagと同じVersionをSkeleton Split Repositoryへ付け、そのSkeletonが同じMajor／MinorのFrameworkをRequireする
- B: Skeletonは独立SemVerとし、Framework Compatibility Matrixを別管理する
- C: SkeletonはVersion Tagを持たず `dev-main` だけを提供する

### Recommendation

Aを推奨する。

初期ReleaseではSkeletonだけが先行または遅延して互換性を失うリスクを下げられる。実利用でSkeleton単独Releaseの必要性が確認された場合に独立Versionへ移行できる。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 3: Composer Lock File

`blackops/skeleton` の配布Sourceへ `composer.lock` を含めるか。

### Options

- A: 含めず、`create-project` 実行時に利用者環境でDependencyを解決して新しいLock Fileを生成する
- B: Framework Release時の検証済みLock Fileを含め、生成Applicationを完全固定する
- C: Stable ReleaseだけLock Fileを含め、Development Versionには含めない

### Recommendation

Aを推奨する。

SkeletonはFramework Version ConstraintとPlatform Requirementを正本とし、生成されたApplicationが自身のLock Fileを所有する。Skeleton SourceのLock更新とFramework Releaseを二重管理せずに済む。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 4: Generated Project Identity

`create-project` 後のComposer Package NameとPHP Root Namespaceをどう扱うか。

### Options

- A: Composer Package Nameは `blackops/skeleton`、PHP Root Namespaceは `App\` のまま保ち、自動書換を行わない
- B: PHP Root Namespaceは `App\` のまま保ち、Composer Package NameだけをTarget Directory名から `app/<slug>` へ自動変更する
- C: Install時にVendor／Package NameとPHP Namespaceを対話入力し、全Sourceを置換する

### Recommendation

Aを推奨する。

Composer `--no-scripts` でも同じSource Treeとなり、Namespace一括置換やComposer Metadata再書換の失敗がない。ApplicationをPublish Packageとして扱う必要がある利用者は、生成後に自身のComposer Nameを明示変更できる。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 5: Post-create Side Effects

`post-create-project-cmd` で何を自動実行するか。

### Options

- A: `.env` がなければ `.env.example` からCopyし、Local生成Directoryを準備して、次のCommandを表示するだけにする
- B: Aに加えてDocker Image Build、Database Migration、Artifact Compileまで自動実行する
- C: Post-create Scriptを一切持たず、すべてREADMEの手順にする

### Recommendation

Aを推奨する。

Network、Docker Daemon、Database状態へ依存せず `create-project` を完了できる。Migration、Build、Worker、PurgeをInstall時に暗黙実行しないというProcess Boundaryも維持できる。

Scriptは再実行可能とし、既存 `.env` を上書きしない。Secret生成、Database接続、Docker起動、Framework Migration、Build Artifact生成を行わない。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Decision

[DECISION]

<!-- Answers確定後に記録する -->

[/DECISION]

## Consequences

[CONSEQUENCES]

<!-- Answers確定後に記録する -->

[/CONSEQUENCES]

## References

- Composer `create-project`: https://getcomposer.org/doc/03-cli.md#create-project
- Composer Scripts: https://getcomposer.org/doc/articles/scripts.md
- Composer Root Package Schema: https://getcomposer.org/doc/04-schema.md#root-package
- Packagist Package Repository: https://packagist.org/about
- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [D064 Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)
