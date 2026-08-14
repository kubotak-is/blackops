# P22-004: Stable 1.2 Publication and Closeout

Status: In Progress (P22-004A Skeleton Workflow Recovery Authorized)

## Goal

P22-003でAcceptedとなったFramework SourceとSkeleton SplitをExperimental Stable `1.2.0`として公開し、GitHub／Packagist／Remote ConsumerのLive Evidenceを収集してPhase 22をCloseする。

## Fixed Publication Inputs

- Framework Source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Framework Merge Commit: `547149109419b62ab769af9d3aad1ed80dbba905`
- Skeleton Split: `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`
- Version: `1.2.0`
- Framework Tag Message: `BlackOps Framework 1.2.0`
- Skeleton Tag Message: `BlackOps Skeleton 1.2.0`
- Same-SHA CI: `31771509163`
- Documentation Delivery: `31771509167`
- Accepted Gate: `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`

Tracking CommitやPublication後のCloseout CommitをRelease Sourceに含めない。Framework Source／Skeleton Splitを暗黙に読み替えない。

## Authorization

User explicitly instructed: `3332fd1でCIを通し、Greenなら1.2.0を発行して`.

Same-SHA CI is Green and P22-003 is Accepted. This authorization covers the fixed publication sequence below. It does not authorize source correction, tag reassignment, destructive recovery, documentation production deployment, or secret retrieval.

## In Scope

- P22-003 Acceptance／P22-004 tracking documentsのclean checkpoint CommitとPR-required remote `main` integration
- Framework annotated tag `1.2.0`のLocal作成／検証／Push
- Skeleton Publication Workflowの監視と、必要時の既存Manual Recovery
- Framework／Skeleton Remote annotated tag、Peeled Commit、Skeleton `main`の検証
- Packagist Framework／Skeleton `1.2.0` MetadataとConstraintの検証
- `CHANGELOG.md`を要約したGitHub Release `1.2.0`の作成
- 公開Packageだけを使う通常／`--no-scripts` Remote Create-project Smoke
- Project Root `blackops`、Documented Quickstart、Migration、HTTP／Worker、Redaction Smoke
- P22-004 Report、TODO、Specification 103、STATEのCloseout

## Out of Scope

- Framework／Skeleton Source、Production Code、Test、Workflowの変更
- Fixed Source／Splitの差し替え
- 公開済みTagの移動、削除、再割当
- CI Failureのskip／waive／allow-failure
- Documentation WebsiteのCloudflare production公開
- Credential／Token／Private Key／Storage Keyの取得または記録

## Relevant Specifications and Decisions

- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/decisions/139-stable-1-2-version-baseline.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003D-runtime-consumer-executable-mode.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/STATE.md`

Production／Test／Workflowの修正が必要ならPublicationを広げず、公開済みTagを不変のままReportへBlockerを記録する。

## Constraints

- UserのGreen-gated Publication承認に従い、P22-003 Acceptedとtracking checkpoint integration後は追加User確認なしで固定Sequenceを継続する
- Tag作成直前にWorking Tree clean、Fixed Sourceのremote `main` ancestor、同名Remote Tag／GitHub Release不存在を再確認する
- FrameworkとSkeletonの`1.2.0`はannotated tagとし、Direct Tag ObjectとPeeled Commitを分離検証する
- Framework Tag Push後は成否にかかわらず移動または削除しない
- Skeleton Workflowは固定Split以外をSuccessとしない
- Packagist反映遅延はTagを変更せず再確認する
- Remote SmokeはLocal Path Repository／Local Framework Mount／既存Composer Cacheを使用しない
- Secretは名前だけを確認し、値を取得、表示、保存しない
- Documentation Website Production Publicationは実行しない

## Acceptance Criteria

- [x] Tracking checkpointがreviewed Commitとしてremote `main`へ統合され、Working Treeがcleanである（Documentation Review P1=0／P2=0／P3=0、PR #4 merge `55bfe12`）
- [x] Framework `1.2.0`がannotated tagで、Peeled CommitがFixed Sourceと一致する（direct object `00e8c587`、peeled `3332fd1`）
- [ ] Skeleton Publication WorkflowがFull Gate後に成功する
- [ ] Skeleton `main`とannotated `1.2.0`のPeeled CommitがFixed Splitと一致する
- [ ] Packagist `blackops/framework`／`blackops/skeleton` `1.2.0`が公開Tagを参照する
- [ ] Skeleton `1.2.0`がFramework `^1.2`を要求する
- [ ] GitHub Release `1.2.0`がExperimental Policy、Changed／Removed Surface、Known Limitationsを要約する
- [ ] Remote通常／`--no-scripts` Create-projectがSkeleton／Framework `1.2.0`をLockする
- [ ] Project Root CLI、Compile、Migration、HTTP／Worker、Sensitive Redactionが公開Packageだけで成功する
- [ ] 既存`1.0.0`／`1.1.0`、Credential、Documentation Website production stateを変更していない
- [ ] Phase 22 Acceptance Criteria、TODO、Report、STATEがEvidence付きでCloseする

## Publication and Verification Commands

```bash
git status --short
git cat-file -t 3332fd1dd0738fc7e79750facd93d49a59054ecf
git merge-base --is-ancestor 3332fd1dd0738fc7e79750facd93d49a59054ecf origin/main
git ls-remote --tags origin 'refs/tags/1.2.0' 'refs/tags/1.2.0^{}'
git ls-remote --heads --tags https://github.com/kubotak-is/blackops-skeleton.git main 'refs/tags/1.2.0' 'refs/tags/1.2.0^{}'
gh release view 1.2.0 --repo kubotak-is/blackops
gh secret list --repo kubotak-is/blackops

git tag -a 1.2.0 3332fd1dd0738fc7e79750facd93d49a59054ecf -m 'BlackOps Framework 1.2.0'
git cat-file -t refs/tags/1.2.0
git rev-parse 'refs/tags/1.2.0^{}'
git push origin refs/tags/1.2.0

gh run list --workflow publish-skeleton.yml --repo kubotak-is/blackops
gh run watch <run-id> --repo kubotak-is/blackops --exit-status

git ls-remote --heads --tags https://github.com/kubotak-is/blackops-skeleton.git main 'refs/tags/1.2.0' 'refs/tags/1.2.0^{}'
gh release view 1.2.0 --repo kubotak-is/blackops
git diff --check
```

Packagist MetadataとRemote Smokeの実行Command、Temporary Path、Cleanup、Checked At、Live Run IDをReportへ記録する。

## Failure and Recovery

- Framework Tag Push前のMismatchはPublicationを停止し、Source correctionが必要なら新Candidate／Full P22-003 Gateへ戻る
- Framework Tag Push後は同Tagを移動、削除、再作成しない
- Tag-trigger Skeleton Workflow失敗時は`release_version=1.2.0`でManual Dispatchし、同じFramework TagをCheckoutして全Gateから再実行する
- Skeleton annotated tagのPeeled CommitがFixed Splitと一致する場合だけ冪等成功とする
- Skeleton同名lightweight tag、異なるPeeled Commit、`main`のFast-forward不能は自動修正せずBlockerとする
- GitHub Release作成失敗はPackage Tagを変更せずRelease作成だけを再処理する
- Packagist反映遅延はImmutable Tagを保持してbounded pollingする

## Active Blocker

Tag-triggered run `31809007808` passed Framework quality but failed before credential configuration／Skeleton push because `tests/Consumer/quickstart-e2e.sh:63` invokes `mise` and `.github/workflows/publish-skeleton.yml` does not install the repository-pinned toolchain. Exit was `127`. Always-run cleanup passed.

D079 permits recovery from the main-branch Workflow while checking out immutable Framework tag `1.2.0`. User authorized completing the release and Composer-installable publication. P22-004A owns the bounded correction: add `jdx/mise-action@v4` with `install: true`／`cache: true`, verify Node `v24.18.0`／pnpm `11.12.0`, review／CI／main integration, then Manual Dispatch `release_version=1.2.0`. The parent Task remains fixed to immutable Framework tag source `3332fd1`.

## Expected Report

`develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`へ次を記録する。

- Summary
- Fixed Source／Split／Preflight Evidence
- Framework／Skeleton Tag and Workflow Evidence
- Packagist／GitHub Release Evidence
- Remote Normal／No-scripts／Quickstart Evidence
- Immutable Tag／Credential／Documentation Website Boundary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
