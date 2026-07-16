# P11-003A: Annotated Skeleton Release Tag Report

Status: Accepted

## Summary

Skeleton PublicationのLocal Consumer TestとGitHub Actions Workflowをannotated tag契約へ修正した。新規Releaseは固定Tagger Identityと`BlackOps Skeleton <version>` Messageを持つTag Objectを生成し、Tag Ref自体をSkeleton RemoteへPushする。Release Contractの決定性はTag Object IDではなく、annotated tagをPeeledしたCommitとSkeleton Split Commitの一致で検証する。

Remote監査はDirect Tag RefとPeeled Refを分離した。既存annotated tagはTag ObjectをFetchしてObject TypeとPeeled Commitを監査し、同じSplit Commitの場合だけ冪等成功とする。新規Releaseのexisting lightweight tag、異なるCommitへPeeledするannotated tagは変更せず失敗する。

公開済みSkeleton `1.0.0` lightweight tagは、Manual DispatchかつDirect Commitが期待Split Commitと一致する場合だけImmutable Recoveryとして維持する。Tag Trigger、別Version、異なるDirect Commitでは例外を適用しない。TagをForce Push、削除、移動、置換するPathは追加していない。

WorkflowのPublication Run Block自体を抽出してTemporary Bare Repositoryへ適用するRegression Testを追加した。External Tag、Repository、Release、Packagist、Documentation Websiteは変更していない。

## Annotated Tag Contract Evidence

- Local Publication Source Commit: `a2d2eb2a13d11d44372e2d646054ce5664e7de85`
- Deterministic Skeleton Split Commit: `293f880940636669f28ded756a888a8d6ba65f1b`
- Tag Object Type: `tag`
- Tag Message: `BlackOps Skeleton 1.1.0`
- Tagger Identity: `BlackOps Release Automation <release@blackops.dev>`
- Peeled Commit: `293f880940636669f28ded756a888a8d6ba65f1b`

`tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`は`--no-tags` Local Cloneでannotated tagを生成する。Framework Tagと同名のLocal Tagを削除する必要はなく、Tag Object Type、Message、Peeled Commitを検証してTemporary Treeごと削除する。

Workflowも`--no-tags` Temporary Cloneへ同じIdentityとMessageでannotated tagを作り、次のRefspecでLocal Tag RefをRemote Tag RefへPushする。

```text
refs/tags/${RELEASE_VERSION}:refs/tags/${RELEASE_VERSION}
```

Commit Objectを`refs/tags/<version>`へ直接PushするRefspecは残していない。

## Remote Tag Inspection / Recovery Evidence

`tests/Consumer/skeleton-publication-workflow.sh`はWorkflow YAMLの`Publish split commit and matching annotated tag` Run Blockをそのまま抽出し、Local／Temporary Bare Repositoryに対して次を検証した。

1. Remote Tag不在時にannotated Tag Objectを生成し、Peeled CommitがSplit Commitと一致する。
2. 同じSplit CommitへPeeledする既存annotated tagの再実行は成功し、既存Tag Object IDを変更しない。
3. 異なるCommitへPeeledする既存annotated tagは所定のDivergence Boundaryで失敗し、既存Tag Object IDを変更しない。
4. 同じSplit CommitをDirectに指すVersion `1.3.0` lightweight tagもManual実行を含めて失敗し、既存Tagを変更しない。
5. Remote annotated tagのDirect Object ID、Object Type `tag`、Peeled CommitをFetchしたAudit Refと相互検証する。

Remote `main`は従来どおりSplit CommitへのFast-forwardだけを許可する。Tag監査はBranch Pushより前に行うため、Tag Contract違反時にRemote `main`を変更するPathもない。

## Legacy 1.0.0 Compatibility Evidence

OrchestratorのTask開始前Read-only確認では、公開済みSkeleton `refs/tags/1.0.0`と`main`はともに`da573f3190e5e855a9c09e275980c6ddc5cce028`を指し、`refs/tags/1.0.0^{}`は存在しない。Legacy Tagが実際にlightweightである前提と実装契約が一致する。WorkerはこのExternal Stateへアクセス、変更していない。

Temporary Bare Repository Regressionでは次を確認した。

- Manual Recovery、Version `1.0.0`、Direct Commit一致の組合せだけ成功する。
- Recovery後もTag Object Typeは`commit`で、Direct Ref Object IDは不変、Peeled Refは存在しない。
- 同じLegacy TagでもTag Triggerでは失敗する。
- Manual RecoveryでもDirect Commitが異なる場合は失敗する。
- 失敗時を含めてLegacy Tagを削除、移動、annotated tagへ置換しない。

## Workflow / Credential Boundary Evidence

- Workflow RegressionはDeploy Key展開Stepより前に実行する。
- Composer、Mago、PHPUnit、Deptrac、Consumer、Local Publication、Workflow Regressionがすべて成功した後だけ`SKELETON_DEPLOY_KEY`を展開する。
- Tagger IdentityとMessageはWorkflow／Consumer内の固定値で、Framework Credential、Deploy Key、Runner／User Local Git Configへ依存しない。
- Deploy Key、Token、Private KeyをSource、Test、Report、Logへ追加していない。
- `--force`、`force-with-lease`、`push -f`、Tag Deleteを追加していない。
- Workflow YAML ParseとShell Syntax Checkが成功した。

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `docs/internal/skeleton-publication.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/mvp-e2e.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-003A-annotated-skeleton-release-tag.md`
- `develop/orchestration/reports/P11-003A-annotated-skeleton-release-tag.md`
- `develop/STATE.md`

Production Code、Public API、Skeleton Source、利用者向けDocumentationは変更していない。

## Decisions and Assumptions

- 新規Skeleton Release Tagはannotated tagであり、Tag Object IDの再現性ではなくPeeled Split Commitの決定性をContractとした。
- Tag Messageは`BlackOps Skeleton <version>`、Tagger Identityは`BlackOps Release Automation <release@blackops.dev>`に固定した。
- RemoteのDirect RefがありPeeled Refがない状態をlightweight tagと判定する。
- 既存annotated tagはMessageやTag Object IDを再生成して一致させず、Object Type `tag`とPeeled Commit一致だけでImmutableな冪等成功を判定する。
- Legacy `1.0.0`例外はManual Dispatch EventをWorkflow Envへ明示し、通常Tag Triggerと同じPublication Run Block内で判定する。
- Task Packetの旧Force Guardが通常の`test -f`まで誤検出したため、Orchestrator確認の上、実際のForce Push Optionだけを検出するPatternへ修正した。Production TestのRegular File Checkは弱めていない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: RootとQuickstartのComposer Metadataがstrict validationに成功。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (871 tests, 2831 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1712 / Warnings 0 / Errors 0。

bash -n tests/Consumer/skeleton-publication.sh
bash -n tests/Consumer/skeleton-publication-workflow.sh
Result: 両方のShell Syntax Checkが成功。

bash tests/Consumer/skeleton-create-project.sh
Result: 通常／--no-scriptsのSkeleton 1.1.0 Create-project Smokeが成功。

bash tests/Consumer/framework-update-generators.sh
Result: Framework 1.0.0から1.1.0へのUpdate、Application所有File不変、Current Generator切替が成功。

bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
Result: Success。source=a2d2eb2a13d11d44372e2d646054ce5664e7de85、split=293f880940636669f28ded756a888a8d6ba65f1b。Annotated Tag Object、Message、Peeled Commitを検証。

bash tests/Consumer/skeleton-publication-workflow.sh
Result: Success。New annotated publication、idempotent annotated recovery、annotated divergence rejection、new lightweight rejection、Legacy 1.0.0 Manual Recovery／Tag Trigger rejection／divergence rejectionをTemporary Bare Repositoryで検証。

mise exec -- node -e '<YAML parse>' <local yaml module> .github/workflows/publish-skeleton.yml
Result: Workflow YAML parse passed。

! rg -n -- '--force|force-with-lease|push.*[[:space:]]-f([[:space:]]|$)' .github/workflows/publish-skeleton.yml tests/Consumer/skeleton-publication*.sh
! rg -n -- 'tag[[:space:]]+(--delete|-d)([[:space:]]|$)|push[[:space:]]+--delete|push[^[:cntrl:]]*[[:space:]]\+' .github/workflows/publish-skeleton.yml tests/Consumer/skeleton-publication*.sh
Result: Force Push Option、Tag Delete、Delete Push、`+` RefspecはMatchなし。

! rg -n 'BEGIN (OPENSSH|RSA|EC) PRIVATE KEY|github_pat_|ghp_|gho_' . --hidden --glob '!.git/**' --glob '!develop/**'
Result: Credential／Private Key PatternはMatchなし。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
Result: PHP Source／Test Management IDはMatchなし。

git diff --check
Result: Success。
```

最初のDocker並列実行はSandbox内Docker Socket Permissionで4 Commandが実行前に失敗した。Repository／Dockerの問題ではなく、同じCommandを許可済みDocker実行境界で再実行してすべて成功した。

Workflow YAML Parseの一回目はNode `require()`へ相対Module Pathを渡したためModule Resolution Errorになった。`path.resolve()`でLocal Installed YAML Moduleを解決するCommandへ修正し、Parse成功を確認した。Workflow YAML自体のParse Errorではない。

## Acceptance Criteria

- [x] Local Publication Testがannotated tag objectを生成する
- [x] Local TestがTag Object Type `tag`、所定Message、Peeled Split Commit一致を検証する
- [x] Workflowがannotated Tag RefをSkeleton RemoteへPushする
- [x] Existing annotated tagはPeeled Commit一致時だけIdempotent Successとなる
- [x] Existing annotated tagのPeeled Commit不一致をFail-closedで拒否する
- [x] New ReleaseのExisting lightweight tagをFail-closedで拒否する
- [x] Legacy Skeleton `1.0.0` lightweight tagだけは同一Split CommitならImmutable Recoveryとして許可する
- [x] Legacy `1.0.0` Tagを移動／削除／置換するPathがない
- [x] Manual DispatchとTag Triggerが同じContractを通る
- [x] Workflow／Shell Regression Guardが成功する
- [x] Composer、Mago、PHPUnit、Deptrac、関連Consumer Gateが成功する
- [x] Internal Documentation、Report、STATEが更新される

## Orchestrator Review

OrchestratorがPublication Workflow、Local Publication Test、Temporary Bare Remote Regression、Spec／Internal Documentationを独立Reviewした。Direct Tag RefとPeeled Refの分離、annotated Tag Object Fetch、同一Peeled Commitだけの冪等成功、Legacy `1.0.0` Manual Recovery限定例外、Branch／Tag変更前のFail-closed順序を確認した。

次を独立再検証し、すべて成功した。

- `bash -n`による2本のShell Syntax Check
- Workflow Publication Run BlockのTemporary Bare Remote Regression
- Skeleton `1.1.0` Publication Full Run: source `a2d2eb2a13d11d44372e2d646054ce5664e7de85`、split `293f880940636669f28ded756a888a8d6ba65f1b`
- Composer Strict Validation: Root／Skeleton成功
- Mago Format Check: 成功
- Workflow YAML Parse: 成功
- Force Push／Delete／`+` Refspec、Credential、PHP Management ID、`git diff --check` Guard: 成功

WorkerのFull PHPUnit／Deptrac／Consumer EvidenceとOrchestratorの独立検証を受け入れ、P11-003AをAcceptedとした。

## Remaining Issues

P11-003Aの実装／Local VerificationにBlockerはない。External Stateは変更していないため、GitHub Actions上のWorkflow成功はOrchestratorのReview／Commit／Push後に確認する必要がある。

P11-003は旧Fixed CandidateのRelease Automation矛盾によりBlockedのままである。P11-003A Accepted CommitとCI成功後、そのCommitを新Fixed CandidateとしてFull Gateを最初から再実行する必要がある。

## Suggested Next Action

P11-003AをTask単位でCommit／Pushする。GitHub Actions成功を確認した後、Accepted Commitを新Fixed Candidate SHAとしてP11-003 Task Packetへ固定し、Release Candidate Full Gateを最初から再開する。
