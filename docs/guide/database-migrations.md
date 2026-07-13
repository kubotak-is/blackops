# Database Migrations

BlackOpsのPostgreSQL Tableは、ApplicationのDeployment工程から明示的にMigrationを適用する。
HTTP ServerやWorkerの起動時にはMigrationを実行しない。

## Command Registration

Public Console KernelがMigration Commandを常時登録し、対象Command実行時だけ `config/database.php` の解決済みConnection ParameterとSchemaからRunnerを構成する。

```php
return [
    'connection' => $resolvedDbalParameters,
    'schema' => 'blackops',
];
```

Schema名は既定で `blackops` である。変更する場合は、先頭を小文字英字またはunderscoreとし、以降を小文字英数字またはunderscoreとする安全なPostgreSQL Identifierを指定する。

## Deployment Flow

Deployment前にstatusとdry runを確認し、その後にNon-interactiveでapplyする。

```bash
php bin/blackops blackops:database:status --no-interaction
php bin/blackops blackops:database:migrate --dry-run --no-interaction
php bin/blackops blackops:database:migrate --no-interaction
php bin/blackops blackops:database:status --no-interaction
```

`status` はApplied／Pending件数とVersionを表示し、Databaseを変更しない。`--dry-run` はBaselineとMetadata更新のSQL Planを表示するが、Schema、Metadata Table、Framework Data Tableを作成・変更しない。

Pendingがない状態で `migrate` を再実行しても成功し、`No pending migrations.` と表示する。

## Application Migrations

Project Rootに`migrations/`がある場合、Database CommandはFramework Migrationに加えて`App\Migrations`の`Version*.php`を読み込む。DirectoryがないApplicationはFramework Migrationだけを扱い、空Directoryを作る必要はない。

Application MigrationはFramework Migrationと同じConnection、Framework Schema内の`schema_migrations` Metadata Table、transactional／all-or-nothing設定を共有する。Framework Migrationが常に先に実行され、その後にApplication MigrationがVersion Class順で実行される。

Application MigrationはDoctrine標準Constructorを使う。Framework Schema名は自動注入されないため、Application TableのSchema選択とSQLはApplicationが明示する。

`Version*.php`はDatabase Command実行時に直接読み込まれる。Parse Error、`App\Migrations`以外のNamespace、File名と異なるClass、`AbstractMigration`でないClassは失敗する。`migrations`がFileまたはsymlinkの場合も、Framework-only状態として無視せず拒否する。

HTTP、Worker、Scheduler、Build、Consoleの`list`／`help`はMigration Directoryを読み込まず、MigrationやDDLを暗黙実行しない。
