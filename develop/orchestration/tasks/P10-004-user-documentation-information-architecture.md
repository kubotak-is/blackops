# P10-004: User Documentation Information Architecture

Status: Accepted

## Goal

Framework利用者がInstallから最初のOperation、Execution、Database／運用機能へ進めるLanding、Navigation、Guide Contentを完成させる。

## In Scope

- 日本語Landing PageとInstall／Quickstart CTA
- Getting Started、Operations、Execution、Database、ReferenceのSidebar
- 既存Guideの目的別Slug／Metadata Mapping
- Install、Directory Structure、First Operation、Local Runtimeの連続導線
- Project CLI、Application Bootstrap、HTTP／Deferred、Migration、Outcome、Retentionの利用者向け整理
- `main` Document ChannelとLatest Stable `1.0.0`表示
- Mobile、Keyboard、Search、Accessibility検証
- Public ArtifactへのInternal／Acceptance Evidence混入Guard
- Root／Guide README、Report、STATE更新

## Out of Scope

- Framework内部Architecture公開
- Contribution Guide／Task Report公開
- Version別Archive／Selector
- Public API変更
- Cloudflare Pages Deploy

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`

## Files Allowed to Change

- `README.md`
- `docs/guide/**`
- `docs/internal/installed-application-status.md`
- `docs/website/**`
- `develop/orchestration/reports/P10-004-user-documentation-information-architecture.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- 原則としてGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答「Y」により、Phase 10に限り、Model／Profileを明示できない現在利用可能なWorkerで進めることを承認した
- この承認はPhase 10以外へ自動継続しない
- Public GuideはFramework利用者のTaskとPublic APIだけで理解できるようにする
- `BlackOps\Internal`、Orchestration ID、Acceptance Commit Hashを利用者へ要求しない
- Existing Guideを削除する場合は利用者向け情報を移行し、失われた内容をReportへ列挙する
- `docs/internal/`をPage、Sidebar、Search Indexへ含めない
- Main DocumentをStable APIと誤認させない
- Site Componentの独自実装を最小限にし、Starlight標準Accessibilityを維持する

## Acceptance Criteria

- [ ] LandingからInstall／Quickstartへ1 Actionで到達できる
- [ ] Installから最初のOperationとLocal実行まで連続して辿れる
- [ ] 5つの利用者目的別Sectionに全公開Guideが配置される
- [ ] Operation／Value／Outcomeの現行Typed Self-handled Authoringが正しい
- [ ] Project CLI、HTTP／Deferred、Migration／Outcome／Retentionの入口がある
- [ ] `main`とLatest Stable `1.0.0`の差が表示される
- [ ] Internal／Contributor／Acceptance EvidenceがNavigationとSearchへ出ない
- [ ] Mobile Sidebar、Keyboard、Skip Link、Contrast、Searchを検証する
- [ ] 全内部LinkとStatic Buildが成功する

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
rg -n 'main|1\.0\.0' docs/website/dist
! rg -n 'BlackOps\\Internal|P[0-9]+-[0-9]+|Acceptance Evidence|docs/internal' docs/website/dist
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Mobile／Keyboard／Contrast／Searchの確認方法と結果をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P10-004-user-documentation-information-architecture.md` に次を記録する。

- Summary
- Information Architecture
- User Journey Evidence
- Accessibility and Search Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
