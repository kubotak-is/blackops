# D120: Documentation Second Review and Feature Parity

Status: Decided

Partially supersedes D117／Specification 84の改善順と、D119／Specification 86のFeature非対称Layout。D119のHero、Brand、GitHub導線、Product Visual、Sidebar Current Page、指定本文、Public Slug、Delivery境界は維持する。

## Context

第2回Documentation Reviewでは、前回の正確性とNavigation修正が概ね完了した一方、H1変更後の被Link文言、`#[Sensitive]`、`#[ExecuteWith]`、Outcome例、GitHub編集Linkに退行または不整合が残っていることを確認した。Sidebar定義の二重管理と旧Starlight CSSも、次の変更で再発しやすい状態である。

LandingのOperation／Journal／Headlessは同格の三要素として指定されている。しかしD119の非対称Layoutは、Operationだけを大きくし、JournalとHeadlessを残余Columnへ積み上げたため、三要素の比較関係、読む順序、Visualの基準線が不明瞭になった。

## Decision

[DECISION]

1. LandingのOperation／Journal／Headlessは同格のFeatureとして扱う。Desktopは均等三列、MobileはOperation、Journal、Headlessの一列とし、見出し、Visual、本文、Linkの基準線と占有寸法を揃える。
2. 三Featureは同一の情報構造と視覚的Weightを持つが、Operation Code、Journal Lifecycle、Headless Client Generationという実物表現は維持する。汎用Marketing Cardへ置き換えず、Feature間の比較可能性を優先する。
3. 指定本文、Link Label、Link Targetは変更しない。Hero、Header Wordmark、Install／GitHub CTA、Stable Command、Header GitHub icon、Sidebar Active Stateも維持する。
4. 第2回Reviewの即時回収は、H1変更後の旧被Link文言、`#[Sensitive]`対象、`#[ExecuteWith]`のStrategy説明、Deferred Outcome Property、壊れたGitHub編集Linkへ限定して先に閉じる。
5. Page titleを指す内部Linkは現H1へ追従させる。文脈を説明するLink LabelまでH1と同一に強制せず、廃止したPage titleが再流入しないCI Guardを追加する。
6. GitHub Repository導線はHeader iconとLanding CTAで維持する。正本`docs/guide/*.md`へ解決できないPage edit linkは、正しいSource Pathを構成できるまでSemanticに出力しない。CSSで隠すだけの対処は行わない。
7. Sidebarは`site-navigation.mjs`を単一正本とし、Blume ConfigとSite Guardを同じ構造から生成または照合する。
8. Blumeから参照されない旧Starlight専用Styleは削除する。必要なDiagram Styleがある場合だけBlume Tokenへ移植し、未定義`--sl-*`を残さない。
9. Stable onboarding、Authentication、Testing、Deployment、Reference欠番、文章編集は後続Taskへ分離する。即時回収Taskへ場当たり的に混在させない。
10. Framework `src/**`、Public API、Migration、Public Slug、Redirect、External Publication／Deployは変更しない。

[/DECISION]

## Design Direction

- Redesign mode: Feature section correction inside the accepted Landing
- `DESIGN_VARIANCE: 6`
- `MOTION_INTENSITY: 4`
- `VISUAL_DENSITY: 5`
- Audience: PHP Frameworkを比較し、Operation／Journal／Headlessの関係を短時間で理解したい技術者
- Comparison language: equal columns, equal visual zones, aligned copy and links
- Preserve: deep Hero canvas, authentic code, lifecycle, generated client expression
- Avoid: one oversized feature plus stacked leftovers, arbitrary masonry, generic rounded card deck, unequal vertical rhythm

## Delivery Order

1. P20-006: Feature parity、即時退行、edit link、Sidebar single source、dead Starlight style
2. P20-007: Stable onboardingとAuthenticationを実行可能な手順へ再構築
3. P20-008以降: Reference欠番、Testing／Deployment、共通Callout／Pagination／Japanese font、文章編集

## Consequences

[CONSEQUENCES]

- Landingの三要素は同じ粒度で比較でき、どれが主要でどれが補助かという意図しない序列を作らない。
- 第2回Reviewの即時退行を先に閉じたうえで、最重要のOnboarding再構築へ独立したReview単位で進められる。
- GitHub Repository導線を失わず、存在しないGenerated Sourceへの編集導線だけを除去できる。
- NavigationとStyleの旧正本を減らし、次のDocumentation変更でのDriftをCIが検出できる。

[/CONSEQUENCES]

## References

- [Documentation Review Second Pass](../../docs/documentation-review.md)
- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [D119 Documentation Brand Expression and Active Navigation](119-documentation-brand-expression-and-active-navigation.md)
- [Specification 87](../spec/87-documentation-second-review-and-feature-parity.md)
