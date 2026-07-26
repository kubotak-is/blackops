# P20-001: Blume Documentation Site

Status: Accepted

## Goal

公開Documentation WebsiteをAstro StarlightからBlumeへ移行し、Operation／Journal／HeadlessのLanding、1.x Experimental Notice、現在の利用者向けNavigationを実装する。

## Source of Truth

- `develop/decisions/116-blume-documentation-site.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/090-documentation-information-architecture.md`

## In Scope

- `docs/website/`のStarlightからBlumeへのPackage／Config／Build移行
- `docs/guide/`を正本とする決定的Content Pipeline
- Operation／Journal／HeadlessのLanding
- 1.x Experimental／2.x Production Ready予定の全Page Notice
- 指定されたSidebar Section、Label、順序
- ConsoleCommand／Outbox／Authentication／Authorization／Frontend Guide
- Existing Public URL／Redirect／Search／Mermaid／Artifact Guard維持
- Local／CIの`test`、`check`、`build` Script互換
- Documentation Delivery Workflowの`dist/` Artifact互換
- Website README、Delivery／Reader／Release Specification、TODO、Report、STATE同期

## Out of Scope

- Framework `src/**`またはPHP Runtime／Public API変更
- Existing Guide本文の全面改稿
- Next.js／Nuxt固有AdapterまたはFrontend UI実装
- Scheduled Operation設計／実装
- Stable 2.0 Release Contract、Tag、Migration Policyの確定
- Cloudflare Project／Credential／Custom Domain変更
- Preview／Production Deploy、External Publication
- 既存Public URLの不要な変更または削除

## Required Contract

### Platform

- BlumeをExact VersionとLockfileで固定する。
- Starlight Package、Integration、固有生成文言、固有Test依存を削除する。
- Blume標準機能を利用し、Custom Page／CSSをLandingへ限定する。
- `pnpm run dev`、`test`、`check`、`build`を維持する。

### Content and Landing

- 公開本文のSource of Truthを`docs/guide/`へ限定する。
- LandingにOperation、Journal、Headlessを指定された意味で表示する。
- Operationを主役にした非対称Layoutとし、Mobileでは自然な一列にする。
- Headless説明はGenerated Clientを接続点とし、各Frontend固有Adapterを提供済みと誤認させない。
- Landing Presentationと`docs/guide/README.md`のClaim DriftをTestで拒否する。

### Version

- 全Pageで1.x Experimentalを強調する。
- 1.x Minor間のBackward CompatibilityとProduction Readinessを保証しない。
- Production Readyは2.xから予定と表現し、既成事実またはRelease保証にしない。
- Document Channel `main`とLatest Stable `1.1.0`を維持する。

### Navigation

Specification 83のSection／Item Label／順序へ完全一致させる。
公開Labelは`What’s BlackOps`、`Quickstart and Skeleton`、`Deferred`、`Execution and Workers`を使用する。
既存のSidebar外Pageは削除せず、Build／Search／内部Link到達性を維持する。

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/**`
- `docs/website/pnpm-lock.yaml`
- `docs/website/pnpm-workspace.yaml`
- `.github/workflows/docs.yml`
- `.github/workflows/ci.yml`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/090-documentation-information-architecture.md`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-001-blume-documentation-site.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run dev

docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

`dev`はHTTP 200と主要Landing Textを確認後に停止してよい。Orchestratorが最終的なLocal Serverを起動する。

## Acceptance Criteria

- [x] Blume SiteがLocal／Static Buildで成功する
- [x] Starlight固有Dependency／Config／生成文言が残らない
- [x] Operation／Journal／Headless Landingが指定内容を伝える
- [x] 1.x Experimental／2.x Production Ready予定が全Pageで目立つ
- [x] SidebarがSpecification 83へ完全一致する
- [x] 新しい5 Guide Pageが実装済みContractだけを説明する
- [x] Existing URL／Redirect／Search／Mermaid／Artifact Guardを維持する
- [x] Desktop／Mobile、Light／Dark、Keyboard／Reduced Motionを検証する
- [x] Documentation Workflowが`dist/` Artifact Contractを維持する
- [x] Report／STATEが実装とEvidenceに一致する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-001-blume-documentation-site.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Blume Runtime and Content Pipeline
- Landing and Version Notice
- Navigation and New Reader Pages
- Compatibility and Artifact Boundary
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
