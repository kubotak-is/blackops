# BlackOps Documentation Website

Astro Starlightで構築するBlackOps利用者向けDocumentation Websiteである。公開本文の編集元はRepository Rootの`docs/guide/`だけであり、このProject内へ本文を手動Copyしない。

## Toolchain

Repository RootでNode.jsとpnpmを導入する。

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
```

Node.jsとpnpmのVersionは`mise.toml`、`package.json`、CIで一致させる。Dependency更新時は`package.json`と`pnpm-lock.yaml`を同じCommitで更新する。

## Development

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run dev
```

`content:generate`は`docs/guide/`からStarlight ContentとManifestを生成する。生成先の`src/content/docs/`と`.generated/`、Astro出力の`dist/`はGit管理しない。生成物を直接編集しても次回実行で全置換されるため、本文変更は必ず`docs/guide/`へ行う。

GeneratorはTitle、Slug、内部Link、Source境界を検証する。`docs/internal/`、`develop/`、Repository Absolute Pathは公開Contentへ取り込まない。

`content-map.mjs`はSource Relative Pathから公開Slug／Page MetadataへのMapping、`site-navigation.mjs`は5つの利用者向けSidebarを管理する。Source追加時は両方を更新する。未登録Source、欠落Source、重複Slug、Sidebar未配置／重複／未知SlugはBuild前に拒否される。

Static Build後の`site:check`はLanding CTA、InstallからLocal Runtimeまでの連続Link、全PageのVersion Notice、Starlight標準Skip Link／Mobile Menu／Search Shortcut／Theme Selector、Pagefind日本語Indexと実Searchを検証する。独自Color／Navigation Componentは追加せず、Starlight標準のContrastとKeyboard Interactionを維持する。

## Delivery

`.github/workflows/docs.yml`はPull Requestと`main`で同じTest／Check／Buildを実行し、検証済みの`dist/`だけをCloudflare Pages Project `blackops-docs`へDirect Uploadする。Fork Pull RequestはSecretなしでBuildまで実行し、DeployだけをSkipする。Cloudflare Project、GitHub Environment、Secret、Rollbackの設定は[Documentation Website Delivery](../internal/documentation-website.md)を参照する。

Wranglerは`package.json`とLockfileでExact Pinする。`pnpm-workspace.yaml`の`allowBuilds`は、Astroが使う`esbuild`に加え、Wranglerの実行Dependencyである`sharp`と`workerd`のInstall Scriptだけを許可する。
