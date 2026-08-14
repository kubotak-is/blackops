# P20-011 Documentation Site UX Report

## Summary

Blume 1.1.4 native UXへDocumentation SiteのCallout、Code Copy、Previous／Next、Tracked GitHub Edit Linkを接続し、日本語UI／Font Stack、Responsive Inline Code、Landing decorative section number削除を反映した。Callout／MermaidのFence-aware MDX判定とNoEditLayoutのClipboard success／failure accessible statusをWebsite Regressionで固定した。Native Code Copyは単一のClipboard writeとcopy→check mutationを維持し、read-only Clipboardでも二重実行しない。Export／Generatingを含むvisible Japanese chromeも補完した。既存のPublic Slug、Sidebar順、Landing指定Copy／CTA／三Feature、Header、Banner、Search、Redirect、Blume Versionは維持した。

## Changed Files

- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/first-operation.md`
- `docs/guide/authentication.md`
- `docs/guide/frontend.md`
- `docs/guide/outbox.md`
- `docs/guide/deployment.md`
- `docs/website/blume.config.ts`
- `docs/website/components/NoEditLayout.astro`
- `docs/website/content-map.mjs`
- `docs/website/pages/index.astro`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/website/theme.css`
- `develop/orchestration/tasks/P20-011-documentation-site-ux.md`
- `develop/orchestration/reports/P20-011-documentation-site-ux.md`
- `develop/TODO.md`
- `develop/STATE.md`

Existing Phase 20 Working Tree changes outside this list were preserved. Generated Content and `docs/website/dist/` were not edited directly.

## Decisions and Assumptions

- Native Blume Callout, Clipboard／Check, Pagination, and Page Action remain the rendering owners; the site adds only the MDX source decision, canonical `root` adapter, tracked source mapping, and accessible status enhancement.
- Seven guide pages use short Callouts for Stable／`main`, destructive-operation, or operator/application responsibility boundaries without duplicating the following prose.
- `content-map.mjs` is the only route-to-source mapping. Index and unmapped routes omit Edit Link rather than guessing a source path.
- A read-only `navigator.clipboard.writeText` property is handled by observing the native button's `copy`→`check` mutation and timeout failure; the adapter never issues a second Clipboard write and leaves native focus and Check behavior intact.
- Browser Acceptanceは全38 Public Routeを1440px Light／Dark、390px Lightで測定し、代表12 Screenshotを目視確認する。

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（69 tests、native Clipboard 1-write success／failure jsdom regressionとJapanese chrome regressionを含む） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `mise exec -- pnpm --dir docs/website run build` | PASS（39 pages、artifact／site guard；Vite chunk-size warning only） |
| Playwright Chromium 1.61.1 Browser Acceptance | PASS（38 Routes × 1440px Light／Dark・390px Light＝114 Page checks、12 Screenshot） |
| Browser Clipboard success／failure | PASS（Native write 1回、Exact Code Text、Focus保持、日本語Label／Live Status） |
| `docker compose run --rm app mago format --check src tests` | PASS（All files are already formatted。Sandbox Docker socket拒否後に許可済みDocker APIで再実行） |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` | PASS（Management-ID guard） |
| `git diff --check` | PASS |

Website `check` and `build` were run sequentially. Worker完了後にOrchestratorがWebsite test／check／Guardを再実行し、最新SourceのLocal Dev ServerをBrowser検証した。

## Acceptance Criteria

- [x] Callout Directive pages alone join Mermaid pages in MDX generation; fenced directive text is ignored.
- [x] Stable／`main` or risk boundaries are represented by native Callouts on seven guide pages.
- [x] Native Code Copy keeps Japanese label, Check behavior, focus, and success／failure `aria-live` status; jsdom regression verifies one native write with exact code text for both success and failure.
- [x] Previous／Next consumes canonical Sidebar `root` entries and Japanese native labels.
- [x] Edit Link resolves only to tracked `docs/guide/*.md` sources under the canonical GitHub URL.
- [x] Japanese-aware font fallback and inline code wrapping are present for landing and content styles.
- [x] Landing decorative `01`／`02` markup and its unused style are removed without changing specified Copy／CTA／Feature layout.
- [x] Existing Public Slug, Navigation, Header, Banner, Search, and Redirect contracts remain guarded by Website tests/checks.
- [x] Website Regression and Required Commands pass.
- [x] Report／STATE／TODO／Task are synchronized to Review Pending; no commit was created.
- [x] Orchestrator Desktop 1440px Light／Dark and Mobile 390px visual／Clipboard interaction review passes.
- [x] Documentation Reviewer reports no P1／P2／P3 findings and recommends Accept.

## Remaining Issues

- P20-011のAcceptance Blockerなし。
- Commit、Push、PR、External DeployはTask Scope外のため実行していない。

## Suggested Next Action

D117の順序に従い、P20-012で表記Guidelineと全Page文章編集PassをTask Packet化する。
