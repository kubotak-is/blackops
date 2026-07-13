# PostgreSQL Journal Store

PostgreSQLCanonicalJournalStoreはCanonicalJournalStoreを実装し、Canonical Journal RecordをPostgreSQLの `journal` Tableへ保存する。

AdapterはDoctrine DBAL `Connection` を受け取る。DB接続生成、Credential管理、Connection Pooling相当の運用判断はApplicationまたはRuntime Compositionが担当する。

MVPでは次の責務だけを扱う。

- `record_id` Primary Keyによる重複排除
- `operation_id` と `sequence` のUnique制約
- `operation_id` 単位のSequence順読み出し
- 検索用Columnと完全な `encoded_record` の併存

`encoded_record` は `bytea` Columnへ保存する。現時点の内部CodecはUTF-8 JSON bytesを使用し、PHPの `serialize()` は使わない。暗号化、Upcaster Chain、Sensitive Projectionは後続実装で追加する。

Runtimeは暗黙にDDLを実行しない。Adapterの `migrate()` はIntegration Test helperとして維持し、Production DeploymentではDoctrine Migrationsを使う `blackops:database:migrate` を明示実行する。Programmatic helperが作る `schema_migrations` もDoctrineのMetadata列形状と互換である。詳細は [Database Migrations](database-migrations.md) を参照する。

InlineDispatcherへこのStoreを `CanonicalJournalWriter` として注入すると、Inline実行のLifecycle RecordはそのままPostgreSQLへ保存される。Completedでは4件、Rejectedでは3件のRecordが同一Operation ID配下へSequence順で保存される。
