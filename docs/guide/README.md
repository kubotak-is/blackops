# BlackOps Documentation

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。一つの型付きOperationをInline HTTPとDurable Deferredの両方へ接続し、受理から完了までのLifecycleをJournalへ残します。

<div class="landing-feature-grid">
  <a class="landing-feature-link" href="/execution/http-and-deferred/"><strong>一つのOperation、二つの実行経路</strong><span>同じ型付きUse CaseをInline HTTPとDeferred Workerへ接続します。</span></a>
  <a class="landing-feature-link" href="/operations/generators/"><strong>型付きOperationを生成</strong><span>Project CLIからOperation、Value、OutcomeのBuild可能な骨格を作ります。</span></a>
  <a class="landing-feature-link" href="/concepts/why-blackops/"><strong>No operation stays in the dark</strong><span>受理したOperationのAttempt、拒否、完了をJournalで追跡します。</span></a>
  <a class="landing-feature-link" href="/operations/lifecycle/"><strong>Durable Deferred Execution</strong><span>PostgreSQLのClaim、Lease、Retry、Outcomeで長い処理を安全に進めます。</span></a>
</div>

## 最短で試す

[Quickstart](mvp-sample.md)は空DirectoryからComposer Install、Inline Request、Deferred受付、Worker実行までを一Pageで案内します。先に別のInstallation Pageを終える必要はありません。

## 読み進め方

1. [Why BlackOps](why-blackops.md) — 解決する分断と設計原則を理解する
2. [Core Concepts](core-concepts.md) — Operation、Value、Outcome、Journalの関係をつかむ
3. [Quickstart](mvp-sample.md) — InstallからInline／Deferred／Workerまで動かす
4. [チュートリアル: Operationを作る](first-operation.md) — Generatorから自分のOperationを実装する
5. [Validation](validation.md) — Binding、Value、Business rejectionの境界を選ぶ
6. [Local Runtime](runtime-bootstrap.md) — Default Worker ModeとClassic Fallbackを運用する

必要なPageだけを探す場合は、左SidebarまたはSearchから[Operation Authoring](operations.md)、[HTTP、Inline、Deferred](execution.md)、[Configuration](configuration.md)、[Troubleshooting](troubleshooting.md)へ進めます。

## Document Channel

このWebsiteは`main` Branchの最新Documentです。最新Stableは`1.0.0`であり、`main`の説明には次のStable Releaseへ向けた未Release機能が含まれる場合があります。各Page上部のVersion Noticeと[Current Status](mvp-status.md)を確認してください。
