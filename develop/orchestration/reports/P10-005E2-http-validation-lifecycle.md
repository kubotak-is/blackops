# P10-005E2: HTTP Validation Lifecycle Report

Status: Accepted

## Summary

Protocol Error、Binding Failure、Value Validation FailureをHTTP AdapterとLifecycle Journalへ接続した。壊れたJSON／JSON Object以外はOperation IDなし400、必須Field欠落／Native型不一致はOperation ID付き422とSequence 1の`operation.rejected`、Value Rule違反は再現可能な`operation.received`から`operation.rejected`へ遷移する。

Inline／DeferredのどちらもValidationをHandler実行／Deferred永続化前に完了する。BlackOps Public AttributeをSymfony Validator 7.4 ConstraintへInternalで変換し、BlackOps Violation、HTTP、Journalの安定ContractへSymfony型やMessageを露出しない。

## Protocol / Binding / Value Matrix

| Failure | HTTP | Operation ID | Canonical Journal | Execution |
| --- | --- | --- | --- | --- |
| 壊れたJSON | 400 | なし | なし | Handler／Deferred受付なし |
| JSON Object以外 | 400 | なし | なし | Handler／Deferred受付なし |
| 必須Field欠落 | 422 | あり | Sequence 1 `operation.rejected` | Handler／Deferred受付なし |
| Native型不一致 | 422 | あり | Sequence 1 `operation.rejected` | Handler／Deferred受付なし |
| Value Rule違反 | 422 | あり | `operation.received`、`operation.rejected` | Handler／Deferred受付なし |

## HTTP Evidence

- Protocol Errorは`status: error`と安定Codeだけを返し、`operationId`を含めない
- 422は`operationId`、Category `validation`、Code `validation.failed`、Field／Rule／CodeだけのViolation Listを返す
- JSON BodyはObjectとArrayをobject decodeで区別する
- BindingはString、Integer、Booleanを暗黙変換せず、FloatだけInteger／Floatを受け付ける
- Existing manual `OperationRejectedException::validation()`は従来のCategory／CodeだけのResponse Shapeを維持し、`violations`やRaw Valueを追加しない

## Journal Evidence

- Binding Failureは具象OperationValueを偽造せず、Sequence 1のRejected RecordをContext／Metadataから生成する
- Value Validation FailureはCanonical Receivedへ実OperationValueを保持し、Sequence 2のRejectedへ進む
- Initial StateからRejectedへのTerminal Transitionを追加した
- Validation ViolationをPostgreSQL Rejected Data CodecへOptional Fieldとして追加し、旧Payload Shapeの`violations`なしRecordを空ListとしてMigrationなしでDecodeする
- Deferred Integration TestでValidation Failure時の`operations`行が0であることを確認した

## Sensitive Boundary

Canonical `OperationReceivedData`は再現可能性のため実Valueを保持するRestricted Dataである。TestではValidation FailureのCanonical ReceivedにSensitive Tokenが保持され、同じRecordのObserved ProjectionからSensitive Propertyが除外されることを分離して確認した。HTTP Response、Binding Exception、Violation、`OperationRejectedData`、Observed Rejection DetailはCategory、Code、Field、Ruleだけを持ち、Raw Value、Symfony Message、Constraint設定を複製しない。

## Changed Files

- Dependency／Quality: `composer.json`、`composer.lock`、`mago.toml`、`deptrac.yaml`
- Rejection／Execution: `src/Core/Rejection/RejectionReason.php`、`src/Execution/ValidationRejectionRecorder.php`、`src/Internal/Execution/InlineDispatcher.php`
- HTTP: `src/Http/Binding/*`、`src/Http/OperationRequestHandler.php`、`src/Http/Responder/JsonOperationResponder.php`
- Validation Backend: `src/Internal/Validation/OperationValueRuleEvaluator.php`、`src/Internal/Validation/SymfonyOperationValueConstraintFactory.php`
- Journal／Projection: `src/Internal/Journal/*`、`src/Internal/Projection/ObservedJournalRecordProjector.php`、`src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`、`src/Transport/PostgreSql/PostgreSqlRejectionJournalDataCodec.php`
- Runtime Composition: `src/Internal/Runtime/ProductionRuntimeComposer.php`
- Quickstart: `examples/quickstart/app/Feature/Report/GenerateReport/GenerateReportValue.php`、`tests/Consumer/quickstart-e2e.sh`
- Tests: `tests/Core/Rejection/*`、`tests/Http/*`、`tests/Integration/MvpSampleEndToEndTest.php`、`tests/Internal/Journal/*`、`tests/Internal/Projection/*`、`tests/Internal/Validation/*`、`tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- Documentation／Orchestration: `docs/internal/operation-value-validation.md`、Task Packet、本Report、`develop/STATE.md`

## Decisions and Assumptions

- D087どおりBinding FailureはReceivedなしのRejected単独とした
- D088どおりBlackOps Attributeを維持し、Symfony ValidatorをInternal Backendとして使用した
- D089どおりCanonical Receivedの実ValueとObserved／Error SurfaceのSensitive境界を分離した
- Nested Object／Collection Binding、Custom Callback、Boolean／Integer文字列Parserは追加していない

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: ともにINFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (861 tests, 2767 assertions).

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1706 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed. Symfony Validator install、422、Received→Rejected、Deferred State未作成、Observed Sensitive非露出を確認。
```

## Acceptance Criteria

- [x] 壊れたJSONとJSON Object以外が400でJournalを作らない
- [x] Field欠落／型不一致がOperation ID付き422とRejected Journalになる
- [x] Value Rule違反が全Violationを返す422とRejected Journalになる
- [x] Inline／Deferredの両方でHandlerを実行しない
- [x] Deferred Validation Failureが202を返さず、Transport Stateを作らない
- [x] Response／Rejected Data／Observed Journal／ExceptionにRaw Sensitive Valueがない
- [x] Value Validation FailureのCanonical Receivedが実Valueを保持する
- [x] Existing manual `OperationRejectedException::validation()`互換を維持する
- [x] PostgreSQL CodecをMigrationなしでBackward Compatibleに拡張した

## Remaining Issues

- 既知のBlockerはない
- Canonical JournalのField-level暗号化はD089どおり独立したSecurity Taskで扱う

## Suggested Next Action

P10-005FでFrankenPHP Worker Modeを明示Opt-inとして実装し、Request間のState Safetyを検証する。

## Orchestrator Review

2026-07-14T02:23:32+09:00に差分、Public／Internal境界、D087／D088／D089との整合、Sensitive非露出、Codec後方互換をReviewした。Composer Validation、Mago Format／Lint／Analyze、全861 PHPUnit、Deptrac、Quickstart Consumer E2E、PHP管理番号Guard、`git diff --check`を独立に再実行し、すべて成功したためAcceptedとする。
