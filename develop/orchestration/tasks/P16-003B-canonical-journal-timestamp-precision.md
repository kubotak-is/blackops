# P16-003B: Canonical Journal Timestamp Precision

Status: Ready

## Goal

PostgreSQL Canonical Journal Dataに埋め込む時刻を、既存のUTC RFC 3339マイクロ秒形式へ統一する。

Retry ScheduledのOperations Row `available_at`はマイクロ秒を保持する一方、Journal Data `scheduled_at`が`DATE_ATOM` Encodeで小数秒を失い、正規LifecycleをStatus QueryがIntegrity Failureにする不整合を修正する。同じCodec境界のDead Letter `moved_at`も同時に補正する。

## In Scope

- `AttemptRetryScheduledData.scheduledAt`のCanonical UTCマイクロ秒Encode
- `OperationDeadLetteredData.movedAt`のCanonical UTCマイクロ秒Encode
- 既存`TimeCodec`の再利用
- Canonical Journal Record JSONとPostgreSQL Storeのbytes／round-trip回帰
- 非UTC Offset入力のUTC正規化回帰
- 既存の秒精度Journal Data Decode互換性維持
- Retry ScheduledのOperations Row／Journal厳密時刻一致とStatus Foundの実Database回帰
- Dead Letter Row／Journal時刻精度の回帰
- Internal Documentation、Report、STATE同期

## Out of Scope

- Database Schema、Migration、Column Precision、Index変更
- Status Queryの厳密時刻照合を緩めること
- Journal Record Schema Version変更
- `TimeCodec` Public Contract変更
- 過去に保存済みCanonical Journalの書換えMigration
- Public Status／Diagnostics DTO、HTTP Response、Generated Client変更
- P16-007のQuickstart、Guide、Website、Skeleton統合

## Relevant Specifications and Decisions

- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`
- `develop/orchestration/reports/P16-003-postgresql-status-projection.md`
- `develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`

## Files Allowed to Change

### Production

- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`
- `src/Transport/PostgreSql/PostgreSqlFailureJournalDataCodec.php`

### Tests

- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`（回帰確認が必要な場合だけ）
- `tests/Transport/PostgreSql/PostgreSqlDeadLetterStoreTest.php`（実在し必要な場合だけ）

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `docs/internal/durable-journal.md`（実在する場合だけ）
- `develop/spec/24-lifecycle-event-data.md`（既存時刻形式の明確化だけ）
- `develop/spec/35-postgresql-transport-schema.md`（既存時刻形式の明確化だけ）
- `develop/STATE.md`
- New `develop/orchestration/reports/P16-003B-canonical-journal-timestamp-precision.md`

上記以外の変更が必要な場合は実装を広げずReportへ記録する。P16-007の未Commit差分は保持するが、このTaskでは変更しない。

## Canonical Timestamp Contract

新しくEncodeするJournal Data時刻は`TimeCodec::format()`を使い、必ず次の形式にする。

```text
YYYY-MM-DDTHH:MM:SS.uuuuuuZ
```

- UTCへ正規化する
- マイクロ秒6桁を常に保持する
- `scheduled_at`と`moved_at`で同じ形式を使う
- `DATE_ATOM`や秒精度FormatへFallbackしない

Decodeは既存Canonical Journalとの互換性を維持する。新しいUTCマイクロ秒形式を復元でき、過去の`DATE_ATOM`秒精度／Offset付き値も引き続き復元できる。新規Encode形式を理由にJournal Record Schema Versionを上げない。

## Integrity Contract

Status SourceのRetry時刻照合は緩めない。

```text
Operations Row available_at U.u === Journal scheduled_at U.u
```

異なる時刻は引き続きIntegrity Failureにする。Codecが同一のRuntime時刻を両Storeへ損失なく保存することで正規JournalをFoundへ戻す。

## Regression Contract

- `2026-07-19T14:22:56.143069Z`をEncode／Store／Readして`143069`を保持する
- `+09:00`等の入力を同一InstantのUTCマイクロ秒へEncodeする
- Retry Scheduled JSONの`scheduled_at`がCanonical形式と完全一致する
- Dead Letter JSONの`moved_at`がCanonical形式と完全一致する
- Legacy秒精度`2026-07-19T14:22:56+00:00`をDecodeできる
- PostgreSQL Operations RowとCanonical Journalが同じ非ゼロマイクロ秒Retry時刻を保持し、Status Queryが`retry_scheduled` Foundを返す
- 故意に時刻を変えた既存Integrity Failureを弱めない

## Acceptance Criteria

- [ ] Retry ScheduledのJournal DataがUTCマイクロ秒を損失なくEncodeする
- [ ] Dead LetteredのJournal Dataも同じ形式でEncodeする
- [ ] Legacy秒精度Journal Dataを引き続きDecodeできる
- [ ] Canonical JSON／Store Round-tripが非ゼロマイクロ秒を維持する
- [ ] 実PostgreSQL Status Queryが非ゼロマイクロ秒RetryをFoundへ投影する
- [ ] 不一致時刻のIntegrity Failureを維持する
- [ ] Public Contract／Schema／Migrationに変更がない
- [ ] Target／Full PHPUnit、Mago、Deptrac、Guardが成功する
- [ ] P16-007途中差分を変更していない
- [ ] WorkerはCommitしていない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php \
  tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
git diff --check
```
