# P5-009: PostgreSQL Dead Letter Retention Delete

Status: Completed

## Summary

P5-009は完了。Retention Planに基づき、PostgreSQL Dead Letter Recordを安全に削除するServiceを追加した。

ServiceはPlan内の `dead_letter` 候補だけを処理し、実行時にもActive Holdなしを再確認する。成功した削除だけPayloadなしのPurge Auditへ記録し、Operations行は削除しない。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlDeadLetterRetentionDeleteService.php`
- `tests/Transport/PostgreSql/PostgreSqlDeadLetterRetentionDeleteServiceTest.php`
- `docs/internal/retention-plan.md`
- `docs/internal/retention-policy.md`
- `develop/orchestration/tasks/P5-009-postgresql-dead-letter-retention-delete.md`
- `develop/orchestration/reports/P5-009-postgresql-dead-letter-retention-delete.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Dead Letter Retention Delete ServiceはOperations行を削除しない。
- ServiceはPlan内の `RetentionTarget::DeadLetter` だけを処理し、他Targetは無視する。
- Active HoldなしはDB DELETE条件で実行時にも再確認する。
- Plan生成後にHold設定や先行削除が起きた場合は安全側にスキップする。
- 成功した削除ごとに `RetentionPurgeAuditRecord` を作成し、`RetentionPurgeAuditPort` へ記録する。
- 削除対象自身のJournalへPurge Eventは追加しない。
- System Log配送は後続のPurge Service / Scheduler接続で扱う。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeadLetterRetentionDeleteServiceTest
Result: OK (1 test, 12 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (428 tests, 1304 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 953 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Plan内のDead Letter候補を削除できる
- [x] Plan内の非Dead Letter候補は無視される
- [x] Active Holdなしを実行時にも再確認する
- [x] 成功した削除についてPurge Auditが記録される
- [x] Operations行は削除されない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Canonical Journal削除、Outcome削除は未実装。後続Taskで扱う。
- System Log配送は未接続。後続Taskで扱う。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-010としてRetention Purge Service Facadeを追加し、Planner、Transport Payload Tombstone、Dead Letter Deleteをまとめる。
