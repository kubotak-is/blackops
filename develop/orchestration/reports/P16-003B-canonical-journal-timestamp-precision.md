# P16-003B Canonical Journal Timestamp Precision Report

Status: Accepted

## Summary

PostgreSQL Canonical Journal DataのRetry `scheduled_at`とDead Letter `moved_at`を、既存`TimeCodec::format()`によるUTC RFC 3339 6桁マイクロ秒形式へ統一した。

`2026-07-19T23:22:56.143069+09:00`を`2026-07-19T14:22:56.143069Z`としてCanonical JSON／PostgreSQL bytesへ保存し、Store Round-trip後もマイクロ秒を維持する。既存の`DATE_ATOM`秒精度／Offset付きJournal Dataは、従来どおり`DateTimeImmutable`でDecodeできる。

実PostgreSQL回帰では、Operations Row `available_at`とJournal `scheduled_at`が同じ非ゼロマイクロ秒を保持するRetry ScheduledをPublic Status `Found`へ投影した。1マイクロ秒だけ異なる場合は引き続き`status_query.integrity_failed`となる。Dead LetterもRowとJournalの`moved_at`を非ゼロマイクロ秒で厳密照合した。

## Changed Files

- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`
- `src/Transport/PostgreSql/PostgreSqlFailureJournalDataCodec.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`
- `docs/internal/status-query.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-003B-canonical-journal-timestamp-precision.md`

P16-007の途中差分は保持し、このTaskでは変更していない。

## Decisions and Assumptions

- 新規Encodeだけを既存`TimeCodec::format()`へ揃え、`TimeCodec`のPublic Contractは変更しない。
- Decodeは既存実装の`DateTimeImmutable`を維持する。これにより新形式とLegacy `2026-07-19T14:22:56+00:00`の両方を復元できる。
- Status Sourceの`U.u`厳密比較は変更しない。正常な二重保存で精度を失わないことによってIntegrity Failureを解消する。
- Record Schema Version、Database Schema、Migration、Public Status DTO／HTTP Contractは変更しない。
- Dead LetterのRow／Journal時刻照合はInternal Diagnosticsの既存Integrity境界で実Database回帰する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Initial Result: 2 files required formatting。
Action: docker compose run --rm app mago format src tests
Final Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php \
  tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php
Result: OK (45 tests, 204 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1430 tests, 5679 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2530 / Warnings 0 / Errors 0。

Management ID Guard
Result: src/tests PHP Comment／DocBlock違反なし。

git diff --check
Result: 成功。
```

## Acceptance Criteria

- [x] Retry Scheduled Journal DataをUTCマイクロ秒で損失なくEncodeする
- [x] Dead Lettered Journal Dataを同じ形式でEncodeする
- [x] Legacy秒精度／Offset付きJournal DataをDecodeできる
- [x] Canonical JSON／PostgreSQL Store Round-tripで非ゼロマイクロ秒を維持する
- [x] 非UTC Offset入力を同じInstantのUTCへ正規化する
- [x] 実PostgreSQL Status Queryが非ゼロマイクロ秒Retryを`retry_scheduled` Foundへ投影する
- [x] Retry時刻が1マイクロ秒異なる場合のIntegrity Failureを維持する
- [x] Dead Letter Row／Journalの非ゼロマイクロ秒を厳密照合する
- [x] Public Contract／Schema／Migrationを変更していない
- [x] Target／Full PHPUnit、Mago、Deptrac、Guardが成功する
- [x] P16-007途中差分を変更していない
- [x] WorkerはCommitしていない

## Remaining Issues

P16-003Bの範囲にRemaining Issueはない。

既に秒精度で保存された過去のCanonical JournalはDecode互換を維持するが、書換えMigrationはTask Scope外である。

## Suggested Next Action

OrchestratorがP16-003Bの差分をReviewし、P16-007差分と分離したCommitとして受理する。その後P16-007を再開し、Real HTTP Journey、Consumer Documentation、全品質Gateを完走する。

## Orchestrator Review

Retry `scheduled_at`とDead Letter `moved_at`の新規Encodeだけが既存`TimeCodec::format()`へ移行し、UTC正規化と6桁マイクロ秒を保持することを確認した。Decodeは既存`DateTimeImmutable`経路を維持し、Legacy秒精度／Offset付きJournalとの互換性を保つ。Statusの`U.u`厳密照合、Public Contract、Schema、Migrationは変更していない。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、Target 45 tests／204 assertions、Deptrac 0違反／0警告／0エラー、Management ID Guard、`git diff --check`が成功した。Worker実行のFull 1430 tests／5679 assertionsも成功しており、範囲逸脱と仕様矛盾はないためAcceptedとした。
