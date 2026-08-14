# D133: Documentation Editorial Style

Status: Decided

## Context

P20-003からP20-011で正確性、Stable入門動線、Task-oriented Guide、Mermaid、Journal、Site UXを段階的に整備した。全38 Public Pageは実行可能になった一方、本文には次のEditorial Driftが残る。

- `Page`、`File`、`Command`、`Example`等の一般語と日本語が混在する
- `NuxtJS`／`Nuxt`、`Latest Stable`、`Document Channel`等の表記がPage間で異なる
- Concept、How-to、Reference、Troubleshootingが同じ仕様書調で書かれ、読者が行動を見つけにくい
- `Symptom`、`Likely Cause`、`How to Verify`、`Fix`等、UI以外のReader Labelが英語のまま残る
- 公開済みDocumentation Websiteを「Local／CI Buildだけで未公開」とする古い説明がReleasesに残る

英単語の機械的置換は、BlackOps固有Concept、Public API、Command、External Product名を壊す。一般語の日本語化とPublic Contractの維持を分けたGuidelineが必要である。

## Decision

[DECISION]

1. P20-012で`docs/guide/*.md`の全38 Public Sourceを一度ずつReviewし、表記、見出し、段落、読者行動を編集する。変更不要と判断したPageもCoverageへ記録し、意味のない差分を作らない。
2. 本文は日本語のです・ます調を基本とする。説明は短く直接的にし、一つの段落へ複数の保証、例外、次の行動を詰め込まない。規約語の「〜である」「〜ものとする」や内部Task／Specification調を公開本文へ使わない。
3. BlackOpsのPublic Conceptと型名は英語表記を維持する。Glossaryの見出しとPublic APIで定義されたConceptを保護の正本とし、少なくとも`Operation`、`OperationValue`、`Value`、`Outcome`、`Journal`、`ExecutionContext`、`Inline`、`Deferred`、`Worker`、`Retry`、`Retention`、`Outbox`、`Lifecycle`、`Attempt`、`Claim`、`Lease`、`Fencing Token`、`Heartbeat`、`Projection`、`Manifest`、`Dead Letter`、`Idempotency Key`、`Idempotency Record`、`Replay`、`Correlation`、`Causation`、`Transport`、`Actor`、`Terminal State`、`Ephemeral Outcome`、`Supervision Policy`、`Execution Strategy`、`Framework`、`Application`、`Frontend`、`BlackOps CLI`は一般語として翻訳しない。
4. External Product／Protocolは公式表記を使う。少なくとも`JavaScript`、`TypeScript`、`Next.js`、`Nuxt`、`SvelteKit`、`PHP`、`PostgreSQL`、`Docker Compose`、`GitHub`、`Composer`、`HTTP`、`JSON`へ統一する。`Javascript`、`NuxtJS`、`Project CLI`は使用しない。
5. Public Conceptでない一般語は日本語を優先する。本文では`Page`を「ページ」、`File`を「ファイル」、`Command`を「コマンド」、`Example`を「例」、`Directory`を「ディレクトリ」、`Default`を「既定」、一般的な`Error`を「エラー」とする。API名、Class名、Code、Error Code、`Default Connection`等の定義済みConceptは置換しない。
6. Version Laneは「Stable `1.1.0`」と「Repository `main`」へ統一する。一般説明では`Experimental`を「試験的」と書き、Public Table LabelやCodeとして必要な場合だけ英語を残す。`main Document Channel`や`Latest Stable`等の内部運用語を読者向け本文へ使わない。
7. Page種別ごとにReader Contractを変える。
   - Concept: なぜ必要か、何と何を区別するか、次に読むPageを示す
   - How-to／Tutorial: 前提、実行場所、手順、期待結果、失敗時の導線を示す
   - Reference: 定義と比較を簡潔に示し、実行手順の正本へLinkする
   - Troubleshooting: 「症状」「考えられる原因」「確認方法」「修正方法」へ統一する
8. Code Fence、Inline Code、Command名、Option、Route、Header、JSON、Error Code、Public Class／Attribute、Public Slug、Fragment、Stable／`main` CapabilityはEditorial都合で変更しない。正確性Conflictを発見した場合はSource Evidenceへ照合し、Reportへ記録する。
9. Landingの指定Copy、Hero、CTA、Operation／Journal／Headless三要素、Header、Banner、Sidebar Label／順序、Public Slug、Redirect、Search、Site UXを変更しない。
10. ReleasesとBlackOps Board GuideのPublication説明はD129／D130とP20-009G Acceptanceへ同期し、Documentation Website公開済みとBlackOps Board未公開を混同しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Editorial GuardはFenceとInline Codeの実行Tokenを除外し、禁止表記をCode Exampleへ誤適用しない。
- Editorial Guardは表示されるMermaidの`accTitle`、`accDescr`、引用符付きLabelと、Code Example内の読者向けCommentを本文として検査する。Mermaid識別子、JSON、正確なShell Outputへは誤適用しない。
- Content MapのDescriptionも同じ表記へ揃え、Search Resultの短い説明を本文と一致させる。
- 全PageのCoverage、正確性を維持したEvidence、変更不要PageはTask Reportへ記録する。
- Framework Production Code、Example、Consumer Test、Stable Tag、Blume Version、External Deployは変更しない。

[/CONSEQUENCES]

## References

- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [D132 Documentation Site UX](132-documentation-site-ux.md)
- [Specification 97 Documentation Editorial Style](../spec/97-documentation-editorial-style.md)
- [Specification 59 Documentation Reader Experience](../spec/59-documentation-reader-experience.md)
- [Documentation Review Agent](../spec/92-documentation-review-agent.md)
