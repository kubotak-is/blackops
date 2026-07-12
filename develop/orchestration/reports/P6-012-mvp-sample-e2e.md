# P6-012 Completion Report

## Summary

MVPで約束したInline／Deferred Sampleを `examples/mvp` に追加し、Compile済みOperation Manifest、HTTP Manifest、Symfony DI Containerと実際のPostgreSQLを使う再現可能なE2E Testを実装した。

`GET /welcome` はtyped `WelcomeShown` JSONをHTTP 200で返し、Operation ID配下へ4件のInline Lifecycle Journalを永続化する。`POST /reports` はHTTP 202とOperation IDを返してcommitし、HTTPとは別Connection／ContainerのWorkerが初回失敗をRetry Scheduledへ遷移させる。さらに再起動相当の別Worker compositionが後続Attemptを成功させ、typed `ReportGenerated`をOutcome Storeから取得できる。

SampleのSensitive値はCanonical Received Recordへ再現可能な値として保持しつつ、Observed Projection／JSONLではmaskされ平文が出力されないことを同じInline実行で検証した。

## Changed Files

- `examples/mvp/src/MvpSample.php`
- `examples/mvp/operation-providers.php`
- `examples/mvp/service-providers.php`
- `examples/mvp/README.md`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/mvp-sample.md`
- `docs/guide/README.md`
- `docs/internals/mvp-e2e.md`
- `docs/internals/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-012-mvp-sample-e2e.md`
- `develop/orchestration/reports/P6-012-mvp-sample-e2e.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Task Packetの不存在参照 `develop/decisions/058-worker-heartbeat.md` は内容上対応する `develop/decisions/059-worker-heartbeat-runtime.md` へ修正して確認した。
- Schema準備には `DatabaseMigrationRunner` のVersioned Baselineを使い、Adapterのprogrammatic `migrate()`やHTTP／Worker startupの暗黙DDLは使わない。
- ProductionRuntimeComposerはInline実行境界を構成する。同じCompile済みArtifactsのRegistry／Routesへ既存 `DeferredHttpOperationAcceptor` を明示追加し、Sample専用Production APIは作らない。
- HTTP受付、初回Worker、再起動Workerはそれぞれ別DBAL Connectionを使う。各Worker loadは同じArtifactから新しいDI Container instanceを作る。
- RetryはAttempt番号を読むSample Handlerのretryable exceptionと既存Exponential Backoff Supervisionを使用する。TestからOperation Stateを直接変更しない。
- Sensitive JSONL検証は既存Observation Pipelineを持つInline DispatcherへObserverを接続して行う。Deferred RuntimeへSample専用Observer配送は追加せず、Canonical Journalを正本とする既存境界を維持する。
- Sample Handlerは短時間であるためE2E Worker Runtimeは `DirectClaimExecutionGuard` を使う。PCNTL Heartbeat／Signalの保証は既存Worker Runtime Testが担当する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
Result: OK (1 test, 34 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (573 tests, 1841 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 316 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1285 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

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

## Remaining Issues

- なし。

## Suggested Next Action

Orchestrator CodexがSample、E2E Test、Documentation、Report、必須Command結果をReviewし、受入可能ならTask単位でCommitする。
