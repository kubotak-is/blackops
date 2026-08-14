# P20-012: Documentation Editorial Pass

Status: Accepted

## Goal

全38 Public Documentation Sourceを、実装済みContractを変えずに一貫した利用者向け日本語へ編集し、表記GuidelineとRegression Guardを確立する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/133-documentation-editorial-style.md`
- `develop/spec/97-documentation-editorial-style.md`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/decisions/129-documentation-website-publication.md`
- `develop/decisions/130-blume-production-search-verification.md`
- Existing P20-007 through P20-011 Task Reports
- Current `docs/guide/*.md`、`docs/guide/glossary.md`、`docs/website/content-map.mjs`、Website Regression
- Current Framework／Skeleton／Example／Consumer Test and Stable tag `1.1.0` as Accuracy Evidence

## In Scope

- 全38 `docs/guide/*.md` SourceのEditorial Review
- です・ます調、短く直接的なSentence／Paragraph
- BlackOps Concept／Official Product／一般語の表記統一
- Concept／How-to／Reference／TroubleshootingのReader Contract
- Stable `1.1.0`／Repository `main`のVersion Lane統一
- Releases／BlackOps Board GuideのDocumentation Website Publication説明Correction
- Content Map Descriptionの表記同期
- Fence／Inline Code aware Editorial Regression
- Website Test／Content／Artifact／Browser Regression
- Decision／Specification／TODO／STATE／Report同期

## Out of Scope

- Framework `src/**`、Test、Generator、Skeleton、Example、Consumer変更
- Public API、Command、Option、Route、Header、JSON、Error Codeの変更
- Public Slug、Sidebar Label／順序、H1、Redirect、Header、Banner、Search
- Landing指定Copy、Hero、CTA、三Feature Layout、Theme、Site UX
- New Guide Page、Navigation Section、Marketing Copy、Image、Dependency
- Stable Tag、Commit、Push、PR、External Deploy

## Files Allowed to Change

- `docs/guide/*.md`
- `docs/website/content-map.mjs`
- `docs/website/README.md`
- `docs/website/tests/**`
- `docs/website/scripts/**`
- `develop/decisions/133-documentation-editorial-style.md`
- `develop/spec/97-documentation-editorial-style.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-012-documentation-editorial-pass.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- 全38 SourceをReviewし、変更不要PageもReportのCoverageへ記録する
- 全38 SourceのPage TypeはSpecification 97の割当を使い、ReportでSourceごとに変更有無とContract確認結果を記録する
- 一般英語を機械置換せず、Fence／Inline Code／Public Concept／Exact Nameを保護する
- `Task Report`、`Consumer Evidence`、本文中の`Phase`管理番号等の内部運用語を、利用者が判断できる説明へ置き換える
- Editorial Guardは表示されるMarkdown、Code Comment、Mermaid Labelを検査し、実行Token、正確なOutput、Inline Code、Link Target、HTML Comment、Mermaid構文を除外する
- Command、Code、JSON、Route、Header、Error Code、Stable／`main` CapabilityをEditorial都合で変更しない
- Existing Regressionを削除または緩和せず、変更した表記を期待値へ同期しながらAccuracy／Security／Journey Assertionを維持する
- Landing Copyは`reader-experience.test.mjs`のLanding exact-copy Testと`check-site.mjs`のLanding assertionをFixtureとして一字も変更しない
- Accuracy ConflictはImplementation／Stable tag／Specificationへ照合し、Documentationだけで推測修正しない
- Landing Custom Page、Theme、Navigation、Generated Content、`dist`を変更しない
- Existing Phase 20 Working Tree差分を保持する
- WorkerはCommitしない

## Required Accuracy Corrections

### Publication

- ReleasesはDocumentation WebsiteをCloudflare Pagesへ公開済みとして説明する
- BlackOps BoardはLocal／CI Reference Applicationで公開Hostを提供しない
- Community Board Guideでも両者を同じ未公開状態として扱わない

### Retention

- Project Rootで実行する完全なHost用`php blackops retention:plan ...`と、対応するContainer用`docker compose run --rm app php blackops ...`を示す
- `retention:purge`もHost／Containerの対応を示し、`--dry-run`を副作用のない確認手順として`--confirm`より先に示す
- Planは`Retention plan`と対象別件数、dry-runは`Retention purge dry run`と対象別件数を表示し、成功時は終了Code `0`になることを説明する
- `--confirm`はPolicy ReferenceとActorを含む完全な例として残す

### Observer Replay

- 新規dry-runはSelectorを一つ、Observerを一つ以上、`--dry-run`を指定する。Checkpoint、Actor、Reasonは要求しない
- 新規confirmはSelectorを一つ、Observerを一つ以上、Checkpoint、Actor、Reason、`--confirm`を指定する
- resume confirmは`--resume`、Actor、Reason、`--confirm`を指定し、Selector、Observer、Checkpointを再指定しない
- dry-runの期待出力は`selected`、`delivered`、`failed`、`has-more`、`complete`と、該当時の`first-record-id`／`last-record-id`である。Checkpoint、Payload、Operatorは出力しない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。Browser VerificationはOrchestratorが最新SourceのDev ServerまたはStatic Artifactを使い、全Public RouteをDesktop 1440px Light／DarkとMobile 390pxで確認する。

## Acceptance Criteria

- [ ] 表記GuidelineがPublic Concept、Official Product、一般語、Version Lane、Page種別を定義する
- [ ] 全38 SourceをReviewし、Coverageと判断をReportへ記録する
- [ ] 一般語を日本語へ整え、Public Concept／Code／External ProductのExact Nameを維持する
- [ ] Concept／How-to／Reference／TroubleshootingがReader Contractへ一致する
- [ ] RetentionとObserver Replayの手順がRequired Accuracy Correctionsどおり実行可能である
- [ ] Stable `1.1.0`とRepository `main`のCapability境界を維持する
- [ ] Documentation Website公開済みとBlackOps Board未公開の説明を正しく分離する
- [ ] Fence／Inline Code aware Editorial Guardが禁止表記の再流入を拒否する
- [ ] Existing Public Slug、Navigation、H1、Landing、Header、Banner、Search、Redirect、Site UXを維持する
- [ ] Website RegressionとRequired Commandsが成功する
- [ ] WorkerはReport／STATE／TODOを同期し、Commitしない

## Completion Report

`develop/orchestration/reports/P20-012-documentation-editorial-pass.md`へSummary、Changed Files、Full-site Coverage、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
