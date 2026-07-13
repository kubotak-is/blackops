# Database Migrations

Framework-owned PostgreSQL SchemaはDoctrine Migrations 3.9のVersioned Migrationで管理する。ORM、DoctrineBundle、Symfony Kernelには依存しない。

## Composition

`DoctrineMigrationDependencyFactory` は既存のDBAL `Connection` からDoctrine `DependencyFactory`を構成する。

- `ExistingConfiguration` と `ExistingConnection` を使用する
- Framework Migration directoryを明示登録し、存在する場合だけApplication Rootの`migrations/`を追加する
- Transactional／All-or-nothingを有効にする
- Metadata Tableをconfigurable Schema内の `schema_migrations` に設定する
- custom `ConfigurablePostgreSqlMigrationFactory`からFramework MigrationだけへSchema名を注入する
- Framework優先ComparatorによりFramework Migrationを先に、各Namespace内をVersion Class順に実行する
- Application MigrationとFramework Migrationで同じConnectionとMetadata Tableを共有する

Migration FactoryはFrameworkのPostgreSQL Migration Namespaceと`App\Migrations`に属する具象`AbstractMigration` subclassだけを受け入れる。Framework Versionにはconfigurable Schema名を注入し、Application VersionはDoctrine標準のConnection／Logger Constructorで生成する。それ以外のNamespaceは拒否する。

Application-aware FinderはFramework directoryをDoctrineのGlob Finderへ委譲し、Application directoryを厳格に読み込む。各`Version*.php`はちょうど一つの、File名と一致する`App\Migrations`具象`AbstractMigration` Classを宣言しなければならない。Parse Error、Namespace不一致、Class不一致をDatabase Command実行時に拒否する。

Application Migration directoryの不在だけをFramework-only状態として扱う。既存PathがDirectoryでない場合やsymlinkの場合は拒否し、Application Root外のPHPをMigrationとして読み込まない。

Schema名は `PostgreSqlMigrationSchema` が検証し、Table名を引用する。MigrationへCredentialやConnection設定は渡さない。

`DatabaseMigrationRunner` がDependencyFactoryを所有し、status、dry-run、applyの内部入口となる。Application Console FactoryはDatabase Command実行時だけ`<basePath>/migrations` Conventionを渡す。HTTP Composition、Worker Runtime、FrankenPHP Front ControllerからRunnerを呼び出す経路は存在しない。

## Metadata Bootstrap

DoctrineのTable Metadata Storageは、対象Schemaが存在しない状態ではSchema内の管理Tableを作れない。一方、Baseline適用状態の判定にはMetadata Storageが必要になる。

apply時は次の順でchicken-and-eggを解消する。

1. configurable Schemaを明示作成する
2. 既存のlegacy metadata列をDoctrine形式へ変換する
3. `ensureInitialized()`でDoctrine自身にMetadata Shapeを検証・補正させる
4. Pending BaselineをAll-or-nothingで適用する

Doctrine Metadataの実形状は次のとおりである。

```text
schema_migrations
  version         varchar(191) primary key
  executed_at     timestamp(0) without time zone nullable
  execution_time  integer nullable
```

以前のprogrammatic schemaが持つ `applied_at timestamptz` は、UTCの `executed_at timestamp without time zone` へ値を移送してから削除する。単純なrenameではDoctrine DBALのexpected typeと一致しないため採用しない。変換とMetadata初期化は一つのTransactionで行う。

statusはread-onlyである。SchemaやMetadata Tableが存在しないfresh Databaseでは0 Applied／1 Pendingを返す。dry-runもMetadata bootstrapを呼ばず、Doctrine executorが返すSQLだけを収集するためDatabase副作用を持たない。

## Baseline Boundary

BaselineはOperations、Journal、Outcomes、Dead Letters、Retention Holds、Retention Purge Auditsと現在のConstraint／Indexを作成する。DoctrineのMetadata bootstrapが `schema_migrations` を作成し、適用後にBaseline Versionを一行記録する。

現在のprogrammatic test helperが先に空Tableを作成している場合に限りadoptできるよう、Baselineは `IF NOT EXISTS` を使用する。これは任意のSchema driftを修復する仕組みではない。Productionは最初からVersioned Migrationを使用する。

Baseline downはData Lossを避けるため常にIrreversibleとして拒否する。

Baseline後の追加VersionはRetention HoldとPurge AuditのOperation参照外部キーを削除する。どちらもOperations行を持たないInline Operation IDを保存するための独立した識別Tableであり、IndexとUUID列は維持する。このMigrationも既存Inline参照を失わずにdownできないためIrreversibleである。

## Transaction Boundary

Baselineはtransactional migrationであり、Runnerもall-or-nothingを要求する。Framework Data Table作成の途中状態はcommitしない。Metadata bootstrapはBaselineとは別の短いTransactionで先に完了するため、Baseline失敗時もMetadata Table自体は残るがVersion Rowは記録されない。
