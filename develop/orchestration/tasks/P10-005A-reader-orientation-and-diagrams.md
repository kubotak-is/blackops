# P10-005A: Reader Orientation and Diagrams

Status: Ready

## Goal

初見の中級PHP開発者が、BlackOpsを採用する理由、中核概念、既存Frameworkとの対応、Execution／Lifecycle／Identifierの関係をGetting Startedより前に理解できる公開導線を作る。

## In Scope

- Why BlackOps Page
- Core Concepts PageとConcept Relationship Diagram
- Laravel／Symfony Mental Model Table
- MermaidのVersion固定Build統合
- Inline vs Deferred Sequence Diagram
- Lifecycle State Transition Diagram
- Operation／Attempt／Correlation／Causation ID Diagram
- Glossary Page
- Navigation／Content Map／Search／Diagram Artifact Guard
- Reader-oriented用語表記規則のWebsite README記録
- Report／STATE更新

## Out of Scope

- First Operation全面Tutorial化
- Troubleshooting／Security／Core API／Attribute Page
- 全既存PageのTone全面改稿
- Framework Public API変更
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/082-documentation-reader-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/**`
- `develop/orchestration/reports/P10-005A-reader-orientation-and-diagrams.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- 原則としてGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答「Y」により、Phase 10に限り、Model／Profileを明示できない現在利用可能なWorkerで進めることを承認済みである
- Mermaid Integrationは実装時点のCurrent Versionを確認してExact Pinし、外部CDNを使用しない
- DiagramはSource Fence表示だけでなくStatic Artifactで描画する
- DiagramにはAccessible Descriptionと本文によるText Alternativeを付ける
- Public Guideだけを変更し、`docs/internal/`／`develop/`をArtifactへ含めない
- Stable／main BannerとCurrent Statusを維持する

## Acceptance Criteria

- [ ] Why BlackOpsとCore ConceptsがGetting Startedより前にSidebarへ配置される
- [ ] Headless、統一Operation Model、No operation stays in the darkを正確に説明する
- [ ] Operation／Value／Outcome／Journal／ExecutionContext／Strategyを一枚の図と短い定義で示す
- [ ] Laravel／Symfony Mental Model Tableと非一対一の注意がある
- [ ] 指定された4 Mermaid DiagramがStatic Artifactで描画される
- [ ] 各DiagramにAccessible DescriptionとText Alternativeがある
- [ ] Glossaryが指定Termを定義しNavigation／Searchへ含まれる
- [ ] Mermaid Dependency、Content Map、Sidebar、Build Guardが決定的である
- [ ] 全PageのVersion BannerとPublic Artifact Boundaryを維持する

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n '```mermaid' docs/website/dist
rg -n 'Why BlackOps|Core Concepts|No operation stays in the dark|Claim|Fencing|Dead Letter' docs/website/dist
! rg -n 'docs/internal|develop/' docs/website/dist
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005A-reader-orientation-and-diagrams.md`へSummary、Information Architecture、Diagram Evidence、Accessibility Evidence、Dependency Decision、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

