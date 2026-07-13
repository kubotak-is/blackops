# D079: Immutable Release Publication Recovery

Status: Decided

## Context

Framework `1.0.0` Tag Pushで起動した初回Skeleton Publicationは、GitHub Runner上のCheckout所有者とCompose Container Userが一致せず、Composerがbind-mounted `/app/vendor`を作成できないため依存関係Installで失敗した。Credential展開とDistribution Pushより前に停止したため、Framework TagとPackagist Stable Versionは公開済みだが、Skeleton Distributionは未生成である。

Tag Push WorkflowはTag Commitに含まれるWorkflow定義を使用する。Main BranchでPermission FixをCommitして既存Runを再実行しても修正版を使用できない。公開済み`1.0.0` TagはD078により移動または削除しない。

## Decision

[DECISION]

1. Tag Pushを通常のPublication Triggerとして維持する。
2. 既存Releaseの復旧用に、bare SemVer `release_version`を必須入力とするManual Dispatchを同じWorkflowへ追加する。
3. Tag PushとManual Dispatchはどちらも`refs/tags/<release_version>`を明示Checkoutし、Tagが存在してChecked-out Commitと一致することをQuality Gate前に検証する。
4. Publication SourceはEvent SHAではなく、検証済みChecked-out Tagの`HEAD`とする。
5. GitHub RunnerのUID／GIDを`HOST_UID`／`HOST_GID`としてCompose Build／Runへ渡し、bind mount上の所有者をContainer Userと一致させる。
6. Manual Dispatchも通常と同じFull Quality、Consumer、Create-project、Publication Dry Run、Credential、Remote Divergence Gateを省略せず実行する。
7. Recoveryは既存Framework Tagを移動または削除せず、Distribution `main`／同名Tagを冪等に生成する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Tag Commit内のWorkflowが失敗しても、Main上の修正版Workflowから既存の不変Tagを安全に再処理できる。
- Manual InputはBranch、SHA、`v` Prefix、Pre-releaseを受理しない。
- Dispatch元のMain CommitとRelease Source Commitを混同せず、Skeletonは必ずFramework Tagの内容からSplitされる。
- Permission FixはGitHub-hosted Runnerだけでなく、UID／GIDが1000以外のRunnerでも同じCompose Contractを使用する。

[/CONSEQUENCES]

## References

- [D073 Skeleton Distribution Publication Boundary](073-skeleton-distribution-publication-boundary.md)
- [D078 Initial Stable Release Version](078-initial-stable-release-version.md)
- [Composer Skeleton Publication](../spec/46-composer-skeleton-publication.md)
- [P8-003 Skeleton Distribution Publication](../orchestration/tasks/P8-003-skeleton-distribution-publication.md)
