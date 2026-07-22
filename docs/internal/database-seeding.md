# Database Seeding Internals

Database SeedingはPublic `Seeder`／`SeederRunner`とFramework-owned `database:seed`／`make:seeder`で構成する。Application SeederはOperation、Symfony Command、Migrationではない。

Application-aware Buildは設定済みDiscovery Rootだけを走査し、`Seeder`実装をPrivate Autowired ServiceとしてCompiled Containerへ登録する。標準Rootは`App\Infrastructure\Seed\DatabaseSeeder`、標準Discovery Rootは`app/Infrastructure/Seed`である。標準Rootが存在しない場合は未構成Runtimeを生成し、明示Rootが不正な場合はBuildを拒否する。

`CompiledSeederRunner`はSeeder専用Service LocatorからClassを解決し、Rootが列挙した順に実行する。同じClassがActive Stackへ再入した場合は循環として拒否する。Rootと子SeederはService Providerへ明示登録しない。

`database:seed`はAccepted ConfigurationのApplication Build IDとCompiled Container内Parameterを比較する。FreshなContainerだけからRuntimeを解決し、Rootを一度呼ぶ。Migration、Build、Transaction、Journal、HTTP、Workerは暗黙実行しない。FrameworkはThrowable、SQL、Seed Value、Credentialを公開せず、固定MessageとExit Codeへ縮約する。

`make:seeder`はFramework PackageのStubから`app/Infrastructure/Seed/<Name>.php`をAtomicかつNo-overwriteで生成する。生成済みSourceはApplication所有であり、Framework Updateは変更しない。

Quickstartは空のRoot Seederを配布する。Community BoardはRootが`SeederRunner`で`CommunityBoardSeeder`を実行し、子Seeder側がApplication Transaction、固定Fixture、Idempotency、Conflict判定、Domain Service再利用を所有する。
