# D073: Skeleton Distribution Publication Boundary

Status: Awaiting Answer

## Context

P8-002でCommitted Quickstartから通常／`--no-scripts` Local Create-projectが成功し、Package Source、Version、Post-create、Copy Install境界は成立した。

P8-003は `examples/quickstart/` をRead-only Distribution Repositoryへ自動Splitし、Frameworkと同じRelease TagをPushしてPackagistへ反映する。現在のWorking RepositoryにはGit remoteとGitHub Actions Workflowがなく、Distribution Repositoryの実URL、Default Branch、Cross-repository Credential、Packagist更新方法を推測できない。

外部Repository作成、Secret登録、Push、Packagist設定はユーザーの所有権と明示承認を必要とするため、実装前にPublication Boundaryを確定する。

## Question 1: Distribution Repository

SkeletonのRead-only Distribution Repositoryをどこに作るか。

### Options

- A: GitHubに空の専用Repositoryを作成し、正確なClone URL、Default Branch、Visibilityを回答する
- B: GitHub以外のGit Providerに専用Repositoryを作成し、同じ情報を回答する
- C: Remote Publicationを保留し、P8-003はLocal Release Artifact／Workflow Validationまでに限定する

### Recommendation

Aを推奨する。

Main RepositoryのRelease WorkflowからSkeleton専用RepositoryへSplit Commitと同一TagをPushでき、Packagistの標準的なVCS Package Sourceとして扱える。Repository名やOwnerは推測せず、作成済みまたは作成予定の正確なURLを回答へ記載する。

[ANSWER]

<!-- A/B/Cと、A/Bの場合はClone URL、Default Branch、Visibilityを記入してください。 -->

[/ANSWER]

## Question 2: Cross-repository Write Credential

Main RepositoryのRelease WorkflowがDistribution RepositoryへCommit／TagをPushする権限をどう与えるか。

### Options

- A: Distribution Repository専用のWrite-enabled Deploy Keyを作り、Private KeyをMain Repository Secretへ登録する
- B: Distribution Repositoryだけへ書込可能なFine-grained Personal Access TokenをMain Repository Secretへ登録する
- C: GitHub App Installation Token等の組織管理Credentialを使用する

### Recommendation

個人または単一Repository運用ではAを推奨する。

CredentialをSkeleton Repository一つへ限定でき、個人Account全体のTokenをWorkflowへ置かずに済む。組織で既存GitHub App運用がある場合はCが適する。Secret名はCredential値を含まない固定名として後続Taskで確定する。

[ANSWER]

<!-- A/B/Cを記入してください。既存Credential運用がある場合は制約も記載してください。 -->

[/ANSWER]

## Question 3: Packagist Update Boundary

Distribution RepositoryへRelease TagをPushした後、Packagistをどう更新するか。

### Options

- A: Packagist側のVCS／GitHub連携でTag Pushを検知させ、Release WorkflowへPackagist Tokenを置かない
- B: Release WorkflowからPackagist APIを呼び、TokenをRepository Secretで管理する
- C: 初期ReleaseはPackagist更新を手動で行い、Remote Create-project Smoke後に自動化する

### Recommendation

Aを推奨する。

Git TagをDistribution Repositoryの正本とし、Publication WorkflowのSecret責務をCross-repository Pushだけに限定できる。利用するProviderやPackagist Account制約で自動連携できない場合はCを選び、初期Release後にBを再検討する。

[ANSWER]

<!-- A/B/Cを記入してください。既存Packagist PackageやAccount設定があれば記載してください。 -->

[/ANSWER]

## Proposed Fixed Rules

回答にかかわらず、次はD065／Spec 46どおり維持する。

- Distribution Repositoryは生成専用で、機能変更を直接Commitしない
- FrameworkとSkeletonは同じbare SemVer Tag（例 `1.0.0`）を使用する
- Skeleton Constraintは同じMajor／MinorのFrameworkを許容する
- `composer.lock`、Vendor、Path Repository、Generated StateをPushしない
- Credential、Token、Private KeyをRepositoryへ保存しない
- Release前にLocal create-project SmokeとFull Quality Suiteを実行する
- External Push／Packagist Mutationは明示的なPublication Taskだけで行う

## Decision

[DECISION]

<!-- 回答後に確定する。 -->

[/DECISION]

## Consequences

[CONSEQUENCES]

<!-- 回答後に確定する。 -->

[/CONSEQUENCES]

## References

- [D065 Composer Skeleton Publication](065-composer-skeleton-publication.md)
- [Composer Skeleton Publication](../spec/46-composer-skeleton-publication.md)
- [Phase 8 Delivery Plan](../spec/52-phase-8-delivery-plan.md)
- [P8-002 Local Create-project Smoke](../orchestration/reports/P8-002-local-split-create-project-smoke.md)
