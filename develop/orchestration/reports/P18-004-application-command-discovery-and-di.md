# P18-004: Application Command Discovery and DI Report

## Summary

Configured `app.command_discovery` RootからSymfony `#[AsCommand]`付きApplication Commandを`build:compile`時に発見し、Schema Version 1のCommand ManifestとCompiled Containerへ固定した。RuntimeはSource ScanやDiscovered Commandの直接生成を行わず、Manifest MetadataからSymfony `LazyCommand`を登録し、Command固有Help／Definition／実行時だけ同じBuildのCompiled ContainerからCommand Serviceを解決する。

既存の`config/app.php` `commands`と`withCommands()`はExplicit Instance／引数なしClass境界として維持した。同じClassのExplicit登録はDiscoveryより優先し、Framework／Discovered／ExplicitのCanonical NameとAlias衝突はBuildまたはBootstrapで拒否する。Missing／Invalid／Stale ManifestからFramework `build:compile`へ到達できるRecovery境界も維持した。Community Board、外部Publication／Deploy、Commitは変更／実行していない。

## Changed Files

- Discovery／Runtime Metadata: `src/Internal/Application/ApplicationCommandDiscovery.php`、`ApplicationCommandRuntimeManifest.php`、`ApplicationCommandRuntimeManifestLoader.php`、`ApplicationCommandContainerResolver.php`、`ExplicitApplicationCommands.php`
- Manifest／Collision: `src/Internal/Console/ApplicationCommandManifestArtifact.php`、`ApplicationCommandManifestFile.php`、`ApplicationCommandMetadata.php`、`ApplicationCommandCollisionValidator.php`、`FrameworkCommandNames.php`
- Build／Container: `src/Internal/Console/ApplicationBuildCompileCommand.php`、`src/Internal/DependencyInjection/RuntimeContainerCompiler.php`、`src/Internal/Runtime/RuntimeContainerArtifactLoader.php`、`src/Internal/Runtime/ProductionRuntimeArtifactLoader.php`
- Application Configuration／Kernel: `src/Internal/Application/ApplicationBuildConfiguration.php`、`ApplicationCommandValidator.php`、`ApplicationConsoleKernel.php`
- PHPUnit／Permanent Fixture: `tests/Internal/Application/ApplicationCommandDiscoveryTest.php`、`ApplicationCommandDiscoveryIntegrationTest.php`、`tests/Internal/Application/Fixture/CommandDiscovery/**`、`tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php`、`ApplicationCommandManifestFileTest.php`、既存Configuration／Container Compiler Test
- Installed Consumer: `examples/quickstart/config/app.php`、`tests/Consumer/quickstart-e2e.sh`、`skeleton-create-project.sh`、`framework-update-generators.sh`
- Documentation／Specification: `docs/guide/application-bootstrap.md`、`configuration.md`、`directory-structure.md`、`project-cli.md`、`docs/internal/application-bootstrap.md`、`installed-application-status.md`、`runtime-container.md`、`develop/spec/44-public-application-bootstrap-api.md`、`48-public-console-kernel-composition.md`、`50-operation-authoring-and-build-discovery.md`、`74-application-ergonomics.md`
- Orchestration: `develop/TODO.md`、`develop/STATE.md`、本Report

## Configuration and Discovery Contract

`app.command_discovery`はIterableな絶対Existing Directory Pathだけを受理する。Missing／EmptyはDiscoveryなしとし、同じReal Pathは一度だけScanする。Application RootやVendorは暗黙にScanせず、Configured Rootだけを既存のSafe Root Resolution、PHP Token Scan、Source Class Verificationで走査する。

CandidateはClass自身へ一つだけ付与されたSymfony `#[AsCommand]`を持つClassである。AttributeなしCommandは無視する。Attribute付きNon-command、Abstract Class、複数Attribute、Missing／Invalid Name、Invalid Alias、NameとAliasの自己衝突は、Absolute PathやRaw Throwableを出さないSafe Build Failureへ閉じる。MetadataはReflection Attributeから読み、Constructorは呼ばない。

Quickstart Canonical Configへ`build.command_manifest`と`command_discovery => [app]`を追加した。既存Applicationで`command_manifest`が欠落する場合は、Configured Container Artifactと同じDirectoryの`commands.php`をDefaultとする。

## Command Manifest Schema and Lifecycle

Command ManifestはPHP Array Artifact、Schema Version 1で、Top-levelに`schema_version`、`application_build_id`、`commands`を持つ。各Command Entryは`class`、`name`、`description`、`aliases`、`hidden`、`help`、`usages`のExact Shapeを持ち、Canonical Name、Classの順で決定的に並ぶ。

WriterはTarget Directoryの存在を要求し、Temporary Fileを同じReaderで検証してからRenameする。ReaderはSchema、Build ID、Exact Shape、型、Duplicate Class／Name／Aliasを検証する。BuildではDiscoveryとContainer Compileが成功した後だけCommand Manifestを置換するため、Unresolved DI時に既存Manifestを保持する。Discovery Rootを空にした成功Buildは空Manifestを発行し、旧Entryを除去する。

## Container DI and Lazy Runtime Evidence

Discovered CommandはCompiled ContainerへPublic／Autowire Serviceとして登録する。Application Service Providerが同じService IDを明示定義した場合はProvider Definitionを優先する。Permanent FixtureではRequired ConstructorがApplication-owned Interfaceを受け、Service Provider Bindingから実装を解決して`fixture:greet`を実行した。

Runtime KernelはValidかつ同じBuild IDのManifest MetadataだけからSymfony `LazyCommand`を構成する。Global `list`ではContainer ArtifactをLoadせずCommand Constructorも呼ばない。Command固有Helpで初めてContainer Serviceを解決し、同じKernel内のAlias実行では同一Command Instanceを再利用する。Logging、およびDatabase Configurationがある場合のDatabase／Transaction Synthetic ServiceはこのLazy Resolution境界で既存Application Configurationから注入し、Kernel構成とGlobal ListではDatabase Connectionを構成しない。

Container ArtifactのSafe Load責務は`RuntimeContainerArtifactLoader`へ抽出し、既存HTTP／Workerの`ProductionRuntimeArtifactLoader`も同じ境界を再利用する。Discovered RuntimeはSource File、Discovery Root、Composer MetadataをScanせず、`new $class()`も使用しない。

## Collision／Recovery／Sensitive Matrix

| Case | Result |
| --- | --- |
| Same discovered class／duplicate real root | 一つへDeduplicate |
| DiscoveredとExplicitが同じclass | Explicitを優先し、Manifestから除外 |
| Different classのname／alias衝突 | Build／Bootstrap Error |
| Framework built-in name／alias衝突 | Build／Bootstrap Error |
| Former `blackops:*` name | Framework予約外のためApplicationで利用可能 |
| Missing／Invalid／Stale command manifest | Discovered未登録、Framework built-inと`build:compile`を維持 |
| Valid manifest＋missing container | Global List可能、実行時だけSafe Bootstrap Failure |
| Valid manifest＋後発Explicit collision | Silent RecoveryせずBootstrap Error |
| Unresolved constructor dependency | Build Error、既存Command Manifestを保持 |

ManifestとConsole ErrorにはSource Absolute Path、Source全文、Constructor Argument、Configuration Value、Credential、Raw Throwable Detailを保存／反射しない。Invalid PHP ArtifactがOutputしたContentもBufferで破棄する。

## Compatibility and Installed Consumer

Explicit Command Instance／引数なしClass、`withCommands()`、Framework List／Help、HTTP／Worker Compiled Containerを維持した。Explicit Factoryは既存Public Contractにないため追加していない。

Quickstart Setup／E2Eは空Schema 1 Command Manifestと同一Build IDを検証する。Skeleton Create-projectはCanonical `command_manifest`／`command_discovery`とArtifactを検証する。Framework Update GeneratorはSynthetic 1.1 Packageへ今回追加したCommand Discovery／Manifest／Runtime Loader依存を含め、1.0から1.1更新後に新Artifact境界が動作することを確認した。

Community Board Source／Config／Command／Service Providerには差分がない。Application-owned `app:seed`のDiscovery／DI移行はP18-007へ据え置く。

## Commands and Results

- `docker compose run --rm app mago format --check src tests`: success、all files formatted
- `docker compose run --rm app mago lint`: success、no issues
- `docker compose run --rm app mago analyze`: success、no issues
- Focused PHPUnit（Task指定4 directory）: OK (304 tests, 982 assertions)
- `docker compose run --rm app vendor/bin/phpunit`: OK (1535 tests, 6093 assertions)
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`: success、0 violations、0 skipped／uncovered／warnings／errors、2575 allowed
- Root／Quickstart `composer validate --strict`: valid
- `bash tests/Consumer/quickstart-setup.sh`: success
- `bash tests/Consumer/quickstart-e2e.sh`: success
- `bash tests/Consumer/skeleton-create-project.sh`: success
- `bash tests/Consumer/framework-update-generators.sh`: success
- Website `pnpm install --frozen-lockfile`: success、Lockfile policy／frozen resolution成功
- Website `pnpm test`: 42／42 success
- Website `pnpm build`: success、31 routes／30 public pages、Artifact／Navigation／Accessibility／Search Check成功。既存Vite chunk-size warningだけを確認
- Management ID Guard、Community Board Scope Guard、`git diff --check`: success
- Generated／Build／Dependency Artifact: cleanup済み

## Decisions and Assumptions

Symfony `AsCommand` MetadataのName、Description、Aliases、Hidden、Help、UsagesをManifestの正本とした。MetadataのためにCommand Instanceを作らない。Manifest EntryへSource PathやFingerprintを追加しない。

Missing／Invalid／Stale ManifestだけをRecovery対象とする。正しいSchema／Build IDを持つManifestと新しいExplicit Configurationが衝突する場合は設定矛盾であり、Discovered Commandを黙って捨てずBootstrap Errorとした。

## Acceptance Criteria

- [x] Configured Rootだけから`#[AsCommand]`をBuild時Discoveryする。
- [x] Schema 1 Manifestを同じApplication Build IDで決定的／Atomicに生成・検証する。
- [x] Discovered CommandをCompiled Containerへ登録し、Required Constructor DependencyをService Provider Bindingから解決する。
- [x] Runtime Source Scan／直接生成なしでSymfony Lazy CommandからContainer解決する。
- [x] Global ListはCommand Constructor、Container、Databaseを解決せず、Valid Manifest Metadataを表示する。
- [x] Missing／Invalid／Stale ManifestからFramework `build:compile`で復旧できる。
- [x] Explicit Instance／Class、Same Class Override、Former Alias利用を維持する。
- [x] Framework／Discovered／Explicit Name／Alias CollisionとUnresolved DIをFail-fastする。
- [x] Quickstart、Skeleton、Framework Updateを新Artifact境界へ同期した。
- [x] Community Board Source／Config／Command／Service Providerを変更していない。
- [x] Required Quality／Consumer／Website／Guardをすべて実行した。
- [x] Documentation Website／Community Boardを外部公開していない。
- [x] WorkerはCommitしていない。

## Remaining Issues

Active Implementation Blockerはない。Public `#[ConsoleCommand]` Operation AdapterはP18-005、Community Board `app:seed`のDiscovery／DI移行はP18-007 Scopeである。Explicit Command Factoryは既存Public Contractにないため、本Taskでは追加していない。

## Suggested Next Action

OrchestratorがConfigured Discovery、Manifest Shape／Atomicity、Container DI、Lazy Runtime、Collision／Recovery／Sensitive境界、Installed Consumer、全GateをReviewする。Accept後はPhase 18 Delivery Planに従いP18-005 Operation Console AdapterのTask Packetを開始する。

## Orchestrator Review

Accepted at `2026-07-22T05:04:09+09:00`。

Configured Root限定Discovery、Schema 1 Manifest、Provider優先のContainer DI、Lazy Runtime、Artifact Recoveryを差分で確認した。Review中にValid Manifestと後発Explicit Commandの衝突までRecovery扱いで黙殺する境界を発見し、Artifact読込だけをRecovery対象へ狭め、同名別ClassはSafe Bootstrap Errorとする修正とIntegration Testを追加させた。

Orchestrator独立再検証はFocused PHPUnit `304 tests／982 assertions`、Mago Format、Deptrac `0 violations`、Management ID Guard、Community Board Scope Guard、`git diff --check`が成功した。WorkerのFull PHPUnit `1535 tests／6093 assertions`、全Consumer、Website `42 tests`／Build結果もReportと差分から妥当と判断し、P18-004をAcceptedとする。
