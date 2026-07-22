# Databaseへ初期Dataを投入する

BlackOpsはApplication Databaseへ初期値、Development Fixture、Demo Dataを投入するため、Framework所有の`database:seed` CommandとApplication所有のSeederを提供します。Seederは[Operation](glossary.md#operation)ではなく、HTTP LifecycleやJournalを通らない明示的な保守処理です。

> **Release:** Database SeederはRepository `main`のExperimental Surfaceです。Stable `1.1.0`には含まれません。

## Root Seeder

標準の入口は`app/Infrastructure/Seed/DatabaseSeeder.php`です。Install直後のSkeletonは空のRoot Seederを持つため、追加設定なしでCommandを実行できます。

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use BlackOps\Database\Seeder;

final readonly class DatabaseSeeder implements Seeder
{
    public function run(): void {}
}
```

Seederを追加する場合はFramework所有Generatorを使います。

```bash
php blackops make:seeder Catalog/ProductSeeder
```

Root Seederは`SeederRunner`をConstructor Injectionし、子Seederを実行順に列挙します。FrameworkはDirectory名やClass名から順序を推測しません。

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;

final readonly class DatabaseSeeder implements Seeder
{
    public function __construct(private SeederRunner $seeders) {}

    public function run(): void
    {
        $this->seeders->run(
            Catalog\ProductSeeder::class,
            Catalog\PriceSeeder::class,
        );
    }
}
```

Rootと子SeederはBuild時に検出され、Compiled ContainerからDependencyを注入されます。SeederをService Providerへ個別登録する必要はありません。

## 実行順序

Migration、Build、Seedは互いを暗黙実行しません。Fresh InstallとDeploymentでは次の順に分けて実行します。

```bash
php blackops database:migrate
php blackops build:compile
php blackops database:seed
```

成功時は`Database seeding completed.`だけを表示します。Root未構成、古いBuild Artifact、Dependency解決失敗、Seeder例外は固定された安全なMessageと非0 Exitで返り、Throwable、SQL、投入値、Credentialを表示しません。

## Applicationの責務

FrameworkはSeederの検出、DI、子Seeder実行、循環検出、安全なCommand境界を担当します。ApplicationはTransaction、再実行方針、固定Fixture、既存DataとのConflict判定を担当します。Frameworkは自動Transaction、truncate、upsert、Seed履歴、Production Environment推測を行いません。

同じSeederを繰り返し実行する運用では、Application側でIdempotentにするか、安全に拒否してください。Productionでの実行権限や対象EnvironmentもDeployment側で制御します。
