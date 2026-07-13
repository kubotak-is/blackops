# P5-007: Retention Plan Contract and PostgreSQL Planner

Status: Completed

## Summary

P5-007は完了。Retention PurgeのDry Runと実行Serviceが共有するPlan Contractを追加し、PostgreSQLから削除候補を副作用なしで抽出するPlannerを実装した。

PlanはPayloadを含まず、Operation ID、Target、基準時刻、期限時刻だけを保持する。PostgreSQL PlannerはTransport Payload Tombstone候補とDead Letter候補を抽出し、Active Hold中のOperationを除外する。

## Changed Files

- `src/Core/Retention/RetentionPlanItem.php`
- `src/Core/Retention/RetentionPlan.php`
- `src/Core/Retention/RetentionPlanner.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPlanner.php`
- `tests/Core/Retention/RetentionPlanTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPlannerTest.php`
- `docs/internal/retention-plan.md`
- `docs/internal/retention-policy.md`
- `docs/internal/README.md`
- `develop/orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`
- `develop/orchestration/reports/P5-007-retention-plan-contract-and-postgresql-planner.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `RetentionPlanItem` は `OperationId`、`RetentionTarget`、`basis_at`、`eligible_at` を持つ。
- `basis_at` は対象ごとの保持期限計算に使う基準時刻とした。
- `eligible_at` は `basis_at + retention period` とし、`eligible_at <= now` の候補だけをPlannerが返す。
- `RetentionPlan` はPlan Itemの不変一覧、`RetentionPlanner` はPolicyと現在時刻からPlanを生成するPortとした。
- PostgreSQL Plannerは副作用を持たず、更新・削除・Tombstone化・Audit記録を行わない。
- Active Hold中のOperationはTargetに関係なく除外する。
- 現在のPostgreSQL物理Schemaに合わせ、Planner実装はTransport Payload Tombstone候補とDead Letter候補を返す。
- Dead Lettered operationはTransport Payloadも保持している場合、Transport Payload候補とDead Letter候補の両方になり得る。
- Canonical JournalとOutcomeの物理削除は、削除順序とStorage境界を後続Taskで固定してからPlannerへ接続する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'RetentionPlanTest|PostgreSqlRetentionPlannerTest'
Result: OK (7 tests, 26 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (426 tests, 1274 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 926 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Retention Plan ContractがPublic APIとして定義される
- [x] Plan ItemがOperation ID、Target、基準時刻、期限時刻を表現できる
- [x] PostgreSQL PlannerがTransport Payload Tombstone候補を抽出できる
- [x] PostgreSQL PlannerがDead Letter候補を抽出できる
- [x] Hold中Operationが候補から除外される
- [x] PlannerはDry Run用途として副作用を持たない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Tombstone実行Serviceと実削除Serviceは未実装。後続Taskで扱う。
- Purge Audit書き込みとSystem Log配送は未接続。後続Taskで扱う。
- Canonical JournalとOutcomeの物理削除Planner接続は未実装。後続Taskで削除順序とStorage境界を固定する。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-008としてTransport Payload Tombstone実行Serviceを実装する。
