# P10-005E2: HTTP Validation Lifecycle

Status: Blocked by D087

## Goal

Protocol Error、Binding Failure、Value Validation FailureをHTTP／Lifecycle境界へ接続し、Inline／DeferredのHandler実行前に安全な400またはOperation ID付き422を返す。

## In Scope

- 壊れたJSON／JSON Object以外のProtocol Error 400
- 必須Field欠落／型不一致のBinding Violation
- OperationValue Validator Invocation
- Operation ID付き`OperationRejected` Lifecycle
- Category `validation`、Code `validation.failed`、Field Violationを持つ422 JSON
- Inline／Deferred Handler非実行
- Deferred受付前Validation
- Rejection／Journal Codec互換性
- Quickstart Validation Fixture／E2E
- Internal Documentation、Report、STATE

## Out of Scope

- Validation Attribute追加
- Nested Object／Collection Binding
- Custom Validation Callback
- Website Content
- FrankenPHP Worker Mode

## Relevant Specifications and Decisions

- `develop/decisions/086-operation-value-validation-runtime.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/05-http.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`

## Files Allowed to Change

- `src/Core/Rejection/**`
- `src/Core/OperationResult.php`
- `src/Execution/**`
- `src/Http/**`
- `src/Internal/Execution/**`
- `src/Internal/Validation/**`
- `src/Journal/**`
- `src/Transport/PostgreSql/**`
- `tests/Core/**`
- `tests/Execution/**`
- `tests/Http/**`
- `tests/Internal/**`
- `tests/Integration/**`
- `tests/Transport/PostgreSql/**`
- `examples/quickstart/app/**`
- `tests/Consumer/quickstart-e2e.sh`
- `docs/internal/**`
- `develop/orchestration/reports/P10-005E2-http-validation-lifecycle.md`
- `develop/STATE.md`

## Constraints

- 原則GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答`Y`により、本TaskでModel／Profile Metadataを確認できない現在利用可能なWorkerを使う例外を承認済み
- Protocol ErrorはOperation ID／Lifecycle Journalを作らない
- Binding／Value ViolationはOperation IDを発行してRejected Journalを残す
- Raw／Sensitive ValueをResponse、Exception、Journalへ含めない
- Validation FailureではInline Handler、Deferred保存／受付、Worker Handlerを実行しない
- Existing Rejection Category／CodeのBackward Compatibilityを維持する

## Acceptance Criteria

- [ ] 壊れたJSONとJSON Object以外が400でJournalを作らない
- [ ] Field欠落／型不一致がOperation ID付き422とRejected Journalになる
- [ ] Value Rule違反が全Violationを返す422とRejected Journalになる
- [ ] Inline／Deferredの両方でHandlerを実行しない
- [ ] Deferred Validation Failureが202を返さない
- [ ] Response／Journal／ExceptionにRaw Sensitive Valueがない
- [ ] Existing manual `OperationRejectedException::validation()`が動作する
- [ ] PostgreSQL Codec／MigrationなしBackward Compatibilityを検証する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005E2-http-validation-lifecycle.md`へSummary、Protocol／Binding／Value Matrix、HTTP Evidence、Journal Evidence、Sensitive Boundary、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
