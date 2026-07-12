# D065: Composer Skeleton Publication

Status: Decided

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

A

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

A

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

A

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

A

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

A

[/ANSWER]

## Decision

[DECISION]

1. `examples/quickstart/` をSkeletonのSource of TruthとしてMain Repositoryで開発する。
2. Release Tagから `examples/quickstart/` だけを自動Splitし、Read-onlyの `blackops/skeleton` Distribution RepositoryへPushする。
3. Packagistの `blackops/skeleton` はDistribution Repositoryを参照する。Main RepositoryのSubdirectoryを直接Package Sourceにしない。
4. FrameworkとSkeletonへ同じRelease Version Tagを付ける。
5. Skeletonは同じMajor／Minorの `blackops/framework` をComposer ConstraintでRequireする。
6. Skeletonの配布Sourceへ `composer.lock` を含めない。生成されたApplicationが `create-project` 時に自身のLock Fileを生成して所有する。
7. 生成後もComposer Package Nameは `blackops/skeleton`、PHP Root Namespaceは `App\` のまま維持する。
8. Install時にComposer Package Name、PHP Namespace、Source Codeの自動置換または対話入力を行わない。
9. `post-create-project-cmd` は `.env` が存在しない場合の `.env.example` Copy、Local生成Directoryの準備、次に実行するCommandの表示だけを行う。
10. Post-create処理は再実行可能とし、既存 `.env` を上書きしない。
11. Post-create処理からSecret生成、Network Access、Docker起動、Database接続、Migration、Artifact Build、Worker、Retentionを実行しない。
12. `composer create-project --no-scripts` でもSource TreeとComposer Autoloadが成立し、READMEの手動手順で同じLocal準備を行えるようにする。

[/DECISION]

## Consequences

[CONSEQUENCES]

### Benefits

- FrameworkとSkeletonをMain RepositoryでAtomicに変更し、Consumer E2Eを通してから同じVersionとして配布できる。
- Distribution RepositoryのRootにSkeletonの `composer.json` が置かれ、Packagistの標準Package境界に適合する。
- 手動Copyや独立開発によるFramework／Skeleton Driftを避けられる。
- 生成Applicationは自身のDependency Resolution結果を `composer.lock` として所有できる。
- Post-createがDocker、Database、Networkへ依存しないため、Install成功とRuntime Setupを分離できる。
- Namespace一括置換を行わず、Install結果を決定的に保てる。

### Constraints

- SkeletonだけをFrameworkと異なるVersionでReleaseできない。独立Releaseが必要になった場合は新しいDecisionを行う。
- Release AutomationはFramework Tag、Split Commit、Skeleton Tagの対応が同一であることを検証しなければならない。
- `blackops/skeleton` というComposer Nameは生成Applicationにも残る。利用者がApplicationをComposer Packageとして公開する場合は自身で変更する。
- Lock Fileを同梱しないため、同じSkeleton VersionでもInstall日時により許容範囲内のDependency Patch Versionが異なり得る。
- `--no-scripts` 利用時は `.env` CopyとLocal Directory準備を利用者が手動で行う必要がある。

### Follow-up Work

- Phase 8でSplit Workflow、Tag整合性Check、Packagist更新境界を実装する。
- SkeletonのComposer MetadataへFramework互換ConstraintとPost-create Scriptを定義する。
- Release前にSplit結果から `composer create-project` 相当のInstall Smoke Testを実行する。
- Direct Commitを受け付けないDistribution Repository運用をDocumentationへ記載する。

[/CONSEQUENCES]

## References

- Composer `create-project`: https://getcomposer.org/doc/03-cli.md#create-project
- Composer Scripts: https://getcomposer.org/doc/articles/scripts.md
- Composer Root Package Schema: https://getcomposer.org/doc/04-schema.md#root-package
- Packagist Package Repository: https://packagist.org/about
- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [D064 Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)
