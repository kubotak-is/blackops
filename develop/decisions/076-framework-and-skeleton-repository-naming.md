# D076: Framework and Skeleton Repository Naming

Status: Decided

## Context

D065はFrameworkとSkeletonの開発をMain RepositoryでAtomicに行い、`examples/quickstart/`だけを生成専用Distribution RepositoryへSplitすると決定した。D073でDistribution Repositoryを`kubotak-is/blackops`としたが、この名前はFramework全体の開発元Repositoryとして自然であり、Skeleton生成物だけを格納する名前として責務が不明確だった。

Laravelの`laravel/framework`と`laravel/laravel`のように、Framework本体とInstall用Application SkeletonはComposer PackageおよびDistribution Rootを分ける。ただしBlackOpsのSkeletonは独立開発せず、Main Repository内の`examples/quickstart/`を正本とする。

## Decision

[DECISION]

1. Main Framework RepositoryはPublic `https://github.com/kubotak-is/blackops` とし、現在のWorking Repository全体を管理する。
2. Skeleton Distribution RepositoryはPublic `https://github.com/kubotak-is/blackops-skeleton` とし、Default Branchを`main`とする。
3. `kubotak-is/blackops-skeleton`は生成専用であり、機能変更を直接Commitしない。
4. SkeletonのSource of TruthはMain Framework Repositoryの`examples/quickstart/`とし、Release Workflowが同じbare SemVer TagでSplit・Publishする。
5. Composer PackageはFrameworkを`blackops/framework`、Skeletonを`blackops/skeleton`とする。
6. Distribution RepositoryへのWrite-enabled Deploy KeyのPrivate KeyはMain Framework RepositoryのActions Secret `SKELETON_DEPLOY_KEY`で管理する。
7. D073のCredential、Packagist GitHub連携、Version、Generated State禁止の決定は維持し、Repository名だけを置き換える。

[/DECISION]

## Consequences

[CONSEQUENCES]

- `kubotak-is/blackops`はFrameworkの開発、Test、Decision、Example、Release Workflowを所有する。
- `kubotak-is/blackops-skeleton`はPackagistがRoot `composer.json`を読むための配布Mirrorとなる。
- Framework開発者はDistribution Repositoryを直接修正せず、`examples/quickstart/`だけを修正する。
- 既存の`kubotak-is/blackops`の`LICENSE` Initial CommitはMain Framework RepositoryのHistoryとして取り込み、Skeleton SplitのFast-forward制約から切り離される。
- Workflow、Documentation、Packagist Source URL、Deploy Key登録先は`kubotak-is/blackops-skeleton`へ更新する。

[/CONSEQUENCES]

## References

- [D065 Composer Skeleton Publication](065-composer-skeleton-publication.md)
- [D073 Skeleton Distribution Publication Boundary](073-skeleton-distribution-publication-boundary.md)
- [Composer Skeleton Publication](../spec/46-composer-skeleton-publication.md)
- [Phase 8 Delivery Plan](../spec/52-phase-8-delivery-plan.md)
