# D116: Blume Documentation Site

Status: Decided

Superseded for Navigation and Version Banner by D117／Specification 84. Landing Hero、CTA、Feature LayoutはD118／Specification 85が置き換える。Operation／Journal／Headless本文は維持する。

## Context

BlackOpsの公開Documentation WebsiteはAstro Starlightで構築されている。利用者から、Documentation専用ThemeであるBlumeへ移行し、Landingの訴求、Experimental Version Notice、Sidebarの情報構造を現在のFramework像へ合わせる要望があった。

既存の`docs/guide/`単一正本、目的別Public URL、Static Artifact、Cloudflare Pages Direct Upload、Credential分離は実績のあるDelivery Contractであり、Theme移行を理由に変更しない。

## Decision

[DECISION]

1. `docs/website/`のDocumentation RuntimeをAstro StarlightからBlumeへ移行する。
2. `docs/guide/`を公開本文の唯一の編集元とし、決定的Content生成、内部文書除外、既存Public URL、`dist/` Artifact、Cloudflare Pages Direct Uploadを維持する。
3. Blumeの標準Header、Search、Sidebar、Table of Contents、Mobile Navigation、Theme、Accessibilityを使用し、LandingだけをBlumeのCustom Pageとして構成する。
4. LandingはUserが指定したOperation、Journal、Headlessの見出しと本文を改変せずに表示する。未指定のConcept Block、英語Marketing Copy、Section Numberを追加しない。
5. Landingの公開Claimは`docs/guide/README.md`にも同じ意味で保持し、Website固有PresentationとのDriftをTestで拒否する。
6. 全Pageで、BlackOps 1.xはExperimentalでBackward CompatibilityとProduction Readinessを保証せず、Production Readyは2.xから予定していることを目立つBannerで示す。
7. Sidebar LabelはUser指定を正とし、`Whats BlackOps`、`Quickstart and Skeleton`、`Deferred`、`Execution and Workers`として公開する。
8. Sidebarから外れる既存Guide Pageは削除せず、既存URL、内部Link、Search Indexを維持する。主要導線は新Sidebarへ集約する。
9. ConsoleCommand、Outbox、Authentication、Authorization、Frontendは独立した利用者向けPageを追加する。既存Guideから内容を無断複製せず、責務別の入口として既存詳細Pageへ接続する。
10. External Publication、Cloudflare Project設定、Custom Domain、Production Deployは本変更に含めない。Local BuildとDormant Delivery Workflowの互換性までを検証する。

[/DECISION]

## Navigation

```text
Introduction
  Whats BlackOps
Getting Started
  Install
  Quickstart and Skeleton
  Directory
  Local Runtime
Operation
  Value and Validation
  Outcome
  Deferred
  Lifecycle
Execution and Workers
  ConsoleCommand
  Outbox
Database
  Transaction
  Migration
  Seeder
Auth
  Authentication
  Authorization
Frontend
Testing
Tutorial
  BlackOps Board Reference Application
Deployment
Security
Troubleshooting
Releases
Reference
  Core API
  Attributes
  Configuration
  BlackOps CLI
  Observer Replay
  Application Bootstrap
  Glossary
```

## Landing Content

Landing Heroの説明は次を正とする。

> BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。

### Operation

> `#[Route]`で同期API、`#[Deferred]`で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。

### Journal

> 受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。

### Headless

> BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。
> フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。

この説明はBlackOpsが各Frontend Framework固有AdapterやUI Componentを提供する意味ではない。生成ClientとHTTP Contractを接続点とする。

Landingへ`ONE MODEL / TWO PATHS`、`Make the work explicit.`、`Nothing stays in the dark.`、`Bring your frontend.`等の未指定Copyを追加しない。

## Consequences

[CONSEQUENCES]

- D081とSpecification 57のAstro Starlight固有記述は本Decisionが置き換える。Markdown、URL、Artifact、Deliveryの境界は維持する。
- D090とSpecification 59のStarlight維持、旧Sidebar、旧三要素に関する記述は本Decisionが置き換える。
- Blume Packageと間接RuntimeはLockfileで固定し、Updateは明示的なDependency変更として扱う。
- BlumeのCustom Page APIはPackage Versionへ依存するため、Landing Buildと主要Accessible MarkupをPermanent Testで固定する。
- 既存公開URLを移動する必要がある場合だけStatic Redirectを追加し、Theme移行だけを理由にURLを変更しない。

[/CONSEQUENCES]

## References

- [D081 Documentation Website Delivery Contract](081-documentation-website-delivery-contract.md)
- [D090 Documentation Information Architecture](090-documentation-information-architecture.md)
- [Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)
- [Documentation Reader Experience](../spec/59-documentation-reader-experience.md)
- [Blume](https://useblume.dev/)
- [Blume Quickstart](https://useblume.dev/docs/quickstart)
- [Blume Navigation](https://useblume.dev/docs/content/navigation)
- [Blume Custom Pages](https://useblume.dev/docs/advanced/custom-pages)
