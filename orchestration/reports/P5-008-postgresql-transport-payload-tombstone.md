# P5-008: PostgreSQL Transport Payload Tombstone

Status: Completed

## Summary

P5-008は完了。Retention Planに基づき、PostgreSQL Operations TableのTransport Payloadを安全にTombstone化するServiceを追加した。

ServiceはPlan内の `transport_payload` 候補だけを処理し、実行時にもTerminal State、未Tombstone、Active Holdなしを再確認する。成功したTombstoneだけPayloadなしのPurge Auditへ記録する。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlTransportPayloadTombstoneService.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditIdGenerator.php`
- `src/Transport/PostgreSql/SymfonyRetentionPurgeAuditIdGenerator.php`
- `tests/Transport/PostgreSql/PostgreSqlTransportPayloadTombstoneServiceTest.php`
- `docs/internals/retention-plan.md`
- `docs/internals/retention-policy.md`
- `orchestration/tasks/P5-008-postgresql-transport-payload-tombstone.md`
- `orchestration/reports/P5-008-postgresql-transport-payload-tombstone.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Tombstone ServiceはOperations行を削除しない。
- Tombstoneは `encoded_payload` と `encoded_context` をNULL化し、`payload_purged_at` と `updated_at` を実行時刻へ更新する。
- ServiceはPlan内の `RetentionTarget::TransportPayload` だけを処理し、他Targetは無視する。
- Terminal State、未Tombstone、Active HoldなしはDB UPDATE条件で実行時にも再確認する。
- Plan生成後にHold設定、先行Tombstone、State変更が起きた場合は安全側にスキップする。
- 成功したTombstoneごとに `RetentionPurgeAuditRecord` を作成し、`RetentionPurgeAuditPort` へ記録する。
- Audit ID生成はTransport配下の小さなPortに分離し、Symfony UID実装をデフォルトにした。
- System Log配送は後続のPurge Service / Scheduler接続で扱う。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlTransportPayloadTombstoneServiceTest
Result: OK (1 test, 18 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (427 tests, 1292 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 942 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Plan内のTransport Payload候補をTombstone化できる
- [x] Plan内の非Transport Payload候補は無視される
- [x] Terminal State、未Tombstone、Active Holdなしを実行時にも再確認する
- [x] 成功したTombstoneについてPurge Auditが記録される
- [x] PayloadやContextはAuditへ保存されない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Dead Letter削除、Canonical Journal削除、Outcome削除は未実装。後続Taskで扱う。
- System Log配送は未接続。後続Taskで扱う。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-009としてDead Letter削除Serviceを実装する。
