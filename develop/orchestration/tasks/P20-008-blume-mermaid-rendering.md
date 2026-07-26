# P20-008: Blume Mermaid Rendering

Status: Accepted

## Goal

公開Documentationの4つのMermaid Fenceを、Syntax-highlighted Code BlockではなくBlume native Diagramとして確実に表示し、同じ退行をArtifact／Browser Contractで検出する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/122-blume-mermaid-rendering.md`
- `develop/spec/89-blume-mermaid-rendering.md`
- `develop/spec/83-blume-documentation-experience.md`
- `docs/website/node_modules/blume/skills/blume-migrate/SKILL.md`
- Blume Local SourceのMarkdown Mermaid Pluginと`<blume-mermaid>` Client Element

矛盾時はD122／Specification 89をMermaid Deliveryの正本とし、Blume Local Sourceを実際のRender Target／Runtime Contractの正本とする。

## In Scope

- Mermaid Fenceを含むPageだけをGenerated `.mdx`へ切り替える
- 通常PageのGenerated `.md`を維持する
- Manifest、deterministic generation、Source非変更、旧File除去のTest
- Artifact／Site Guardを`<blume-mermaid>` Contractへ修正
- 4 Diagram PageのBrowser SVG描画確認
- Light／Dark ThemeとMobile Responsive確認
- Website READMEの実際のGuard説明を同期
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- Diagram Source／意味／本文の変更
- 全DocumentationのMDX化
- 独自Mermaid Renderer／Theme
- External CDN
- Landing、Header、Sidebar、Public Slug、Redirect、Search、Bannerの変更
- Framework `src/**`、Public API
- External Publication／Deploy
- Reference欠番、Testing、Deployment、文章編集

## Files Allowed to Change

- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/README.md`
- `develop/decisions/121-executable-stable-onboarding.md`（Delivery Order同期のみ）
- `develop/decisions/122-blume-mermaid-rendering.md`
- `develop/spec/89-blume-mermaid-rendering.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-008-blume-mermaid-rendering.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Mermaid Fence判定はFenceの開始行を対象とし、通常本文中の文字列だけでMDX化しない
- Mermaidを含まないPageを`.mdx`へ変更しない
- `docs/guide/*.md`を生成物へ合わせて改名しない
- Blume native `<blume-mermaid>`とLocal Mermaid Dependencyを再利用する
- `data-language="mermaid"`を成功条件にしない
- Diagram Sourceと`accTitle`／`accDescr`を維持する
- Existing Public RouteとLinkを変更しない
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Browser Verificationで4 Routeの`<blume-mermaid> svg`を確認し、Desktop Light／DarkとMobileのScreenshotまたは同等の視覚EvidenceをReportへ記録する。

## Acceptance Criteria

- [ ] Mermaidを含む4 PageがGenerated `.mdx`、通常PageがGenerated `.md`になる
- [ ] Generated Manifestが実際の拡張子へ一致する
- [ ] 再生成で旧Counterpartが残らず、Source Markdownを変更しない
- [ ] Static Artifactの各対象Pageに`<blume-mermaid>`が一つある
- [ ] Static Artifactに`data-language="mermaid"` Code Blockがない
- [ ] `accTitle`／`accDescr`が4 Diagramすべてで維持される
- [ ] Browser上の各対象PageにSVGが一つ描画される
- [ ] Diagram Error Messageが表示されない
- [ ] Light／Dark Theme切替後もDiagramが描画される
- [ ] MobileでDocument全体の横Overflowがない
- [ ] External Mermaid CDNを追加しない
- [ ] Existing Landing、Header、Sidebar、Public Slug、Redirect、Search、Bannerを維持する
- [ ] Website full gateが成功する
- [ ] Framework `src/**`を変更しない
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-008-blume-mermaid-rendering.md`へSummary、Root Cause、Changed Files、Generation Contract、Artifact／Browser Contract、Responsive／Theme／Accessibility、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
