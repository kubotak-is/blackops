# D118: Documentation Landing Visual Hierarchy

Status: Decided

Landing PresentationはD119／Specification 86が置き換える。指定本文、CTA Target、Landing Link Integrityは維持する。

Supersedes D116／Specification 83／Specification 84 Landing Hero、CTA、三列Grid、Hero説明保持の規定。Operation／Journal／Headlessの本文とDocumentation Delivery境界は維持する。

## Context

Blume移行後のLandingは、`BlackOps - The PHP Framework`を同じ文字サイズで表示し、長いHero説明と均等三列のFeatureを並べていた。Framework名の階層が弱く、Feature本文とHero説明も重複している。Custom Astro Page内のLinkはBlumeのMarkdown Link Validation対象外で、Build済みLandingから実Pageへ到達できることを恒久検証していなかった。

UserはAstro公式Websiteのように、強いFramework名、短い導入、実際に使うCommand／Code、明確なGetting StartedとGitHub導線を求めている。

## Decision

[DECISION]

1. Landing H1は`BlackOps`を主見出し、`The PHP Framework`を小さい補助見出しとして同じH1内に表示する。
2. 旧Hero説明の長文はLandingから削除する。`docs/guide/README.md`にはFramework説明として残せるが、Custom Landingとの完全一致要件は解除する。
3. HeroはInstall、GitHub Repository、Stable Install Commandを最初のViewportで確認できる構成にする。
4. GitHub導線は`https://github.com/kubotak-is/blackops`を正とし、Landingの明示CTAとして表示する。
5. Feature見出しは`BlackOpsの特徴`とする。Operation／Journal／Headless本文は変更しない。
6. Featureは均等三列Cardをやめ、一つのGrid内でOperationを主Feature、Journal／Headlessを補助Featureとする非対称Layoutへ変更する。MobileではOperation、Journal、Headlessの順に一列表示する。
7. Operation Featureは実装済みPublic APIに一致する最小PHP CodeをVisualとして表示できる。未実装API、架空Dashboard、装飾だけのFake Screenshotは追加しない。
8. `Operationを始める`はFirst Operation、`Lifecycleを読む`はLifecycle、`Frontendを接続する`はFrontendへ接続する。
9. Custom Landingの全Internal LinkをBuild Artifact上の実Pageと照合し、Missing RouteをTestで拒否する。GitHub Linkも正確なRepository URLへ固定する。
10. Existing Public Slug、Sidebar、Banner、Search、Artifact、Cloudflare Delivery、Framework `src/**`は変更しない。

[/DECISION]

## Design Direction

- Redesign mode: targeted overhaul of the Landing only
- `DESIGN_VARIANCE: 7`
- `MOTION_INTENSITY: 3`
- `VISUAL_DENSITY: 4`
- Light／Darkは同じSemantic Tokenと一つのAccentを使う
- Typography、Spacing、Code Visual、Link Hierarchyを優先し、Decorationや自動Animationを追加しない
- Desktopは非対称、Mobileは厳密な一列Reading Orderへ戻す

## Consequences

[CONSEQUENCES]

- Landingは公開Guide本文を要約するPageではなく、Framework名、開始Command、主要概念、Documentation／GitHub入口を短時間で伝える入口になる。
- Operation／Journal／HeadlessのClaimは維持するが、Presentationは同一三列ではなくなる。
- Custom Astro PageのLink切れがMarkdown Pipelineの外側でも検出される。
- Stable Quickstart本文、Value Validation Extension、Operation Core APIは本Decisionで変更しない。

[/CONSEQUENCES]

## References

- [D116 Blume Documentation Site](116-blume-documentation-site.md)
- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [Specification 85](../spec/85-documentation-landing-visual-hierarchy.md)
- [Astro](https://astro.build/)
- [BlackOps GitHub Repository](https://github.com/kubotak-is/blackops)
