# P7-006 Local Runtime and Consumer End-to-End Report

## Summary

Public JSONL Journal ConfigurationをApplication HTTP Runtimeへ接続し、Quickstart所有のPHP 8.5 CLI、FrankenPHP 1 HTTP、PostgreSQL 18 Compose Runtimeを追加した。一時Quickstart ConsumerへFrameworkをcopy installし、Build、Migration、HTTP、Worker Retry、Outcome、Sensitive JSONL、RetentionをRoot Dev AutoloadなしでEnd-to-End検証した。

## Public Journal Configuration and Sensitive Projection Evidence

- `journal.jsonl.enabled` はBooleanで省略時false。無効時はObserverを構成しない。
- 有効時は絶対Path、存在／書込可能なParent Directory、`best_effort`／`required` Deliveryを検証する。
- JSONL Streamはappend-binaryで開き、既存内容を切り捨てない。Open failureはConfig Keyだけを含む安全なBootstrap Errorへ変換する。
- Application内部でSensitive Filter、Observed Record Projector、Binding、Aggregator、Pipelineを構成しInline Dispatcherへ渡す。
- IntegrationとConsumer E2EでSensitive raw value不在と `[masked]` 出力を確認した。

## Docker and Compose Default and Explicit Process Evidence

- CLI: `php:8.5-cli-bookworm`、Composer、PCNTL、PDO PostgreSQL、ZIP、Host UID/GID。
- HTTP: `dunglas/frankenphp:1-php8.5-bookworm`、PDO PostgreSQL、Classic `php_server` Caddyfile。
- PostgreSQL: `postgres:18`、`pg_isready` Health Check、named volume `/var/lib/postgresql`。
- Default Serviceは`postgres`と`http`だけ。`app`はtools、`worker`はworker、`scheduler`はmaintenance Profileで明示起動する。
- Image startupへInstall、Build、Migration、Worker、Scheduler、Purgeを含めない。

## Consumer Isolation Evidence

- Checked-in QuickstartをTemp Directoryへcopyし、Temp composerだけへPath Repositoryを注入した。
- `/framework` はComposer config用ContainerとComposer install専用app overrideだけへread-only mountし、`symlink=false`、version `1.0.0` でConsumer `vendor/blackops/framework` へmirror installした。
- 通常Build／Migration／HTTP／Worker／Retentionはbase Composeだけを使い、rendered runtime configに `/framework` がないことを検証した。RuntimeはTemp Consumer mountとConsumer `vendor/autoload.php` だけを使用した。
- Trapは成功／失敗ともCompose Project、Container、Volume、Local Image、Temp Directoryをcleanupした。

## Inline, Deferred, Retry, Outcome and Retention Evidence

Consumer E2EでOperation List、3 Artifact、Build時Schema不在、Status直後もSchema不在、明示Migration、Welcome 200、Report HTTP 202を機械検証した。Report JSONからstatusとUUIDv7 `operationId` をConsumer PHPで検証・抽出し、そのIDを指定してRetry Scheduled、Completed State、encoded outcome rowをQueryした。Retention Plan／Purge Dry Runも成功し、SchedulerとPurge Confirmは実行していない。

## Source Cleanliness and Cleanup Evidence

Scriptは実行前後のChecked-in Quickstart statusを比較する。最終検証でSourceにcomposer.lock、vendor、Path Repository、Build Artifact、JSONL Logは残らず、Consumer Project／Volume／Image／Tempもcleanupされた。

## Changed Files

- Application: JSONL Config validation、Observation factory、HTTP composition接続
- Quickstart: journal config、Dockerfiles、Caddyfile、Compose、environment、README
- Tests: Application config、HTTP sensitive integration、Quickstart architecture、Consumer E2E shell
- Docs／管理: Guide、Internals、TODO、Task、Report、STATE

## Decisions and Assumptions

- PostgreSQL 18のversion-specific data layoutに従いnamed volumeを `/var/lib/postgresql` へmountする。
- CLI `app` ServiceもDefault起動から除外するためtools Profileを持たせる。`docker compose run app` はProfileをDefault起動せず利用できる。
- Worker初回claimの時刻境界を安定化するためDeferred受付後に1秒待機する。
- Required guardの元Regex `/framework` は正規のPackage名 `blackops/framework` を誤検出した。Task Packetを`repositories` Key、`type:path`、`url:/framework`だけを検出するRegexへ訂正した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose -f examples/quickstart/compose.yaml config
Result: Valid. Default rendered services are http and postgres.

docker compose -f examples/quickstart/compose.yaml config --services
Result: postgres, http.

docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Application tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (43 tests, 215 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (647 tests, 2187 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 350 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1489 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed. Framework mirrored, scenario passed, cleanup completed.

Required boundary guards and git diff --check
Result: No matches／no output. composer.lock and vendor absent.
```

初回Consumer runはPostgreSQL 18 volume target、次のrunはTest SQLのoutcome column名を検出して停止した。両方ともcleanup trap完了後に仕様準拠のTest／Compose修正を行い、最終runが成功した。Mago lintは当初error-control operatorを拒否したため、局所Error Handlerで安全にOpen Errorを変換する実装へ修正し、再実行で成功した。

Orchestrator Review後、Framework mountをinstall-only overrideへ分離し、Report HTTP status／UUIDv7 IDとID指定DB Query、database status直後のSchema不在検証を追加した。Consumer E2Eは再度成功し、focused Testは `OK (43 tests, 215 assertions)`、全boundary guardと `git diff --check` も再成功した。

Orchestrator AcceptanceではComposer Validation、Compose Config／Default Service、Focused `43 tests / 215 assertions`、Full `647 tests / 2187 assertions`、Mago、Deptrac `350 files / 0 violations`、Consumer E2E、cleanup、全boundary guardを独立に再実行し、すべて成功した。

## Acceptance Criteria

Task Packetの全Acceptance Criteriaを満たした。

## Remaining Issues

- なし。
- Worker側Observed Lifecycle／Flush、Purge Confirm、Remote Packagist／Skeleton SplitはTask範囲外である。

## Suggested Next Action

P7-007でPhase 7のInstalled Quickstart／Consumer Boundaryをcloseoutし、Phase 8 Skeleton Publicationへ進む。
