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

`blume.config.ts`はBlume標準のHeader、Sidebar、Search、Table of Contents、Theme、Skip Link、Mobile Navigationを有効にし、Sidebarを利用者の学習順へ固定します。Landingだけは`pages/index.astro`のCustom Pageで指定されたHeroと、Desktop三列／Mobile一列の三要素Gridを構成します。

## Content and URL boundary

`content-map.mjs`はSource Relative Pathから公開Slug／Page Metadataへ決定的にMappingし、未登録Source、欠落Source、重複Slug、壊れたLinkはBuild前に拒否します。`docs/internal/`、`develop/`、Task／Reportは公開Page、Navigation、Search、Artifactへ含めません。

既存のPublic Slugと`public/_redirects`は維持します。Slugを変更するときはSource Link、Content Map、Sidebar、Redirect、Search／Artifact Testを同じ変更単位で更新してください。

## Version notice

BlumeのDismiss不可Bannerを全Pageへ表示します。本文は`BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。`とし、Releases Linkを維持します。将来計画をRelease済みの保証として表現しません。

## Mermaid and assets

Mermaid Fenceを含むPageだけを`.mdx`へ生成し、通常Pageは`.md`を維持します。Blume native `<blume-mermaid>`がExact PinしたLocal `mermaid` DependencyでParseし、外部CDNへ接続しません。各FenceはAccessible Metadataと隣接する自然な説明を持ち、DesktopではDiagramをArticle幅で表示し、Mobileでは42rem以上の可読幅をHost内だけ横Scrollさせます。Artifact GuardはBuild後の`<blume-mermaid>` Render Target、`data-language="mermaid"` Code Block不在、Local Renderer Bundle、および可読幅の寸法CSSを確認します。SVGの実寸、Page Overflow、Light／Dark Theme切替、Responsive OverflowはBrowser Verificationで確認します。

`docs/guide/assets/`のTracked PNGはContent Pipelineが検証してStatic Artifactへコピーします。Artifact Guardは公開禁止Path、Credential、Repository Absolute Path、Source Mapの混入を拒否します。

## Delivery

`.github/workflows/docs.yml`はPull Requestと`main`で同じInstall／Test／Check／Buildを実行し、検証済みの`docs/website/dist/`だけをArtifactとしてCloudflare Pages Direct Uploadへ渡します。Project、Credential、Custom Domain、External PublicationはこのTaskのScope外です。
