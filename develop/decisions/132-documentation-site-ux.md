# D132: Documentation Site UX

Status: Decided

## Context

P20-007からP20-010でStable入門動線とTask-oriented Guideを整備した。現在のBlume 1.1.4はCode CopyとPrevious／Next、Callout、Page Actionを提供するが、BlackOps Websiteでは次の接続不足が残る。

- Code CopyのAccessible Labelが英語Fallbackの`Copy code`になる
- Manual Sidebar Entryの`pageId`が空になり、Blume native PaginationへPage順が渡らない
- Generated ContentのSource Pathから作るEdit URLは`docs/website/src/content/docs/**`を指すため無効化している
- `docs/guide/*.md`はMermaid PageだけをMDX生成するため、Callout Directiveを利用できない
- Latin Fontだけを指定し、日本語GlyphのFallbackをBrowser任せにしている
- 長いInline CodeがMobile Page全体へ横Overflowを出す

## Decision

[DECISION]

1. Redesign modeはPreserveとし、Public Slug、Sidebar、Landing指定Copy、Operation／Journal／Headless三要素、Header、Banner、Search、Redirectを維持する。
2. Site UXはBlume 1.1.4のNative Callout、Code Copy、Pagination、Page Actionを優先し、同じComponentを再実装しない。
3. Callout Directiveを持つPageもMermaid Pageと同様にMDXへ生成する。Stable／`main`、破壊的操作、運用上の注意を短いCalloutへ移し、本文と重複させない。
4. Previous／Nextは`site-navigation.mjs`のSidebar順を唯一の正本とする。Manual NavigationでNative PaginationへPageが渡らない境界だけを薄いAdapterで補い、表示はBlume native Paginationへ委譲する。
5. Edit LinkはCurrent Routeから`content-map.mjs`のSourceへ逆引きし、`https://github.com/kubotak-is/blackops/edit/main/docs/guide/<source>`だけを出力する。Generated Content、存在しないSource、Repository外PathへLinkしない。
6. Code CopyはBlume native ButtonとClipboard処理を維持する。日本語Accessible Labelを設定し、成功／失敗をIconだけでなく更新LabelまたはLive Statusで支援技術へ通知する薄いEnhancementを追加する。
7. Latin Fontは既存のSelf-hosted Inter／IBM Plex Sans／IBM Plex Monoを維持し、日本語向けにHiragino Sans、Yu Gothic UI／Yu Gothic、Noto Sans JP、System Sansの明示Fallback Stackを追加する。Runtime Google Fontsや外部Font CDNへ依存しない。
8. Long Inline CodeはArticle内で折り返し、Code Block、Table、Mermaidの局所横Scrollを維持する。Page全体へ横Overflowを出さない。
9. Landingの`01`／`02`というDecorative Section Numberは削除する。Hero、CTA、Stable Command、三Featureの指定本文と同格Layoutは変更しない。
10. 全Page文章編集と用語の日本語化はP20-012へ分離する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Pagination、Edit Link、Callout、Copy Label／StatusのRegressionはWebsite TestとStatic Artifactで固定する。
- BrowserではDesktop Light／DarkとMobile 390pxでCopy成功／失敗通知、Callout、Pagination、Edit Link、Focus、Overflowを確認する。
- Blume Version、Framework Production Code、GuideのPublic Slug、External Publicationは変更しない。
- Japanese Font Packageを追加せず、既存Self-hosted Latin FontとOS／BrowserのCJK Fontを明示的に組み合わせる。

[/CONSEQUENCES]

## References

- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [Specification 84 Documentation Learning Journey](../spec/84-documentation-learning-journey.md)
- [Specification 96 Documentation Site UX](../spec/96-documentation-site-ux.md)
