# PostgreSQL Journal Store

PostgreSQLCanonicalJournalStoreはCanonicalJournalStoreを実装し、Canonical Journal RecordをPostgreSQLの `journal` Tableへ保存する。

MVPでは次の責務だけを扱う。

- `record_id` Primary Keyによる重複排除
- `operation_id` と `sequence` のUnique制約
- `operation_id` 単位のSequence順読み出し
- 検索用Columnと完全な `encoded_record` の併存

`encoded_record` は `bytea` Columnへ保存する。現時点の内部CodecはUTF-8 JSON bytesを使用し、PHPの `serialize()` は使わない。暗号化、Upcaster Chain、Sensitive Projectionは後続実装で追加する。

Migration SQLは `migrations/postgresql/001_create_canonical_journal.sql` に置く。Runtimeは暗黙にDDLを実行せず、Adapterの `migrate()` はTestや明示的なMigration Commandから呼び出すための入口として扱う。
