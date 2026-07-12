# P1-015: PostgreSQL Canonical Journal Store - Implementation Report

Status: Accepted

## Summary

PostgreSQL Canonical Journal Storeを追加した。`journal` TableのDDL、JSON bytes Codec、`CanonicalJournalStore` Adapter、DB統合Testを実装し、Journal RecordをOperation ID単位でSequence順に保存・読み出しできるようにした。

## Changed Files

- `migrations/postgresql/001_create_canonical_journal.sql` (add): `blackops` Schema、`schema_migrations` Table、`journal` Table、検索Indexを追加。
- `src/Journal/Exception/JournalReadFailed.php` (add): Canonical Journal読み出し失敗用Exception。
- `src/Transport/PostgreSql/PostgreSqlCanonicalJournalStore.php` (add): PostgreSQL版Canonical Journal Store。
- `src/Transport/PostgreSql/PostgreSqlIdentifier.php` (add): Schema／Table識別子の安全な組み立て。
- `src/Transport/PostgreSql/PostgreSqlJournalSchema.php` (add): Journal用DDL Statement生成。
- `src/Transport/PostgreSql/PostgreSqlJson.php` (add): JSON encode/decodeと型付きField取得。
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php` (add): `JournalRecord` のJSON bytes変換。
- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php` (add): Event DataのJSON変換。
- `src/Transport/PostgreSql/PostgreSqlJournalValueCodec.php` (add): OperationValue／Outcomeの最小JSON変換。
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php` (add): Migration、append/read、重複制約、Completed／Rejected Data往復を検証。
- `docs/internals/postgresql-journal-store.md` (add): Adapterの責務と制限を記録。
- `docs/internals/README.md` (edit): PostgreSQL Journal Store文書へのリンクを追加。
- `develop/orchestration/tasks/P1-015-postgresql-canonical-journal-store.md` (add): Task Packet。
- `develop/STATE.md` (edit): P1-015進行・完了状態へ更新。

## Decisions and Assumptions

- `encoded_record` は `bytea` Columnへ保存し、中身はUTF-8 JSON bytesとした。PHP `serialize()` は使用していない。
- `PostgreSqlCanonicalJournalStore::migrate()` はTestや将来の明示的Migration Commandから呼ぶ入口であり、Runtime起動時の暗黙DDLではない。
- P1-015のValue Codecは、現時点のJournal Data往復に必要な「公開Constructor + scalar/null property」に限定した。複雑なネスト、Enum、DateTime、Identifier property、Upcaster Chain、暗号化は後続のCodec本格化Taskで扱う。
- DB統合Testは専用Schema `blackops_p1_015` を作成・破棄し、既定Schema `blackops` には触れない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (183 tests, 434 assertions)。Runtime PHP 8.5.7。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 221 / Warnings 0 / Errors 0。

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Migration SQLがPostgreSQL Schemaと `journal` Tableを作成する
- [x] `journal.record_id` がPrimary Keyである
- [x] `journal.operation_id, sequence` がUniqueである
- [x] `encoded_record` が `bytea` で保存される
- [x] Storeが `CanonicalJournalStore` を実装する
- [x] StoreがappendしたRecordをOperation ID単位・Sequence順で読み戻せる
- [x] 重複Record IDまたは重複Sequenceは `JournalWriteFailed` になる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- `operations`、`outcomes`、`dead_letters` は未実装。
- CLI Migration Commandは未実装。
- InlineDispatcherのDI構成でPostgreSQL Storeを注入する実装は未実装。
- Canonical Codecの本格仕様、Upcaster、暗号化、Sensitive Projectionは未実装。

## Suggested Next Action

Inline DispatcherをPostgreSQL Canonical Journal Storeへ接続する統合テストを追加し、Inline Vertical SliceのJournal永続化をDBまで通す。

## Codex Review

Accepted at `2026-07-08T01:43:38+09:00`。
