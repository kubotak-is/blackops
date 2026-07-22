# Database Seeding

## Purpose

BlackOpsはApplication Databaseへ初期値、Development Fixture、Demo Dataを投入するため、Framework-owned `database:seed` CommandとApplication-owned Seeder Contractを提供する。利用者はSymfony Console Commandを実装せず、Seed LogicとApplication固有の安全性だけを記述する。

SeederはOperationではない。Operation Lifecycle、Operation ID、Journal、Outcome、Deferred Executionは作成せず、Database Migrationとも別の明示Deployment Stepとして扱う。

## Public API

Frameworkは次のPublic Interfaceを提供する。

```php
namespace BlackOps\Database;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface Seeder
{
    public function run(): void;
}

#[PublicApi]
interface SeederRunner
{
    /** @param class-string<Seeder> ...$seeders */
    public function run(string ...$seeders): void;
}
```

`Seeder`は実行結果を返さず、正常終了または例外だけを伝える。Framework標準の件数、Message、Output Port、Result DTOは持たない。

`SeederRunner`は渡された順序でChild Seederを実行する。Classは検出済みの`Seeder`実装かつCompiled Seeder Locatorに存在しなければならない。RunnerはContainer、Service ID、Reflection、Console OutputのGetterを公開しない。

同一実行Stack内で同じSeederへ再入する循環呼出しは、Seed Logicを実行する前に安全な失敗へする。Sequentialな再呼出しはApplicationが明示した処理として許可する。

## Application Authoring

標準Root Seederは次へ置く。

```text
app/Infrastructure/Seed/DatabaseSeeder.php
App\Infrastructure\Seed\DatabaseSeeder
```

Root Seederは`SeederRunner`を一つだけ受け取り、Child Seederの順序をClass名で明示する。

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

Child Seederは通常のApplication ServiceとしてRepository、Domain Service、Clock、Identifier等をConstructor Injectionできる。Domain RuleをSeederへ複製せず、既存Domain Serviceを利用する。Domain層は`Seeder`や`SeederRunner`へ依存しない。

## Configuration

標準Layoutでは追加設定を要求しない。

- Root Class: `App\Infrastructure\Seed\DatabaseSeeder`
- Discovery Root: `<application-base>/app/Infrastructure/Seed`

標準Discovery Rootが存在しない場合はSeeding未構成として扱い、Buildを失敗させない。標準Root Classが存在しない場合も、他のApplication RuntimeとCommand一覧を維持する。

独自Layoutでは`config/database.php`の`seeding` SectionでOverrideする。

```php
use App\Database\Seed\RootSeeder;

return [
    // connections / framework ...
    'seeding' => [
        'root' => RootSeeder::class,
        'discovery' => [dirname(__DIR__) . '/app/Database/Seed'],
    ],
];
```

- `root`は空でない`class-string<Seeder>`で、一つだけ指定する。
- `discovery`はApplication Base Path内にあるReadableなDirectoryの順序付きListとする。
- `root`または`discovery`の片方だけを明示した場合も、もう片方は標準値を使う。
- 明示Discovery RootのMissing、Unreadable、Symlink Escape、Root外PathはSafe Configuration／Build Failureにする。
- 明示RootのMissing、Non-instantiable、`Seeder`未実装、Discovery外Source、Container未解決DependencyはSafe Build Failureにする。
- 標準Rootが存在しないだけの場合はBuild Failureにしない。

Configuration ClosureとEnvironment Snapshotは既存Application Configuration Contractを使う。Seeder実行時にEnvironmentを再読込しない。

## Build-time Discovery and Container

Application-aware `build:compile`だけがSeeder Sourceを探索する。

- Configured Discovery Root内のComposer Autoload対象PHP Classから`Seeder`実装を決定的に検出する。
- Interface、Trait、Enum、Abstract Class、Anonymous Class、Source外Classを登録しない。
- Seeder ConstructorをDiscovery時に実行しない。
- 検出済みSeederをCompiled ContainerのPrivate Autowired Serviceへ登録する。
- `SeederRunner`のFramework実装をPublic InterfaceへBindingし、検出済みSeederだけを持つCompiled Locatorを注入する。
- Root Classも検出済みSeederとして同じContainerから解決する。
- Duplicate Class／Source、Invalid Constructor Dependency、Root不一致は既存有効Artifactを置換しないSafe Build Failureにする。

Seeder用の別Runtime Manifestは必須としない。検出結果、Root、Locatorは同じApplication Build IDのCompiled Containerへ固定し、既存Container Freshness Contractで検証する。実装上Manifest追加が必要な場合も、Runtime Source ScanやReflection Fallbackを許可しない。

Application Build IDはCompiled ContainerのInternal Parameterへ固定する。`database:seed`はAccepted ConfigurationのBuild IDとParameterを比較し、Missing／MismatchならRoot Seederを解決せずStale Artifactとして拒否する。

## Runner Contract

`SeederRunner::run()`は次を満たす。

- Argument順をそのまま実行順とする。
- Empty Listを成功として扱う。
- 未検出Class、`Seeder`未実装、Locator不一致、循環呼出しをSeed実行前または該当Child実行前に安全に拒否する。
- Child Seederは同じCompiled Container Instanceから一度解決する。
- Childの例外を成功へ変換せず、残りのSeederを実行しない。
- Application Data、Constructor Argument、Throwable Message／TraceをRunnerの外部Errorへ反射しない。
- Runner自身はTransaction、Retry、Parallelism、Timeout、Journal、Loggingを暗黙追加しない。

Nested Child Seederも同じRunnerを注入できる。Active StackをCommand実行単位で管理し、Long-running Processや次のCommandへ状態を残さない。

## Database and Transaction Responsibility

Frameworkは次を行わない。

- Root Seeder全体またはChild Seeder単位の暗黙Transaction
- Table Truncate、Sequence Reset、Foreign Key Disable
- Migrationの暗黙適用
- 固定IDやClockの自動注入
- 重複Rowの更新、無視、削除
- Seed Version／履歴Tableの作成

Applicationは使用Connection、Transaction、投入順、Deterministic ID／Clock、Repeatability、既存DataとのConflictを所有する。Frameworkの`#[Transactional]`またはDBAL Transactionを利用する場合も、Applicationが明示する。

## Console Contract

Framework Console Kernelは次を常時登録する。

```text
database:seed
make:seeder
```

`list`と`help`はSeed Directory Scan、Compiled Container Load、Database接続、Root Seeder生成を行わない。

`php blackops database:seed`は実行時に次を行う。

1. ValidでFreshなCompiled Containerを読み込む。
2. Root Seeder設定とCompiled Locatorの整合を検証する。
3. Root SeederをContainerから解決する。
4. `run()`を一度だけ呼ぶ。

成功時は`Database seeding completed.`とExit Code `0`を返す。未構成、Missing／Stale Artifact、Root解決失敗、Runner拒否、Seeder例外はSafe MessageとExit Code `1`を返す。`-v`／`-vv`／`-vvv`でもCredential、SQL、Seed Value、Application Throwable Message／Traceを標準出力または標準Errorへ表示しない。

標準の固定Failure Messageは次とする。

```text
Database seeding is not configured.
Database seeding artifacts are unavailable.
Database seeding runtime could not be resolved.
Database seeding failed.
```

初期ContractはArgumentとOptionを持たない。`--class`、`--force`、`--pretend`、Environment名推測、Interactive Confirmationを提供しない。DeploymentとProcess権限が対象Environmentでの実行可否を制御する。

`database:seed`は`database:migrate`、`build:compile`、HTTP Server、Workerを暗黙実行しない。標準順序は次とする。

```text
php blackops database:migrate
php blackops build:compile
php blackops database:seed
```

## Seeder Generator

公式形式は次とする。

```text
php blackops make:seeder DatabaseSeeder
php blackops make:seeder Board/PostSeeder
```

Nameは一つ以上のPascalCase Segmentを`/`で区切る。Absolute Path、`.`、`..`、空Segment、Backslash、制御文字、無効なPHP Class名を拒否する。

TargetとNamespaceは標準Seed Directoryから決定する。

```text
DatabaseSeeder
  -> app/Infrastructure/Seed/DatabaseSeeder.php
  -> App\Infrastructure\Seed\DatabaseSeeder

Board/PostSeeder
  -> app/Infrastructure/Seed/Board/PostSeeder.php
  -> App\Infrastructure\Seed\Board\PostSeeder
```

生成Classは`final readonly`で`Seeder`を実装し、空の`run(): void`を持つ。`DatabaseSeeder`であってもRunnerやChild Seederを推測して注入しない。利用者が必要なDependencyと実行順を追加する。

CommandはFile生成だけを行い、Build、Database接続、Migration、Seed実行、Composer Dump-autoload、Configuration書換えを行わない。既存Target、Directory Target、Root外／Symlink Escapeを拒否し、既存Fileを上書きしない。StubはFramework Package、生成済みSourceはApplicationが所有する。

成功時は`Created: app/Infrastructure/Seed/<Name>.php`を表示する。単一FileもTarget Directory内のTemporary Fileを完全に書いてからAtomicに公開し、Write／Publish失敗ではCommandが作ったTemporary Fileと空Directoryだけを除去する。

## Application Command Coexistence

Symfony `#[AsCommand]` Discoveryは、Cache Warm、External System同期、独自Maintenance等の高度なApplication Command向けに維持する。Seederは専用Contractを標準形とし、Applicationが出力やOptionを独自化する明示的な理由がない限りSymfony Commandで包まない。

FrameworkはSeeder導入だけを理由に汎用`ApplicationCommand` Interfaceを追加せず、既存Symfony Console統合を廃止しない。

## Security and Failure Boundary

- Seeder入力、投入値、SQL、CredentialをCommand Output、Build Artifact、Exception Message、Reportへ保存しない。
- Application例外のRaw Message／TraceをConsole Verbosityへ反射しない。
- Safe Errorは未構成、Artifact不整合、Root解決失敗、Seeder実行失敗を区別できる固定Code／Messageを使えるが、Class名は必要最小限にする。
- Seederは認証／認可されたHTTP APIではない。OS、Container、CI／Deploymentの実行権限をApplication運用者が管理する。
- Production Data保護、Backup、Retention、Encryption、Access ControlはApplication／Deployment責任である。

## Verification

- Public API InventoryとArchitecture Boundary
- Default Convention、Explicit Override、Missing Default、Invalid Explicit Configuration Matrix
- Build-time Discovery、Private Service、Constructor DI、Fresh／Stale Container
- Ordered／Nested／Empty Runner、Unknown Seeder、Cycle、Exception Stop
- `list`／`help`のScan／Container／Database Side Effect不在
- `database:seed`のSuccess／Unconfigured／Artifact／Resolution／Exception／Verbosity Safe Surface
- `make:seeder`のRoot／Nested生成、Invalid Input、Traversal、Symlink、Existing File、Atomic Write
- Migration／Build／Seedの暗黙連鎖不在
- Quickstart／Skeleton Framework Update、Community Board Existing Volume／Clean Install
- Application Command Discovery／Operation Consoleの回帰
- Mago、PHPUnit、Deptrac、Composer Strict、Website、Package Export

公式Skeletonは空の標準Root Seederを配布する。Community BoardはRootからChild Seederを明示実行する参照実装とし、専用Symfony Command、`app:seed`、Seederの明示Service登録を持たない。Application SourceでSymfony Consoleを直接Importしない場合、ApplicationのComposer Direct Dependencyから`symfony/console`を削除する。

## Traceability

- Decision: [D113 Database Seeder Contract](../decisions/113-database-seeder-contract.md)
- Database: [Database Access and Migration Library](../decisions/057-database-access-and-migration-library.md)
- Console: [Public Console Kernel Composition](48-public-console-kernel-composition.md)
- Generators: [Project Generators and Application Migrations](55-project-generators-and-application-migrations.md)
- Application Ergonomics: [Application Ergonomics](74-application-ergonomics.md)
