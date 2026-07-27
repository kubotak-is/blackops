# P20-009G: Blume Production Search Verification

Status: Accepted

## Goal

初回Production Deployで検出したPagefind／Oramaの運用契約不整合を補正し、Blume Production Searchの正しいLive Verification手順をRepositoryへ同期する。

## In Scope

- D130とD129のSearch verification同期
- `docs/internal/documentation-website.md`のPagefind URLを`blume-search.json`へ置換
- Production Deploy EvidenceのTask Report／TODO／STATE同期
- Active contractからPagefind Live Verification要求を除去
- Targeted text／link／diff guard

## Out of Scope

- Blume Search provider、UI、生成Artifactの変更
- Cloudflare Project、Token、Environment、Access policyの変更
- Historical Starlight／Pagefind Checkpointの書き換え
- Framework Production Code

## Relevant Specifications and Decisions

- `develop/decisions/116-blume-documentation-site.md`
- `develop/decisions/129-documentation-website-publication.md`
- `develop/decisions/130-blume-production-search-verification.md`
- `develop/spec/57-documentation-website-delivery-contract.md`

## Files Allowed to Change

- `docs/internal/documentation-website.md`
- `develop/decisions/129-documentation-website-publication.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P20-009G-blume-production-search-verification.md`
- `develop/orchestration/reports/P20-009G-blume-production-search-verification.md`
- `develop/STATE.md`

## External Evidence

- PR #1 merge commit: `42cf27807919078f9d0e82e4b1f6a2e2b9debb8d`
- Documentation Production run: `30212941358` — SUCCESS
- `https://blackops-php.pages.dev/` — HTTP 200
- `https://blackops-php.pages.dev/getting-started/installation/` — HTTP 200
- `https://blackops-php.pages.dev/pagefind/pagefind.js` — HTTP 404
- `https://blackops-php.pages.dev/blume-search.json` — HTTP 200

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Secret値、Account ID、Tokenを取得・表示・保存しない
- Existing Website build artifactを変更しない
- Historical Task／Report／STATE内のPagefind記録は変更しない

## Acceptance Criteria

- [x] D129がBlume Orama Search IndexをLive Verification対象とする
- [x] 現行運用手順が`/blume-search.json`をHTTP確認する
- [x] Specification 57がBlume Search live gateを明示する
- [x] Active contractに`/pagefind/pagefind.js`確認が残らない
- [x] Production成功／HTTP 200／404のEvidenceを推測せず記録する
- [x] TODO／Task／Report／STATEがReview Pendingへ同期する
- [x] `git diff --check`がPASSする
- [x] Worker Commitがない

## Required Commands

```bash
rg -n 'blume-search\.json|Orama|Browser.*Search' docs/internal/documentation-website.md develop/decisions/129-documentation-website-publication.md develop/spec/57-documentation-website-delivery-contract.md
! rg -n 'pagefind/pagefind\.js|Pagefind Asset' docs/internal/documentation-website.md develop/decisions/129-documentation-website-publication.md develop/spec/57-documentation-website-delivery-contract.md
git diff --check
```

## Expected Report

`develop/orchestration/reports/P20-009G-blume-production-search-verification.md`へSummary、Changed Files、Decisions、External Evidence、Commands、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
