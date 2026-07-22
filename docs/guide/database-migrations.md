# Database Migrationを適用する

ApplicationのDeployment工程からBlackOpsのPostgreSQL TableへMigrationを明示的に適用します。HTTP ServerやWorkerの起動時にはMigrationを実行しません。

## Commandの登録境界

Public Console KernelはMigration Commandを常時登録し、対象Commandを実行したときだけ`config/database.php`の解決済みConnection ParameterとSchemaからRunnerを構成します。

```php
return [
    'connection' => $resolvedDbalParameters,
    'schema' => 'blackops',
];
```

Schema名の既定値は`blackops`です。変更する場合は、先頭を小文字英字またはunderscoreとし、以降を小文字英数字またはunderscoreとする安全なPostgreSQL Identifierを指定します。

## Deployment手順

Deployment前にStatusとDry-runを確認し、その後にNon-interactiveで適用します。

```bash
php blackops database:status --no-interaction
php blackops database:migrate --dry-run --no-interaction
php blackops database:migrate --no-interaction
php blackops database:status --no-interaction
```

`status`はApplied／Pending件数とVersionを表示し、Databaseを変更しません。`--dry-run`はBaselineとMetadata更新のSQL Planを表示しますが、Schema、Metadata Table、Framework Data Tableを作成・変更しません。

Pendingがない状態で`migrate`を再実行すると、`No pending migrations.`と表示して成功します。

## Application Migrations

Project Rootに`migrations/`がある場合、Database CommandはFramework Migrationに加えて`App\Migrations`の`Version*.php`を読み込みます。DirectoryがないApplicationはFramework Migrationだけを扱うため、空Directoryを作る必要はありません。

Application MigrationはFramework Migrationと同じConnection、Framework Schema内の`schema_migrations` Metadata Table、transactional／all-or-nothing設定を共有します。RunnerはFramework Migrationを先に実行し、その後にApplication MigrationをVersion Class順で実行します。

Application MigrationはDoctrine標準Constructorを使います。FrameworkはSchema名を自動注入しないため、ApplicationがTableのSchemaとSQLを明示します。Install直後のSkeletonにはOrder Journey用のApplication Migrationがあり、`database:migrate`後に`quickstart_orders`と`quickstart_order_commits`を作ります。HTTP起動だけではこれらのTableを作りません。

```php
<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreateQuickstartOrderTables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE public.quickstart_orders (reference VARCHAR(64) NOT NULL, PRIMARY KEY (reference))',
        );
        $this->addSql(
            'CREATE TABLE public.quickstart_order_commits (reference VARCHAR(64) NOT NULL, PRIMARY KEY (reference))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public.quickstart_order_commits');
        $this->addSql('DROP TABLE public.quickstart_orders');
    }
}
```

Database Commandは実行時に`Version*.php`を直接読み込みます。Parse Error、`App\Migrations`以外のNamespace、File名と異なるClass、`AbstractMigration`でないClassを検出すると失敗します。`migrations`がFileまたはsymlinkの場合も無視せず拒否します。

HTTP、Worker、Scheduler、Build、Consoleの`list`／`help`はMigration Directoryを読み込まず、MigrationやDDLを暗黙実行しません。

初期Dataも投入するDeploymentでは、Migration後にApplicationをBuildしてからRoot Seederを実行します。Seederの作り方と責任境界は[Database Seeding](database-seeding.md)を参照してください。

```bash
php blackops database:migrate
php blackops build:compile
php blackops database:seed
```
