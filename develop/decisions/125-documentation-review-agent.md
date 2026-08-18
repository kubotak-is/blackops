# D125: Documentation Review Agent

Status: Superseded for Reviewer Model／Reasoning Profile selection by D144; the Read-only Review Contract remains applicable.

## Context

Documentation Websiteの反復Reviewでは、文章だけでなく次の問題が繰り返し発見された。

- Stable `1.1.0`とRepository `main`の機能・記法混同
- 実装に存在しないRoute、Command、Fixture、Error Code、Response
- CopyしてもCompile／実行できない不完全なCode Example
- Host／Container境界、前提条件、期待結果の不足
- Link TextとTarget H1の不一致、壊れたLanding CTA、Sidebar現在地不在
- 指定していないCopy／Section／導線の追加
- Desktop／Mobileでの非対称Layout、Code clipping、Page Overflow
- MermaidがSVGとして描画されても約300pxへ縮小され、読めない状態

これらは文面校正だけでは検出できない。実装、Stable Tag、Static Artifact、実Browserを突合する専用Reviewerが必要である。

## Decision

[DECISION]

1. `.codex/agents/documentation-reviewer.toml`へDocumentation専用Reviewer Profileを置く。
2. ReviewerはOrchestratorと同じ`gpt-5.6-sol`／`high`を使用し、Production実装Workerとは分離する。
3. ReviewerはRead-onlyを既定とし、修正、Commit、Push、Deployを行わない。
4. Review Contractの正本をSpecification 92とし、Agent Profileはその文書を必ず読む。
5. ReviewはImplementation／Test、Stable Tag、Specification、Decision、Guideの順でEvidenceを扱う。
6. 過去ReviewのDecision、Specification、Task ReportをRegression Checklistとして使う。`docs/documentation-review.md`が存在する場合は補助資料として使うが、未確認の古いFindingを再掲しない。
7. Accuracy、Runnable Journey、Information Architecture、Editorial、Visual、Accessibility、Artifact Boundaryを分離して確認する。
8. Visual Passは実Browserを必須とし、Desktop 1440px Light／Dark、Mobile 390pxを基準にする。CSSやHTMLだけから合格を推測しない。
9. FindingはP1／P2／P3へ分類し、Location、User Impact、Evidence、Required Correction、Verificationを必須とする。
10. Review AgentのFindingは修正権限を与えず、OrchestratorがTask Packet化とAcceptanceを判断する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Documentation Reviewを再開するたびに評価軸を再発明しなくてよい。
- 実装と読者体験の両方をEvidence付きでReviewできる。
- Reviewerが修正まで行って自己承認する役割混同を防ぐ。
- Browser環境がない場合はVisual Passを主張せず、Not Verifiedとして残す。

[/CONSEQUENCES]

## References

- [Specification 59](../spec/59-documentation-reader-experience.md)
- [Specification 83](../spec/83-blume-documentation-experience.md)
- [Specification 90](../spec/90-documentation-third-review-accuracy.md)
- [Specification 91](../spec/91-mermaid-diagram-legibility.md)
- [Specification 92](../spec/92-documentation-review-agent.md)
