# P5-005: Purge Audit Contract

Status: Completed

## Summary

P5-005は完了。Retention Purge結果をPayloadなしで記録するPublic Contractを追加した。

専用のPurge Audit ID、対象種別、Policy Reference、Audit Record、保存Portを定義し、IdentifierFactoryからPurge Audit IDを生成できるようにした。System Log連携とStorage実装は後続Taskへ分離した。

## Changed Files

- `src/Core/Identifier/RetentionPurgeAuditId.php`
- `src/Core/Retention/RetentionPurgeTarget.php`
- `src/Core/Retention/RetentionPolicyRef.php`
- `src/Core/Retention/RetentionPurgeAuditRecord.php`
- `src/Core/Retention/RetentionPurgeAuditPort.php`
- `src/Internal/Identifier/IdentifierFactory.php`
- `tests/Core/Identifier/IdentifierTest.php`
- `tests/Internal/Identifier/IdentifierFactoryTest.php`
- `tests/Core/Retention/RetentionPurgeAuditTest.php`
- `docs/internals/retention-purge-audit.md`
- `docs/internals/README.md`
- `orchestration/tasks/P5-005-purge-audit-contract.md`
- `orchestration/reports/P5-005-purge-audit-contract.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- `RetentionPurgeAuditId` は専用UUIDv7 Value Objectとして追加した。
- Audit Recordは `OperationId` を必須にし、Operation単位Purgeの監査に絞った。
- `RetentionPurgeTarget` は `transport_payload` / `journal` / `outcome` / `dead_letter` の安定したWire Valueを持つ。
- `RetentionPolicyRef` は空白をtrimし、空文字を拒否する非空文字列Referenceとした。
- `RetentionPurgeAuditRecord` はPayloadを含めず、対象Operation、対象種別、影響件数、Policy、実行時刻、実行Actorだけを保持する。
- `affected_count` は1以上とし、0件のDry RunやPlan結果はこのRecordでは表現しない。
- `purged_at` は読み出し時にUTCへ正規化する。
- 保存Portは `record(RetentionPurgeAuditRecord $record): void` の最小構成にした。
- System Log連携はPurge Service側でAudit StoreとLoggerへ別配送する後続Taskへ分離した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'RetentionPurgeAuditTest|IdentifierTest|IdentifierFactoryTest'
Result: OK (70 tests, 161 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (415 tests, 1235 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 896 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Purge Audit RecordのPublic API方針が確定している
- [x] Payloadを含まないAudit Recordが表現される
- [x] 対象Operation ID、対象種別、件数、Policy、実行時刻、実行Actorを表現できる
- [x] 保存Portが定義される
- [x] 必須Commandがすべて成功している

## Remaining Issues

- PostgreSQL Purge Audit Storeは未実装。後続Taskで扱う。
- Tombstone実行Service、Purge Plan、CLI、Scheduler Workerは未実装。後続Taskで扱う。
- System Log連携はPurge Service側で扱う。

## Suggested Next Action

P5-006としてPostgreSQL Purge Audit Storeを実装する。
