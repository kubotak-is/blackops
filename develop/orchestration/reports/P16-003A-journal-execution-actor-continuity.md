# P16-003A Journal Execution Actor Continuity Report

Status: Accepted

## Summary

Status／DiagnosticsのCanonical Journal Identity Fingerprintからexecution Actorを除外した。origin Actorとauthorization Actorは全Recordで引き続き厳密に一致させる。Diagnosticsでは既存仕様に合わせ、Journal Record Schema VersionもFingerprintへ追加した。

HTTP受付、Retry後の別Worker、同一Attemptを別Workerが継続するLease Recovery相当のexecution Actor変化をUnit Testと実PostgreSQL Queryで固定した。Statusは`retry_scheduled`／`completed`をFoundへ投影し、Diagnosticsは2 AttemptとActor IDのMaskを維持する。

## Changed Files

### Production

- `src/Internal/Status/OperationStatusJournalValidator.php`
- `src/Internal/Diagnostics/OperationDiagnosticsQuery.php`

### Tests

- `tests/Internal/Status/OperationStatusJournalValidatorTest.php`
- `tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-003A-journal-execution-actor-continuity.md`

`docs/internal/operation-diagnostics.md`は存在しないため変更していない。P16-007の途中差分は変更、削除、整形していない。

## Decisions and Assumptions

- execution ActorはRecord生成主体であり、Operation Identityではない。
- HTTP受付からDeferred Worker、Retry後の別Worker、Lease Recoveryでexecution Actorが変わっても正規Journalとして扱う。
- origin Actorとauthorization Actorのnull性、ID、TypeはOperation全体で維持する。
- Record Schema Version、Operation ID／Type／Schema Version／Strategy、Correlation／Causation、Sequence、Lifecycle、Attempt ID／Number／Started At、Retry参照の既存検証は弱めない。
- Diagnostics Safe Projectionは先頭RecordのActor ContextをMaskして返す既存契約を維持し、Actor IDをPublic Statusへ追加しない。
- Public DTO、HTTP Response、Journal Schema、Database Migrationは変更していない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。初回は削減後に不要となったhalstead expect pragmaを警告し、削除後に再実行して成功した。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Status \
  tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php
Result: OK (59 tests, 274 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: 1426 tests、5656 assertions中、既知のP16-007共有差分に起因する1 Failure。P16-003A対象Testは成功した。

Failure: BlackOps\Tests\Integration\ApplicationHttpRuntimeTest::testComposesAndReusesInlineAndDeferredHttpRuntimeWithoutImplicitMigration
Expected 404, actual 200 at tests/Integration/ApplicationHttpRuntimeTest.php:130。
Cause: P16-007の未Commit差分がQuickstart ApplicationServiceProviderへSampleOperationStatusAuthorizerを登録している一方、既存TestはAuthorizer未登録のdefault-deny 404を期待している。Task Packetの指示に従いP16-007側を変更していない。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2526 / Warnings 0 / Errors 0。

Management ID Guard
Result: 成功。

git diff --check
Result: 成功。
```

## Acceptance Criteria

- [x] Status Validatorがexecution Actorの正規変化を受理する
- [x] Diagnostics Validatorが同じ正規変化を受理する
- [x] origin／authorization Actor継続性を引き続き検証する
- [x] Retry Attempt間とLease Recoveryのexecution Actor変化を回帰で固定する
- [x] PostgreSQL Status Queryが正規Deferred JournalをFoundへ投影する
- [x] PostgreSQL Diagnostics Queryが同じJournalをFoundへ投影する
- [x] Public DTO／HTTP Response／Schema／Migrationに変更がない
- [x] Target PHPUnit、Mago、Deptrac、Guardが成功した
- [ ] Full PHPUnitが成功した。P16-003A対象外のP16-007共有差分と既存Test期待値の競合1件だけが残る
- [x] P16-007の途中差分を変更していない
- [x] WorkerはCommitしていない

## Remaining Issues

P16-003AのProduction／RegressionにBlockerはない。Full PHPUnitの1 Failureは共有Working Tree上のP16-007途中差分に起因し、P16-003Aの許可範囲では修正できない。P16-007再開時にQuickstartの明示Authorizer登録とApplication Runtime Test期待値を同じTaskで同期する必要がある。

## Suggested Next Action

OrchestratorがP16-003Aの許可範囲だけをReviewし、Task単位でCommitする。その後P16-007を再開し、Quickstart Authorizerを前提とするApplication Runtime Testを同期してFull PHPUnitを再実行する。

## Orchestrator Review

Production差分がexecution ActorだけをStatus／DiagnosticsのOperation Identity Fingerprintから除外し、origin／authorization Actor、Record Schema、Operation Metadata、Sequence、Lifecycle、Attempt、Retry参照の検証を維持することを確認した。Public DTO、HTTP Response、Journal Schema、Migrationの変更はない。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、Target 59 tests／274 assertions、Deptrac 0違反／0警告／0エラー、Management ID Guard、`git diff --check`が成功した。Full PHPUnitの1 Failureは保持中のP16-007 Application-owned Authorizer登録と旧default-deny期待の一時競合だけであり、P16-003A対象回帰はすべて成功している。P16-007で同期待を同期し、Full Gateを再実行する条件でP16-003AをAcceptedとした。
