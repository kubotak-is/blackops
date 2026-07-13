# Project Generators and Application Migrations

## Scope

Installed ApplicationのProject RootにあるProject所有`blackops`から、Framework Package所有のOperation／Migration Generatorを実行する。生成物はApplication所有Sourceとなり、生成後の編集と保守はApplicationが行う。

## Framework Commands

Framework Console Kernelは次のCommandを常時登録する。

```text
make:operation
make:migration
```

Command Class、入力検証、File生成、StubはFramework Packageが所有する。Projectの`blackops`と`bootstrap/app.php`はGenerator実装を持たない。Kernelの`list`／`help`はSource Scan、Migration Directory Scan、Database接続、Buildを行わない。

Application独自Commandはこの2つの名前を上書きできない。

## Operation Generator

公式形式は次とする。

```bash
php blackops make:operation Welcome/ShowWelcome --type=welcome.show
```

第一引数は`<Feature>/<Action>`の2 Segmentで、各Segmentは有効なPascalCase PHP Class名でなければならない。Absolute Path、`.`、`..`、空Segment、追加Segment、Backslash、制御文字は拒否する。`--type`は必須で、`OperationType`と同じlowercase dot-separated ID Contractを満たす。

TargetはApplication Base Pathからだけ解決し、次の3 Fileを生成する。

```text
app/Feature/Welcome/ShowWelcome/ShowWelcome.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php
```

生成するOperationはTyped Self-handled形とする。

- Operationは`Operation`を実装し、`#[OperationType('welcome.show')]`を持つ
- `handle(ShowWelcomeValue $value): ShowWelcomeOutcome`は空Outcome Instanceを返す
- ValueはPropertyを持たない`readonly` Classとして`OperationValue`を実装する
- OutcomeはPropertyを持たない`readonly` Classとして`Outcome`を実装する
- `#[Accepts]`、`#[Returns]`、Generic DocBlock、Value Narrowing Guard、`OperationResult`を生成しない
- Route、HTTP Method、Execution Strategy、ExecutionContextを生成しない

生成直後の3 ClassはComposer Autoload下にあり、Application Buildで有効でなければならない。

## Migration Generator

公式形式は次とする。

```bash
php blackops make:migration CreateOrdersTable
```

Descriptionは有効なPascalCase Identifierとする。Target DirectoryはApplication Rootの`migrations/`であり、存在しない場合だけ作成する。File／Class名はClockから得たUTC時刻による`VersionYYYYMMDDHHMMSS`とする。同一秒のVersionが存在する場合は上書きせず失敗する。

生成Classは`App\Migrations` NamespaceでDoctrine `AbstractMigration`を継承し、次を持つ。

- 入力Descriptionを返す`getDescription()`
- 空の`up(Schema $schema): void`
- 空の`down(Schema $schema): void`

CommandはFile生成だけを行う。Database Connection、Status、Migration適用、Composer Dump-autoload、Artifact Buildを実行しない。

## Application Migration Runtime

Application Rootに`migrations/`が存在する場合、`blackops:database:status`と`blackops:database:migrate`は`App\Migrations`をFramework Migrationと同じDoctrine Migration Configurationへ追加する。Directoryが存在しない場合はFramework Migrationだけを扱い、空Directoryを要求しない。

Framework MigrationとApplication Migrationは次を共有する。

- `config/database.php`で構成された一つのDBAL Connection
- Framework Schema内のDoctrine Metadata Table
- Transactional／All-or-nothing設定
- 明示的なStatus／Dry-run／Migrate Command

MigrationはFramework Namespaceを先に実行し、その後にApplication Namespaceを実行する。各Namespace内ではVersion Class名の昇順とする。これによりApplication MigrationはFramework Schemaの現在Versionを前提にできる。

Framework Migration FactoryはFramework NamespaceにSchema名を注入する。Application MigrationはDoctrine標準Constructorで生成し、Framework専用Schema名を注入しない。未知NamespaceのMigration Classは拒否する。

Doctrine Directory FinderがVersion Fileを直接Loadするため、Application Migration用Composer PSR-4 Mappingは要求しない。Migration FileのParse Error、Namespace不一致、Class不正はDatabase Command実行時にFail-fastする。

HTTP、Worker、Scheduler、Build、Consoleの`list`／`help`はApplication MigrationをScanまたは適用しない。

## File Safety

Generatorはすべての入力、Target Path、既存File衝突をWrite前に検証する。Operationの3 Targetの一つでも存在する場合は何も変更しない。初期Versionでは`--force`を提供しない。

複数Fileは同じTarget Directory内のTemporary Fileへ完全にWriteしてから確定する。確定途中で失敗した場合は、このCommandが作成したTemporary／Target Fileだけを除去する。既存Fileと既存Directoryは削除しない。

Error MessageはApplication Absolute Path、Credential、Stub内部Pathを不必要に公開せず、修正可能なInputと衝突Targetを示す。成功時は生成したProject Relative Pathを表示する。

## Stub Ownership and Updates

StubはFramework Package内の配布Resourceであり、Skeletonへ複製しない。Framework Update後、既存Projectの変更されていない`blackops`は更新済みCommandとStubを使用する。

Framework Updateは生成済みOperation／Migrationを変更しない。新StubはUpdate後に新規生成するFileだけへ反映される。Stub Contract変更はFramework ReleaseのCompatibility管理対象とし、必要に応じUpgrade Guideを提供する。

## Verification

- 正常なOperation 3 File生成とBuild成功
- 不正Path／Type拒否、Traversal不在、既存File非上書き、部分生成不在
- MigrationのUTC Version／Description／Namespace／Method生成
- `make:migration`のDatabase／Build Side Effect不在
- Application MigrationなしのFramework-only Status／Migrate互換
- FrameworkとApplication MigrationのStatus／Dry-run／Migrate統合
- Framework先行、各Namespace内Version順の実行
- `list`／`help`のDB／Migration Scan不在
- Framework Update前後でProject Entrypointと生成済みFileが不変
- Framework Update後の新Command／新Stub利用

## Traceability

- Decision: [D080 Project Generator Command Contract](../decisions/080-project-generator-command-contract.md)
- Entrypoint: [D083 Project Root BlackOps Entrypoint](../decisions/083-project-root-blackops-entrypoint.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Console: [Public Console Kernel Composition](48-public-console-kernel-composition.md)
- Operation: [Operation Authoring and Build Discovery](50-operation-authoring-and-build-discovery.md)
- Outcome: [Native Outcome and Rejection Exception](54-native-outcome-and-rejection-exception.md)
