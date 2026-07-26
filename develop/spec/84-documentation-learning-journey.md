# Documentation Learning Journey

Supersedes: Specification 83 navigation, sidebar labels, and version-banner sections; D117 is the decision record for this contract.

## Scope

Blume Websiteの正確性、公開Pageの発見性、学習順、文章とUIの可読性を段階的に改善する。`docs/guide/`を公開本文の唯一の正本とし、Framework実装済みCapabilityだけを説明する。

## Immediate Accuracy Contract

- `#[Sensitive]`の付与対象を実装に合わせ、`OperationValue`／`Outcome` Propertyとして説明する
- Application Bootstrapが認識するConfig Fileを現在の実装とConfiguration Referenceへ一致させ、固定個数の古い断言を残さない
- Markdownの相対Page LinkだけでなくFragment Anchorも検証し、存在しないAnchorを拒否する
- `config/database.php`の例は`Environment`を受け取るClosureとして単体で成立させる
- Transactional child Operation例は標準Operation Metadata、`#[Deferred]` child、必要なImportを含む一貫した例にする

## Navigation Contract

SidebarはD117のSection、Label、順序を正とし、`contentMap`の`index`を除く全Public Slugを一度だけ配置する。Unknown、Duplicate、Missing、Section ReorderをValidationで拒否する。

既存Public Slugは変更しない。Sidebar LabelとH1は意味を一致させる。

- `What's BlackOps`
- `Core Concepts`
- `First Operation`
- `Authoring`
- `Generators`
- `Inline and Deferred`
- `Execution Context`
- `Retention`

既存のSidebar PageもD117の順序を維持する。

## Version Notice

全PageのBannerは日本語で一行表示し、次を含む。

- `main` Document Channel
- Latest Stable `1.1.0`
- BlackOps 1.xはExperimental
- Production Readyは2.xから予定
- Releasesへの詳細Link

Backward CompatibilityとProduction Readinessの詳細説明はReleases Pageを正本にする。

## Landing

Hero、CTA、Feature Layout、Custom Landing Link IntegrityはD118／Specification 85が置き換える。Operation／Journal／Headless本文、Keyboard、Dark Mode、Reduced Motionは維持する。

## Staged Follow-up

1. Stable `1.1.0`だけで完走できる入門動線とFirst Operation分岐
2. Operation／Lifecycle／Glossaryの学習者向けCodeと用語
3. Testing／Deployment／ConsoleCommand／Outbox／Database／Auth／FrontendのTask-oriented How-to
4. Callout、Copy Button、日本語Font、Prev／Next、Edit Link等のSite UX
5. 表記Guideline、一般語の日本語化、全Page編集Pass
6. Core API／BlackOps CLI／GlossaryのReference検索性

各Follow-upはTask Packetを分け、実装済みPublic APIとStable／`main`差を検証する。

## Verification

- Accuracy Contractの5件が実装Sourceと一致する
- Broken Page LinkとBroken Fragment AnchorをTestで拒否する
- 全Public SlugがSidebarへ一度だけ配置される
- Sidebar LabelとH1が一致する
- Bannerが日本語でExperimental PolicyとReleases Linkを表示する
- Landing指定本文とGridが変わらない
- Existing Public Slug、Redirect、Search、Static Artifactを維持する
- Documentation full gateが成功する

## Traceability

- Decision: [D117 Documentation Learning Journey](../decisions/117-documentation-learning-journey.md)
- Blume Experience: [Specification 83](83-blume-documentation-experience.md)
- Review Source: [Documentation Review](../../docs/documentation-review.md)
