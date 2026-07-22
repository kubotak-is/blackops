# BlackOps — The PHP Framework

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。一つの型付きOperationをInline HTTPとDurable Deferredの両方へ接続し、受理から完了までのLifecycleをJournalへ残します。

<div class="landing-feature-grid">
  <a class="landing-feature-link" href="/operations/authoring/"><code>#[Route]</code><strong>Operationが中心</strong><span><code>#[Route]</code>で同期API、<code>#[ExecuteWith(Deferred)]</code>で非同期化。HTTPもコンソールコマンドもJobも、すべてはOperation。</span></a>
  <a class="landing-feature-link" href="/concepts/lifecycle/"><code>Journal</code><strong>Journalですべてを可視化</strong><span>受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。</span></a>
  <a class="landing-feature-link" href="/execution/http-and-deferred/"><code>Deferred</code><strong>非同期処理を標準装備</strong><span>リトライ／バックオフ／重複防止／Dead Letter／型付きOutcome保存をPostgreSQLで標準提供。</span></a>
</div>

## 最短で試す

[Quickstart](mvp-sample.md)は空DirectoryからComposer Install、Inline Request、Deferred受付、Worker実行までを一Pageで案内します。初めて使う場合は[Installation](installation.md)で前提を確認してから進んでください。

## 目的からExampleを選ぶ

- [Quickstart](mvp-sample.md)は、Typed Operation、HTTP 202、Worker、Journal、Status／Outcome、Generated Operation ObjectというFramework Contractを最短距離で確認します。
- [BlackOps Board](community-board.md)は、Application-owned Authentication、Domain／Infrastructure、SvelteKit Same-origin BFF、Inline Post／Comment、Deferred Digest、Accessible Browser UIまでを一続きで確認します。

まずFrameworkの形を覚える場合はQuickstart、実Applicationの責任分界をBrowserから追う場合はBlackOps Boardを選んでください。

## 読み進め方

1. [Installation](installation.md) — Stableと`main`の前提を確認してProjectを作る
2. [Quickstart](mvp-sample.md) — InstallからInline／Deferred／Workerまで動かす
3. [Tutorial](first-operation.md) — Generatorから自分のOperationを実装する
4. [Directory Structure](directory-structure.md) — Applicationが所有する構成をつかむ
5. [Local Runtime](runtime-bootstrap.md) — Default Worker ModeとClassic Fallbackを運用する

初期DataやDemo Fixtureが必要なApplicationでは、[Database Seeding](database-seeding.md)でRoot Seeder、子Seeder、Migration／Build／Seedの順序を確認してください。

設計から理解する場合は[Why BlackOps](why-blackops.md)、[Core Concepts](core-concepts.md)、[Operation Lifecycle](operation-lifecycle.md)の順に進みます。必要なPageだけを探す場合は、左SidebarまたはSearchから[Operation Authoring](operations.md)、[HTTP、Inline、Deferred](execution.md)、[Testing](testing.md)、[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)へ進めます。

ApplicationのAuthentication実装を薄くする場合は、Framework同梱のOpt-in Session Coreを[Application Bootstrap](application-bootstrap.md#session-authenticationをopt-in登録する)で登録し、[Security](security.md#http-authenticationの境界)でToken LifecycleとApplication責務の分界を確認できます。

Install直後のApplicationへUser／Password／Register／Login／Logoutを追加する場合は、[Session Authentication Starter](security.md#session-authentication-starter)の`make:auth`手順を使います。

## Document Channel

このWebsiteは`main` Branchの最新Documentです。Latest Stableは`1.1.0`です。`main`にはPHP Operationから生成するFrontend Operation ObjectとLocal Full-stack Reference Applicationもありますが、Stable `1.1.0`には含まれません。BlackOpsはExperimentalであり、1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。各Page上部のVersion Noticeと[Current Status](mvp-status.md)を確認してください。
