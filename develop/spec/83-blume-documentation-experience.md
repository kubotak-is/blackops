# Blume Documentation Experience

Navigation、Sidebar Label、Version BannerはD117／Specification 84が置き換える。Landing Hero、CTA、Feature Layout、Hero説明保持はD118／Specification 85が置き換える。Operation／Journal／Headless本文とDelivery境界は維持する。

## Scope

BlackOpsの公開Documentation WebsiteをBlumeで構築し、現在のOperation Frameworkとしての価値、Experimental Version Policy、利用者の学習順を一貫して伝える。

## Preserved Delivery Contract

- 公開本文のSource of Truthは`docs/guide/`だけとする
- `docs/internal/`と`develop/`をPage、Navigation、Search Index、Artifactへ含めない
- 現行Public SlugとStatic Redirectを維持する
- 同一入力から決定的なContentとManifestを生成する
- Static Build出力は`docs/website/dist/`とする
- Node.js／pnpm／BlumeをRepository MetadataとLockfileで固定する
- GitHub Actionsは検証済みArtifactを既存のCredential Gate経由でCloudflare Pagesへ渡す
- External Publicationは明示的な後続Taskまで実行しない

## Blume Runtime

- Astro Starlight Integrationを削除し、Blume CLIとConfigurationへ置き換える
- `dev`、`check`、`build`のRepository Script名を維持する
- `content:generate`と`content:check`の利用者向け入口を維持する
- Blume標準のSearch、Sidebar、Table of Contents、Syntax Highlight、Light／Dark Theme、Mobile Navigation、Skip Linkを利用する
- Mermaidは外部CDNへ依存せず、Light／DarkとAccessible Descriptionを維持する
- Custom CSSとCustom Pageは公開APIを優先し、生成DOMへの脆いPatchを避ける

## Landing

H1は`BlackOps - The PHP Framework`とする。Primary CTAはInstall、Secondary CTAはWhats BlackOpsとする。

Hero説明は次の完全一致を要求する。

```text
BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。
```

Hero直後に次の三要素を表示する。

1. Operation
   - `#[Route]`で同期API、`#[Deferred]`で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。
2. Journal
   - 受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。
3. Headless
   - BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。
   - フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。

三要素は同一のDOM StructureとColumn規則を使い、Desktopでは一つの三列Grid、Mobileでは一列のReading Orderへ変換する。要素を別Gridへ分断したり、固定Marginで一部だけOffsetしたりしない。Card装飾より見出し、本文、Linkの可読性を優先する。

LandingへUser未指定のConcept Block、英語Marketing Copy、Section Numberを追加しない。

CTA、Feature Link、Theme Toggle、Search、Mobile NavigationはKeyboardで操作できる。Light／Darkの双方でText、Border、Code AccentのContrastを保ち、`prefers-reduced-motion`を尊重する。

## Version Notice

全PageでDismiss不可の目立つBannerを表示し、少なくとも次を伝える。

- Document Channelは`main`
- Latest Stableは`1.1.0`
- BlackOps 1.xはExperimental
- 1.x Minor間のBackward CompatibilityとProduction Readinessを保証しない
- Production Readyは2.xから予定

Current Status PageはBannerと同じPolicyを説明し、将来計画を既に提供済みの保証として表現しない。

## Navigation

Sidebarは次のSection、Label、順序を正とする。

| Section | Items |
| --- | --- |
| Introduction | Whats BlackOps |
| Getting Started | Install、Quickstart and Skeleton、Directory、Local Runtime |
| Operation | Value and Validation、Outcome、Deferred、Lifecycle |
| Execution and Workers | ConsoleCommand、Outbox |
| Database | Transaction、Migration、Seeder |
| Auth | Authentication、Authorization |
| Frontend | Section landing |
| Testing | Section landing |
| Tutorial | BlackOps Board Reference Application（`testing/community-board`） |
| Deployment | Section landing |
| Security | Section landing |
| Troubleshooting | Section landing |
| Releases | Current Status |
| Reference | Core API、Attributes、Configuration、BlackOps CLI、Observer Replay、Application Bootstrap、Glossary |

Sidebarにない既存PageもPublic ContentとしてBuild、Search、Link Validationの対象にする。Sidebar全配置を要求していた旧Validationは、全公開PageがSidebarまたは明示的な関連Page Linkから到達できる検証へ変更する。

## New Reader Pages

- ConsoleCommand: OperationをBlackOps CLIのConsole入口へ接続する責務とHTTP／Deferredとの差
- Outbox: root Transaction内の`Operations::dispatch()`、Relay、at-least-once、重複耐性の入口
- Authentication: FrameworkのOpt-in Session CoreとApplication-owned Identity境界
- Authorization: `#[Authorize]`、Actor Context、Deferred再認可、Resource所有権の境界
- Frontend: Generated JavaScript／TypeScript Client、Same-origin BFF、選択可能なFrontend Frameworkの境界

各Pageは実装済みPublic APIだけを説明し、Framework固有UI、Next.js／Nuxt Adapter、Exactly Once、外部Message Broker等を提供済みと主張しない。

## Reader Task Structure

Sidebarから到達する各PageはNavigation LabelとH1の意味を一致させ、少なくとも次を読者の作業順で説明する。

- このPageで何ができるか
- 実行前提
- 実行するCommandまたは記述するCode
- 編集するApplication-owned File
- 成功時に生成または観測できる結果
- 失敗時に最初に確認する箇所
- 関連する次のPage

Authenticationは`make:auth`、直接Dependency、生成File、Environment、Migration、Build、Register／Login／Logout、Bearer／Cookie選択を一つのPageで開始できるようにする。Authorizationは`#[Authorize]`、Policy実装、Service Binding、Inline／Deferred再認可、Status参照認可の最小例を持つ。

FrontendはBlackOpsがHeadlessでUIを提供しない代わりにJavaScript／TypeScript Client Codeを生成することを冒頭で説明する。`build:compile`、`frontend:generate`、`frontend:check`の順序、`config/frontend.php`の`output`変更、既定`resources/js/blackops/`、Application-owned Wrapper、Base URL／Credential注入、再生成条件を一つのPageで完走できるようにする。

## Verification

- Blume PackageだけがDocumentation Theme Runtimeとして残り、Starlight依存と設定がない
- Content生成、Manifest、内部Link、Diagram、Artifact Guard、Searchが成功する
- LandingがOperation、Journal、Headless、二つのCTAを持つ
- Landing Heroと三要素の本文が指定文言へ完全一致する
- `ONE MODEL / TWO PATHS`等の未指定Copyが存在しない
- 三要素が同一Grid内でDesktop三列／Mobile一列になる
- Landingの三要素と`docs/guide/README.md`の意味が一致する
- 全PageのBannerが1.x Experimentalと2.x Production Ready予定を表示する
- SidebarのSection／Item Label／順序が完全一致する
- 新しいConsoleCommand、Outbox、Authentication、Authorization、Frontend PageがBuildとSearchへ含まれる
- Sidebar対象PageのH1とContent StructureがNavigation LabelとReader Taskへ一致する
- 既存Public URLとRedirectが維持される
- `docs/internal/`、`develop/`、Credential、Repository Absolute PathがArtifactへ含まれない
- Desktop／390 px Mobile、Light／Dark、Keyboard、Reduced MotionのReader Experienceを確認する
- GitHub ActionsのDocumentation Buildが既存`dist/` Artifact Contractを維持する

## Traceability

- Decision: [D116 Blume Documentation Site](../decisions/116-blume-documentation-site.md)
- Delivery Contract: [Documentation Website Delivery Contract](57-documentation-website-delivery-contract.md)
- Reader Experience: [Documentation Reader Experience](59-documentation-reader-experience.md)
- Experimental Release: [Experimental Release Contract](61-experimental-release-contract.md)
