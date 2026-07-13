# P10-001: Documentation Website Contract

Status: Completed

## Goal

`docs/guide/`だけを公開Sourceとし、`docs/internal/`をRepository内専用とするAstro Starlight Websiteを実装可能な単位へ分割するため、Content適合、Information Architecture、Toolchain、Version表示、Cloudflare Pages公開境界を確定する。

## In Scope

- D063と現行Documentation Directory／Markdown形式の確認
- Astro StarlightとCloudflare Pagesの現行公式Contract確認
- Markdown Single Source Build方式の設計
- URL、Language、Version表示、Package Managerの設計
- Preview／Production Deployment境界の設計
- User回答をD081へ記録する設計対話
- 回答後のPhase 10 Specification／Production Task分割

## Out of Scope

- Astro Starlight Projectの実装
- `docs/internals/`から`docs/internal/`への実移行
- 既存Documentation本文の改稿
- Cloudflare Pages Project／Credential／Custom Domainの作成
- GitHub Actions Deployment Workflowの実装

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/41-developer-experience-roadmap.md`

## Files Allowed to Change

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/orchestration/tasks/P10-001-documentation-website-contract.md`
- `develop/orchestration/reports/P10-001-documentation-website-contract.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/README.md`
- `develop/orchestration/tasks/P10-002-documentation-directory-migration.md`
- `develop/orchestration/tasks/P10-003-starlight-single-source-foundation.md`
- `develop/orchestration/tasks/P10-004-user-documentation-information-architecture.md`
- `develop/orchestration/tasks/P10-005-cloudflare-pages-delivery.md`
- `develop/orchestration/tasks/P10-006-phase-10-closeout.md`
- `develop/TODO.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、Reportへ記載する。

## Constraints

- Production CodeとWebsite Codeを変更しない
- 未回答事項を推測でDecidedにしない
- D063で決定済みのAstro Starlight、Documentation Directory、Cloudflare Pages静的公開を維持する。Website公開対象はUser回答によりD081で置き換える
- Repositoryで直接読めるMarkdown導線を失わない
- Website用に編集対象Contentを複製しない
- Credential、Token、Account ID、Custom Domainを推測またはRepositoryへ保存しない
- Production TaskはGPT-5.6 Luna High workerへ依頼し、Model／Profileを指定できない場合は黙ってFallbackしない

## Acceptance Criteria

- [x] 現在のDocumentation Directory、Markdown Title、内部Linkの形を確認している
- [x] Starlight ContentとCloudflare Pagesの現行公式Contractを確認している
- [x] 実装を分岐させるUser判断がD081へOption／Recommendation付きで記録されている
- [x] User回答をD081へ反映し、StatusをDecidedにする
- [x] Phase 10の確定SpecificationとProduction Task Packetを作成する
- [x] Production Code実装前にWorker Model／Profileの指定可否を確認する

## Required Commands

```bash
rg -n "Starlight|Single Source|Cloudflare Pages|docs/internal" develop/decisions/081-documentation-website-delivery-contract.md develop/orchestration/tasks/P10-001-documentation-website-contract.md develop/orchestration/reports/P10-001-documentation-website-contract.md develop/STATE.md
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-001-documentation-website-contract.md` に次を記録する。

- Summary
- Existing Documentation Findings
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
