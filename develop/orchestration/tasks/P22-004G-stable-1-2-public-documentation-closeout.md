# P22-004G: Stable 1.2 Public Documentation Closeout

Status: Accepted

## Goal

公開済みExperimental Stable `1.2.0`のFramework／Skeleton／Packagist／GitHub Release／Remote Consumer evidenceへ、利用者向けInstall、Release、Upgrade、Quickstart、Website Source、Internal Status、version baseline、Phase 22管理文書を同期する。Documentation Website production deploymentは行わない。

## Live Inputs

- Framework annotated tag direct object: `00e8c5875047a3c47acbebfe57f75b0e581d18b9`
- Framework peeled source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Skeleton annotated tag direct object: `fedcfda5f39caf320ad67196e8ced459176cedb1`
- Skeleton peeled split／main: `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`
- Successful Manual Recovery: `31889808876`／job `95024306339`
- GitHub Release: `https://github.com/kubotak-is/blackops/releases/tag/1.2.0`
- Published at: `2026-08-15T16:21:09Z`
- Remote package smoke checked at: `2026-08-16T01:47:00+09:00`

## In Scope

- `1.2.0`をLatest Experimental Stableとして表示し、通常／`--no-scripts` Packagist Installを`1.2.0`へ同期する
- CHANGELOGの`1.2.0` release entry／compare linkとUPGRADEの公開Package upgrade pathを確定する
- Candidate／未公開／後続Publicationというstale current claimを除去する
- 公開Remote smokeのnormal／no-scripts／CLI／compile／migration／HTTP／Worker／redaction evidenceをInternal／managementへ記録する
- HTTP後のnon-root `operation:inspect`がroot-owned `var/log/journal.jsonl`で`diagnostics.storage_failed`となる確認済み制約を隠さずKnown Limitation／Remaining Issueへ記録する
- Website Sourceを同期し、Local check／buildだけを実行する
- P22-004／Phase 22管理文書をcloseする

## Out of Scope

- Framework／Skeleton Production Code、Test behavior、Workflow behaviorの変更
- 公開済みTag／Release／Packagist metadataの変更
- Documentation Website production deployment
- `operation:inspect`所有権問題のSource修正または`1.2.1` publication
- Credential／Secret値の取得または記録

## Files Allowed to Change

- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `examples/quickstart/README.md`
- `docs/guide/installation.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-status.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/observability.md`
- `docs/internal/installed-application-status.md`
- `docs/website/pages/index.astro`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `tests/Consumer/version-baseline.sh`
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Commit前にOrchestratorとDocumentation Reviewerの独立Reviewを受ける
- Live evidenceをSource変更後のrelease evidenceとして読み替えず、公開Tag／Splitはimmutable inputとして記録する
- Experimental、1.x Minor breaking-change可能性、Production Readyではない境界を維持する
- Stable `1.0.0`／`1.1.0` historical recordsを変更しない
- Website source更新はproduction deploymentを意味しない
- Website Test／Artifact guardは旧`1.1.0`／candidate期待値だけをlive `1.2.0` claimへ同期し、検査強度を落とさない
- 確認済み`operation:inspect`制約をRemote smoke全体の失敗として誤記せず、成功項目と制約を分離する
- Documentation Review findingsだけを補正する: First Operationを公開Stable `1.2.0`契約へ同期し、Key書込み前に既存`.env`をexact `0600`へ変更／検証し、normal／`--no-scripts`の両laneを同じ必須Key工程へ合流させ、Quickstartのfragment linkを実在Anchorへ同期する
- Website／version baseline guardは旧Stable `1.1.0` current claim、Key permission検査の欠落、`--no-scripts` Key工程skip、存在しないQuickstart fragmentをfail closedで拒否する
- Documentation re-review P2だけを補正する: generated Quickstart artifactのexact target `id`をsite checkで要求し、`mvp-sample.md`のnormal／`--no-scripts` setup→共通Key block順序をnegative fixture付きでfail closed検証する

## Acceptance Criteria

- [x] Public install commandsがnormal／`--no-scripts`とも`1.2.0`を使う
- [x] 公開Install／QuickstartがKey書込み前に`.env`をexact `0600`へ変更／検証し、Local-only 32-byte Base64 `BLACKOPS_STORAGE_KEY`を安全に準備し、normal／`--no-scripts`両laneとProduction Secret Manager境界を説明する
- [x] Latest Stable、Release、Packagist、Skeleton statusがlive stateと一致する
- [x] CHANGELOG／UPGRADEが公開`1.2.0`と不可逆Migration／Backup境界を正しく説明する
- [x] version baselineが新しいStable claimsを要求し、旧candidate claimsを拒否する
- [x] Remote smoke成功項目と`operation:inspect`既知制約がEvidence付きで分離記録される
- [x] First Operation／generated Quickstart fragment／Key permission／両Guideのno-scripts convergenceをnegative fixture付きで含むDocumentation Website unit／check／buildと必須Documentation guardsが成功する
- [x] Orchestrator独立ReviewとDocumentation ReviewがP1=0／P2=0を確認する（final P1=0／P2=0／P3=0）
- [x] P22-004／Phase 22がReport／TODO／Spec／STATEでcloseする
- [x] Completion handoffが残り工程または明示的noneと一つのNext Actionを記載する

## Required Commands

```bash
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
mise exec -- pnpm --dir docs/website test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。Remaining IssuesはTask完了と上位Goalの残作業を区別する。
