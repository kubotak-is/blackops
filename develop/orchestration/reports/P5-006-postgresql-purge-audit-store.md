# P5-006: PostgreSQL Purge Audit Store

Status: Completed

## Summary

P5-006は完了。Retention Purge AuditをPostgreSQLへPayloadなしで保存するTableとStoreを追加した。

`retention_purge_audits` TableはAudit ID、Operation ID、Target、影響件数、Policy、実行時刻、実行Actorだけを保存する。Operation IDはOperations Tableへ `ON DELETE RESTRICT` で参照する。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditStore.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditStoreTest.php`
- `docs/internal/retention-purge-audit.md`
- `docs/internal/retention-policy.md`
- `develop/orchestration/tasks/P5-006-postgresql-purge-audit-store.md`
- `develop/orchestration/reports/P5-006-postgresql-purge-audit-store.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Table名は `retention_purge_audits` とした。
- TableへPayload、Context、Error本文は保存しない。
- `operation_id` はOperations Tableへ `ON DELETE RESTRICT` で参照する。Cascade Deleteは使わない。
- `target` はContractのWire Valueをそのまま保存する。
- `affected_count` はDB Constraintでも1以上に制限する。
- `policy` と `purged_by` は空文字をDB Constraintでも拒否する。
- Storeは `RetentionPurgeAuditPort::record()` だけを実装し、読み取りAPIは追加しない。
- System Log配送は後続のPurge Service側へ分離した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlRetentionPurgeAuditStoreTest
Result: OK (4 tests, 13 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (419 tests, 1248 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 903 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `retention_purge_audits` TableがPayloadを含まない
- [x] Audit RecordのID、Operation ID、Target、件数、Policy、実行時刻、実行Actorを保存できる
- [x] Operation IDはOperationsへ`ON DELETE RESTRICT`で参照する
- [x] PostgreSQL Storeが `RetentionPurgeAuditPort` を実装する
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Tombstone実行Service、Purge Plan、CLI、Scheduler Workerは未実装。後続Taskで扱う。
- System Log配送はPurge Service側で扱う。

## Suggested Next Action

P5-007としてRetention Purge Plan / Tombstone実行Serviceの前段Contractを実装する。
