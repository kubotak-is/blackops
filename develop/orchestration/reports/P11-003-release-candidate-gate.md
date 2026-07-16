# P11-003: Release Candidate Gate Report

Status: Blocked

## Summary

Fixed Release Candidate `49b42efe5a0671cbae9212203a07271c1cf36f2b`のSource of TruthとRelease AutomationをGate開始時に照合し、Skeleton Tagの形式が確定仕様と矛盾するBlockerを検出した。

`develop/spec/61-experimental-release-contract.md`はFrameworkとSkeletonの両方へannotated tag `1.1.0`を付けることを要求する。一方、Fixed Candidateの`tests/Consumer/skeleton-publication.sh`は`git tag <version> <split-commit>`でlightweight tagを生成し、`.github/workflows/publish-skeleton.yml`は`<split-commit>:refs/tags/<version>`を直接Pushするため、Distribution Repositoryにもlightweight tagを作成する。

現行Workflowの既存Tag監査も`git ls-remote`で得たTag Ref Object IDをSplit Commit IDと直接比較する。annotated tagではTag Ref Object IDはTag Objectを指し、Peeled Commitは`refs/tags/<version>^{}`で得る必要があるため、正しいannotated tagが存在するとDivergenceとして拒否する。

この問題はRelease AutomationとConsumer Testの変更をFixed Candidateへ含める必要がある。Task ConstraintによりCandidate SHAを暗黙に変更できないため、Production／Test／Workflowを変更せず、残りのFull GateとExternal Publication Preflightを実行せずに停止した。Tag、Release、Repository、Packagist、Documentation Websiteを含むExternal Stateは変更していない。

## Fixed Release Candidate Evidence

- Checked At: `2026-07-17T00:02:37+09:00`
- Fixed Source Commit: `49b42efe5a0671cbae9212203a07271c1cf36f2b`
- Subject: `docs: prepare experimental 1.1 release`
- Local Task Commit／Remote `origin/main`: `e44aa345c666d910083517875282039677f67e2c`
- Fixed SourceはLocal／Remote `main` Historyのancestorである。
- Fixed SourceからTask Commitまでの変更は`develop/STATE.md`と`develop/orchestration/tasks/P11-003-release-candidate-gate.md`だけであり、Release Source自体は変更していない。

## Blocking Contract Difference

### Required Contract

`develop/spec/61-experimental-release-contract.md:19`:

```text
FrameworkとSkeletonへannotated tag 1.1.0を付ける
```

### Current Consumer Validation

`tests/Consumer/skeleton-publication.sh:136`:

```bash
git -C "${source_clone}" tag "${version}" "${split_commit}"
```

`-a`または`-s`を指定しないため、`refs/tags/<version>`はSplit Commitを直接指すlightweight tagになる。続く`rev-list`検証はTagがSplit Commitへ解決されることだけを確認し、Tag Objectの存在とannotated tagであることを検証しない。

### Current Publication Workflow

`.github/workflows/publish-skeleton.yml:134-136`:

```bash
git -C "${publication_root}/source" push "${SKELETON_REMOTE}" \
  "${split_commit}:refs/tags/${RELEASE_VERSION}"
```

Commit Object IDをTag Refへ直接Pushするため、Skeleton Distribution Repositoryへlightweight tagを生成する。

同Workflowの既存Tag監査は`git ls-remote`の`refs/tags/<version>`をSplit Commitと直接比較する。annotated tagの場合、この値はTag Object IDでありSplit Commit IDではないため、Recovery／Idempotency Gateも確定仕様と両立しない。

## Local Full Gate Evidence

Full PHP／Website Gateは未実行。Source of Truth監査でFixed Candidateの修正が必要なBlockerを先に検出し、Task ConstraintのFail-fast条件に従って停止した。

次のRequired Commandsはすべて未実行である。

- Composer Strict Validation（Root／Skeleton）
- Mago Format／Lint／Analyze
- Full PHPUnit／Deptrac
- Website Unit／Check／Build／Public Artifact Guard
- Public API、Management ID、Generated State、Working Tree Guard

## GitHub Actions Evidence

Task PacketにCI Run `29508886946`のSuccess、Documentation Delivery Run `29508886458`のArtifact Build Successが記録されている。ただし本WorkerによるExternal Read-only再確認は、Blocker検出後に追加Commandを実行せず停止したため未実行である。

## Split and Create-project Evidence

次は未実行である。

- 全6 Consumer／Installation／Worker／Framework Update Smoke
- `bash tests/Consumer/skeleton-publication.sh 1.1.0 49b42efe5a0671cbae9212203a07271c1cf36f2b`
- Fixed Sourceの決定的なSplit Commit生成
- 通常／`--no-scripts` Create-project境界監査

Publication Dry Run自体がlightweight tagを正常扱いするため、現行結果を`1.1.0` Publication Acceptance Evidenceとして採用できない。

## Release Surface and Known Limitations Review

CHANGELOG Known LimitationsとUPGRADE手順の最終照合は未実行。Release Automation Blockerの解消と新Fixed Candidate確定後に、同じCandidateを対象として再実行する必要がある。

## Publication Preflight State

Framework／Skeleton `1.1.0` Tag、GitHub Release、Packagist Stable、Actions Secret、Remote Branch／TagのExternal Read-only確認は未実行。Blocker検出後の停止指示に従い、外部状態へアクセスする追加Commandも実行していない。

External Mutationは一切実行していない。

## P11-004 Publication Checklist and Recovery

P11-004 ChecklistはFixed Candidateが確定していないため固定できない。Release Automation follow-upでは少なくとも次を満たした上で、新Candidate SHAをTask Packetへ明示する必要がある。

1. Skeleton Split Commitを指すannotated tag objectを明示的に生成する。
2. Consumer TestがTag Object Typeを`tag`として検証し、Peeled Commitが決定的なSplit Commitと一致することを検証する。
3. WorkflowがCommit Refではなくannotated Tag RefをDistribution RepositoryへPushする。
4. Remote既存TagをTag Object IDではなくPeeled Commitで照合し、同名lightweight tagをSuccessとして扱わない。
5. Manual Dispatch Recoveryも通常Tag Triggerと同じannotated tag、Peeled Commit、Immutable Tag条件を検証する。
6. 修正を含む新CandidateでCI／Documentation Artifact Buildを成功させ、P11-003の全Local GateとRead-only Publication Preflightを最初から再実行する。
7. 上記Evidenceが揃うまでannotated Framework Tag `1.1.0`をPushせず、P11-004へ進まない。

## Changed Files

- `develop/orchestration/tasks/P11-003-release-candidate-gate.md`
- `develop/orchestration/reports/P11-003-release-candidate-gate.md`
- `develop/STATE.md`

Production Code、Test、Workflow、Release Metadata、利用者向けDocumentationは変更していない。

## Decisions and Assumptions

- `develop/spec/61-experimental-release-contract.md`のannotated tag要件を正本とした。
- lightweight tagとannotated tagは同じCommitへ解決できても、Tag Object、署名可能性、Tagger Metadata、Remote Ref監査の意味が異なるため同一契約として扱わない。
- Spec変更で現行Automationを追認せず、既存仕様どおりRelease Automationを修正する必要がある。
- Fixed Candidateを変更できないP11-003内では修正せずBlockerとして返す。

## Commands and Results

```text
Source of Truth read:
AGENTS.md
develop/STATE.md
develop/orchestration/tasks/P11-003-release-candidate-gate.md
develop/spec/README.md
develop/spec/61-experimental-release-contract.md
develop/spec/62-phase-11-delivery-plan.md
develop/spec/46-composer-skeleton-publication.md
develop/spec/57-documentation-website-delivery-contract.md
develop/decisions/094-stable-1-1-release-contract.md
develop/decisions/079-immutable-release-publication-recovery.md
develop/decisions/076-framework-and-skeleton-repository-naming.md
develop/orchestration/reports/P11-001-release-surface-reset.md
develop/orchestration/reports/P11-002-release-documentation-and-metadata.md
Result: Fixed Candidate、annotated tag契約、Publication／Recovery境界を確認した。

git show -s --format='%H%n%s%n%ci' 49b42efe5a0671cbae9212203a07271c1cf36f2b
git rev-parse origin/main
git merge-base --is-ancestor 49b42efe5a0671cbae9212203a07271c1cf36f2b origin/main
git diff --name-status 49b42efe5a0671cbae9212203a07271c1cf36f2b..HEAD
Result: Fixed SourceはLocal／Remote main Historyに存在する。HEAD／origin/mainはe44aa345c666d910083517875282039677f67e2c。Fixed Source以後はTask／STATEのみ。

git show <fixed>:develop/spec/61-experimental-release-contract.md
git show <fixed>:tests/Consumer/skeleton-publication.sh
git show <fixed>:.github/workflows/publish-skeleton.yml
Result: SpecはSkeleton annotated tagを要求するが、Consumerはgit tag <version> <split>、Workflowは<split>:refs/tags/<version>を使用しlightweight tagを生成する。既存Tag比較もannotated tagのpeeled commitを扱わない。
```

Task Packet記載のFull Gate、Consumer、Website、External Read-only Evidence CommandはBlocker検出後のFail-fast停止により未実行である。

## Acceptance Criteria

- [x] Fixed Release Candidate SHAがLocal／Remote main Historyに存在し、P11-002 Accepted Commitと一致する
- [ ] GitHub Actions Evidenceを本WorkerがRead-onlyで再確認する
- [ ] Composer、Mago、Full PHPUnit、Deptracが成功する
- [ ] 全6 Consumerが成功する
- [ ] Skeleton `1.1.0` Publication Dry Runがannotated tag契約を満たして成功する
- [ ] 通常／`--no-scripts` Create-projectが成功する
- [ ] Website Gateが成功する
- [ ] Public API／Management ID／Credential／Generated State／Working Tree Guardが成功する
- [ ] CHANGELOG Known LimitationsとUPGRADE手順が実装Surfaceと一致する
- [ ] External Publication前状態をRead-onlyで確認する
- [ ] P11-004 Publication Checklistを固定する
- [x] Blocker、実行済みCommand、未実行理由をReportとSTATEへ記録する

## Orchestrator Review

OrchestratorがFixed SourceのSpec、Consumer Script、Publication Workflowを独立照合し、Blockerを再現した。現行Scriptはlightweight tagを作成し、WorkflowもSplit CommitをTag Refへ直接Pushする。annotated tagのTag ObjectとPeeled Commitを区別する検証がないため、Spec 61のFramework／Skeleton annotated tag契約を満たさない。

仕様は明確であり、互換性方針の追加判断は不要と判定した。P11-003内でCandidateを差し替えず、Release Automation follow-upを先行させるBlocker処理を受け入れた。

## Remaining Issues

P11-003はBlockedである。Fixed CandidateのRelease AutomationはSkeletonへannotated tagを公開できず、正しいannotated tagをRecovery時に既存Tagとして受理できない。

## Suggested Next Action

OrchestratorがRelease Automation follow-up Taskを作成し、`tests/Consumer/skeleton-publication.sh`と`.github/workflows/publish-skeleton.yml`をannotated Skeleton tag契約へ修正する。修正をReview／Commit／PushしてGitHub Actions成功を確認した後、そのCommitを新しいFixed Release Candidate SHAとしてP11-003 Task Packetへ明記し、全Gateを最初から再実行する。
