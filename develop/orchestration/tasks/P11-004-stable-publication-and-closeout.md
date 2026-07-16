# P11-004: Stable Publication and Closeout

Status: In Progress

## Goal

P11-003で固定したFramework SourceとSkeleton SplitをExperimental Stable `1.1.0`として公開し、GitHub／Packagist／Remote ConsumerのLive Evidenceを収集してPhase 11をCloseする。

## Fixed Publication Inputs

- Framework Source: `e3df5576c7216cfe8bd9e10e12ee6795f7674088`
- Skeleton Split: `293f880940636669f28ded756a888a8d6ba65f1b`
- Version: `1.1.0`
- Framework Tag Message: `BlackOps Framework 1.1.0`
- Skeleton Tag Message: `BlackOps Skeleton 1.1.0`
- Accepted Gate: `develop/orchestration/reports/P11-003-release-candidate-gate.md`

Tracking CommitやPublication後のCloseout CommitをRelease Sourceに含めない。上記Source／Splitを暗黙に読み替えない。

## In Scope

- Framework annotated tag `1.1.0`のLocal作成／検証／Push
- Skeleton Publication Workflowの監視と、必要時の既存Manual Recovery
- Framework／Skeleton Remote annotated tag、Peeled Commit、Skeleton `main`の検証
- Packagist Framework／Skeleton `1.1.0` MetadataとConstraintの検証
- `CHANGELOG.md`を要約したGitHub Release `1.1.0`の作成
- 公開Packageだけを使う通常／`--no-scripts` Remote Create-project Smoke
- Project Root `blackops`とDocumented Quickstartの実行
- Phase 11 Report、TODO、Spec 62、STATEのCloseout

## Out of Scope

- Framework／Skeleton Source、Production Code、Test、Workflowの変更
- Fixed Source／Splitの差し替え
- 公開済みTagの移動、削除、再割当
- Documentation WebsiteのCloudflare公開
- Phase 12以降のFeature
- Credential／Token／Private Keyの取得または記録

## Relevant Specifications and Decisions

- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/reports/P11-003-release-candidate-gate.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-004-stable-publication-and-closeout.md`
- `develop/orchestration/reports/P11-004-stable-publication-and-closeout.md`
- `develop/STATE.md`

Production／Test／Workflowの修正が必要ならPublicationを広げず、公開済みTagを不変のままReportへBlockerを記録する。

## Constraints

- D094の事前承認に従い、P11-003 AcceptedとGitHub CI成功後は追加User確認なしでPublicationを継続する
- Tag作成直前にWorking Tree clean、Fixed Source、Remote同名Tag不存在を再確認する
- FrameworkとSkeletonの`1.1.0`はannotated tagとし、Direct Tag ObjectとPeeled Commitを分離検証する
- Framework Tag Push後は成否にかかわらず移動または削除しない
- Skeleton Workflowは固定Split以外をSuccessとしない
- Packagist反映遅延はTagを変更せず再確認する
- Remote SmokeはLocal Path Repository／Local Framework Mount／既存Composer Cacheを使用しない
- Documentation Website Publicationは実行しない

## Acceptance Criteria

- [ ] Framework `1.1.0`がannotated tagで、Peeled CommitがFixed Sourceと一致する
- [ ] Skeleton Publication WorkflowがFull Gate後に成功する
- [ ] Skeleton `main`とannotated `1.1.0`のPeeled CommitがFixed Splitと一致する
- [ ] Packagist `blackops/framework`／`blackops/skeleton` `1.1.0`が公開Tagを参照する
- [ ] Skeleton `1.1.0`がFramework `^1.1`を要求する
- [ ] GitHub Release `1.1.0`がExperimental Policy、Changed／Removed Surface、Known Limitationsを要約する
- [ ] Remote通常／`--no-scripts` Create-projectがSkeleton／Framework `1.1.0`をLockする
- [ ] Project Root `blackops`でCompileとDocumented Quickstartが成功する
- [ ] 既存`1.0.0`、Credential、Documentation Websiteを変更していない
- [ ] Phase 11 Acceptance Criteria、TODO、Report、STATEがEvidence付きでCloseする

## Publication and Verification Commands

```bash
git status --short
git cat-file -t e3df5576c7216cfe8bd9e10e12ee6795f7674088
git merge-base --is-ancestor e3df5576c7216cfe8bd9e10e12ee6795f7674088 origin/main
git ls-remote --tags origin 'refs/tags/1.1.0' 'refs/tags/1.1.0^{}'
git ls-remote --tags https://github.com/kubotak-is/blackops-skeleton.git 'refs/tags/1.1.0' 'refs/tags/1.1.0^{}'
gh release view 1.1.0 --repo kubotak-is/blackops

git tag -a 1.1.0 e3df5576c7216cfe8bd9e10e12ee6795f7674088 -m 'BlackOps Framework 1.1.0'
git cat-file -t refs/tags/1.1.0
git rev-parse 'refs/tags/1.1.0^{}'
git push origin refs/tags/1.1.0

gh run list --workflow publish-skeleton.yml --repo kubotak-is/blackops
gh run watch <run-id> --repo kubotak-is/blackops --exit-status

git ls-remote --heads --tags https://github.com/kubotak-is/blackops-skeleton.git main 'refs/tags/1.1.0' 'refs/tags/1.1.0^{}'
gh release view 1.1.0 --repo kubotak-is/blackops
git diff --check
```

Packagist MetadataとRemote Smokeの実行Command、Temporary Path、Cleanup、Checked At、Live Run IDをReportへ記録する。

## Failure and Recovery

- Tag-trigger Workflow失敗時は`release_version=1.1.0`でManual Dispatchし、同じFramework TagをCheckoutして全Gateから再実行する
- Skeleton annotated tagのPeeled CommitがFixed Splitと一致する場合だけ冪等成功とする
- Skeleton同名lightweight tag、異なるPeeled Commit、`main`のFast-forward不能は自動修正せずBlockerとする
- GitHub Release作成失敗はPackage Tagを変更せずRelease作成だけを再処理する

## Expected Report

`develop/orchestration/reports/P11-004-stable-publication-and-closeout.md`へ次を記録する。

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
