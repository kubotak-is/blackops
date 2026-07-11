# Database Migrations

BlackOpsのPostgreSQL Tableは、ApplicationのDeployment工程から明示的にMigrationを適用する。
HTTP ServerやWorkerの起動時にはMigrationを実行しない。

## Command Registration

ApplicationがDBAL `Connection` を生成し、Migration RunnerとCommandをConsole Applicationへ登録する。
CredentialやConnection生成はApplicationの責務であり、BlackOpsの公開APIには含まれない。

```php
use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Application;

/** @var Connection $connection */
$runner = new DatabaseMigrationRunner($connection, schema: 'blackops');

$application = new Application();
$application->add(new DatabaseMigrationMigrateCommand($runner));
$application->add(new DatabaseMigrationStatusCommand($runner));
```

Schema名は既定で `blackops` である。変更する場合は、先頭を小文字英字またはunderscoreとし、以降を小文字英数字またはunderscoreとする安全なPostgreSQL Identifierを指定する。

## Deployment Flow

Deployment前にstatusとdry runを確認し、その後にNon-interactiveでapplyする。

```bash
php bin/console blackops:database:status --no-interaction
php bin/console blackops:database:migrate --dry-run --no-interaction
php bin/console blackops:database:migrate --no-interaction
php bin/console blackops:database:status --no-interaction
```

`status` はApplied／Pending件数とVersionを表示し、Databaseを変更しない。`--dry-run` はBaselineとMetadata更新のSQL Planを表示するが、Schema、Metadata Table、Framework Data Tableを作成・変更しない。

Pendingがない状態で `migrate` を再実行しても成功し、`No pending migrations.` と表示する。

## Existing Test Schema

既存Adapterの `migrate()` はIntegration Test用helperとして残している。Production DeploymentではCommandを使用する。

現在のhelperが作成した空SchemaはVersioned Baselineでadoptできる。Migrationは既存Tableを削除せず、Baseline VersionをDoctrine Metadataへ記録する。この互換性は現在のhelperが生成するSchemaだけを対象とし、任意に変更されたTableの修復機能ではない。
