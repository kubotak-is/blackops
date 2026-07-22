# Project Generatorを使う

Install済みApplicationでは、Project Rootの`blackops`からFrameworkが提供するGeneratorを実行できます。生成対象となる[Operation](glossary.md#operation)は、Applicationが実行したい一つの意図と処理単位です。

> **Release:** `make:operation`と`make:migration`はExperimental Stable `1.1.0`で利用できます。`make:seeder`はRepository `main`のExperimental Surfaceです。生成済みSourceはApplication所有であり、Framework Updateでは変更されません。

## Operationを生成する

FeatureとActionを`<Feature>/<Action>`形式で指定し、永続的なOperation Typeを`--type`へ渡します。

```bash
php blackops make:operation Welcome/ShowWelcome --type=welcome.show
```

Commandは次の3 Fileを生成します。

```text
app/Feature/Welcome/ShowWelcome/ShowWelcome.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php
```

FeatureとActionにはPascalCaseのPHP Class名を指定します。Operation Typeには`welcome.show`のようなlowercase dot-separated IDを使います。Pathは必ず2 Segmentとし、Absolute Path、Backslash、`.`、`..`、追加Segmentを使わないでください。

Generatorは、ValueをNative Parameter、OutcomeをNative Return Typeに持つTyped Self-handled Operationを生成します。ValueとOutcomeは空の`readonly` Classです。Use Caseに必要なPropertyと処理を追加してください。

```php
public function handle(ShowWelcomeValue $value): ShowWelcomeOutcome
{
    return new ShowWelcomeOutcome();
}
```

GeneratorはRoute、HTTP Method、Execution Strategy、`ExecutionContext`を推測しません。HTTP公開やDeferred実行が必要なOperationだけへ、それぞれのAttributeと処理を追加してください。

## Safety and Build

3 Targetの一つでも存在すると、Generatorは既存Fileを上書きせず、何も生成しません。`--force`は提供しません。成功時は生成したProject Relative Pathだけを表示します。

GeneratorはFileだけを作成し、Composer、Database、Network、Artifact Buildを実行しません。生成後は通常のApplication BuildでOperationを検証してください。

```bash
php blackops build:compile
```

`blackops/framework` PackageがGenerator Stubを所有します。Framework Update後に新しく生成するFileには更新済みStubを使いますが、Applicationが所有する生成済みFileは変更しません。

## Framework Updates

Project Rootの`blackops`はApplication所有のBootstrapであり、Framework CommandやStubのCopyではありません。通常のComposer Updateで`blackops/framework`を更新すると、Entrypointを変更せずに更新後の`make:operation`／`make:migration`／`make:seeder`とStubを利用できます。

```bash
composer update blackops/framework
```

Framework Updateは既に生成したOperation／MigrationをUpgradeしません。生成済みSourceはApplication所有のままbyte-for-byte維持し、新しいStubはUpdate後に新規生成するFileだけへ反映します。Stub Contractに互換性対応が必要なReleaseでは、そのReleaseのUpgrade Guideを確認してください。

## Migrationを生成する

Application固有のDatabase変更はPascalCaseのDescriptionから生成します。

```bash
php blackops make:migration CreateOrdersTable
```

CommandはUTCの現在時刻から次のFileを作ります。

```text
migrations/VersionYYYYMMDDHHMMSS.php
```

生成Classは`App\Migrations` NamespaceでDoctrine `AbstractMigration`を継承し、Descriptionと空の`up()`／`down()`を持ちます。必要なSQLを追加してから、既存のDatabase CommandでFramework Migrationと一緒に確認・適用してください。

```bash
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
```

Commandは最初の生成時だけ`migrations/`を作ります。同じ秒のVersion Fileがすでに存在する場合は上書きせず失敗します。CommandはFile生成だけを行い、Database接続、Migration適用、Composer更新、Artifact Buildを行いません。

## Seederを生成する

Application固有のSeederはPascalCaseのClass名から生成します。Slashで区切るとSeed Directory内へNestできます。

```bash
php blackops make:seeder DatabaseSeeder
php blackops make:seeder Catalog/ProductSeeder
```

```text
app/Infrastructure/Seed/DatabaseSeeder.php
app/Infrastructure/Seed/Catalog/ProductSeeder.php
```

生成Classは`BlackOps\Database\Seeder`を実装し、空の`run(): void`を持ちます。Database接続、Migration、Build、Seed実行、Rootへの自動登録は行いません。子Seederの実行順はRoot `DatabaseSeeder`へ明示してください。詳しくは[Database Seeding](database-seeding.md)を参照してください。
