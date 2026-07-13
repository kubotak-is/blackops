# D084: Documentation Reader Journey Corrections

Status: Decided

## Context

P10-005A／P10-005Bは仕様由来の正確さ、概念導入、一気通貫Example、Referenceを追加した。一方、実際のWebsite Reviewでは、LandingでFrameworkの魅力が伝わらない、QuickstartがInstallを含まず最短経路になっていない、TutorialがGeneratorを使わず手書きを要求する、Diagramの見出しと表示Sizeが読者体験を損なう、Validationの実装可能範囲が分からないという問題が見つかった。

StarlightとNext.jsのLandingは、Hero直後に特徴を独立したBlockとして示し、詳細Pageへの入口として使っている。BlackOpsも説明文の列挙ではなく、読者が価値を選んで掘り下げられる構成にする。

## Decision

[DECISION]

1. Landing Hero直後にBlackOpsの価値を4つの大きなLink Blockで示す。
2. 4 Blockは「一つのOperation、二つの実行経路」「型付きOperationを生成」「No operation stays in the dark」「Durable Deferred Execution」を扱い、それぞれ関連GuideへLinkする。
3. LandingのPrimary CTAはInstallを含むQuickstartとし、Why BlackOpsを理解用のSecondary CTAにする。
4. Quickstartは空Directoryから`composer create-project`、Build、Migration、HTTP起動、Inline Request、Deferred Request、Worker実行までを一Pageの最短手順にする。別PageのInstall完了を前提にしない。
5. First Operation Pageは「チュートリアル: Operationを作る」へ改名し、`make:operation`でSourceを生成してから必要なValue、Outcome、Route、Execution Strategyだけを編集する。4 Fileの完全手書きを標準手順にしない。
6. `main`限定Generatorを使うPageはStable `1.0.0`との差をPage冒頭で明示し、Stable向けQuickstartと混同させない。
7. MermaidのAccessible Descriptionと隣接する同等本文は維持するが、「図のテキスト代替」という読者向け見出しは表示しない。本文は自然な説明として配置する。
8. Sequence DiagramはDesktopでも縮小しすぎず、Diagram領域内のHorizontal Scrollで読める最小幅を持たせる。Page全体のHorizontal Overflowは発生させない。
9. Validation GuideをOperationsへ追加し、現在利用できるBinding、Handler内のValue／Business Validation、`OperationRejectedException::validation()`、HTTP／Journal結果を動く完全例で示す。
10. 未実装の宣言的Value Validation Attributeを利用可能であるかのように説明しない。Current Statusへ実装Gapを明示する。
11. Stable／main BannerとCurrent Statusの正直さを維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Top Pageは説明の索引ではなく、BlackOpsを採用する理由からGuideへ進む入口になる。
- QuickstartとTutorialの役割が、完成済みApplicationを最短で試す経路と、自分のOperationを作る学習経路に分かれる。
- Accessibilityの代替説明を保ちながら、機械的な見出しを読者へ見せずに済む。
- Validation Attributeの仕様と実装の差を公開し、利用者が現時点で動かせる方法を選べる。

[/CONSEQUENCES]

