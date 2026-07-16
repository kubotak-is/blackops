# Experimental Release Contract

## Scope

BlackOpsはFull-stack機能が揃いPublic Readinessを別Decisionで宣言するまで実験ProjectとしてReleaseする。Phase 11ではFramework／Skeleton `1.1.0`を公開する。

## Compatibility Policy

- 実験期間中はMinor Releaseでも破壊的変更を許容する
- 1.x Minor間のBackward Compatibilityは保証しない
- Breaking SurfaceとMigrationは`CHANGELOG.md`と`UPGRADE.md`へ記録する
- 公開済みTagは移動、削除、再割当しない
- Full-stack Public Readiness時にCompatibility開始点、Semantic Versioning、Deprecation Windowを再決定する

`1.1.0`ではProject Root `blackops`を唯一の公式Entrypointとし、旧`bin/blackops`を互換対象にしない。Project CLIの旧`blackops:*` Aliasは削除し、PrefixなしCanonical CommandだけをFramework所有名として予約する。

## Version and Package Contract

- FrameworkとSkeletonへannotated tag `1.1.0`を付ける
- Skeleton `1.1.0`は`blackops/framework: ^1.1`を要求する
- Skeleton SourceはMain Repositoryの`examples/quickstart/`とする
- Distribution Repositoryへ同じTagでSplitする
- PackagistはGitHub Tag連携でFramework／Skeletonを反映する

## Release Documentation

Repository Rootに次を置く。

- `CHANGELOG.md`: Version別のAdded、Changed、Removed、Fixed、Known Limitations
- `UPGRADE.md`: 直前Stableからの実行可能なMigration手順

README、利用者向けGuide、Website Version Notice、Package MetadataはLatest Stable `1.1.0`とExperimental Compatibility Policyを一致させる。Publication成功後、`CHANGELOG.md`の`1.1.0`を要約したGitHub Releaseを作成する。

## Release Freeze

Release Candidateへ追加できる変更は次に限定する。

- Release Surface Auditと旧互換Layer整理
- Test／Consumer Gate
- Documentation、Version、Package Metadata
- Release／Publication Automation修正

Phase 12以降の新Featureを含めない。Release Gateで新しい仕様判断が必要になった場合は実装を止め、Decisionへ戻す。

## Publication Gate

Tag作成前に次を満たす。

- Composer Strict Validation
- Mago Format／Lint／Analyze
- PHPUnit／Deptrac
- 全Consumer E2E
- Framework Update／Worker Mode／Skeleton Publication Dry Run
- `1.1.0` Split Artifactの通常／`--no-scripts` Create-project Smoke
- Release Candidate CommitのGitHub Actions CI／Documentation Artifact Build
- Working Tree clean、Release Source SHA固定

全Gate成功後は追加User確認なしでannotated tag `1.1.0`をPushする。Skeleton Publication Workflow、Framework／Skeleton Tag、Packagist Metadata、GitHub Release、Remote Create-project、Documented Quickstartを検証してPhaseをCloseする。

## Failure and Recovery

Publication失敗時もFramework Tagを移動または削除しない。既存Manual Dispatch Recoveryを使用し、同じTag Checkoutと全GateからSkeleton Publicationを再処理する。Remote Tagが異なるSplit Commitを指す場合は自動修正せずBlockerとして停止する。

## Traceability

- Decision: [D094 Stable 1.1 Release Contract](../decisions/094-stable-1-1-release-contract.md)
- Skeleton Publication: [Composer Skeleton Publication](46-composer-skeleton-publication.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
