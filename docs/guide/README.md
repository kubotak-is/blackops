# BlackOps

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTPとPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Typed Outcome、Retention、Project CLIを提供します。

初めて読む場合は、[Why BlackOps](why-blackops.md)で採用理由とHeadlessの意味を確認し、[Core Concepts](core-concepts.md)でOperationを中心とした概念の関係を把握してからGetting Startedへ進んでください。

## 最短で動かす

公開済みStable SkeletonをComposerで作成し、Feature-firstのApplicationを起動できます。

1. [Why BlackOps](why-blackops.md) — 解決する課題と設計原則を理解する
2. [Core Concepts](core-concepts.md) — Operation、Value、Outcome、Journalの関係を把握する
3. [インストール](installation.md) — Stable `1.0.0`からProjectを作る
4. [Directory Structure](directory-structure.md) — FeatureとProcess Boundaryを把握する
5. [最初のOperation](first-operation.md) — Typed Self-handledのValueとOutcomeを読む
6. [Local Runtime](runtime-bootstrap.md) — Build、Migration、HTTPを明示的に起動する

完成済みのInline／Deferred Exampleを先に試す場合は[Quickstart](mvp-sample.md)へ進んでください。

## 目的から探す

- [Operations](operations.md): Operation、Value、Outcome、Generator、Lifecycle
- [Execution](execution.md): HTTP、Inline、Deferred、Worker
- [Database](database-migrations.md): Migration、Outcome Retrieval、Retention
- [Reference](configuration.md): Configuration、Application Bootstrap、Project CLI、現行Status
- [Glossary](glossary.md): Attempt、Claim、Fencing、Journal等のBlackOps用語

## Document Channel

このWebsiteは`main` Branchの最新Documentです。最新Stableは`1.0.0`であり、`main`の説明には次のStable Releaseへ向けた未Release機能が含まれる場合があります。各Page上部のVersion Noticeと[Current Status](mvp-status.md)を確認してください。
