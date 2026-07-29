# Documentation Editorial Style

## Scope

`docs/guide/*.md`の全Public SourceとSearch Descriptionを、実装済みContractを変えずに利用者向けの日本語へ整える。文章編集、一般語の日本語化、Version Lane、Page種別ごとの構成、Regression Guardを扱う。

## Voice and Sentence Contract

- 本文は日本語のです・ます調を使う
- 最初の段落で、そのPageで何が分かるか、何を完了できるかを示す
- 手順は読者が実行する順に書き、実行場所と期待結果をCommandまたはCodeの近くへ置く
- 必須操作は「〜してください」、説明は「〜します」「〜です」を使う
- 「〜である」「〜ものとする」「本仕様」「当該」「下記」「上記の通り」等の仕様書調を使わない
- 一文へ保証、例外、Security境界、次の行動を詰め込まず、必要なら段落またはListへ分ける
- 「可能です」「することができます」を多用せず、主語と動作を直接書く
- Framework内部のClass構成より、利用者が触るPublic API、入力、出力、失敗境界を先に書く

## Terminology Contract

### Preserve as BlackOps Concepts

次はBlackOpsのConceptまたはPublic表記として維持する。

保護対象の正本はGlossaryの見出しとPublic APIで定義されたConceptであり、次の一覧だけに限定しない。

- BlackOps
- Framework
- Application
- Operation
- OperationValue
- Value
- Outcome
- Journal
- ExecutionContext
- Lifecycle
- Inline
- Deferred
- Worker
- Retry
- Retention
- Outbox
- Attempt
- Claim
- Lease
- Fencing Token
- Heartbeat
- Projection
- Manifest
- Dead Letter
- Idempotency Key
- Idempotency Record
- Replay
- Correlation
- Causation
- Transport
- Actor
- Terminal State
- Ephemeral Outcome
- Supervision Policy
- Execution Strategy
- Frontend
- BlackOps CLI
- Canonical／Observed
- Build Artifact／Manifest

Class、Interface、Attribute、Method、Command、Option、Route、Header、JSON Field、Error CodeはBacktickで囲み、SourceのExact Nameを使う。

### Preserve Official External Names

- PHP 8.5
- JavaScript
- TypeScript
- Next.js
- Nuxt
- SvelteKit
- PostgreSQL
- Docker Compose
- GitHub
- Composer
- FrankenPHP
- Doctrine DBAL
- OpenTelemetry

`Javascript`、`NuxtJS`、`Project CLI`、`BlackOps Project CLI`は使用しない。

### Prefer Japanese for General Reader Words

Public ConceptまたはExact Nameでない場合は次へ統一する。

| Avoid | Use |
| --- | --- |
| Page | ページ |
| File | ファイル |
| Command | コマンド |
| Example | 例 |
| Directory | ディレクトリ |
| Default | 既定 |
| Error | エラー |
| Latest Stable | 最新のStable |
| Document Channel | 対象Versionの説明 |
| Available | 利用可 |
| Not available | 未提供 |
| Symptom | 症状 |
| Likely Cause | 考えられる原因 |
| How to Verify | 確認方法 |
| Fix | 修正方法 |

`Default Connection`、`Command Manifest`、`Error Code`等、Page内で定義して使うConceptは表の機械置換対象にしない。

## Version and Publication Contract

- Release済みLaneは`Stable 1.1.0`または`Stable \`1.1.0\``と書く
- 未Release Laneは`Repository \`main\``と書く
- 1.xは試験的で、Production Readyは2.xから予定する
- StableとRepository `main`のCapabilityを同じ手順内で混ぜない
- Documentation WebsiteはCloudflare Pagesへ公開済みである
- BlackOps BoardはLocal／CI Reference Applicationで、公開Hostを提供しない
- 両者を同じ「Local／CIのみ」または「未公開」とまとめない
- 将来構想は「候補」「未実装」と明示し、現在のCapabilityとして書かない

## Page Type Contract

全38 Sourceの種別は次で固定する。`README.md`はLandingの指定Copyと構成を維持するOrientationとして扱う。

| Type | Source |
| --- | --- |
| Orientation | `README.md` |
| Concept | `why-blackops.md`、`core-concepts.md`、`operation-lifecycle.md`、`journal.md`、`execution-context.md`、`security.md` |
| Reference | `attributes.md`、`configuration.md`、`core-api.md`、`directory-structure.md`、`glossary.md`、`mvp-status.md`、`project-cli.md` |
| How-to／Tutorial | `application-bootstrap.md`、`authentication.md`、`authorization.md`、`community-board.md`、`console-command.md`、`database-and-transactions.md`、`database-migrations.md`、`database-seeding.md`、`deployment.md`、`execution.md`、`first-operation.md`、`frontend.md`、`installation.md`、`mvp-sample.md`、`observer-replay.md`、`operations.md`、`outbox.md`、`outcome-retrieval.md`、`project-generators.md`、`retention.md`、`runtime-bootstrap.md`、`testing.md`、`validation.md` |
| Troubleshooting | `troubleshooting.md` |

### Concept

- Reader Questionまたは区別したい境界から始める
- Definitionを重複させずGlossaryへLinkする
- 最後に次の学習Pageを示す

### How-to and Tutorial

- Prerequisiteと実行Directoryを最初に示す
- Command直後へ期待Status／Output／生成Fileを置く
- Stable／`main`の分岐は実行前に示す
- Failureの詳細はTroubleshootingの具体的なHeadingへLinkする

### Reference

- 型、Attribute、Command、Option、設定KeyをTableまたは短い定義で比較できる
- 利用者が通常直接使わない型を明示する
- 同じRunnable Journeyを複製せずHow-toへLinkする

### Troubleshooting

各Problemは次の順を使う。

1. 症状
2. 考えられる原因
3. 確認方法
4. 修正方法

Credential、Raw Payload、Throwable、SQL等を診断出力へ貼るよう案内しない。

## Full-site Editorial Pass

- `docs/guide/README.md`を含む全38 Markdown SourceをReviewする
- H1はSidebar Labelと一致する既存Contractを維持する
- Landingの指定Copyは`docs/website/tests/reader-experience.test.mjs`の`guide landing source keeps the exact product title and three claims`／`landing product contract keeps the why link and exact Deferred sample`、`docs/website/scripts/check-site.mjs`のLanding assertionを正本として変更しない
- Content Map Descriptionを本文の目的と同じ表記へ揃える
- Code Fence、Mermaid Source、JSON／JSONL、Shell Output、Public Contractを文章都合で書き換えない
- Link先を具体的なReader Actionで表し、「こちら」「詳細」だけのLink Textを追加しない
- 重複説明は正本Pageへ集約し、現在地では要点とLinkだけを残す
- ReleasesとBlackOps Board Guideに残るDocumentation Website未公開表現を現在のPublication境界へ同期する
- Retention GuideはProject Rootで実行するHost用`php blackops retention:plan ...`／`retention:purge ...`と、Container用`docker compose run --rm app php blackops ...`の対応を示す。Purgeは`--dry-run`を先に示し、成功時の見出し、件数、終了Code `0`を説明してから`--confirm`を案内する
- Observer Replay Guideは新規dry-run、新規confirm、resume confirmのOption境界を分ける。新規dry-runはSelector一つとObserver一つ以上、新規confirmはそれらにCheckpoint、Actor、Reasonを追加し、resume confirmはResume、Actor、Reasonだけを使う。dry-runの`selected`、`delivered`、`failed`、`has-more`、`complete`と任意の先頭／末尾Record IDを期待出力として説明する
- 一般語の表記変更でAnchor、Link、Search、Raw Markdown、LLM Artifactを壊さない

## Regression Guard

表示されるMarkdown本文、Heading、Table、Link Text、Callout Label、Code Example内の読者向けCommentと、Mermaidの`accTitle`、`accDescr`、引用符付きLabelで、少なくとも次を拒否する。

- `Javascript`
- `NuxtJS`
- `Project CLI`
- `このPage`、`各Page`、`一Page`
- `次のFile`、`生成済みFile`、`全File`
- `次のCommand`、`各Command`、`確認Command`
- `Latest Stable`
- `Document Channel`
- `Task Report`
- `Consumer Evidence`
- 本文中の`Phase`＋管理番号
- TroubleshootingのEnglish Label
- 仕様書調のSentence Ending

GuardはBacktick数の異なるFenceと`~~~` Fenceを扱う。Shell／PHP／JSON／JSONL／正確なOutputの実行Token、Inline Code、Link Target、HTML Comment、Mermaid識別子と構文を検査対象から外す。GlossaryのConcept、Official Product Name、Code Exampleへ誤反応しないFixtureと、表示されるProse、Heading、Table、Link Text、Callout Label、Code Comment、Mermaid Labelの禁止表記を検出するFixtureを持つ。

## Information Architecture and UX Boundary

- Public Slug、Sidebar Label／順序、H1、Redirect、Header、Banner、Searchを変更しない
- Landing Hero、CTA、Operation／Journal／Headless Copyと同格Layoutを変更しない
- Callout、Copy、Previous／Next、Edit Link、日本語Font、Mermaid、Responsive Overflowを維持する
- Generated Contentと`dist`を直接編集しない

## Verification

- 全38 SourceのReview CoverageがReportへ記録される
- Editorial Guardが禁止表記を検出し、Code Fence／Inline Codeを除外する
- Existing Accuracy、Link、Navigation、Code、Artifact Testが成功する
- Website test、check、buildが成功する
- 全38 Public RouteをDesktop 1440px Light／DarkとMobile 390pxで確認し、Heading、Callout、Table／Code、Pagination、Page Overflowを維持する
- Documentation ReviewerがAccuracy、Runnable Journey、IA、Editorial、Visual、AccessibilityをRead-onlyで再Reviewする

## Traceability

- Decision: [D133 Documentation Editorial Style](../decisions/133-documentation-editorial-style.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
- Review Agent: [Specification 92](92-documentation-review-agent.md)
- Site UX: [Specification 96](96-documentation-site-ux.md)
