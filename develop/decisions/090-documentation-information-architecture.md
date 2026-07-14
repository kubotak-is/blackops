# D090: Documentation Information Architecture

Status: Decided

## Context

現行Websiteは公開内容を揃えた一方、SidebarがPage追加順に近く、一般的なFramework Documentationの学習順と参照用途が混在している。

- Getting StartedでQuickstartがInstallationより先にある
- ReferenceへTroubleshooting、Security、Current Statusが混在している
- Operation Lifecycleが手順中心のOperationsにあり、概念説明として探しにくい
- Execution／DatabaseというLabelだけではWorker運用やRetentionまで含むことが伝わりにくい
- TestingとProduction Deploymentの入口がない
- LandingのFeature BlockがBlackOpsの中核価値を直接表していない

このDecisionでは本文の全面改稿を行わず、Diátaxisの「説明／Tutorial／How-to／Reference」を意識して、Navigation、URL、入口Page、Landing Link Blockだけを再編する。

## Information Architecture Principles

1. 初見読者はInstallationからTutorialへ順番に進める。
2. Overviewは仕組みを理解する説明に限定する。
3. Getting StartedはInstallと最初の成功体験に限定する。
4. Operations、Execution & Workers、Data & Retention、Testing、Deployment、Security、Troubleshootingは目的別How-toとして配置する。
5. ReferenceはAPI、Attribute、設定、Command、Bootstrap、用語を引く場所に限定する。
6. Releasesは実装済み範囲、Stable／main差、既知制約を確認する独立セクションにする。
7. Page本文の新しい仕様追加は行わない。Testing／Deploymentの入口Pageは既存情報への導線と責務境界だけを示し、詳細本文は別Taskで拡充できる形にする。

## Proposed Sidebar

Sidebarは次の順序とする。

```text
Overview
  Why BlackOps
  Core Concepts
  Operation Lifecycle

Getting Started
  Installation
  Quickstart
  Tutorial
  Directory Structure
  Local Runtime

Operations
  Operation Authoring
  Generators
  Validation

Execution & Workers
  HTTP、Inline、Deferred
  Execution Context

Data & Retention
  Database Migrations
  Outcome Retrieval
  Data Retention

Testing
  Testing Overview

Deployment
  Production Worker Operations

Security
  Security & Sensitive Data

Troubleshooting
  Troubleshooting / FAQ

Releases
  Current Status

Reference
  Core API Types
  Attributes
  Configuration
  Project CLI
  Application Bootstrap
  Glossary
```

### Diátaxis Classification

| Section | Primary type | Reader intent |
| --- | --- | --- |
| Overview | Explanation | BlackOpsの設計とLifecycleを理解する |
| Getting Started | Tutorial | Installから最初のOperationまで順に進める |
| Operations | How-to | Operationを作成、生成、検証する |
| Execution & Workers | Explanation / How-to | Inline／DeferredとWorker実行を選ぶ |
| Data & Retention | How-to | Migration、Outcome、Retentionを運用する |
| Testing | How-to | Applicationの検証入口を確認する |
| Deployment | How-to | Production Workerの運用入口を確認する |
| Security | Explanation / How-to | FrameworkとApplicationの責任境界を確認する |
| Troubleshooting | How-to | 症状から原因と修正方法を探す |
| Releases | Explanation | Stable／main差と既知制約を確認する |
| Reference | Reference | API、Attribute、設定、Command、用語を引く |

## URL Mapping

変更しないPageは現行Slugを維持する。役割が変わるPageだけ次のように移動する。

| Source | Current URL | Proposed URL | Change |
| --- | --- | --- | --- |
| `operation-lifecycle.md` | `/operations/lifecycle/` | `/concepts/lifecycle/` | Overviewへ移動 |
| `security.md` | `/reference/security/` | `/security/` | 独立トップセクションへ移動 |
| `troubleshooting.md` | `/reference/troubleshooting/` | `/troubleshooting/` | 独立トップセクションへ移動 |
| `mvp-status.md` | `/reference/current-status/` | `/releases/current-status/` | Releasesへ移動 |
| `testing.md` | なし | `/testing/` | 入口Pageを新設 |
| `deployment.md` | なし | `/deployment/worker-operations/` | 入口Pageを新設 |

`Execution`配下と`Database`配下はURLを変えず、Sidebar Labelだけを`Execution & Workers`と`Data & Retention`へ変更する。Label変更だけを理由に既存URLを壊さない。

## Testing and Deployment Entry Pages

今回のIA Taskでは、空のSidebar Groupを作らないために入口Pageを一つずつ追加する。

### Testing Overview

- BlackOps Applicationで確認すべき層を短く示す
- Quickstart／Consumer E2E／Validation／Deferred Workerの既存説明へLinkする
- 新しいTesting APIやTesting Frameworkを提供済みとして説明しない
- 詳細なTesting Guideの執筆は後続Taskへ分離できる

### Production Worker Operations

- HTTP WorkerとDeferred Workerが別Process責務であることを示す
- Build Artifact、Migration、Process Supervision、Graceful Shutdown、Heartbeat、Retry、Dead Letter、Monitoringの既存説明へLinkする
- Local Runtimeを本番推奨構成としてそのまま提示しない
- Orchestrator、Kubernetes、systemd等の未実装Integrationを提供済みとして説明しない
- 詳細なDeployment Guideの執筆は後続Taskへ分離できる

## Landing Page

トップページのH1は`BlackOps Documentation`ではなく、Frameworkそのものを示す次のTitleへ変更する。

```text
BlackOps — The PHP Framework
```

Website全体のBrand名とBrowser Titleは`BlackOps`を維持する。`Documentation`はNavigationや説明上の文脈で使用できるが、Landingの主Titleには使用しない。

Hero直後のFeature Link Blockを4個から3個へ変更する。表示文言は次を正とする。

### Operationが中心

`#[Route]`で同期API、`#[ExecuteWith(Deferred)]`で非同期化。HTTPもコンソールコマンドもJobも、すべてはOperation。

Link: `/operations/authoring/`

### Journalですべてを可視化

受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。

Link: `/concepts/lifecycle/`

### 非同期処理を標準装備

リトライ／バックオフ／重複防止／Dead Letter／型付きOutcome保存をPostgreSQLで標準提供。

Link: `/execution/http-and-deferred/`

Getting Startedの順序と揃えるため、Hero Primary CTAは`Installation`、Secondary CTAは`Why BlackOps`とする。

Landing本文の「最短で試す」はQuickstartへのLinkを維持するが、読み進め方はInstallationから開始する。Stable／main BannerとCurrent Statusへの導線は維持し、Current Status Linkだけ新URLへ更新する。

## Visual Direction

Starlightを別Themeへ置き換えず、標準のSidebar、Search、Mobile Navigation、Table of Contents、Color Theme、Accessibilityを維持する。その上でLandingを一般的なDocumentation Indexではなく、FrameworkのProduct Pageとして見せる。

Design Toneは「technical、operational、confident」とする。過度な装飾やMarketing Animationではなく、Operation、Journal、WorkerというBlackOpsの性格が伝わる端正なVisualを目指す。

### Hero

- `BlackOps — The PHP Framework`を大きなDisplay Typeとして見せる
- Taglineの行幅を抑え、TitleとのHierarchyを明確にする
- Accent ColorのGradientまたはText Highlightを限定的に使う
- `Installation`をPrimary Button、`Why BlackOps`をSecondary Buttonとして視覚的に区別する
- 背景はCSSだけの淡いGrid、Glow、Gradient等を使い、画像Assetや外部Resourceへ依存しない

### Feature Cards

- Desktopでは3 Cardを一列、狭い幅では二列または一列へ自然にReflowする
- 各Cardは見出し、短い説明、関連PageへのLink全体をClick Targetにする
- `#[Route]`、`Journal`、`Deferred`等のSymbolを小さなCode Accentとして扱える
- Border、Surface、Shadow、Hoverの差は控えめにし、Dark／Light Themeの両方で読めるようにする
- Hoverだけに情報を置かず、Keyboard Focusを同等以上に明示する

### Documentation Pages

- 本文PageはStarlight標準Layoutを維持し、Landing用装飾を持ち込まない
- Section Label、Heading、Table、Code、Mermaidの可読性を優先する
- Sidebarの11 Sectionが増えても、Navigation Hierarchyを装飾で曖昧にしない
- Starlight内部DOMへ強く依存するSelectorやJavaScript Patchを避け、Custom CSSと公開Configurationを優先する

### Motion and Accessibility

- Motionを使う場合は短いOpacity／Transform Transitionだけに限定する
- `prefers-reduced-motion: reduce`で不要なTransitionを停止する
- WCAG相当のColor Contrast、Visible Focus、Keyboard Navigation、Skip Linkを維持する
- 390 px相当Mobile ViewportでPage Level Horizontal Overflowを発生させない
- Decorative BackgroundはContentのAccessible NameやReading Orderへ影響させない

### Visual Acceptance

- LandingがDesktopでProduct Pageとして成立し、3 Feature Cardが一目で区別できる
- MobileでTitle、CTA、Cardが読みやすい順に一列化する
- Dark／Light Themeの両方でText、Border、Code Accentが判別できる
- KeyboardだけでCTAと全Feature Linkへ移動できる
- Reduced Motion設定で不要なAnimationが動かない
- 本文Page、Search、Sidebar、Mobile Menu、Mermaidの既存挙動を壊さない

## Navigation Validation

`docs/website/site-navigation.mjs`の`required`は次の完全一致を要求する。

```js
[
  'Overview',
  'Getting Started',
  'Operations',
  'Execution & Workers',
  'Data & Retention',
  'Testing',
  'Deployment',
  'Security',
  'Troubleshooting',
  'Releases',
  'Reference',
]
```

`validateNavigation()`は次を維持する。

- Sectionの順序とLabelの完全一致
- Public PageのSidebar未配置検出
- 同一Slugの重複配置検出
- Content Mapに存在しないSlugの検出

Error Messageの`the six public sections`という固定表現は削除し、Section数に依存しない表現へ変更する。

Navigation Testは少なくとも次を固定する。

- Getting StartedがInstallationから始まり、指定された5 Page順である
- Referenceが指定された6 Pageだけを持つ
- Lifecycle、Security、Troubleshooting、Current Statusが旧Sectionに残っていない
- 11 SectionのLabelと順序が完全一致する
- Missing／Duplicate／Unknown／Reorderedを拒否する

## Link and Compatibility Policy

公開前でもRepository内Linkと検索Indexの一貫性を保つため、`content-map.mjs`、Guide内Link、Landing、Sidebar、Testを同じ変更単位で更新する。

旧URLは次の301 RedirectをStatic Artifactへ含める。

```text
/operations/lifecycle/*       /concepts/lifecycle/:splat       301
/reference/security/*         /security/:splat                 301
/reference/troubleshooting/*  /troubleshooting/:splat          301
/reference/current-status/*   /releases/current-status/:splat  301
```

Generated Contentは手動編集せず、`docs/guide/`とMappingから再生成する。

## Scope Boundary

### In Scope

- Sidebar Section、Label、Page順序
- Content MapのSlug変更
- Testing／Deployment入口Page
- LandingのProduct Title、3 Feature Link Block、CTA
- Starlight標準機能を維持したLanding Visual Polish
- 内部Linkと旧URL Redirect
- Navigation／Content／Build／Artifact Test
- Website READMEのSection説明
- Delivery Plan、Reader Experience Spec、Task Report、STATE同期

### Out of Scope

- 既存Guide本文の全面改稿
- 新しいFramework APIまたはTesting API
- Kubernetes、systemd、Supervisor等のProduction Adapter実装
- Changelog生成機能、Release Note Archive、Version Selector
- Cloudflare Project／Credential設定

## Review

次の回答を一度に記入してからProduction Taskを作成する。

### Question 1: Sidebar and URL Mapping

上記11 Section、Page順、URL移動で確定するか。

- A: 提案どおり確定する
- B: 修正コメントを反映して再提案する

[ANSWER]

A
[/ANSWER]

### Question 2: Testing and Deployment Scope

今回のIA Taskでは入口Pageだけを追加し、詳細Guideは後続Taskへ分離するか。

- A: 入口Pageだけ追加する
- B: 今回から詳細Guideも作成する

Recommendation: A。今回はIAを先に確定し、本文品質のReviewを別単位にする。

[ANSWER]

A

[/ANSWER]

### Question 3: Redirects

移動する4 URLへStatic 301 Redirectを用意するか。

- A: Redirectを用意する
- B: 未公開Siteなので旧URLを破棄する

Recommendation: A。Repository内外のLinkやPreview URLを壊さず、公開後も同じ運用を継続できる。

[ANSWER]

A

[/ANSWER]

### Question 4: Landing CTA

Hero Primary CTAをGetting Startedの先頭と同じInstallationへ変更するか。

- A: Installationへ変更する
- B: Quickstartのまま維持する

Recommendation: A。SidebarとLandingで開始地点を統一する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Sidebarは提案した11 SectionとPage順へ再編する。
2. Getting StartedはInstallation、Quickstart、Tutorial、Directory Structure、Local Runtimeの順とする。
3. LifecycleはOverview、Security／Troubleshooting／Releasesは独立Sectionへ移動する。
4. ReferenceはCore API、Attributes、Configuration、Project CLI、Application Bootstrap、Glossaryだけを置く。
5. `Execution`を`Execution & Workers`、`Database`を`Data & Retention`へ改名し、既存URLは維持する。
6. Testing／Deploymentは入口Pageを追加し、詳細Guideの全面執筆は後続Taskへ分離する。
7. 移動する既存URLへStatic 301 Redirectを用意する。
8. Landing Titleを`BlackOps — The PHP Framework`とし、Primary CTAをInstallationへ変更する。
9. LandingはOperation、Journal、Deferredの3 Feature Cardへ再構成する。
10. Starlight標準機能とAccessibilityを維持し、Custom CSS中心でProduct SiteらしいVisualへ調整する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Public URL、Sidebar、内部Link、Search Index、Redirect、Testを同じ変更単位で更新する。
- Testing／Deploymentは空Sectionにせず入口を提供するが、未提供機能を実装済みとして説明しない。
- LandingのVisual変更は本文Pageへ広げず、Starlight標準Layoutの保守性を維持する。
- P10-006 Closeoutの前にP10-005Hとして実装、Browser Review、Acceptanceを完了する。

[/CONSEQUENCES]

## References

- [D081 Documentation Website Delivery Contract](081-documentation-website-delivery-contract.md)
- [D082 Documentation Reader Experience](082-documentation-reader-experience.md)
- [D084 Documentation Reader Journey Corrections](084-documentation-reader-journey-corrections.md)
- [Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)
- [Documentation Reader Experience](../spec/59-documentation-reader-experience.md)
