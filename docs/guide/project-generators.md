# Project Generators

Install済みApplicationでは、Project Rootの`bin/blackops`からFrameworkが提供するGeneratorを実行できる。

> **Document Channel:** このPageは`main`に実装済みで次回Stable Releaseへ含まれる機能を説明する。Latest Stable `1.0.0`には`make:operation`／`make:migration`がまだ含まれない。

## Creating an Operation

FeatureとActionを`<Feature>/<Action>`形式で、永続的なOperation Typeを`--type`で指定する。

```bash
php bin/blackops make:operation Welcome/ShowWelcome --type=welcome.show
```

次の3 Fileが生成される。

```text
app/Feature/Welcome/ShowWelcome/ShowWelcome.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php
```

FeatureとActionはPascalCaseのPHP Class名でなければならない。Operation Typeは`welcome.show`のようなlowercase dot-separated IDとする。Pathは必ず2 Segmentで、Absolute Path、Backslash、`.`、`..`、追加Segmentは使用できない。

生成されるOperationは、ValueをNative Parameter、OutcomeをNative Return Typeに持つTyped Self-handled Operationである。ValueとOutcomeは空の`readonly` Classなので、Use Caseに必要なPropertyと処理を追加する。

```php
public function handle(ShowWelcomeValue $value): ShowWelcomeOutcome
{
    return new ShowWelcomeOutcome();
}
```

GeneratorはRoute、HTTP Method、Execution Strategy、`ExecutionContext`を推測しない。HTTP公開やDeferred実行が必要なOperationだけへ、それぞれのAttributeと処理を追加する。

## Safety and Build

3 Targetの一つでも存在する場合、Generatorは既存Fileを上書きせず、何も生成しない。`--force`は提供しない。成功時は生成したProject Relative Pathだけを表示する。

GeneratorはFileを作成するだけで、Composer、Database、Network、Artifact Buildを実行しない。生成後は通常のApplication BuildでOperationを検証する。

```bash
php bin/blackops blackops:build:compile
```

Generator Stubは`blackops/framework` Packageが所有する。Framework Update後に新しく生成するFileには更新済みStubが使われるが、Applicationが所有する生成済みFileは変更されない。

## Framework Updates

Projectの`bin/blackops`はApplication所有のBootstrapであり、Framework CommandやStubのCopyではない。通常のComposer Updateで`blackops/framework`を更新すると、Entrypointを変更せずに更新後の`make:operation`／`make:migration`とStubを利用できる。

```bash
composer update blackops/framework
```

Framework Updateは既に生成したOperation／MigrationをUpgradeしない。生成済みSourceはApplication所有のままbyte-for-byte維持され、新しいStubはUpdate後に新規生成するFileだけへ反映される。Stub Contractに互換性対応が必要なReleaseでは、そのReleaseのUpgrade Guideを確認する。

## Creating a Migration

Application固有のDatabase変更はPascalCaseのDescriptionから生成する。

```bash
php bin/blackops make:migration CreateOrdersTable
```

UTCの現在時刻から次のFileが作られる。

```text
migrations/VersionYYYYMMDDHHMMSS.php
```

生成Classは`App\Migrations` NamespaceでDoctrine `AbstractMigration`を継承し、Descriptionと空の`up()`／`down()`を持つ。必要なSQLを追加してから、既存のDatabase CommandでFramework Migrationと一緒に確認・適用する。

```bash
php bin/blackops blackops:database:status
php bin/blackops blackops:database:migrate --dry-run
php bin/blackops blackops:database:migrate
```

`migrations/`は最初の生成時だけ作られる。同じ秒のVersion Fileがすでに存在する場合は上書きせず失敗する。CommandはFile生成だけを行い、Database接続、Migration適用、Composer更新、Artifact Buildを行わない。
