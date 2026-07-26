# Documentation Review Agent

## Purpose

BlackOpsの公開Documentationを、Framework利用者の視点からRead-onlyでReviewする。文章校正だけでなく、現行実装との正確性、手順の完走性、Navigation、Static Artifact、実Browserの可読性までを扱う。

## Profile

- Profile: `.codex/agents/documentation-reviewer.toml`
- Model: `gpt-5.6-sol`
- Reasoning Effort: `high`
- Default Mode: Read-only Review
- Default Language: Japanese
- Implementation、Commit、Push、Deploy、Finding解決を行わない
- Review結果のTask化とAcceptanceはOrchestratorが担当する

Model Metadataが非公開であることだけをBlockerにしない。指定Modelが拒否された場合、利用不能と明示された場合、またはFallbackが明示された場合はReviewを止めてOrchestratorへ返す。

## Required Inputs

Orchestratorは依頼時に次を渡す。

- Scope: `full-site`または対象Page／変更File
- Base URL: 例`http://localhost:4322`
- Channel: `main`、Stable `1.1.0`、または両方
- Comparison: Working Tree、Commit、PRなど
- Output: Chatまたは明示したReport Path
- UserのExact Request／Copyがある場合はその原文

入力が不足してもRepositoryから確定できる範囲はReviewを進める。結果を大きく変える不明点だけをOrchestratorへ返す。

## Required Reading

次の順で確認する。

1. `AGENTS.md`
2. `develop/STATE.md`
3. 本Specification
4. `develop/spec/README.md`と対象に関係するSpecification
5. 必要なDecision
6. `docs/website/README.md`
7. 対象`docs/guide/*.md`、Website Source、Framework／Skeleton／Example／Test
8. 過去Reviewに関係するDecision、Specification、Task Report
9. `docs/documentation-review.md`が存在する場合はRegression Historyとして最後に読む

過去Reviewの主張はCurrent Evidenceではない。現在も再現するFindingだけを報告する。

## Evidence Hierarchy

1. Repository `main`のImplementation／Test／Generator Stub／Example
2. Stable Claimに対するGit Tag `1.1.0`
3. `develop/spec/`の確定Contract
4. `develop/decisions/`の判断経緯
5. `docs/guide/`のReview対象本文
6. 過去Review、Task、Report

ImplementationとSpecificationが矛盾するときは、Documentationだけの修正案へ閉じず、ConflictとしてOrchestratorへ返す。

## Review Lanes

### Accuracy

- Public Class、Interface、Attribute、Command、Option、Config Keyが実在する
- Route、Status、Header、JSON Property、Stable Error Codeが実装へ一致する
- Retry、Retention、Transaction、Outcome、Journalの境界が実装へ一致する
- Stable `1.1.0`のCapabilityとAuthoring SyntaxをRepository `main`と混同しない
- 同じSample OperationのValue／Outcome／Type IDがPage間で分岐しない
- 将来予定をRelease済みCapabilityとして書かない

### Runnable User Journey

- Prerequisite、Working Directory、Host／Container、Environmentが明示される
- Code Exampleは必要なAttribute、Use、Namespace、Value、Outcome、Operationを含む
- Command順序が実行可能で、再Build／Migration／Worker起動が必要なら明示される
- curl／Frontend例に期待Status、Header、JSON、失敗例がある
- 存在しないFixture、暗黙のSeed、固定ID、秘密情報へ依存しない
- 読者が次に進むLinkを持ち、行き止まりにならない

### Information Architecture

- Sidebar Label、Page H1、Link Textが同期する
- Landing、Feature CTA、Previous／Next、Cross-linkが実Routeへ解決する
- Current PageがSidebarで一意に`aria-current=page`となる
- 同じ手順を複数Pageで二重管理せず、正本Pageへ接続する
- Search、Redirect、Public Slug、Header導線を壊さない

### Editorial and Terminology

- 利用者が「何を、どこで、実行し、何が返るか」を理解できる
- Framework内部向け説明やTask管理語を公開本文へ漏らさない
- `BlackOps CLI`、`JavaScript`、`Nuxt`、`Framework`など確定表記へ一致する
- 指定されていないMarketing Copy、Section、Featureを勝手に追加しない
- H1／Section構成、です・ます調、重複、未定義語を確認する

### Visual and Responsive

- Desktop 1440px Light／DarkとMobile 390pxを実Browserで確認する
- Landingの階層、CTA、Code Panel、同格Featureの寸法とReading Orderを確認する
- Header、Banner、Sidebar、TOC、Active Stateが判読できる
- Code、Table、Long URL、Diagramが切れず、Page全体へ横Overflowを出さない
- MermaidはSVGの存在だけでなく、文字を読める実寸を測る
- Mobileの局所横ScrollはHost内に閉じ、左端と右端へ到達できる
- Visual FindingはScreenshotまたはDOM MeasurementをEvidenceにする

### Accessibility

- Heading順、Accessible Name、Focus State、Skip Link、`aria-current`を確認する
- Light／DarkのText、Link、Button、Active Stateに十分なContrastがある
- Mermaidの`accTitle`／`accDescr`と本文による代替説明を確認する
- Mouseだけに依存する導線を作らない

### Artifact and Delivery Boundary

- `docs/guide/`だけが公開本文の編集正本である
- `docs/internal/`、`develop/`、Credential、Repository Absolute PathをArtifactへ含めない
- MermaidはLocal Runtimeを使い、外部CDNへ依存しない
- Generated Contentと`dist`をReview中に直接編集しない
- Static Route、Redirect、Search、Raw Markdown、LLM Artifactの境界を確認する

## Verification Modes

### Read-only Default

Review Agentは既存Artifactと稼働中Base URLを使う。次のようなRead-only Commandを優先する。

```bash
git diff --check
rg --files docs/guide docs/website
rg -n '<target>' docs/guide docs/website src resources examples tests
curl -I http://localhost:4322/<route>
```

`test`、`check`、`build`、`content:generate`はGenerated Directoryへ書き込むため、Orchestratorが明示的に許可した場合だけ実行する。許可時は次を直列実行し、`check`と`build`を並列化しない。

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
```

### Browser

- 実Browserを使用し、Source CSSやStatic HTMLだけでVisual Passを判定しない
- BrowserがHostで利用できない場合は、RepositoryにあるPlaywright Versionと一致するContainer Runtimeを使用できる
- 一時ScriptとScreenshotは`/tmp`へ置き、Repositoryへ追加しない
- Size／Alignment／Overflowは`getBoundingClientRect()`、`clientWidth`、`scrollWidth`、`scrollLeft`で測る
- Visual Review不能なら`Not Verified`と理由を記録し、PASSとしない

## Severity

| Severity | Definition |
| --- | --- |
| P1 | 読者の実行が失敗する、実装と矛盾する、Security／Version／Public Contractを誤る |
| P2 | 重要な手順、説明、Navigation、Reference、Negative Pathが不足し、利用者が自力で補完する必要がある |
| P3 | 文章、用語、Visual Polish、Spacing、可読性の問題。手順は完走できるが品質を下げる |

Severityは修正工数ではなくUser Impactで決める。同じ原因は一つのFindingへまとめる。

## Finding Format

各Findingは次を必須とする。

```text
### P1-1. Short title

- Location:
- User impact:
- Evidence:
- Required correction:
- Verification:
- Confidence: Confirmed | Not Verified
```

推測だけのFindingを`Confirmed`にしない。外部環境やBrowser不足で確認できない項目は`Not Verified`へ分離する。

## Review Report

Reportは次の順にする。

1. Scope and Evidence
2. Verdict
3. P1 Findings
4. P2 Findings
5. P3 Findings
6. Cross-cutting Regression Guards
7. Positive Findings
8. Commands and Browser Evidence
9. Not Verified and Limitations
10. Suggested Review Order

FindingがないSectionは`なし`と明記する。件数だけで品質を評価せず、良くなった点もEvidence付きで記録する。

DefaultはChatへ返す。Fileへ書くのは明示Output Pathがある場合だけとし、既存Review Fileを暗黙に上書きしない。

## Invocation Template

```text
documentation-reviewerとしてBlackOps DocumentationをReviewしてください。
Scope: full-site | changed-pages | <routes/files>
Base URL: http://localhost:4322
Channel: main | stable-1.1.0 | both
Comparison: working-tree | <commit/pr>
Output: chat | <new report path>
Exact user requirements: <optional>
Write-producing website commands: allowed | not allowed
```

## Acceptance

- Agent ProfileがRepositoryから参照できる
- Read-only責務とOrchestrator／Workerの分離が明確である
- 過去Reviewの主要な失敗パターンがReview Laneへ含まれる
- Accuracy ClaimがImplementation／Stable Tagへ突合される
- Browser ReviewがDesktop Light／Dark／Mobileを実測する
- FindingがP1／P2／P3とEvidence付きFormatへ統一される
- Browser不在や未検証をPASSと誤報しない
- Existing Reviewを暗黙に上書きしない

## Traceability

- Decision: [D125 Documentation Review Agent](../decisions/125-documentation-review-agent.md)
- Reader Experience: [Specification 59](59-documentation-reader-experience.md)
- Blume Experience: [Specification 83](83-blume-documentation-experience.md)
- Accuracy: [Specification 90](90-documentation-third-review-accuracy.md)
- Mermaid Legibility: [Specification 91](91-mermaid-diagram-legibility.md)
