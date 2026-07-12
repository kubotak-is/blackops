# P6-014: Retention Audit System Log

Status: Completed

## Goal

Retention Purge AuditをDatabase StoreとPSR-3 System Logの両方へPayloadなしで配送し、System Log失敗時にPurgeとDatabase Auditをrollbackするfail-closed境界を完成する。

## In Scope

- Primary `RetentionPurgeAuditPort` とPSR-3 Loggerを組み合わせる内部Decorator
- Payloadなしの構造化Retention Audit Log Context
- Audit ID／Operation ID／Target／Affected Count／Policy／Purge Time／ActorのJSONL出力
- Logger例外のfail-closed伝播
- System Log失敗時のJournal Purge／Database Audit rollback Integration Test
- Monolog JSONL Backendとの実出力Test
- Retention Guide／Internals／TODO／Decision Index／Task Report／STATE更新

## Out of Scope

- Transactional Outbox／Log Relay
- Remote Log Backend／CloudWatch／OpenTelemetry
- Best-effort Retention Audit Log Mode
- Retention Policy／Planner／Delete SQLの変更
- Phase 6全体Closeout

## Relevant Specifications and Decisions

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`
- `develop/decisions/062-retention-audit-log-delivery.md`

## Files Allowed to Change

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

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- Decoratorはprimary Database Audit Portを先に呼び、成功後にSystem Logへ配送する
- Logger例外は捕捉して成功扱いにせず、Purge Transactionへ伝播する
- Log ContextにPayload／Context／Journal本文／Outcome／Error本文／Credentialを含めない
- Timestampはマイクロ秒付きUTC RFC 3339とする
- Existing Monolog JSONL Factoryを再利用し、別のLogger Backendを作らない
- Public APIを追加しない

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

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'LoggingRetentionPurgeAudit|JournalRetention'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-014-retention-audit-system-log.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
