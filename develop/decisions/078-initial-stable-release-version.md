# D078: Initial Stable Release Version

Status: Partially Superseded by D094

## Supersession

D094は、Full-stack Public Readiness前のExperimental ReleaseではMinor Release間のBackward Compatibilityを保証しない方針へ変更した。公開済みTagを移動または削除しない契約、Framework／Skeletonの同一Version、初回`1.0.0` Publicationに関する決定は維持する。

## Context

P8-003のPublication ContractはFrameworkとSkeletonへ同じbare SemVer Tagを付け、SkeletonのFramework ConstraintがRelease Major／Minorを許容することを要求する。現在のSkeletonは`blackops/framework: ^1.0`を要求するため、現行Contractで公開可能な初回Versionは1.xである。

一方、P8-003 Task PacketはStable `1.0.0` ReleaseをOut of Scopeとしていた。Packagistへの`blackops/framework`登録後、Userは現行Constraintを維持して初回Stable Releaseへ進むことを承認した。

## Decision

[DECISION]

1. FrameworkとSkeletonの初回Public Stable Versionをbare SemVer `1.0.0`とする。
2. Framework Repositoryの`1.0.0` TagをPublication WorkflowのTriggerとし、Skeleton Split Commitにも同じ`1.0.0` Tagを付ける。
3. Skeletonの`blackops/framework: ^1.0` Constraintは変更しない。
4. P8-003は`1.0.0` Tag Push、Workflow Live Run、Distribution `main`／Tag検証までをScopeに含める。
5. GitHub Release Notesの作成はP8-003に含めない。
6. `blackops/skeleton`のPackagist登録とRemote Create-project Smokeは、Distribution生成後のExternal Action／P8-004として扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- `blackops/framework`はPackagist上でStable `1.0.0`として解決可能になる。
- `blackops/skeleton`は同じVersionから`blackops/framework: ^1.0`を解決する。
- `1.0.0`はPublic Stable Contractとなるため、以後の互換性管理はSemantic Versioningに従う。
- Pre-1.0へ戻す場合は新しいDecisionが必要であり、公開済み`1.0.0` Tagを移動または削除しない。

[/CONSEQUENCES]

## References

- [D065 Composer Skeleton Publication](065-composer-skeleton-publication.md)
- [D076 Framework and Skeleton Repository Naming](076-framework-and-skeleton-repository-naming.md)
- [Composer Skeleton Publication](../spec/46-composer-skeleton-publication.md)
- [P8-003 Skeleton Distribution Publication](../orchestration/tasks/P8-003-skeleton-distribution-publication.md)
