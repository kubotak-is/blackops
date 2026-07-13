# P10-005F: FrankenPHP Worker Mode Report

Status: Accepted

## Summary

FrankenPHP Worker ModeをQuickstartの明示Opt-inとして導入した。`public/worker.php`はApplication、Environment、Configuration Snapshot、Compile済みManifest／Container、PSR-15 HandlerをRequest Loop前に一度構成する。Default `http` Serviceと`public/index.php`はClassic Mode Fallbackとして維持した。

Application HTTP RuntimeをRequest Lifecycle Handlerで包み、Request前のDatabase Health Check／一度だけのReconnect、Throwable時Connection Close、未完了TransactionのSuccess拒否、Operation Scope終了確認、Journal Observer Flushを一つのPSR-15境界へ集約した。

## Bootstrap Count Evidence

- Caddyの`php_server` long-form Workerへ`/app/public/worker.php`、`num 1`、`match *`を明示した
- Worker EntrypointはBootstrap／`Application::http()`を`frankenphp_handle_request()` Loopの外で一度だけ実行する
- E2Eは各Entrypoint評価で一意なBoot IDを1行だけ記録し、同一Boot IDが複数Requestを処理することを確認した
- `FRANKENPHP_MAX_REQUESTS=8`で各Bootが8 Requestを超えないこと、少なくとも1 Bootが8件へ到達し、その後に異なるBoot IDで処理を継続することを確認した

## Request Isolation

- Success、Validation Rejected、Database Throwable、Reconnect後Success、32件の連続Successを同じWorker Serviceへ送信した
- Observed JSONLの`operation.received` Operation IDがすべて一意であることを確認した
- RequestごとのSample TokenとRejected TokenがJSONL、Worker Memory Evidence、HTTP Errorへ露出しないことを確認した
- Workerが自動Resetしない`$_ENV`をProcess Bootstrap後のSnapshotへ各Callback終了時に復元し、E2EではRequest処理による差分がないことを確認した
- Rejected後とThrowable後の次Requestが正常なWelcome Responseを返した

## Flush, Reconnect, and Restart Evidence

- Welcome Response受信直後にJSONL行数が増加しており、Request終了時Flushを確認した
- PostgreSQL停止中はHealth CheckとReconnectの両方が失敗し、成功Responseではなく500 `internal_error`を返した
- 同一HTTP Workerを停止せずPostgreSQLを再起動し、次Requestが200へ復帰した
- HandlerがSuccess Responseを生成してもActive Transactionが残る場合はConnectionをcloseしてLogicExceptionを投げ、200を返さないUnit Testを追加した
- 各BootのMemory Sample増分を16 MiB以下に制限し、8 Request上限到達後のWorker Restartを確認した
- 最後にClassic `http` Serviceを起動し、Welcome 200を確認した

FrankenPHP固有挙動は公式[Worker Mode](https://frankenphp.dev/docs/worker/)と[Configuration](https://frankenphp.dev/docs/config/)を正本とした。Callback内でThrowableを処理すること、Request SuperglobalはResetされる一方`$_ENV`はResetされないこと、`max_requests`がWorker Threadを完全Restartすることへ合わせた。

## Changed Files

- Application Runtime: `src/Internal/Application/ApplicationHttpRuntimeComposer.php`、`ApplicationHttpRequestHandler.php`、`ApplicationDatabaseConnectionLifecycle.php`
- Journal Lifecycle: `src/Internal/Application/ApplicationJournalObservationFactory.php`、`ApplicationJournalObservations.php`
- Quickstart Runtime: `examples/quickstart/public/worker.php`、`Caddyfile.worker`、`compose.yaml`、`README.md`
- Tests: `tests/Internal/Application/ApplicationDatabaseConnectionLifecycleTest.php`、`ApplicationHttpRequestHandlerTest.php`、`tests/Consumer/frankenphp-worker-mode.sh`、Quickstart Architecture／Skeleton Publication Guard
- Documentation: `docs/internal/frankenphp-runtime.md`、`docs/guide/runtime-bootstrap.md`
- Orchestration: Task Packet、本Report、`develop/STATE.md`

## Decisions and Assumptions

- 実行環境からModel／Profile Metadataを確認できないWorker利用は、Phase 10対象TaskについてUser回答`Y`で承認済みの例外に従った
- Worker ModeはDefaultへ昇格せず`worker-mode` Profileへ限定した
- Classic ModeとWorker Modeを同時に検証できるよう、既定Port 8080とWorker既定Port 8081を分離した
- Connection HealthはDBALにportable ping APIがないため`SELECT 1`を使用し、失敗時にcloseして一度だけ再試行する
- Request失敗時と未完了Transaction時はConnectionを次Requestへ持ち越さない。未完了Transactionを暗黙Commitしない
- Operation Scopeは既存`ExecutionScopeProvider::run()`の`finally` Cleanupを維持し、Request境界で空を検査する
- Application固有ServiceのStateをFrameworkが推測してResetしない。GuideでRequest固有StateをService／staticへ保持しないContractを明示した
- Boot／Memory Evidence FileはConsumer E2Eが明示Environment Variableを設定した場合だけ出力する

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 最終RunはFormat済み、Lint／AnalyzeともにNo issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (869 tests, 2800 assertions).

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1712 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/frankenphp-worker-mode.sh
Result: FrankenPHP worker mode consumer E2E passed. Bootstrap reuse、Flush、Rejected／Throwable隔離、DB 500→Reconnect、32 Request、Secret／State、Memory、max_requests Restart、Classic Fallbackを検証。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Skeleton publication dry run passed. Caddyfile.workerをDistribution allowlistへ含めた。

docker compose config
Result: Success.

PHP Management ID Guard、Shell Syntax、git diff --check
Result: すべて成功。
```

## Acceptance Criteria

- [x] Bootstrap／Config評価がWorker Process起動時の一度だけである
- [x] 複数RequestでOperation ID、Context、Observer Bufferが混線しない
- [x] Success／Rejected／Throwable後の次Requestが正常に動く
- [x] JSONL Journalが各Request終了時にFlushされる
- [x] DB切断後に安全に失敗し、復旧後にReconnectする
- [x] `max_requests`到達時にWorkerがRestartする
- [x] Classic Modeが明示Fallbackとして動く
- [x] Consumer E2EがMemory GrowthとSecret／State Leakを検査する

## Remaining Issues

- Worker Modeは意図どおりOpt-inであり、Default昇格はReader Journey同期と追加のDeployment Evidence後に判断する
- Application固有のStateful Singletonを自動検出／Resetする汎用Service Contractは追加していない
- Cloudflare External Configuration待ちは本Taskの範囲外で継続する

## Suggested Next Action

P10-005DでQuickstart、Runtime Bootstrap、Validation、Worker ModeのReader Journeyを最終実装へ同期する。その後、Worker Mode Default昇格の判断とP10-006 Closeoutへ進む。

## Orchestrator Review

2026-07-14T03:01:51+09:00に差分、D058／D085、Public PSR境界、Application Request Lifecycle、Connection／Transaction Safety、Journal Flush、Opt-in／Classic分離をReviewした。Composer Validation、Mago Format／Lint／Analyze、全869 PHPUnit、Deptrac、Worker Mode Consumer E2E、既存Quickstart E2E、Skeleton Publication Dry Run、Root／Quickstart Compose Config、PHP管理番号Guard、Shell Syntax、`git diff --check`を独立に再実行し、すべて成功したためAcceptedとする。Application固有Stateful Singletonの汎用Resetは未実装であるため、Worker Modeは本TaskどおりOpt-inを維持し、Default昇格時の判断対象とする。
