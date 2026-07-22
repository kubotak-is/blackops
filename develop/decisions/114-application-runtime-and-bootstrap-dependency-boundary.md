# D114: Application Runtime and Bootstrap Dependency Boundary

Status: Awaiting User Decision

## Context

P18-008でFramework Database Seederを導入し、Community BoardからSeeder専用Symfony Commandと`symfony/console` Direct Dependencyを削除した。残るProduction Direct Dependencyは、Application Sourceの実Importを基準にすると次のとおりである。

| Package | 現在の利用箇所 | 現在の責務 |
| --- | --- | --- |
| `doctrine/dbal` | Infrastructure Repository、Seeder、Integration Test | Application Data Access |
| `doctrine/migrations` | Application Migration Class | Application Schema Migration |
| `vlucas/phpdotenv` | `bootstrap/app.php` | Process Environmentと`.env`の読込 |
| `nyholm/psr7` | HTTP／FrankenPHP Entrypoint | PSR-7 Request／Response Factory |
| `nyholm/psr7-server` | HTTP／FrankenPHP Entrypoint | PHP GlobalからServerRequestを生成 |
| `laminas/laminas-httphandlerrunner` | HTTP／FrankenPHP Entrypoint | SAPI Response Emit |
| `symfony/uid` | Identity／Board Identifier Adapter | UUIDv7生成 |

D110は、Application Sourceが直接Vendor APIをImportするPackageをApplicationの`composer.json`へ明示し、DBAL等を隠すだけの独自Wrapperを作らないと決めた。この原則は維持する。一方、HTTP Entrypoint、Environment Bootstrap、UUIDv7生成はFramework内部でも同じVendorを利用し、QuickstartとCommunity Boardが定型Codeを複製している。

本Decisionでは、これらをFirst-party Runtime CapabilityとしてFrameworkへ寄せるか、Applicationの明示Dependencyとして維持するかを決める。DBAL／MigrationsのQuery／Schema APIは本DecisionでWrapper化しない。

## Inherited Decisions

- ApplicationがVendor APIを直接利用する限り、そのPackageをDirect Dependencyへ明示する。
- Vendorを隠すことだけを目的にBlackOps独自Query／Repository／Migration APIを再発明しない。
- `Application::http()`のPSR-15 `RequestHandlerInterface`境界は、Custom Server／Test Adapter向けに維持する。
- FrankenPHP Worker ModeをDefaultとし、Classic SAPI Fallbackを維持する。
- EnvironmentはApplication Bootstrap時に一度だけSnapshotし、Request／Operationごとに再読込しない。
- Configuration Fileは`Environment` Closureを標準形とし、`$_ENV`／`getenv()`を直接参照しない。
- Domain層はBlackOps、Doctrine、Symfonyへ依存しない。Identifier AdapterはInfrastructure層に置く。
- RuntimeでApplication SourceをScanせず、CredentialやEnvironment値をBuild Artifactへ保存しない。
- Phase 19 Reliability and DeliveryのContractはD109で決定済みである。

## Decision Drivers

- Install直後の`bootstrap/app.php`、`public/index.php`、`public/worker.php`を薄くする
- Frameworkが既に所有するRuntime Adapterの選択をApplicationへ繰り返させない
- Classic SAPIとFrankenPHP Worker ModeのSafe Failure／Environment Restoreを一か所で検証する
- Environmentを一度だけ読み、Long-running Workerで再利用する
- UUID生成のためだけにApplicationへSymfony UIDの直接Importを要求しない
- PSR-15 Handlerや明示Environment Arrayを使う高度なOverrideを失わない
- DBAL／MigrationsのVendor APIをBlackOps固有APIで再実装しない
- Phase 19へ入る前のFollow-up Scopeを有限に保つ

## Question 1: Delivery Position

### Options

- A: Post-Phase 18 Follow-up `P18-009`としてHTTP Runtime、Environment Bootstrap、UUIDv7境界を実装し、Community BoardのDependencyを再監査してからPhase 19へ進む
- B: Phase 19を先に実装し、Application Runtime依存整理はPhase 19 Closeout後へ送る
- C: D110の現状境界を最終形とし、追加のDependency整理を行わない

### Recommendation

Aを推奨する。

3領域はいずれもApplication Bootstrapの定型配線であり、Idempotency／Outbox Contractとは独立している。Phase番号を変えず小さいFollow-upとして閉じれば、Phase 19のCommunity Board Journeyを薄い標準Runtime上で実装できる。

[ANSWER]

[/ANSWER]

## Question 2: HTTP／FrankenPHP Runtime Boundary

### Options

- A: Framework-owned SAPI Runtimeを追加し、Classic HTTPとFrankenPHP Worker ModeのRequest生成、Response Emit、Safe 500、Environment Restore、GCを所有する。Applicationは薄いEntrypointからApplication Instanceを渡す。既存`Application::http()`はCustom Adapter／Test向けに維持する
- B: Request FactoryだけFrameworkが提供し、Response EmitとWorker LoopはApplicationに残す
- C: 現状どおりNyholm／LaminasをApplicationが直接利用する

### Recommendation

Aを推奨する。

現在のQuickstartとCommunity Boardは同じPSR-7 Factory、ServerRequestCreator、SapiEmitter、Worker Mode例外境界を複製している。これらはApplication固有の業務方針ではなくFramework Runtimeの責務である。PSR-15 HandlerをPublic Escape Hatchとして残せば、RoadRunner等の別Server Adapterを妨げない。

Framework Runtimeは認証、CORS、Application Error文言を所有せず、Requestを既存Handlerへ渡してResponseをEmitするだけとする。Raw Throwable、Credential、Request Bodyを出力しない。

[ANSWER]

[/ANSWER]

## Question 3: Dotenv and Environment Bootstrap

### Options

- A: Application Builderへ明示的なEnvironment File読込Capabilityを追加する。Process Environmentと任意の`.env`を一度だけSnapshotし、既存`withEnvironment(array)`はTest／External Loader向けに維持する。Dotenv実装はFramework内部へ隠す
- B: Framework-neutralなEnvironment Loader Interfaceだけを追加し、ApplicationがDotenv Adapterを実装・登録する
- C: 現状どおりApplicationの`bootstrap/app.php`で`vlucas/phpdotenv`を直接利用する

### Recommendation

Aを推奨する。

QuickstartとCommunity Boardは同じProcess Environment merge、`safeLoad()`、string-only Snapshotを複製している。FrameworkがBootstrap時に一度だけ実行すれば、Configuration Closureの既存Contractを変えず、Applicationから`vlucas/phpdotenv`の直接Importと定型Codeを削除できる。

`.env`欠落はLocal／Production双方で許可し、型・範囲検証は引き続き`Environment` AccessorとConfigurationが行う。Environment値をContainer Dump、Manifest、Logへ保存しない。

[ANSWER]

[/ANSWER]

## Question 4: Application UUIDv7 Generation

### Options

- A: PublicなUUIDv7 Generator ContractとFramework Default Serviceを提供する。Application Infrastructure AdapterはこれをConstructor Injectionし、Domain固有Identifier Interfaceへ変換する。Auth GeneratorとCommunity BoardからSymfony UID直接Importを削除する
- B: 現状どおりApplication Infrastructureが`symfony/uid`を直接利用する
- C: UUIDに限定しない汎用`Identifier`／Entity ID Frameworkを導入し、Domain ID全体をBlackOpsが所有する

### Recommendation

Aを推奨する。

BlackOps自身がUUIDv7を一貫して生成しており、Algorithmを明示した小さいContractならEntity ID Frameworkを再発明せずに定型Adapterを薄くできる。Domainは引き続き自分の`BoardIdGenerator`／`IdentityIdentifier`を所有し、InfrastructureだけがBlackOps Generatorへ依存する。CはDomainのID PolicyまでFrameworkへ持ち込むため採用しない。

[ANSWER]

[/ANSWER]

## Fixed Boundary in This Decision

次は選択肢に含めず、D110どおり固定する。

- `doctrine/dbal`はApplication Repository／SeederがConnection、Exception、Parameter Typeを直接利用するためDirect Dependencyを維持する
- `doctrine/migrations`はApplication Migrationが`AbstractMigration`と`Schema`を直接利用するためDirect Dependencyを維持する
- DBAL Query Builder、Repository Base Class、ORM、Migration DSLをBlackOps固有APIとして追加しない
- Applicationが明示的にNyholm、Laminas、Dotenv、Symfony UIDを利用し続けるOverrideを禁止しない。その場合はApplicationのDirect Dependencyへ明示する

## Expected Follow-up Scope

Question 1から4でRecommendationを採用する場合、`P18-009`は次のTaskへ分ける。

1. Environment File Bootstrap Contract、Safe Snapshot、Quickstart Consumer
2. Classic SAPI／FrankenPHP Worker Runtime Adapter、Safe Failure／Restore、Entrypoint移行
3. Public UUIDv7 Generator、Auth Generator／Community Board移行
4. Skeleton／Framework Update／Package Export、Dependency Audit、Guide／Website、Phase Follow-up Closeout

Phase 19のIdempotency／Outbox Production Codeは混在させない。

## Proposed Invariants

- Default EntrypointはVendor Runtime Classを直接Importしない
- `Application::http()`のPSR-15 Handler境界を削除しない
- Framework SAPI RuntimeはApplication Error Projection、Authentication、Authorization Policyを推測しない
- Classic／Worker ModeでRaw Throwable、Credential、Request Bodyを出力しない
- Worker Request終了時にEnvironmentとExecution Scopeを復元する
- Environment FileはBootstrap時に一度だけ読み、Request／Operationごとに再読込しない
- Environment値をCompiled Container、Manifest、Generated Source、Logへ保存しない
- Public UUID GeneratorはUUIDv7だけを保証し、Domain EntityやRepositoryを所有しない
- Domain層へBlackOps／Symfony依存を追加しない
- DBAL／Migrationsを隠すだけのWrapperを追加しない
- Documentation Website／Community Boardを外部公開しない

## Traceability

- Dependency Boundary: [D110 Application Ergonomics](110-application-ergonomics.md)
- Seeder Follow-up: [D113 Database Seeder Contract](113-database-seeder-contract.md)
- Worker Runtime: [D085 Worker Mode Default](085-worker-mode-default.md)
- Installed Layout: [Installed Application Layout and Bootstrap](../spec/43-installed-application-layout-and-bootstrap.md)
- Application Ergonomics: [Application Ergonomics](../spec/74-application-ergonomics.md)
- Next Phase: [D109 Phase 19 Idempotency and Outbox](109-phase-18-idempotency-and-outbox.md)
