# P18-004: Application Command Discovery and DI

Status: Accepted

## Goal

Configured Application SourceからSymfony `#[AsCommand]`付きMaintenance Commandを`build:compile`時に発見し、同じBuild IDのCommand ManifestとCompiled Containerへ固定する。RuntimeではSource Scanや`new $class()`を行わず、SymfonyのLazy Command境界からContainer-resolved Commandを実行し、ConstructorへApplication Service Providerの依存を注入できるようにする。

既存の`config/app.php` `commands`と`withCommands()`は明示的なApplication／Package Commandの追加、Instance、Override境界として維持する。Framework Built-in、Discovered、Explicit CommandのName／Alias衝突は実行前に拒否し、`build:compile`を壊れた／Missing Command Artifactから復旧できる入口として残す。

## In Scope

- `app.command_discovery`のConfigured Source Root
- Symfony `#[AsCommand]`付きCommandのBuild-time Discovery
- Versioned Command Manifest Artifact
- `app.build.command_manifest`とBackward-compatible Default
- Discovered CommandのCompiled Container登録／Autowire／Public Service
- Symfony Lazy CommandによるRuntime Container Resolution
- Service Provider Bindingを使うConstructor DI
- Framework／Discovered／Explicit CommandのClass、Name、Alias Collision検証
- Missing／Stale／Invalid Command Artifactから`build:compile`できるRecovery境界
- Explicit `commands`／`withCommands()`互換
- Quickstart Canonical Configuration、Permanent Integration Fixture、Guide／Internal Documentation
- Report、TODO、STATE同期

## Out of Scope

- Public `#[ConsoleCommand]` Operation AttributeとOperation CLI Adapter（P18-005）
- Community Board `app:seed`のDiscovery／DI移行（P18-007）
- Runtime Source Scan、Development Reflection Fallback、Composer Package全体の暗黙Scan
- Symfony ConsoleのWrapper／独自Command Base Class
- Console Actor、Authentication、Authorization、Operation Lifecycle
- Position Argument／Option Mapping、Prompt、Renderer、Shell Completion
- Command用独立Container、Service LocatorのApplication公開
- Existing Built-in CommandのLazy Composition変更
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Specifications and Decisions

- `develop/decisions/068-public-console-kernel-composition.md`
- `develop/decisions/071-operation-authoring-and-discovery.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Files Allowed to Change

### Discovery and Artifact

- New focused types under `src/Internal/Discovery/**` or `src/Internal/Application/**` for Command Source Discovery
- New focused Manifest／Artifact／Codec／File types under `src/Internal/Console/**` or `src/Internal/Application/**`
- Existing `src/Internal/Discovery/PhpSourceFileFinder.php`、`PhpTokenClassScanner.php`、`PhpSourceClassLoader.php` only for responsibility-neutral diagnostics or reuse required by Command Discovery
- `src/Internal/Application/ApplicationBuildConfiguration.php`
- `src/Internal/Application/ApplicationConfigurationSnapshot.php` only if typed discovery access cannot remain a focused resolver
- `src/Internal/Application/ApplicationConfigurationRegistrations.php` only for explicit registration compatibility
- `src/Internal/Application/ApplicationConfigurationLoader.php` only if no new configuration file is added; do not add implicit directory scanning

Reuse existing safe root resolution、token scanning、class source verification. Do not make Operation-specific diagnostics less strict merely to share a class.

### Build and Container

- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php` only if deterministic command metadata requires a mechanical dump adjustment
- New or extracted focused Container Artifact Loader under `src/Internal/Runtime/**`
- `src/Internal/Runtime/ProductionRuntimeArtifactLoader.php` only to reuse the same safe Container load boundary

Application Service Provider definitions win over automatic Command registration for the same Service ID. The discovered class must still resolve to a Symfony `Command` at runtime.

### Console Runtime and Explicit Registration

- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationCommandValidator.php`
- `src/Internal/Application/ApplicationRegistrationValidator.php`
- `src/Internal/Console/LazyFrameworkCommand.php` only if a responsibility-neutral extraction is needed
- New focused Lazy Application Command／Command Container Composer types under `src/Internal/Application/**` or `src/Internal/Console/**`
- Existing Runtime Database／Logging／Transaction Injector only for reuse, not semantic weakening

Use Symfony's existing `LazyCommand` where it satisfies the contract. Do not instantiate a discovered Command to build its metadata or to render the global command list.

### Tests and Fixtures

- New or existing tests under `tests/Internal/Discovery/**`
- New or existing tests under `tests/Internal/Application/**`
- New or existing tests under `tests/Internal/Console/**`
- New or existing tests under `tests/Internal/DependencyInjection/**`
- Focused fixture source under those test directories
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Application/ApplicationTest.php` only if Public API inventory changes（Public PHP API追加は想定しない）

Permanent Integration Fixtureは、`#[AsCommand]` CommandがRequired ConstructorでApplication-owned Interfaceを受け、Service Provider Bindingから解決されることを検証する。Community Board Sourceは使用／変更しない。

### Installed Consumers

- `examples/quickstart/config/app.php`
- QuickstartのApplication／Console Test Source only if a minimal discovered command fixture is necessary;既定ではPermanent Framework Fixtureを優先する
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-create-project.sh`
- Skeleton Publication scripts only if canonical Quickstart Config inventory changes mechanically

Quickstartにはcanonical `command_manifest`と`command_discovery`を設定する。新しいUser-facing Sample Commandを追加する必要はない。Community Board Config／Command／Service ProviderはP18-007まで変更しない。

### Documentation and Orchestration

- `docs/guide/application-bootstrap.md`
- `docs/guide/configuration.md`
- `docs/guide/project-cli.md`
- `docs/guide/directory-structure.md` only if canonical config／artifact inventory changes
- `docs/guide/core-api.md` only if Public PHP API changes（想定しない）
- `docs/internal/application-bootstrap.md`
- `docs/internal/runtime-container.md`
- `docs/internal/installed-application-status.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md` only for non-semantic implementation clarification
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P18-004-application-command-discovery-and-di.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Configuration Contract

Canonical Configurationは`config/app.php`に次を持つ。

```php
return [
    'build' => [
        'application_build_id' => 'my-app',
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
        'command_manifest' => dirname(__DIR__) . '/var/build/commands.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
    'command_discovery' => [
        dirname(__DIR__) . '/app',
    ],
    'services' => [],
    'commands' => [],
];
```

- `app.command_discovery`欠落時はDiscoveryなし
- 値はIterableな絶対Existing Directory Path。重複実Pathは一度だけScanする
- Symlink Escape、Unreadable Source、Class／File不一致を既存Discoveryと同じSafe Errorで拒否する
- Application Root全体やVendorを暗黙にScanしない。設定されたRootだけをBuild／Application Operation ListのうちBuildでScanする
- HTTP、Worker、`Application::create()`、global `list`はSource Scanしない
- `app.build.command_manifest`はCanonicalでは明示する
- 既存Application互換のため、`command_manifest`欠落時はConfigured Container Artifactと同じDirectoryの`commands.php`をDefaultとする
- Explicit `app.commands` Listと`withCommands()`は既存形を維持する

## Discovery Contract

CandidateはConfigured Root内のPHP Classで、Symfony `#[AsCommand]` Attributeを一つ持つものとする。

- Attribute付きClassは`Symfony\Component\Console\Command\Command`のSubclassでなければBuild Error
- Abstract、Trait、Interface、Enum、Anonymous、Non-instantiable ClassはBuild ErrorまたはCandidate外を曖昧にせず決定的に扱う。少なくともAttribute付きAbstract ClassはBuild Error
- AttributeなしCommand Classは自動登録しない
- Attributeは継承で暗黙適用せず、Candidate Class自身への付与だけを扱う
- 複数`#[AsCommand]`、Missing／Empty／Invalid Name、Invalid Alias、Canonical NameとAliasの自己衝突はBuild Error
- Name、Description、Aliases、Hidden、Help、UsagesはAttribute Metadataから読む。Metadata取得のためCommand Constructorを呼ばない
- Class、Name、Aliasの順序を決定的にし、同じClassは一度だけ扱う
- Class Name、Command Name、AliasはArtifactへ保存してよい。Absolute Source Path、Source全文、Constructor Argument、Configuration Value、Credentialは保存しない
- PHP Parse／Load／Reflection／Attribute ErrorはSafe Build Failureへ閉じ、Absolute PathやRaw Throwable DetailをConsoleへ出さない

Symfony `AsCommand`の`name`に含まれるCanonical Name／Alias／Hidden表現は現在のLocked Symfony Contractに従って正規化する。独自Attributeや独自Command Base Classは追加しない。

## Command Manifest Contract

Command ManifestはPHP Array Artifact、Schema Version 1とし、少なくとも次を持つ。

```php
return [
    'schema_version' => 1,
    'application_build_id' => 'my-app',
    'commands' => [
        [
            'class' => App\Console\SeedCommand::class,
            'name' => 'app:seed',
            'description' => 'Seed application data.',
            'aliases' => [],
            'hidden' => false,
            'help' => null,
            'usages' => [],
        ],
    ],
];
```

- EntryはCanonical Name、次にClassの決定的順序
- WriterはTarget Directory存在を要求し、Temporary File＋Renameで置換する
- ReaderはSchema、Build ID、Exact Shape、Type、Duplicate Class／Name／Aliasを検証する
- Configured Application Build IDとArtifact Build ID不一致はStaleとする
- Build開始後にDiscovery／Container Compileが失敗した場合、今回の不完全Command Manifestを公開しない
- Discovery Rootを空にした次の成功Buildは空Manifestで旧Commandを消す
- Command ManifestはRuntime Source File PathやSource Fingerprintを保存しない

## Collision and Explicit Registration Contract

予約対象はFramework Built-in Canonical NameとそのAlias、Discovered Name／Alias、Explicit Name／Aliasの全組合せとする。

- 同じDiscovered Classは一つへDeduplicateする
- DiscoveredとExplicitが同じClassならExplicit Registrationを優先し、Consoleへ一つだけ登録する
- 異なるClassのCanonical Name／AliasがCase-sensitiveに同一ならBuild／Bootstrap Error
- Aliasが別CommandのCanonical Name、別Alias、Framework Built-inへ衝突してもError
- 旧`blackops:*`はFramework予約へ戻さず、Application Commandが引き続き利用できる
- Explicit Instance／引数なしClassの既存挙動を維持する
- Discovered Classへ既存の引数なしConstructor制約を適用しない
- Application Service Providerが同じClass Serviceを明示定義した場合はApplication定義を尊重する
- Command ServiceがContainer Compile時に解決不能ならBuild Error。RuntimeでClass不一致ならSafe Failure

Explicit Factoryを既存Public Contractが現在受理していない場合、本Taskで新しい曖昧なCallable Shapeを追加しない。`config/app.php` `commands`／`withCommands()`のInstance／Class追加境界を維持し、Factory拡張の要否はReportへ記録する。Closure ConfigurationとCommand Factoryを混同しない。

## Runtime and Recovery Contract

- Successful Build後の新しいProcessではCommand ManifestからSymfony Lazy Commandを登録する
- Global `php blackops list`はAttribute MetadataだけでDiscovered Name／Description／Alias／Hiddenを表示し、Command Constructor、Container Service、Database Connectionを解決しない
- Discovered Command実行またはそのCommand固有Help／Definition取得時だけCompiled ContainerをLoadし、Command Serviceを一回解決する
- ContainerはHTTP／Workerと同じCompiled Artifactを使い、Application Service Provider Bindingを再利用する
- Database／Logger／Transaction Synthetic ServiceがCommand Dependency Graphに必要な場合だけ、既存Application Configurationから安全に注入できるCompositionを提供する。Kernel構成でDatabase Connectionを開かない
- Command Instance、Container、Database Managerを異なる`Application` InstanceまたはProcessへ共有しない
- Missing／Invalid／Stale Command ManifestはFramework Built-in `build:compile`を隠したり壊したりしない。Source ScanへFallbackせず、Discovered Commandを未登録としてRecovery Build可能にする
- Valid Manifest＋Missing／Invalid ContainerではCommand名をList可能だが、実行時にCredentialを含まない`ApplicationBootstrapException`へ閉じる
- RuntimeはCommand Class File、Discovery Root、Composer MetadataをScanしない
- Command実行時にMigration、DDL、Worker、Schedulerを暗黙実行しない

## Required Evidence

- Configured RootだけのDiscoveryと重複Root Deduplication
- AttributeなしCommand非登録
- Required Constructor CommandをInstance化せずManifest化
- Interface DependencyをService Provider BindingからContainer Injectionして実行
- Same Class Dedup、Explicit Same Class Override、Different Class Name／Alias Collision
- Framework Name／Alias Collision、旧`blackops:*`非予約
- Missing Name、Invalid Name／Alias、Multiple Attribute、Attribute on Non-command／Abstract Class
- Unresolved Constructor DependencyをBuild Error
- Manifest Schema／Build ID／Shape／Determinism／Atomic Replace／Empty Stale Cleanup
- Missing／Invalid／Stale Manifestでも`build:compile`へ到達可能
- Valid Manifestだけで`list`がCommand Constructor／Database／Containerを解決しない
- Command固有Helpと実行時だけLazy Resolve
- Valid Manifest＋Missing ContainerのSafe Failure
- Runtime Source Scan 0のGuard
- Secret／Absolute Path／Raw Throwable DetailがArtifact／Console Errorへない
- Existing Explicit Command、Framework List／Help、HTTP／Worker Containerが回帰しない

## Acceptance Criteria

- [ ] `app.command_discovery`のConfigured Rootだけから`#[AsCommand]`をBuild時Discoveryする
- [ ] Command Manifest Schema 1が同じApplication Build IDで決定的に生成／検証される
- [ ] Discovered CommandがCompiled Container ServiceとしてAutowireされ、Required Constructor Dependencyを受け取る
- [ ] RuntimeはSource Scan／`new $class()`を行わずSymfony Lazy CommandからContainer解決する
- [ ] Global ListはCommand Constructor、Container、Database、Artifact必須化なしでFramework Commandを維持し、Valid Manifest時はDiscovered Metadataを表示する
- [ ] Missing／Invalid／Stale Manifestから`build:compile`で復旧できる
- [ ] Explicit `commands`／`withCommands()`のInstance／Class、Dedup、Former Alias利用が回帰しない
- [ ] Framework／Discovered／ExplicitのName／Alias CollisionとUnresolved DIがBuild／Bootstrap時にFail-fastする
- [ ] Quickstart Canonical Config、Skeleton、Framework Updateが新Artifact境界と互換になる
- [ ] Community Board Source／Config／Command／Service Providerを変更しない
- [ ] Mago Format／Lint／Analyze、PHPUnit、Deptrac、Quickstart、Skeleton、Framework Update、Website、Management ID Guard、Diff Checkが成功する
- [ ] Documentation Website／Community Boardを外部公開しない
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Application tests/Internal/Console tests/Internal/DependencyInjection tests/Internal/Discovery
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website test
mise exec -- pnpm --dir docs/website build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Permanent FixtureでDB Runtimeを必要としない場合、PostgreSQL接続をConstructor DI成功の前提にしない。Consumer Script名がRepository内で異なる場合は同じ責務の既存Commandへ置き換え、Reportへ記録する。Generated／Build／Dependency ArtifactはTask完了前にCleanupする。Full Commandが環境理由で実行できない場合は未実行理由を記載し、代替で成功扱いにしない。

## Expected Report

`develop/orchestration/reports/P18-004-application-command-discovery-and-di.md` に次を記録する。

- Summary
- Changed Files
- Configuration and Discovery Contract
- Command Manifest Schema and Lifecycle
- Container DI and Lazy Runtime Evidence
- Collision／Recovery／Sensitive Matrix
- Compatibility and Installed Consumer
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
