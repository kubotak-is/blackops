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
| Release tag | Frameworkと同じ`MAJOR.MINOR.PATCH` |
| Write credential | Repository専用Deploy Key |
| Main repository secret | `SKELETON_DEPLOY_KEY` |
| Packagist update | Distribution RepositoryのGitHub連携 |

Packagist TokenはMain Repositoryへ登録せず、Publication WorkflowからPackagist APIを呼ばない。

## Local dry run

Release候補は外部状態を変更せずに検証できる。

```bash
bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
```

ScriptはVersionとSource Refを検証し、RepositoryのLocal Cloneで`git subtree split --prefix=examples/quickstart`を2回実行する。両方のCommit IDが一致しなければ失敗する。Split Treeは一時Directoryへ展開され、Root Allowlist、必須Entrypoint、Executable Bit、Composer Metadata、Framework Constraint、Post-create Script、Generated State不在を検証する。

Version `M.N.P`に対してSkeletonの`blackops/framework` Constraintは`^M.N`でなければならない。Composer Metadataへ`version`または`repositories`がある場合も失敗する。Split Commitへ同じbare SemVer Tagを作成して対応を確認する。

Dry RunはMain Working Tree、Docker Resource、Remote Repository、Credential、Packagistを変更しない。一時Clone、Split Tree、Local Tagは成功時と失敗時の両方で削除する。

## Release workflow

`.github/workflows/publish-skeleton.yml`はTag Pushを受け取るが、最初のStepでbare SemVer以外を拒否する。Full Checkout後にFramework TagがChecked-out Commitを指すことを確認し、次のGateを順番に実行する。

1. Composer Validation、Mago、Full PHPUnit、Deptrac
2. Quickstart Consumer E2E
3. Local通常／`--no-scripts` Create-project Smoke
4. Skeleton Publication Dry Run
5. Deploy Key設定とRemote Divergence検証
6. Split Commitを`main`へPush
7. 同じVersion TagをSplit CommitへPush

Quality Gateを一つでも通過しない限りCredentialを展開しない。Private KeyはRunnerの一時DirectoryへMode `0600`で書き、Job終了時に必ず削除する。GitHub Host KeyはTLS検証されたGitHub Metadata APIから取得し、`StrictHostKeyChecking=yes`で使用する。Workflow LogまたはArtifactへKeyを出力しない。

Remoteの同名Tagが別Commitを指す場合は失敗する。Remote `main`が新しいSplit CommitのAncestorでない場合も失敗する。Force PushとTag上書きは行わず、すでに同じCommit／Tagが公開されている再実行は成功扱いにする。

## External setup

Local Workflow受入後、Repository Ownerが次を一度だけ設定する。

1. Main Framework Repository `kubotak-is/blackops`へWorking Repository Historyを接続する。
2. GitHubへ空のPublic `kubotak-is/blackops-skeleton` Repositoryを作成する。READMEやLicenseを初期生成せず、最初の`main`をWorkflowにPublishさせる。
3. Repository専用Ed25519 Key PairをRepository外の一時Directoryに作成する。
4. Public Keyを`kubotak-is/blackops-skeleton`のWrite-enabled Deploy Keyとして登録する。
5. Private Keyを`kubotak-is/blackops`のActions Secret `SKELETON_DEPLOY_KEY`へ登録する。
6. Local Private Keyを削除し、Working TreeとGit Historyへ混入していないことを確認する。
7. Packagistの`blackops/skeleton`を`https://github.com/kubotak-is/blackops-skeleton`へ接続し、GitHub Tag連携を有効にする。

GitHub／Packagist認証が利用できない場合、Local Dry Runまでを完了状態とし、外部設定と最初のRelease Tag PushはBlockerとして残す。

## Recovery

Branch Push後にTag Pushだけが失敗した場合、同じFramework Release TagのWorkflowを再実行する。Remote `main`が同じSplit CommitならBranch Pushを省略し、不足したTagだけを作成する。異なるCommitを手動でPushして回避せず、Main RepositoryのSourceまたはCredentialを修正して再実行する。
