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

## Existing Test Schema

既存Adapterの `migrate()` はIntegration Test用helperとして残している。Production DeploymentではCommandを使用する。

現在のhelperが作成した空SchemaはVersioned Baselineでadoptできる。Migrationは既存Tableを削除せず、Baseline VersionをDoctrine Metadataへ記録する。この互換性は現在のhelperが生成するSchemaだけを対象とし、任意に変更されたTableの修復機能ではない。
