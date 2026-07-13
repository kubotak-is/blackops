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
