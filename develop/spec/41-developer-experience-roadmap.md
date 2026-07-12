# Developer Experience Roadmap

## Scope

MVP後のDeveloper Experienceは、Install直後のApplication、Composer Project Bootstrap、Project CLI、Documentation Websiteを一貫した利用体験として提供する。

実装順序は次のとおりとする。

1. Phase 7: Installed Application Example and Skeleton Layout
2. Phase 8: Composer Project Bootstrap
3. Phase 9: Project BlackOps CLI
4. Phase 10: Documentation Website

## Installed Application Example

`examples/quickstart/` は次の2つの役割を兼ねる。

- Framework利用者が参照して実行できるInstalled Application Example
- Composer Project Package `blackops/skeleton` のSource of Truth

このDirectoryはFramework Repository内部だけで成立するPathや未公開APIへ依存してはならない。Consumer E2Eは、通常のComposer DependencyとしてFrameworkを読み込むApplication境界を検証する。

最低限、Inline、Deferred、Worker、Migration、Retentionの構成と実行例を含める。実際のDirectory LayoutとPublic Composition APIはPhase 7のTask Packetで確定する。

## Composer Project Bootstrap

Application作成の公式Commandは次の形式とする。

```bash
composer create-project blackops/skeleton my-app
```

`blackops new` Commandおよび専用Installer Packageは提供しない。

`blackops/skeleton` は独立したComposer Project Packageとして公開できる配布境界を持つ。Framework、Skeleton、Websiteの開発は当面同一Repositoryで行い、公開時に `examples/quickstart/` を分離して配布できる構成とする。

## Project BlackOps CLI

生成されたApplicationは、Project所有の薄い `bin/blackops` Entrypointを持つ。

Project Entrypointの責務は次に限定する。

- Composer Autoloaderの読み込み
- Application固有のEnvironment、DB Connection、Provider、Custom Commandの構成
- Framework Console Kernelの起動

Framework Packageは次を所有する。

- `make:operation` と `make:migration` を含むCommand実装
- Migration、Worker、Build、Retention、Scheduler Command
- Generator Stub
- Command Registration APIとConsole Kernel

Project EntrypointはCommand実装を複製しない。Framework Package Update後は、既存Projectの `bin/blackops` も更新済みのFramework Command実装とGenerator Stubを読み込む。

Entrypointが利用するPublic Bootstrap Contractの互換性を破る変更には、Backward CompatibilityまたはUpgrade Guideを必要とする。

## Documentation Website

Documentationは次のDirectoryへ集約する。

```text
docs/guide/
docs/internal/
docs/website/
```

- `docs/guide/`: Framework利用者向けMarkdownのSource of Truth
- `docs/internal/`: Framework実装者向けMarkdownのSource of Truth
- `docs/website/`: Astro StarlightのWebsite、Build設定、Website固有Asset

Website Contentを別に複製せず、`docs/guide/` と `docs/internal/` のMarkdownから決定的に静的Buildする。WebsiteはCloudflare PagesへDeployし、PreviewとProductionのPublication Boundaryを持つ。

`docs/internals/` から `docs/internal/` への移行は、Repository内リンク、AGENTS、CI、関連Task Packetを同じ変更単位で更新する。

## Delivery Milestones

### Phase 7

- Public Composition APIのConsumer Audit
- Install後と同じApplication Directory Layout
- Consumer E2E
- ExampleとSkeletonの共通Source確立

### Phase 8

- `blackops/skeleton` Package Metadataと配布境界
- `composer create-project` による生成
- Install後Smoke Test

### Phase 9

- Project所有の `bin/blackops`
- Console KernelとCommand Registration API
- `make:operation`、`make:migration`
- Runtime／Maintenance CommandのApplication構成

### Phase 10

- Astro Starlight Website
- `docs/internal/` への移行
- Markdown Single Source Build
- Cloudflare Pages Preview／Production Deploy

## Traceability

- Decision: [D063 Developer Experience Roadmap](../decisions/063-developer-experience-roadmap.md)
