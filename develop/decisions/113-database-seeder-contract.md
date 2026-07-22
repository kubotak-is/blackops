# D113: Database Seeder Contract

Status: Decided

## Context

Phase 18では、Application固有の保守処理をSymfony `#[AsCommand]`でBuild時Discoveryし、Compiled ContainerからConstructor Injectionできるようにした。Community Boardはこの仕組みを検証するため、`app:seed` Commandと`CommunityBoardSeeder`をApplication側へ実装している。

この構成は汎用Commandの拡張口としては妥当だが、初期データ投入だけを行いたい利用者にも次を要求する。

- Symfony Consoleの`Command`、`#[AsCommand]`、Input／Output APIを直接利用する
- Application Commandの名前、Exit Code、出力、例外処理を自分で設計する
- `symfony/console`をApplicationの直接Dependencyとして宣言する
- BlackOpsがConsoleを提供しているにもかかわらず、Seeder用Console自体はApplicationが所有するように見える

SeederはMigrationと同様に、多くのApplicationで目的と実行方法が共通するDatabase Capabilityである。一方、Seed Dataの内容、業務規則、Transaction、再実行時の扱いはApplication固有であり、Frameworkへ持ち込むべきではない。

本DecisionではSeederを汎用Application Commandから分離し、Framework-owned CommandとApplication-owned Seed Logicの境界を決める。Dotenv、HTTP Runtime、UUID等の他のComposer Dependency整理は別Decisionで扱う。

## Inherited Decisions

- Project CLIの公式形式は`php blackops <command>`であり、Framework Command名に`blackops:` Prefixを付けない。
- Framework標準CommandはFramework Packageが所有し、Project EntrypointはFramework Update後もその実装を利用する。
- RuntimeでApplication SourceをScanせず、Build ArtifactとCompiled Containerを正本とする。
- Application SourceがVendor APIを直接利用する場合、そのPackageをApplicationの直接Dependencyへ明示する。
- Symfony `#[AsCommand]` Discoveryは、Frameworkが用意していないApplication固有の保守Command向けに維持する。
- Application MigrationはFramework Migrationと同じConnectionおよびMetadata Tableで管理するが、HTTP／Worker起動時に暗黙適用しない。
- Community BoardのDomainはFramework／Doctrine／Symfonyへ依存せず、Application ServiceとInfrastructure Adapterが外部境界を所有する。

## Decision Drivers

- 初期データ投入のためだけに利用者へConsole実装を要求しない
- `php blackops database:seed`をFramework標準Commandとして提供する
- Seed Dataと業務規則はApplicationが所有する
- SeederをCompiled Containerから解決し、RepositoryやDomain ServiceをConstructor Injectionできる
- Runtime Reflection、Directory Scan、`new $class()`を追加しない
- Migration、Build、Seedを暗黙に連鎖させず、失敗箇所を明確にする
- Seed処理の途中失敗をFrameworkが誤って成功として扱わない
- Community BoardからSeeder用Symfony Console Codeと直接Dependencyを削除できる

## Question 1: Delivery Position

### Options

- A: Phase 18のCloseout Follow-up `P18-008`としてSeederを実装し、Phase 19 Reliabilityへ進む前にCommunity BoardのDependency境界を再度確定する
- B: Phase 19のIdempotency／Outbox実装にSeederを含める
- C: Seederを将来Roadmapへ延期し、Community Boardの`app:seed`を維持する

### Recommendation

Aを推奨する。

SeederはPhase 18のCommunity Board簡素化で見つかったApplication Ergonomicsの残課題であり、Reliability Contractとは独立している。小さいFollow-upとして閉じれば、Phase番号を再度並べ替えずにComposer Dependency整理の前提を確定できる。

[ANSWER]
A
[/ANSWER]

## Question 2: Public Seeder Model

### Options

- A: Public `BlackOps\Database\Seeder` InterfaceとFramework-owned `database:seed` Commandを追加する。SeederはOperationでもSymfony CommandでもないDatabase用Application Serviceとする
- B: BlackOps独自の汎用`ApplicationCommand` Interfaceを追加し、Seederもその実装にする
- C: 現状どおりSymfony `#[AsCommand]`をSeederの標準形にする

### Recommendation

Aを推奨する。

SeederにはCommand名、Option、Output、Exit Codeを選ばせる必要がない。専用ContractならFrameworkが入口と失敗境界を所有し、ApplicationはSeed Logicだけを書ける。汎用`ApplicationCommand`はSymfony Consoleと責務が重複するため、Seederだけを理由に追加しない。

[ANSWER]
A
[/ANSWER]

## Question 3: Root Seeder Selection

### Options

- A: `App\Infrastructure\Seed\DatabaseSeeder`を標準Conventionとし、`config/database.php`で別Classへ明示Overrideできる。Build時に一つだけ検証し、Directory全体はScanしない
- B: `config/database.php`へRoot Seeder Classを常に明示する
- C: `Seeder`実装ClassをBuild時にすべてDiscoveryし、個別にCommandへ公開する

### Recommendation

Aを推奨する。

Install直後と`make:seeder DatabaseSeeder`は追加設定なしで動き、独自Layoutも明示Overrideできる。FQCNを一つ解決するだけなので、曖昧な複数DiscoveryやRuntime Scanを避けられる。Root Seederが存在しないApplicationでもCommand一覧は維持し、実行時に安全な未設定Errorを返す。

[ANSWER]
A
[/ANSWER]

## Question 4: Seeder Interface and Composition

### Options

- A: `Seeder::run(): void`だけをPublic Contractにする。Root SeederとChild SeederはCompiled ContainerのConstructor DIで構成し、Framework固有の`call()`、Output、Result DTOは初期Scopeへ入れない
- B: `Seeder::run(): SeedResult`として件数やMessageをFrameworkへ返す
- C: Laravel互換に近いAbstract Seederと`call()` Helperを提供する

### Recommendation

Aを推奨する。

Seed件数はApplication Dataの構造を漏らしやすく、標準Result Schemaを決める根拠がまだない。`void`なら正常終了と例外だけがCommand Contractになり、Repositoryや複数Seederの構成は通常のConstructor DIで表現できる。共通Composition APIが実Applicationで必要になった時点で追加できる。

```php
namespace App\Infrastructure\Seed;

use BlackOps\Database\Seeder;

final readonly class DatabaseSeeder implements Seeder
{
    public function __construct(
        private UserSeeder $users,
        private BoardSeeder $board,
    ) {
    }

    public function run(): void
    {
        $this->users->run();
        $this->board->run();
    }
}
```

[ANSWER]
Seederの数が増えたらコンストラクタ肥大化しない？
[/ANSWER]

### Review

その懸念は正しい。Root SeederがすべてのChild SeederをConstructor Injectionすると、Seed対象の追加ごとにConstructorが肥大化する。Application ServiceのDependencyではなく実行順を表すだけのため、Constructorへ列挙する利点も小さい。

`Seeder::run(): void`は維持し、Framework-owned Public `SeederRunner`を一つだけRoot Seederへ注入する形へRecommendationを修正する。

```php
namespace App\Infrastructure\Seed;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;

final readonly class DatabaseSeeder implements Seeder
{
    public function __construct(
        private SeederRunner $seeders,
    ) {
    }

    public function run(): void
    {
        $this->seeders->run(
            UserSeeder::class,
            BoardSeeder::class,
        );
    }
}
```

`SeederRunner::run(class-string<Seeder> ...$seeders): void`は、指定された順にCompiled ContainerからChild Seederを解決して`run()`する。Child Seeder自身のRepositoryやDomain Serviceは通常のConstructor DIで受け取る。Runnerは同じSeederの循環呼出しを検出して安全に失敗し、Application Dataや例外Detailを標準出力へ出さない。

この形には、Question 3のAを次のように補足する必要がある。

- Root Seederの選択は引き続き`App\Infrastructure\Seed\DatabaseSeeder` Conventionと明示Overrideを使う
- 標準Seed Directory内の`Seeder`実装はBuild時にだけDiscoveryし、Compiled ContainerのPrivate Service／Seeder Locatorへ登録する
- Child Seederを個別Console Commandとして公開しない
- Runtime Directory Scan、Runtime Reflection、`new $class()`は行わない
- 実行順はDiscovery順ではなく、Root Seederが`SeederRunner`へ渡した順序だけを使う

これによりConstructorは`SeederRunner`一つで固定され、Seeder追加時は`run()`のListだけを更新する。Laravelの`call()`に近い使用感だが、継承やContainer Setterを要求せず、BlackOpsのCompiled Container境界を維持できる。

### Revised Recommendation

Question 4はAを、Public `SeederRunner`とBuild-time Child Seeder Discoveryを含む上記Contractへ修正して採用することを推奨する。

[CONFIRM]

この具体化でQuestion 3のAを補足し、Question 4のAとして確定してよいか。

A

[/CONFIRM]

## Question 5: Transaction and Repeatability

### Options

- A: Transaction、投入順、固定ID、重複時の更新／無視／失敗はApplication Seederが所有する。Frameworkは暗黙TransactionやTruncateを行わない
- B: FrameworkがRoot Seeder全体を一つのDatabase Transactionで囲む
- C: FrameworkがSeed実行前に対象TableをTruncateする

### Recommendation

Aを推奨する。

複数Connection、External Service、既存DataとのMerge等をFrameworkは推測できない。Community Boardは現在どおりApplication ServiceでTransactionと再実行可能性を実装し、Domain Serviceを再利用する。Frameworkは例外を捕捉して安全なCommand Failureへ変換するが、部分更新を成功扱いしない。

[ANSWER]
A
[/ANSWER]

## Question 6: Command Execution Contract

### Options

- A: `database:seed`はCompiled ContainerからRoot Seederを取得して一度だけ`run()`する。Build、Migration、HTTP Server、Workerを暗黙実行せず、初期Versionでは`--class`、`--force`、Environment推測を提供しない
- B: `database:seed`が必要に応じて`build:compile`と`database:migrate`も自動実行する
- C: `--class`で任意SeederをRuntime Reflectionから直接実行できるようにする

### Recommendation

Aを推奨する。

標準手順を`database:migrate`、`build:compile`、`database:seed`という明示Stepに分ける。SeedはOperatorが明示実行する保守処理であり、Headless Frameworkが`production`等のEnvironment名を推測して`--force`を要求しない。Deployment側が実行権限と対象Environmentを制御する。

成功時は固定の完了MessageとExit Code `0`、未設定／Artifact不整合／解決失敗／Seeder例外はCredentialやSQL Detailを含まないMessageとExit Code `1`を返す。SeederはOperationではないためOperation ID、Journal、Outcomeを生成しない。

[ANSWER]
A
[/ANSWER]

## Question 7: Generator and Application Command Coexistence

### Options

- A: Framework-owned `make:seeder <Name>`を追加し、既定で`app/Infrastructure/Seed/<Name>.php`を生成する。既存Fileは上書きしない。Symfony Application Command Discoveryは高度な保守Command用に維持する
- B: Generatorは追加せず、Seeder Classを利用者が手動作成する
- C: Symfony Application Command Discoveryを廃止し、すべての保守処理をBlackOps専用APIへ置き換える

### Recommendation

Aを推奨する。

SeederのAuthoringをFrameworkの標準導線へ載せつつ、Cache Warmや外部System同期等の任意CommandはSymfonyの成熟したAPIへ任せられる。Community Boardは`CommunityBoardSeedCommand`を削除して標準`DatabaseSeeder`へ移行し、Application SourceがSymfony Consoleを直接Importしなくなったことを確認して`composer.json`から`symfony/console`を削除する。

[ANSWER]
A
[/ANSWER]

## Proposed Command Contract

Recommendationどおりの場合、利用者の標準導線は次となる。

```text
php blackops make:seeder DatabaseSeeder
  -> app/Infrastructure/Seed/DatabaseSeeder.php

php blackops database:migrate
php blackops build:compile
php blackops database:seed
  -> Database seeding completed.
```

`make:seeder`はClassを生成するだけで、Database接続、Migration、Build、Seed実行を行わない。`DatabaseSeeder`以外の名前はChild Seederまたは明示Override先として生成できる。

## Decision

[DECISION]

1. Database SeederをPhase 18 Closeout Follow-up `P18-008`として実装し、Phase 19 Reliabilityの前に完了する。
2. Public `BlackOps\Database\Seeder` InterfaceとFramework-owned `database:seed` Commandを追加する。SeederはOperationでもSymfony CommandでもないApplication-owned Database Serviceとする。
3. Root Seederは`App\Infrastructure\Seed\DatabaseSeeder`を標準Conventionとし、`config/database.php`でRoot ClassとDiscovery RootをOverrideできる。
4. Public `Seeder::run(): void`とPublic `SeederRunner`を提供する。Root SeederはRunnerを一つだけConstructor Injectionし、`SeederRunner::run(class-string<Seeder> ...$seeders): void`へChild Seederを実行順に渡す。
5. Seeder実装は標準または明示Discovery RootからBuild時にだけ検出し、Compiled ContainerのPrivate Service／Seeder Locatorへ登録する。Runtime Scan、Runtime Reflection、`new $class()`を行わない。
6. Transaction、投入順、固定ID、重複時の更新／無視／失敗はApplication Seederが所有する。Frameworkは暗黙Transaction、Truncate、Repeatabilityを追加しない。
7. `database:seed`はCompiled ContainerからRoot Seederを解決して一度だけ実行する。Build、Migration、HTTP、Workerを暗黙実行せず、初期Versionでは`--class`、`--force`、Environment推測を提供しない。
8. Framework-owned `make:seeder <Name>`は`app/Infrastructure/Seed/`へSeederを生成し、既存Fileを上書きしない。
9. Symfony `#[AsCommand]` DiscoveryはFramework標準機能で扱えないApplication固有の保守Command向けに維持する。Seederだけを理由にBlackOps固有の汎用Application Command APIを追加しない。
10. Community BoardはFramework Seederへ移行し、Seeder用Symfony Commandと直接`Symfony Console` Import／Dependencyを削除する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 利用者はSeeder Logicだけを実装し、Command名、Input／Output、Exit Code、例外表示をFrameworkへ委ねられる。
- Root SeederのConstructorはSeeder数に比例せず、実行順は`SeederRunner`への明示Listとして読める。
- Child SeederはCompiled ContainerからRepositoryやDomain ServiceをConstructor Injectionできる。
- Root Conventionが存在しないApplicationはBuild可能で、`database:seed`実行時だけ安全な未設定Errorになる。明示設定されたRootや検出済みSeederの不正はBuild Errorになる。
- Seeder実行はOperation ID、Journal、Outcomeを生成しない。「No operation stays in the dark」はOperationに対するInvariantとして維持する。
- ApplicationはSeed処理のAtomicityと再実行可能性を自ら設計する必要がある。
- `database:migrate`、`build:compile`、`database:seed`は独立した明示Stepとして失敗箇所を分離する。
- `make:seeder`のStubはFramework Packageが所有し、生成済みSeederはApplicationが所有する。
- Generic Maintenance Commandには引き続きSymfony Consoleを直接利用できるため、既存Application Command Contractは破壊しない。

[/CONSEQUENCES]

## Proposed Invariants

- Seeder用Console Command、Command名、Output、Exit CodeをApplicationへ実装させない
- SeederはOperation、Outcome、Journal、Deferred ExecutionのContractへ入れない
- Seeder DirectoryはBuild時だけScanし、RuntimeでScanまたはReflection Discoveryしない
- Root SeederはBuild時に`Seeder`実装、Instantiable、Compiled Containerで解決可能であることを検証する
- `database:seed`のCommand一覧とHelpはDatabase接続、Compiled Container、Root Seederの生成を要求しない
- Migration、Build、Seedを暗黙実行しない
- FrameworkがApplication TableをTruncateしない
- FrameworkがApplication固有のTransactionとRepeatabilityを推測しない
- Seeder例外のMessage、Trace、SQL、Seed Valueを標準出力へ反射しない
- Generatorは既存Application Sourceを上書きしない
- Symfony Application Command DiscoveryをSeeder導入だけを理由に削除しない
- Community BoardのDomainへBlackOps、Doctrine、Symfony依存を追加しない

## Expected Delivery Scope

`P18-008`を次のTask境界へ分ける。

1. `P18-008A`: Public Seeder Contract、Configuration、Build-time Discovery／Validation、Compiled Container／Runner
2. `P18-008B`: Built-in `database:seed`、`make:seeder`、Stub、Console Regression Test
3. `P18-008C`: Quickstart／Skeleton、Guide／Website、Community Board移行、Dependency削除、Clean Install／Phase Follow-up Closeout

Dotenv、PSR-7 Runtime、UUID、DBAL／MigrationsのComposer Dependency境界は本Taskへ混在させず、Community Board移行後の実ImportとPackage Exportを基に別Decisionで扱う。

## Traceability

- Database Library: [D057 Database Access and Migration Library](057-database-access-and-migration-library.md)
- Installed CLI: [D080 Project Generator Command Contract](080-project-generator-command-contract.md)
- Application Ergonomics: [D110 Application Ergonomics](110-application-ergonomics.md)
- Console Composition: [Public Console Kernel Composition](../spec/48-public-console-kernel-composition.md)
- Application Ergonomics: [Application Ergonomics](../spec/74-application-ergonomics.md)
