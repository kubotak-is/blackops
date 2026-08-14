# PostgreSQL Journal Store

PostgreSQLCanonicalJournalStoreはCanonicalJournalStoreを実装し、Canonical Journal RecordをPostgreSQLの `journal` Tableへ保存する。

AdapterはDoctrine DBAL `Connection` を受け取る。DB接続生成、Credential管理、Connection Pooling相当の運用判断はApplicationまたはRuntime Compositionが担当する。

MVPでは次の責務だけを扱う。

- `record_id` Primary Keyによる重複排除
- `operation_id` と `sequence` のUnique制約
- `tenant_type`／`tenant_id`のPair制約とTenant-scoped Predicate
- `operation_id` 単位のSequence順読み出し
- 検索用ColumnとBOPD `encoded_record` の併存

`encoded_record` はBOPD v1の`bytea` Columnへ保存する。内部CodecはXChaCha20-Poly1305 Envelopeを使い、PHPの `serialize()` とPlaintext JSONは保存しない。Operation内の`actors`へorigin／authorization／execution ActorのIDとTypeだけを保存する。ActorContextなしは`actors: null`、Fieldがない旧RecordはMigration Guardで受理しない。

Actor Context Objectはorigin／authorization／execution、各Actor Objectはid／typeの完全一致だけを受け付ける。欠落Field、余分なCredential／Role／Permission Field、不正型、空ID／TypeはDecode Errorにする。Canonical CodecはActor IDをMaskしない。Observer向けMaskはSensitive Projection境界で行う。StorageKeyProvider、AAD、Access Control、RetentionはFrameworkの保護境界とApplication／運用Policyを組み合わせる。

Runtimeは暗黙にDDLを実行しない。Adapterの `migrate()` はIntegration Test helperとして維持し、Production DeploymentではDoctrine Migrationsを使う `database:migrate` を明示実行する。Programmatic helperが作る `schema_migrations` もDoctrineのMetadata列形状と互換である。詳細は [Database Migrations](database-migrations.md) を参照する。

InlineDispatcherへこのStoreを `CanonicalJournalWriter` として注入すると、Inline実行のLifecycle RecordはそのままPostgreSQLへ保存される。Completedでは4件、Rejectedでは3件のRecordが同一Operation ID配下へSequence順で保存される。
