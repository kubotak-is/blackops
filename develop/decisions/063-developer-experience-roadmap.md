# D063: Developer Experience Roadmap

Status: Decided

## Context

BlackOps MVPは完了したが、現在のSampleはFramework Repository内のE2E検証が中心であり、利用者が実際にPackageをInstallした直後のApplication構成をそのまま示すものではない。

Post-MVPのDeveloper Experienceとして、次の4つを実現したい。

1. 実際のInstall後Applicationと同じDirectory構成を持つExamples
2. `composer create-project` でApplication Skeletonを生成するInstall Experience
3. Laravel `artisan` のようにOperation／Migration等の雛形生成とFramework Commandを提供するProject CLI
4. Repository内の `docs/website` にAstro Starlightで構築するDocumentation Website

Composerの `create-project` は、Project Packageの取得と依存Installを行う標準的なProject Bootstrap境界である。`post-root-package-install` と `post-create-project-cmd` もRoot Project側で使用できる。

Astro StarlightはDocumentation専用のAstro Themeであり、Navigation、Search、Internationalization、SEO、Code Highlighting、Dark Mode、Markdown／MDXを標準提供する。Svelte Component等の追加UIも必要に応じて利用できる。

## Question 1: Delivery Order

Developer Experienceをどの順序で構築するか。

### Options

- A: Installed Application Example -> Composer Skeleton -> Project `blackops` CLI -> Documentation Websiteの順で進める
- B: Installed Application ExampleとDocumentation Websiteを並行し、その後Installerを作る
- C: `blackops new` Installerを最初に作り、生成物を後から固める

### Recommendation

Aを推奨する。

Install後の期待Directory、Public Composition API、Config、Console、HTTP／Worker Bootstrapが確定してから、同じ完成形をSkeletonとInstallerに共有する。Installerを先に作ると、生成Layoutの変更ごとにCLIも変更することになる。

[ANSWER]

A
Composer Skeletonがあるなら `blackops new` は作らない。
むしろProject内の `blackops` Commandを拡充し、Laravelの `artisan` のようにOperationの雛形作成やMigration Fileの生成などができるようにする。

[/ANSWER]

## Question 2: Example and Skeleton Boundary

Install後ApplicationとExample／SkeletonのSource of Truthをどう管理するか。

### Options

- A: `app-skeleton/` を生成Templateの正本、`examples/quickstart/` を実際にInstallした出力Fixtureとし、Drift Testで同期する
- B: `examples/quickstart/` 一つをExampleとSkeletonの両方に使う
- C: SkeletonとExampleを最初から別Repositoryで独立管理する

### Recommendation

Aを推奨する。

`examples/quickstart/` を「生成されたApplicationが実際に動く」ことを証明するConsumer Fixtureにする。TemplateのPlaceholderとInstall後の確定値を混同せず、CIで生成し直した結果とFixtureを比較できる。

[ANSWER]

B
`examples/quickstart/` 一つをExampleとSkeletonの両方に使う。

[/ANSWER]

## Question 3: Installation Commands

Application作成Commandをどの段階で提供するか。

### Options

- A: まず `composer create-project blackops/skeleton my-app` を実装し、後から同じSkeleton Pipelineを呼ぶ `blackops new my-app` を追加する
- B: `blackops new my-app` だけを公式手順にし、Composer Commandを直接案内しない
- C: Composer `create-project` だけを提供し、専用Installerは作らない

### Recommendation

Aを推奨する。

Composer標準の再現可能な手順を先に確立し、`blackops new` はInteractive Option、Environment Check、通信エラー表示などのUXを付加する薄い専用Installerとする。

[ANSWER]

C

[/ANSWER]

## Question 4: Installer Packaging

`blackops new` をどの形で配布するか。

### Options

- A: `blackops/installer` をFrameworkとは別のComposer Packageとし、Global Composer InstallまたはStandalone PHARから `blackops` Binaryを提供する
- B: `blackops/framework` の `vendor/bin/blackops` に `new` Commandを含める
- C: Shell Scriptだけを配布する

### Recommendation

Aを推奨する。

Projectが存在する前に実行するCommandは、Projectの `vendor/bin` へは置けない。Framework RuntimeとInstallerのRelease Cycle／Dependencyも分離できる。初期はGlobal Composer Installを正式経路にし、PHARは後続で追加できる。

[ANSWER]

N/A
Question 3でComposer `create-project` だけを提供するため、`blackops new`専用Installer Packageは作らない。

[/ANSWER]

## Question 5: Documentation Website Stack

`docs-website/` に使うStackをどれにするか。

### Options

- A: Astro + Starlight
- B: SvelteKitでDocumentation UIを独自実装する
- C: Astro CoreでDocumentation UIを独自実装する

### Recommendation

Aを推奨する。

StarlightはDocumentationに必要なNavigation、Search、i18n、SEO、Code Highlighting、Dark Modeを標準で備える。BlackOps独自UIが必要になった場合もSvelteを含むUI Componentを統合でき、Documentation基盤を自作する必要がない。

[ANSWER]

A

[/ANSWER]

## Question 6: Documentation Source of Truth

WebsiteとRepository Markdownの内容をどう管理するか。

### Options

- A: 現在の `docs/guide/` と `docs/internal/` を正本とし、`docs-website/` のBuildが読み込むまたは検証付きで同期する
- B: Documentationの正本を `docs-website/src/content/docs/` へ移す
- C: Repository MarkdownとWebsite Contentを別々に編集する

### Recommendation

Aを推奨する。

AGENTSと現行Workflowが使うMarkdown導線を維持し、Web UIの導入で二重管理にしない。StarlightのFrontmatterに必要なMetadataは、Source Markdown側へ追加するかBuild時に決定的に補う。

[ANSWER]

A
Documentationを次のDirectoryへ集約する。

```text
docs/guide
docs/internal
docs/website
```

`docs/guide` と `docs/internal` をMarkdownの正本とし、`docs/website` のBuildが利用する。

[/ANSWER]

## Question 7: Repository and Publication Boundary

Skeleton、Installer、Documentation Websiteをどこで開発／公開するか。

### Options

- A: まず現Repository内の `app-skeleton/`、`installer/`、`docs-website/` で開発し、公開時にPackage／Website単位へSplitまたは独立配布する
- B: 最初からFramework、Skeleton、Installer、Documentationを別Repositoryにする
- C: すべてFramework Package一つの中へ配布する

### Recommendation

Aを推奨する。

Application LayoutとPublic Composition APIが安定するまではAtomic Commitと一つのCIを維持する。Composer公開時には `blackops/skeleton` と `blackops/installer` を独立Packageとして参照できる配布境界を用意する。

[ANSWER]

A
Websiteは静的BuildしてCloudflare PagesへDeployする。

[/ANSWER]

## Question 8: Project CLI Entrypoint

Install後Applicationが使うLaravel `artisan` 相当のCommand Entrypointをどこに置くか。

### Options

- A: SkeletonがProject所有の `bin/blackops` を持ち、FrameworkとApplicationのCommandを登録する
- B: `blackops/framework` が `vendor/bin/blackops` を提供し、すべてのProjectが同じBinaryを直接実行する
- C: Symfony慣習の `bin/console` を使い、BlackOps専用名のEntrypointは作らない

### Recommendation

Aを推奨する。

Project所有のEntrypointなら、ApplicationのDB Connection、Provider、Environment、追加Commandを構成できる。Frameworkは `make:operation`、`make:migration`、Migration／Worker／Build／Retention Commandの実装を提供し、Skeletonの `bin/blackops` がApplicationのComposition Rootとして登録する。

[ANSWER]

B
laravelもこの方式じゃないっけ？

[/ANSWER]

### Follow-up 8-1: Laravel Artisanとの対応

Laravelの公式Documentationでは、ArtisanはApplication Rootにある `artisan` Scriptであり、`php artisan` として実行する。Laravel Application SkeletonのRepository Rootにも `artisan` Fileが含まれる。

```text
laravel application/artisan       Project所有
laravel application/vendor/bin/*  Composer Dependencyが提供するBinary
```

`./vendor/bin/sail artisan` はSail PackageのContainer Wrapper経由でArtisanを呼ぶ例であり、Artisan本体が `vendor/bin` にあるわけではない。

BlackOpsでLaravel Artisanと同じ所有境界にする場合、Skeletonが `bin/blackops` を持ち、Framework PackageがCommand ClassとRegistration APIを提供するQuestion 8 Option Aとなる。

`bin/blackops` はCommand実装のCopyではなく、Project所有の薄いBootstrap Entrypointとする。

```php
#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

exit(App\BlackOps\ConsoleKernel::boot()->run());
```

上記は概念例であり、実際のPublic Bootstrap APIはPhase 7で確定する。重要な所有境界は次の通り。

```text
Project bin/blackops
  - vendor/autoload.phpの読み込み
  - Application固有のEnvironment／DB／Provider構成
  - Framework Console Kernelの起動

blackops/framework
  - make:operation / make:migrationのCommand実装
  - Migration / Worker / Build / Retention / Scheduler Command
  - Generator StubとCommand Registration API
```

Framework PackageをUpdateした後は、次回の `bin/blackops` 起動で更新済みのFramework Command実装を読み込む。Project側のEntrypointが古いCommand実装を保持することはない。

EntrypointのPublic Bootstrap Contract自体を変更する場合は通常のBackward Compatibility／Upgrade Guideの対象とする。Skeletonの新バージョンは新規Projectの初期Fileを改善するが、既存ProjectのCommand実装更新はFramework Package Updateで反映される。

#### Question

Laravel Artisanと同じく、Command実装はFramework Updateで更新しつつ、薄いBootstrapだけをProjectが所有するOption Aへ変更するか。

#### Options

- A: `bin/blackops` をProjectが所有する
- B: Laravelとは異なることを理解したうえで、Frameworkの `vendor/bin/blackops` を使う

#### Recommendation

Aを推奨する。ApplicationのDB Connection、Provider、Environment、Custom CommandをProject側で構成でき、Laravel Artisanと同じ所有境界になる。

[ANSWER]

A

Projectは薄い `bin/blackops` Bootstrapだけを所有し、Command実装とGenerator StubはFramework Packageから読み込む。Framework Update後は、既存Projectも更新済みのCommand実装を利用する。

[/ANSWER]

## Proposed Milestones

### Phase 7: Installed Application Example and Skeleton Layout

- Public Composition APIのConsumer視点Audit
- Install後と同じApplication Directory Layout
- Inline／Deferred／Worker／Migration／RetentionのConsumer E2E
- `examples/quickstart/` をExampleとSkeletonの共通Source of Truthとする

### Phase 8: Composer Project Bootstrap

- `blackops/skeleton` Composer Project
- `composer create-project blackops/skeleton my-app`
- Project Name／Namespace／Environmentの初期化
- Install後Smoke Test

### Phase 9: Project BlackOps CLI

- Project所有のBlackOps Console Entrypoint
- `make:operation` と関連Operation Feature Files生成
- `make:migration` とVersion Class生成
- Migration／Worker／Build／Retention／Scheduler CommandのApplication構成

### Phase 10: Documentation Website

- `docs/website/` Astro Starlight Project
- `docs/internals/` から `docs/internal/` へ移行
- Repository MarkdownのSingle Source Build
- Search／Navigation／Version表示／Mobile／Accessibility
- Static Build／Cloudflare Pages Preview／Production Deploy

## References

- Composer CLI `create-project`: https://getcomposer.org/doc/03-cli.md#create-project
- Composer scripts: https://getcomposer.org/doc/articles/scripts.md
- Astro Starlight: https://starlight.astro.build/
- Starlight Getting Started: https://starlight.astro.build/getting-started/
- Cloudflare Pages Astro Guide: https://developers.cloudflare.com/pages/framework-guides/deploy-an-astro-site/
- Laravel Artisan Console: https://laravel.com/docs/artisan
- Laravel Application Skeleton: https://github.com/laravel/laravel

## Decision

[DECISION]

Post-MVPのDeveloper Experienceを、次の順序で実装する。

1. Phase 7: Installed Application Example and Skeleton Layout
2. Phase 8: Composer Project Bootstrap
3. Phase 9: Project BlackOps CLI
4. Phase 10: Documentation Website

`examples/quickstart/` を、利用者向けExampleと `blackops/skeleton` の共通Source of Truthとする。Application作成の公式経路は `composer create-project blackops/skeleton my-app` とし、`blackops new` と専用Installer Packageは作らない。

Install後ApplicationはProject所有の薄い `bin/blackops` を持つ。このEntrypointはApplication固有のEnvironment、DB Connection、Provider、Custom Commandを構成し、Framework Console Kernelを起動する。`make:operation`、`make:migration`、Migration／Worker／Build／Retention／Scheduler Commandの実装とGenerator Stubは `blackops/framework` が所有する。

Documentation Websiteは `docs/website/` にAstro Starlightで構築し、静的BuildをCloudflare PagesへDeployする。Documentation Directoryは `docs/guide/`、`docs/internal/`、`docs/website/` に集約し、`docs/guide/` と `docs/internal/` のMarkdownを内容のSource of Truthとする。

Framework、Skeleton、Websiteは当面このRepositoryでAtomicに開発する。`blackops/skeleton` はComposer Project Packageとして公開できる境界を持たせ、公開時には `examples/quickstart/` から独立配布できるようにする。

[/DECISION]

## Consequences

[CONSEQUENCES]

### Benefits

- Installed Exampleを先に確定するため、Skeleton、CLI、Documentationが同じApplication Layoutを説明できる。
- Composer標準のProject作成経路だけを保守すればよく、Global InstallerやPHARのRelease Cycleを持たない。
- Project固有のCompositionを維持しながら、Framework UpdateでCommand実装とGenerator Stubを更新できる。
- Guide／Internal Markdownを二重管理せず、Repository閲覧とWebsite公開の両方に利用できる。
- SkeletonとFrameworkを同じRepositoryで変更し、Consumer E2Eで互換性を検証できる。

### Constraints

- `examples/quickstart/` はExampleであると同時に配布物になるため、Repository内部専用のPathやDependencyを含められない。
- `bin/blackops` のPublic Bootstrap Contractを変更する場合は、既存Project向けのBackward CompatibilityまたはUpgrade Guideが必要になる。
- `docs/internals/` から `docs/internal/` への移行では、AGENTS、リンク、CI、Task Packetを同じ変更単位で更新する必要がある。
- Cloudflare Pages向けWebsite Buildは、Source Markdownから決定的に生成できなければならない。

### Follow-up Work

- Phase 7の最初に、Framework Repository外のConsumerとして必要なPublic Composition APIをAuditする。
- `examples/quickstart/` の配布対象File、Package Metadata、Local Monorepo Test方法を確定する。
- Project Console Kernel、Command Registration API、Generator StubのCompatibility方針をPhase 9 Taskで仕様化する。
- StarlightのContent Integration方式とCloudflare PagesのPreview／Production PipelineをPhase 10 Taskで仕様化する。

[/CONSEQUENCES]
