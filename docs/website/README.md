# BlackOps Documentation Website

Blumeで構築する利用者向けDocumentation Websiteです。公開本文の編集元はRepository Rootの`docs/guide/`だけであり、このProject内へ本文を手動Copyしません。

## Local workflow

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run dev
```

`content:generate`は`docs/guide/`からBlume ContentとManifestを生成します。生成先の`src/content/docs/`と`.generated/`、Static出力の`dist/`はGit管理しません。生成物を直接編集しても次回実行で全置換されるため、本文変更は必ず`docs/guide/`へ行います。

上記の全件検証は変更が確定した段階で実行します。作業中は変更に関係するTestを選び、成功した同じ入力の検証を繰り返しません。検証の選択と担当は`develop/spec/109-ai-development-workflow.md`に従います。

`blume.config.ts`はBlume標準のHeader、Sidebar、Search、Table of Contents、Theme、Skip Link、Mobile Navigationを有効にし、SidebarをStart Here、Build、Async and Lifecycle、Data and Security、Operate、Reference、Releasesの順へ固定します。Landingは`pages/index.astro`のCustom Pageで、What's BlackOpsとInstallを最初の導線にします。HTTP／Console／Scheduleの入口、実際のOperation code、目的別のSection navigationを掲載します。単一のArchifyアニメーションではHTTPのInline／DeferredとJournalイベントを段階番号とともに表示します。図の要素を選ぶと再生を停止して説明パネルを切り替え、パネルの「詳しく見る」からGuideへ進めます。PCでは横方向、Mobileでは縦方向のCanonical Layoutを使います。自動再生の停止・手動操作・Reduced Motionに対応し、Core Conceptsの図はGuideへ掲載します。

## Content and URL boundary

`content-map.mjs`はSource Relative Pathから公開Slug／Page Metadata／canonical Sectionへ決定的にMappingし、未登録Source、欠落Source、重複Slug、wrong-section、壊れたLinkはBuild前に拒否します。`docs/internal/`、`develop/`、Task／Reportは公開Page、Navigation、Search、Artifactへ含めません。

Reader Contractも`content-map.mjs`を単一正本とし、Landingを除く40 PageをTutorial 3、How-to 18、Concept 10、Reference 8、Troubleshooting 1へ分類します。各Pageのreader outcome、type別role、next導線は`reader-contract.mjs`がSourceと生成Artifact（HTML、Search、raw Markdown、`llms.txt`、`llms-full.txt`）で同じ形になるよう検証します。`content-pipeline.mjs`が生成するreader outcome markerはLLM full-text segmentの境界確認に使い、本文を手動Copyする用途ではありません。Protected BOPD Blobのdecode／JSON cast、誤ったRetry Event、現行Stableのmain-only claim、Source-derived Reference coverageも同じfail-closed guardへ集約しています。

Source-derived Referenceの216 types／25 attributesは、Release AuthorityのExperimental Stable 1.2.0 Framework tupleとroadmap 1.3.0 unreleasedを境界にします。Stable sourceにまだない9つの未公開Pathと、`ApplicationBuilder`の未公開method 1件だけをPath／FQCN／method名の完全一致で除外し、Authority tupleが変わった場合は除外の再評価を要求します。類似名や新しいSourceはこの除外に含まれません。

既存のPublic Slugと`public/_redirects`は維持します。Slugを変更するときはSource Link、Content Map、Sidebar、Redirect、Search／Artifact Testを同じ変更単位で更新してください。

## Version notice

BlumeのDismiss不可Bannerを全Pageへ表示します。本文は`BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。`とし、Releases Linkを維持します。将来計画をRelease済みの保証として表現しません。

## Diagram and font assets

公開する全10図はArchifyで生成します。画像は`width:100%`、`max-width:100%`、`height:auto`とし、記事内に収めます。横長の図を縮小するだけでなく、縦方向の配置や図の分割でMobileの可読性を確保します。固定最小幅による横Scrollや画像の切り落としは使いません。対応する日本語の本文も維持します。既存のMermaid処理は互換性のために残していますが、今回の公開図では使用しません。

`docs/guide/assets/`のTracked PNGはContent Pipelineが検証してStatic Artifactへコピーします。サイトはLocalのNoto Sans JPを読み込み、ArchifyのHTML／SVGには同じFontを埋め込みます。FontのLicense、取得元、再生成方法は[fonts/README.md](fonts/README.md)を参照してください。Font名の宣言に加え、実際の日本語GlyphとPNG出力を確認します。Artifact Guardは公開禁止Path、Credential、Repository Absolute Path、Source Mapの混入を拒否します。

## Archify diagrams

`docs/website/public/diagrams/`のJSONが図のCanonical Sourceです。`diagrams/manifest.json`は10個のArchitecture図について、所有Guide、Route、Source／HTML／PNGの固定Path、SHA-256、生成Receipt、ArchifyのPinned Revisionを一つに記録します。`execution-overview`はCanonical SVGとHTML／SVG Hashを結んだExport Receiptも持ちます。TOPでは同じ説明の`desktop` Variantとして横長の`execution-overview-desktop.{json,html,svg}`を登録し、Mobile用の縦長レイアウトと切り替えます。Variantも固定Path、Git追跡、Byte Hash、日本語Font、Release Claim、実際のvalidate／deliver／SVG Export Receiptを検証します。通常のcheck/buildは登録済みFileの存在、Git追跡、Byte Hash、JSON／HTML／PNGの対応、ViewerとSVGのAccessibility、未知のFlat HTMLを検証します。所有Guideには対応するPNGを置きますが、保守用Viewer HTMLへの読者向けLinkは要求しません。登録されていない`/diagrams/`PathはGuide本文からも参照できません。

再生成では、一時Directoryへ固定Revisionを取得して`offline-fonts.patch`を適用します。PatchはTemplateの外部フォント読込を除き、MIT Noticeを非表示Commentとして保持し、Localの日本語Fontと可読性の修正を含みます。各図をArchitecture Rendererで検証・生成し、Sourceを編集した場合はHTML、Guide用PNG、該当OverviewレイアウトのCanonical SVGを再生成してから全HashとReceiptを更新します。

```bash
ARCHIFY_REVISION=2ead014aa8ec91f104cd052f1a6ca82de5e26c31
ARCHIFY_CHECKOUT="$(mktemp -d /tmp/blackops-archify.XXXXXX)/checkout"
git clone https://github.com/tt-a1i/archify "$ARCHIFY_CHECKOUT"
git -C "$ARCHIFY_CHECKOUT" checkout --detach "$ARCHIFY_REVISION"
ARCHIFY_ROOT="$ARCHIFY_CHECKOUT/archify"
ARCHIFY_OUTPUT="$ARCHIFY_CHECKOUT/output"
mkdir -p "$ARCHIFY_OUTPUT"
git -C "$ARCHIFY_CHECKOUT" apply "$PWD/docs/website/diagrams/offline-fonts.patch"
ARCHIFY_CLI="$ARCHIFY_ROOT/bin/archify.mjs"
for ID in runtime execution-overview execution-overview-desktop execution-inline execution-acceptance execution-worker execution-context lifecycle-success lifecycle-rejection lifecycle-failure outbox; do
  ARCHIFY_UPDATE_CHECK_DISABLED=1 node "$ARCHIFY_CLI" validate architecture "docs/website/public/diagrams/$ID.json" --quality showcase --json
  ARCHIFY_UPDATE_CHECK_DISABLED=1 node "$ARCHIFY_CLI" deliver architecture "docs/website/public/diagrams/$ID.json" "$ARCHIFY_OUTPUT/$ID.html" --quality showcase --json
  cp "$ARCHIFY_OUTPUT/$ID.html" "docs/website/public/diagrams/$ID.html"
done
for ID in runtime execution-overview execution-overview-desktop execution-inline execution-acceptance execution-worker execution-context lifecycle-success lifecycle-rejection lifecycle-failure outbox; do
  ARCHIFY_CHROME=/path/to/chrome node docs/website/scripts/archify-visual-check.mjs "$ARCHIFY_ROOT" "$ARCHIFY_OUTPUT/$ID.html" --json
done
# 固定Viewerで両Overviewレイアウトの Export > Image > SVG を実行し、ReceiptへHTML／SVG Hashを記録します。
```

PNGは同じDelivered HTMLを固定Viewerで開き、`Export`→`Image`→`PNG`から各図のFull Diagramを保存します。Guide用PNGのThemeはLightを選び、Viewerの操作UIやFocus表示をPNGへ含めません。`execution-overview`と`execution-overview-desktop`のSVGはCanonical Pathへコピーし、それぞれのHTML／SVG HashをExport Receiptへ保存します。画面のCaptureと検証Sidecarは一時Directoryへ残し、`public/diagrams/`へコピーしません。`public/diagrams/`には登録済みのJSON、HTML、および登録済みのOverview SVGだけを置きます。各`validate`／`deliver`のJSON Receiptは9/9 showcase、Error 0、Warning 0を確認してManifestへ保存します。`archify-visual-check.mjs`はPinned Archifyの変更していない`runVisualCheck`を使い、各Measurement／Captureに新しいChromeを起動して自動Browser Evidenceを収集します。`ARCHIFY_CHROME`はChrome実行ファイル、Dockerでは必要に応じて`ARCHIFY_CHROME_NO_SANDBOX=1`を指定します。PatchとTemplateのBefore／After SHA-256もManifestへ記録します。生成HTMLの固定Viewer UIと`<html lang>`は英語で、図中の日本語ラベルは翻訳されません。

## Delivery

`.github/workflows/docs.yml`はPull Requestと`main`で同じInstall／Test／Check／Buildを実行し、検証済みの`docs/website/dist/`だけをArtifactとしてCloudflare Pages Direct Uploadへ渡します。公開Websiteは`https://blackops-php.pages.dev`です。Project CredentialとCustom DomainはRepositoryへ保存しません。
