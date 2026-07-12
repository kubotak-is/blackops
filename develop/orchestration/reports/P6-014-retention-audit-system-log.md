# P6-014 Completion Report

## Summary

Retention Purge Auditをprimary Database StoreとPSR-3 System Logへ順番に配送する内部Decoratorを実装した。System LogへはPayloadなしの7項目だけをUTCマイクロ秒付きで構造化出力する。

Primary失敗時はLoggerを呼ばず、Logger失敗時も例外を隠さずPurgeへ伝播するfail-closed境界とした。Journal Purge Integration Testで、Logger失敗時にJournal削除と先行保存したDatabase Auditの両方がouter DBAL TransactionによってRollbackされることを確認した。

## Changed Files

- `src/Internal/Retention/LoggingRetentionPurgeAuditPort.php`
- `tests/Internal/Retention/LoggingRetentionPurgeAuditPortTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteServiceTest.php`
- `docs/guide/retention.md`
- `docs/internals/retention-purge-audit.md`
- `docs/internals/monolog-jsonl-backend.md`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/decisions/062-retention-audit-log-delivery.md`
- `develop/orchestration/tasks/P6-014-retention-audit-system-log.md`
- `develop/orchestration/reports/P6-014-retention-audit-system-log.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Decoratorはprimary `RetentionPurgeAuditPort::record()`を先に呼び、成功後にPSR-3 `info()`を一回呼ぶ。
- Log Messageは `Retention purge audit recorded.` とした。
- Context Keyは `audit_id`、`operation_id`、`target`、`affected_count`、`policy`、`purged_at`、`purged_by` の7つだけとした。
- `purged_at` はUTC RFC 3339のマイクロ秒付き文字列とした。
- Decoratorはprimary／Logger例外をcatchまたはwrapしない。既存Journal Purge ServiceはAdapter例外へ変換するが、Logger例外をpreviousとして保持してRollbackを発生させる。
- Monolog Backendは既存 `MonologJsonlLoggerFactory` を再利用し、`info` Levelを受け入れる構成を要求する。
- DatabaseとSystem Logは分散Transactionではない。Log成功後のDatabase Commit失敗では過剰Logが残り得るが、Audit IDで照合する。ログなし削除は許容しない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'LoggingRetentionPurgeAudit|JournalRetention'
Result: OK (9 tests, 40 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (586 tests, 1899 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 318 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1307 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Decoratorがprimary Database Audit PortとPSR-3 Loggerの両方へ一回ずつ配送する
- [x] JSONLがAudit ID／Operation ID／Target／Affected Count／Policy／Purge Time／Actorを構造化Contextとして持つ
- [x] JSONLがOperation Payload／Journal本文／Outcome／Error本文を含まない
- [x] primary Audit失敗時はLoggerを呼ばず失敗を伝播する
- [x] Logger失敗時は失敗をPurge Serviceへ伝播する
- [x] Logger失敗時にJournal削除とDatabase Audit保存がrollbackされる
- [x] Monolog JSONL Backendで一行JSONのSystem Logを生成できる
- [x] Guide／Internalsがfail-closed構成と再実行境界を説明する
- [x] 必須Commandがすべて成功する

## Remaining Issues

- DatabaseとSystem Log間にTransactional Outboxはなく、Database Commit失敗時の過剰Logは起こり得る。決定済みのMVP境界である。
- Remote Log Backend、Log Relay、Best-effort Audit ModeはTask範囲外である。

## Suggested Next Action

Orchestrator Codexがprimary先行順、safe Context、例外伝播、outer Transaction rollback、JSONL実出力、DocumentationをReviewし、受入後にTask単位でCommitする。
