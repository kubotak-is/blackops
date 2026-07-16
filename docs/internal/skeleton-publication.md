# Skeleton Publication

BlackOpsは`examples/quickstart/`を`blackops/skeleton`の唯一のSourceとして管理し、Frameworkのbare SemVer Release TagからPublic Distribution Repositoryへ自動Publishする。Distribution Repositoryは生成専用であり、機能変更を直接Commitしない。

## Distribution boundary

| Item | Value |
| --- | --- |
| Source | `examples/quickstart/` |
| Package | `blackops/skeleton` |
| Main repository | `https://github.com/kubotak-is/blackops` |
| Distribution repository | `https://github.com/kubotak-is/blackops-skeleton` |
| Default branch | `main` |
| Release tag | Frameworkと同じ`MAJOR.MINOR.PATCH`のannotated tag |
| Tag message | `BlackOps Skeleton <version>` |
| Tagger identity | `BlackOps Release Automation <release@blackops.dev>` |
| Write credential | Repository専用Deploy Key |
| Main repository secret | `SKELETON_DEPLOY_KEY` |
| Packagist update | Distribution RepositoryのGitHub連携 |

Packagist TokenはMain Repositoryへ登録せず、Publication WorkflowからPackagist APIを呼ばない。

## Local dry run

Release候補は外部状態を変更せずに検証できる。

```bash
bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
```

ScriptはVersionとSource Refを検証し、RepositoryのLocal Cloneで`git subtree split --prefix=examples/quickstart`を2回実行する。両方のCommit IDが一致しなければ失敗する。Split Treeは一時Directoryへ展開され、Root Allowlist、必須Entrypoint、Executable Bit、Composer Metadata、Framework Constraint、Post-create Script、Generated State不在を検証する。

Version `M.N.P`に対してSkeletonの`blackops/framework` Constraintは`^M.N`でなければならない。Composer Metadataへ`version`または`repositories`がある場合も失敗する。Split Commitへ同じbare SemVerのannotated tagを作成し、Object Type `tag`、所定Message、Peeled Commitの一致を確認する。Tag Object IDはTagger Date等で変わり得るため、再現性の契約には含めない。

Dry RunはMain Working Tree、Docker Resource、Remote Repository、Credential、Packagistを変更しない。一時Clone、Split Tree、Local Tagは成功時と失敗時の両方で削除する。

## Release workflow

`.github/workflows/publish-skeleton.yml`はTag Pushを通常Triggerとして受け取り、最初のStepでbare SemVer以外を拒否する。既存TagのPublication Recoveryでは、Default Branch上のWorkflowをManual Dispatchし、必須`release_version`へ既存bare SemVer Tagを指定できる。どちらのTriggerも同名TagをFull Checkoutし、Framework TagがChecked-out Commitを指すことを確認してから次のGateを順番に実行する。

GitHub-hosted RunnerではRunnerのUID／GIDを`HOST_UID`／`HOST_GID`としてComposeへ渡す。これによりbind-mounted WorkspaceへContainer UserがDependencyやTest Artifactを作成できる。Manual DispatchでもEvent SHAはPublication Sourceに使用せず、検証済みTag Checkoutの`HEAD`からSplitする。

1. Composer Validation、Mago、Full PHPUnit、Deptrac
2. Generatorを含むQuickstart Consumer E2E
3. Generatorを含むLocal通常／`--no-scripts` Create-project Smoke
4. Local Framework Update Generator Smoke
5. Framework Stub AllowlistとSkeletonへのStub非複製検証
6. Skeleton Publication Dry Run
7. Temporary Bare Repositoryを使うWorkflow Regression
8. Deploy Key設定とRemote Tag Object／Peeled Commit監査
9. Split Commitを`main`へPush
10. 同じVersionのannotated Tag RefをPush

Quality Gateを一つでも通過しない限りCredentialを展開しない。Private KeyはRunnerの一時DirectoryへMode `0600`で書き、Job終了時に必ず削除する。GitHub Host KeyはTLS検証されたGitHub Metadata APIから取得し、`StrictHostKeyChecking=yes`で使用する。Workflow LogまたはArtifactへKeyを出力しない。

Remote TagはDirect RefとPeeled Refを分けて読み、既存annotated tagをFetchしてObject Type `tag`とPeeled Commitを監査する。Peeled CommitがSplit Commitと一致する既存annotated tagだけを冪等成功とする。別Commitを指すannotated tagと、新規Versionに存在するlightweight tagは失敗する。Remote `main`が新しいSplit CommitのAncestorでない場合も失敗する。Force Push、Tag削除、Tag上書きは行わない。

## External setup

Local Workflow受入後、Repository Ownerが次を一度だけ設定する。

1. Main Framework Repository `kubotak-is/blackops`へWorking Repository Historyを接続する。
2. GitHubへ空のPublic `kubotak-is/blackops-skeleton` Repositoryを作成する。READMEやLicenseを初期生成せず、最初の`main`をWorkflowにPublishさせる。
3. Repository専用Ed25519 Key PairをRepository外の一時Directoryに作成する。
4. Public Keyを`kubotak-is/blackops-skeleton`のWrite-enabled Deploy Keyとして登録する。
5. Private Keyを`kubotak-is/blackops`のActions Secret `SKELETON_DEPLOY_KEY`へ登録する。
6. Local Private Keyを削除し、Working TreeとGit Historyへ混入していないことを確認する。
7. Packagistの`blackops/skeleton`を`https://github.com/kubotak-is/blackops-skeleton`へ接続し、GitHub Tag連携を有効にする。

初回Stable `1.0.0`では上記設定を完了し、Framework Commit `279716f`とSkeleton Split Commit `da573f3`を同じVersionでGitHub／Packagistへ公開した。Skeleton `1.0.0` Tagはlightweight tagとして公開済みであり、ImmutableなLegacyとしてそのまま維持する。Remote通常／`--no-scripts` Create-projectも空のComposer Homeから検証済みである。

GitHub／Packagist認証が利用できない場合、Local Dry Runまでを完了状態とし、外部設定と最初のRelease Tag PushはBlockerとして残す。

## Recovery

Branch Push後にTag Pushだけが失敗した場合、同じFramework Release TagをManual Dispatchへ指定して再処理する。Remote `main`が同じSplit CommitならBranch Pushを省略し、不足したTagだけを作成する。

Tag Commit内のWorkflowがPublication完了前に失敗した場合も、公開済みFramework Tagを移動または削除しない。修正版WorkflowをMainへCommitした後、Manual Dispatchで同じVersionを再処理する。RecoveryもFull Quality、Consumer、Create-project、Publication、Credential、Remote Divergence Gateを省略しない。異なるCommitを手動でPushして回避しない。

公開済みSkeleton `1.0.0`だけはManual Dispatch時に限り、既存lightweight TagのDirect Commitが期待Split Commitと一致すればRecoveryを継続する。Tag Push Trigger、別Version、異なるDirect Commitではこの例外を適用しない。Legacy Tagをannotatedへ置換、削除、移動する処理はない。
