# P6-012: MVP Sample End-to-End

Status: Completed

## Goal

MVPで約束したInline／DeferredのSample Operationを実際のPostgreSQLとCompile済みRuntime Artifactで一連実行し、HTTP Response、Lifecycle Journal、Worker再起動境界、Retry、Typed Outcome、Sensitive Projectionを一つの再現可能なE2E SampleとTestで証明する。

## In Scope

- `GET /welcome` -> `ShowWelcome` Inline -> `WelcomeShown` -> HTTP 200 Sample
- `POST /reports` -> `GenerateReport` Deferred -> HTTP 202 + Operation ID Sample
- HTTP受付後に別のWorker compositionを作り、PostgreSQLの未処理Operationを実行するE2E
- GenerateReport Handlerの初回失敗、`attempt.failed`、最低一回のRetry、後続成功
- `ReportGenerated` Typed OutcomeをOperation IDで取得するE2E
- InlineとDeferredのLifecycle JournalをOperation IDごとに検証
- SampleのSensitive値がObserved Projection／JSONLへ露出しないことの検証
- Sample Operation／Handler／RouteのManifestとDI Container Compile、Production Artifact Loadの検証
- Sampleの実行方法とMVP到達点のGuide
- Task ReportとSTATE更新

## Out of Scope

- Deferred Outcome取得HTTP Endpointの新設
- 認証／認可
- Browser UI／HTML Frontend
- SQS／Kafka／SQLite Adapter
- OpenTelemetry／CloudWatch
- Production Process Supervisor設定
- Framework Public APIの新規設計
- Retention機能の追加
- Phase 6全体CloseoutとTODO全件の整理

## Relevant Specifications and Decisions

- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/28-mvp-lifecycle-events.md`
- `develop/spec/34-mvp-database-transport.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/017-mvp-scope.md`
- `develop/decisions/047-frontend-integration.md`
- `develop/decisions/055-inline-dispatcher-api.md`
- `develop/decisions/059-worker-heartbeat-runtime.md`

## Files Allowed to Change

- `examples/mvp/**`
- `tests/Integration/MvpSample*Test.php`
- `tests/Fixtures/Mvp/**`
- `docs/guide/mvp-sample.md`
- `docs/guide/README.md`
- `docs/internal/mvp-e2e.md`
- `docs/internal/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-012-mvp-sample-e2e.md`
- `develop/orchestration/reports/P6-012-mvp-sample-e2e.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／Test／SampleのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- SampleはRoot Repository内のDocker Composeと依存だけで再現でき、外部Serviceを要求しない
- PostgreSQL SchemaはTestごとに独立させる
- Schema準備はVersioned Migrationを使い、HTTP／Worker startupの暗黙DDLに戻さない
- DeferredはHTTP受付のDB Commit後に別のWorker／Connection compositionから実行する
- Retryは既存Supervision境界を使い、Test専用の近道でStateを書き換えない
- Sensitive値はCanonical再現性とObserver安全化を混同せず、Observed／Logging出力で検証する
- RuntimeはCompile済みManifest／Containerをloadし、Production実行時にDiscoveryしない

## Acceptance Criteria

- [x] SampleがInline `GET /welcome`にHTTP 200とTyped `WelcomeShown` JSONを返す
- [x] Inline Operation IDのJournalがreceived／attempt started／attempt succeeded／completedの順に永続化される
- [x] SampleがDeferred `POST /reports`にHTTP 202とOperation IDを返す
- [x] HTTP compositionとは別のWorker compositionが未処理OperationをPostgreSQLから実行できる
- [x] 初回Handler失敗が`attempt.failed`とRetry Scheduledとして記録され、少なくとも一回Retryして成功する
- [x] Deferred Operation IDでTyped `ReportGenerated` Outcomeを取得できる
- [x] Deferred Journalが受付からRetry後のCompletedまでOperation IDで追跡できる
- [x] SampleのSensitive値がObserved Projection／JSONLに平文で出力されない
- [x] Sample Operation／HTTP ManifestとDI Containerをcompileし、同じBuild IDでProduction Loaderが受け入れる
- [x] Sample E2EがPHP 8.5／PostgreSQLのDocker Compose上で再現可能に成功する
- [x] Sampleの実行方法と証明範囲がGuide／Internalsに記録される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-012-mvp-sample-e2e.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
