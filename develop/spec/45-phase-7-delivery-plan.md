# Phase 7 Delivery Plan

## Goal

Phase 7は、Framework Repository内のMVP Test Fixtureを、公開APIだけを利用するFeature-first Installed Application Exampleへ置き換える。

Phase完了時の `examples/quickstart/` は、Phase 8で `blackops/skeleton` として配布できるSource Boundaryを持つ。ただしPackagist公開と `composer create-project` のRemote InstallはPhase 8で行う。

## Task Sequence

### P7-002: Public Application Bootstrap Foundation

- Application Builder
- Base Path、Environment、Configの検証
- Operation／Service ProviderとApplication Command登録
- 再読込しないConfiguration Snapshot

HTTP／Console RuntimeはこのTaskへ含めない。

### P7-003: Public HTTP Runtime Composition

- `Application::http()`
- Compile済みArtifact Load
- Inline／Deferred RouteのPSR-15 Composition
- Public ConfigからPostgreSQL、Journal、PSR-17 Dependencyを構成
- Internal Runtime型をApplication Bootstrapから隠す

P7-002受入後にTask Packetを確定する。

### P7-004: Public Console Kernel Composition

- `Application::console()` とPublic `ConsoleKernel`
- Build、Migration、Worker、Retention、Scheduler Command登録
- Application Command追加
- Project所有の薄い `bin/blackops`

Generator CommandはPhase 9へ残す。

P7-003受入後にTask Packetを確定する。

### P7-005: Feature-first Quickstart Application

- `examples/quickstart/` の独立Composer Project Boundary
- `bootstrap/app.php`、`public/index.php`、`bin/blackops`
- Welcome Inline Feature
- Report Deferred FeatureとRetry例
- Config、Environment Example、Build／Log Directory
- Application Code／BootstrapからInternal Importを排除

Phase 7ではLocal Path Repository等を使い、Remote Packagist公開を前提にしない。

### P7-006: Local Runtime and Consumer End-to-End

- Quickstart所有のDockerfile／FrankenPHP Dockerfile／Compose
- PHP 8.5、FrankenPHP、PostgreSQL Health Check
- Explicit Build／Migration／Worker実行
- Inline 200、Deferred 202、Retry、Outcome、Sensitive Projection、Retention Dry Run
- Root Dev Autoloadへ依存しないConsumer Boundary検証

Default Compose起動でWorker、Scheduler、Migration、Purgeを自動実行しない。

### P7-007: Phase 7 Closeout

- Installed Treeと実Fileの一致確認
- Public API Architecture Guard
- Guide／Internals／Quickstart README
- Full Quality SuiteとConsumer E2E
- Phase 8へ渡すPackage Source Boundaryの確認

## Dependency Order

```text
P7-002 Bootstrap Foundation
  -> P7-003 HTTP Composition
    -> P7-004 Console Composition
      -> P7-005 Quickstart Application
        -> P7-006 Local Runtime and Consumer E2E
          -> P7-007 Phase Closeout
```

一つのTaskがAcceptedになる前に、後続Taskの変更可能FileとConstructor Signatureを最終確定しない。後続Taskは本仕様のGoalとScopeを維持しつつ、Accepted APIへ合わせてPacketを作成する。

## Phase Acceptance Criteria

- [ ] Quickstartが独立Composer Projectとして成立する
- [ ] Application CodeとBootstrapに `BlackOps\Internal` Importがない
- [ ] Feature-firstのWelcome／ReportをDirectory単位で削除できる
- [ ] Public BuilderからHTTPとConsoleを同じConfiguration Snapshotで構成できる
- [ ] Project所有の `bin/blackops` がFramework所有Command実装を起動する
- [ ] BuildとMigrationが明示Commandであり、HTTP／Worker起動時に暗黙実行されない
- [ ] Local RuntimeでInline／Deferred／Worker／Retry／Outcome／Retentionを検証できる
- [ ] Root Dev Autoloadへ依存しないConsumer E2Eが成功する
- [ ] Full PHPUnit、Mago、Deptrac、Public API Guardが成功する

## Traceability

- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Boundary: [Installed Application Boundary](42-installed-application-boundary.md)
- Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- API: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
