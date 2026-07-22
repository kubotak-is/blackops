# Application Ergonomics

## Goal

Installed ApplicationがBlackOpsのPublic Contractを利用するために繰り返しているEnvironment、Frontend Transport、Console登録、Session Authenticationの定型実装をFrameworkまたは任意Integrationへ移し、Application CodeをDomain、Policy、UI Projectionへ集中させる。

Phase 18はHeadless CoreをSvelteKitや特定のUser／Password／UI実装へ固定しない。Framework-neutralな生成物、Build-time Discovery、型付きConfiguration、Opt-in Session Capabilityを使い、Community Boardで手動配線の削減と安全境界を検証する。

## Scope

- Public Readonly `Environment` Snapshotと型付きAccessor
- ArrayまたはEnvironment Closureを返すConfiguration File
- Framework-neutralなGenerated Frontend Bound Client Factory
- Composer直接依存とFirst-party Integrationの責任分界
- Symfony `#[AsCommand]`のBuild-time DiscoveryとCompiled Container DI
- 明示的な`#[ConsoleCommand]` Operation Adapter
- Framework同梱のOpt-in `BlackOps\Auth\Session`と`make:auth` Generator
- Quickstart、Composer Skeleton、Community Board、Guide、Consumer Gate

次は対象外とする。

- SvelteKit固有Package、Hook、Store、Form Helper
- DBAL Query Builder、Repository Base Class、ORM Wrapper
- JWT、OAuth、OpenID Connect、Social Login、MFA
- User Model、Password Policy、Registration Policyの標準化
- 全Operationの自動Console公開
- Consoleの位置引数、対話Prompt、Table Renderer、Shell Completion
- Session Authentication用の別Package／Repository／Packagist Publication
- Idempotency Storage、Outbox、Relay、Retry Policy
- Documentation Website／Community Boardの外部公開

## Responsibility Boundary

| Area | Framework／Integration | Application |
| --- | --- | --- |
| Environment | Process／Dotenvから渡された値のSnapshot、型付き読取、Safe Failure | Dotenv読込、Secret Injection、値の選定 |
| Configuration | Closure評価、Shape検証、Compiled Snapshot | Connection、Path、Retention等の値 |
| Frontend Client | Operation型、Request、Fetch／Status／Wait、Transport Binding | Server-only配置、Session Header、Safe View Model |
| Composer | Public Package境界、任意Integration | 直接ImportするPackageの明示Dependency |
| Maintenance Command | Discovery、Container DI、Name Collision検証 | Command本体、運用Policy |
| Operation Command | 明示公開、Value Binding、Lifecycle、Safe Output | 公開名、Operation、Console Actor Policy |
| Session Auth | Token Lifecycle、Hash、TTL、Revocation、DBAL Store、Credential抽出 | User、Password、Registration、Route／UI、Authorization Role |

## Environment Snapshot

Public型は`BlackOps\Application\Environment`へ置き、`#[PublicApi]`を持つ`final readonly class`とする。同じInstanceを全Configuration Closureへ渡すが、Environment全体をCompiled ArtifactやContainer Serviceへ保存しない。Application Serviceは検証済みConfigurationを依存として受け取る。

```php
namespace BlackOps\Application;

final readonly class Environment
{
    /** @param array<array-key, mixed> $variables */
    public function __construct(array $variables);
    public function string(string $name, ?string $default = null): string;
    public function optionalString(string $name): ?string;
    public function int(string $name, ?int $default = null): int;
    public function positiveInt(string $name, ?int $default = null): int;
    public function bool(string $name, ?bool $default = null): bool;
}
```

- Constructorへ渡す値は`array<string, string>`だけを受理し、外部MutationからCopyして保持する
- Constructor／Accessorの不正入力はRaw Valueを含まない`InvalidArgumentException`とし、Application Bootstrapは既存`ApplicationBootstrapException`へ包む
- `string()`、`int()`、`positiveInt()`、`bool()`は値がなくDefaultもない場合に起動失敗する
- `optionalString()`は未定義なら`null`、定義済みの空文字列は空文字列を返す
- `int()`は10進整数、`positiveInt()`は1以上だけを受理する
- `bool()`は大文字小文字を区別せず`true`／`false`と`1`／`0`だけを受理する
- DefaultはEnvironmentに値がない場合だけ使い、不正な値をDefaultへFallbackしない
- Failure MessageはVariable名と期待型を含めてよいが、Raw Valueを含めない
- Array全体を返すPublic API、Runtime Mutation、Global Singleton、Global `env()` Helperを追加しない

`withEnvironment()`へ値を明示しない場合の既存Process Environment取得は維持する。DotenvはInstalled ApplicationのBootstrap責務であり、Frameworkは`.env` Fileを暗黙に探索しない。

## Configuration Closure

Configuration Fileは次のいずれかを返す。

```php
return [
    'build' => [
        'application_build_id' => 'local',
    ],
];
```

```php
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'build' => [
        'application_build_id' => $env->string('APP_BUILD_ID', 'local'),
    ],
];
```

- `withConfiguration()`はDirectoryとFile集合を登録し、Closure評価は`create()`まで遅延する
- `withEnvironment()`と`withConfiguration()`の呼出順に結果を依存させない
- 全Configuration Closureは最終Environment Snapshotで一回だけ評価する
- Arrayを返す既存Configurationは互換として維持する
- Closure以外のCallable、引数Shape不正、Array以外の戻り値を起動時に拒否する
- Configuration File名はSafe Diagnosticsへ含めてよいが、Absolute Path、Raw Environment Value、Throwable DetailをHTTPへ出さない
- Worker ModeでRequest／OperationごとにFile再読込、Closure再評価、Environment再取得をしない
- Example／SkeletonのConfiguration Fileは`$_ENV`／`$_SERVER`／`getenv()`を直接参照しない

## Frontend Bound Client

Frontend Generatorは既存のOperation Module、DTO、Strict Decoderに加え、次のFactoryを生成する。

```ts
const blackops = createBlackOpsClient({
  baseUrl: 'http://blackops:8080',
  fetch: event.fetch,
  headers: { Authorization: `Bearer ${token}` },
});

const result = await blackops.CreatePost.fetch({
  title: 'Hello',
  body: 'First post',
});
```

Factoryは次を満たす。

- SvelteKitをImportせず、SvelteKit Server `fetch`とGlobal FetchをApplication-owned Adapterなしで受け取る
- Base URL、Fetch、Default Header、Credential Modeを一度Bindingする
- `.fetch()`、`.status()`、`.wait()`、`.toRequest()`、`.url()`の既存型とStrict Decoderを再利用する
- Call単位でSignal、Header、Credential Mode、Idempotency Keyを指定できる
- DefaultとCall HeaderをCase-insensitiveにMergeし、Content-Type等のGenerated Protected Headerを上書きさせない
- Idempotency Keyは1文字以上255文字以下の空白／Control Characterを含まないPrintable ASCIIとする。POST／PUT／PATCH／DELETEだけへ`Idempotency-Key`として追加し、GET／HEADではClient-side Failureにする。Backend重複制御はPhase 19まで有効にならない
- Factory Optionと生成ObjectはCopyしてFreezeし、呼出し間でMutable Header／Signal／Credentialを共有しない
- Missing Fetch、Invalid Base URL、Invalid Header、Invalid Idempotency KeyはNetwork Call前に既存Safe Transport Resultへする
- Credential、Header Value、Base URL、Raw Response、Thrown ErrorをError Result／String化／Generated Manifestへ反射しない
- Operation ClassのShort NameはClient PropertyとしてCase-insensitiveに一意でなければBuild Errorにする
- Generated Outputは決定的で、Fresh CheckとClient Bundle Guardの対象にする

ApplicationはServer-only Factoryを一つ作り、Domain固有のSafe View ModelとError文言だけを手書きする。Generated ResultをBrowserへそのまま返さない。

## Composer Dependency Boundary

- Application SourceがVendor PackageのClass、Interface、Function、Attributeを直接利用する場合、そのPackageをApplicationの`require`へ明示する
- `blackops/framework`が同じPackageへ依存していることを、Applicationの直接Dependency省略理由にしない
- Applicationから直接ImportしなくなったPackageは、Consumer Testで不要と確認した後に削除する
- BlackOpsはDoctrine DBAL、PSR-7、Symfony Console、Dotenvを名前変更だけのWrapperで包まない
- 任意Integration Packageは独立したCapability、Dependency、Migration、Test、Versioning境界を持つ場合だけ作る
- Root Framework Packageへ任意Integrationを`require`しない

## Application Command Discovery and DI

Application Maintenance CommandはSymfony Consoleの標準`#[AsCommand]`を使う。

- Configured Application Source PathだけをBuild時に走査する
- Attribute付きでInstantiableなSymfony `Command` Classを決定的にDiscoveryする
- Discovery結果をBuild Artifactへ保存し、Production RuntimeでSource Scanしない
- Command ClassをCompiled Container Serviceとして登録し、Constructor DependencyをContainerから解決する
- Attribute Name欠落、重複Name、Abstract Class、Container未解決DependencyはBuild Errorにする
- `config/app.php`の`commands`と`withCommands()`はPackage Command、Factory、Instance、明示Override／追加用に維持する
- Discoveryと明示登録で同じClassは一つへまとめ、異なるClassの同じCommand NameはFail-fastする
- 既存Built-in Command名とProject Command名の予約を維持する
- Runtimeの`new $class()`と引数なしConstructor制約をDiscovery済みCommandへ適用しない

Source Path Configuration、Artifact Schema、Stale Cleanupは既存Operation Discoveryと同じBuild Lifecycleを使い、Application Source外を暗黙に走査しない。実装上のCommand ManifestはSchema 1のPHP Arrayとし、Class、Canonical Name、Description、Alias、Hidden、Help、Usageだけを同じApplication Build IDで保存する。Missing／Invalid／Stale ArtifactではDiscovered Commandだけを未登録とし、Framework `build:compile`によるRecoveryを維持する。Global ListはManifest Metadataだけを使い、Command固有Help／実行時にSymfony `LazyCommand`からCompiled Container Serviceを一度解決する。

## Operation Console Adapter

Public Attributeは`BlackOps\Core\Attribute\ConsoleCommand`へ置き、Operation ClassだけをTargetにする。

```php
#[ConsoleCommand('board:digest')]
final readonly class GenerateWeeklyDigest
{
    public function handle(GenerateWeeklyDigestValue $value): DigestGenerated;
}
```

AttributeはCanonical Command NameとOptional Descriptionだけを保持する。Alias、Position Argument、Prompt、Rendererは初期Capabilityへ含めない。

- AttributeがないOperationをConsoleへ公開しない
- Command Nameは明示値だけを使い、Class名から推測しない
- Build時にOperationValueのPublic Constructor-promoted Propertyをkebab-case Named Optionへ写像する
- Property名、Option名、Symfony共通Option、`--json`のCollisionをBuild Errorにする
- 最初のCapabilityは既存HTTP Scalar Binderが扱うScalar／Nullable Scalarだけを受理する
- Sensitive Property、Array、Object、Union、Intersection、Variadicを持つOperationはConsole公開をBuild Errorにする
- すべてのValue Optionは値付きNamed Optionとし、位置引数、Prompt、Shell履歴へ配慮が必要なSecret入力を実装しない
- Binding、Validation、Authorization、Execution Strategy、Journal、Outcomeを既存Operation Lifecycleで実行する
- Inline CompletedはSafe Outcome、Voidは完了、Deferred AcceptedはOperation IDを表示して待機せず終了する
- `--json`は安定Schemaの一行JSON、既定は人間向けSafe Textを出力する
- Exit Codeは成功／受付を0、CLI Shape／Binding／Validationを2、Rejected／Internal／Transport Failureを1とする
- OutcomeのSensitive Projection、Rejected Safe Code、Internal Detail非表示をHTTP／Statusと同じContractで維持する

Value Option名はProperty名をASCII kebab-caseへ変換する。`userId`は`--user-id`、`URLValue`は`--url-value`、`user_id`は`--user-id`となる。同一Optionへの変換、Symfony Global Option、`--json`との衝突はBuild Errorとする。BooleanもFlagにせず`true`／`false`の値を要求する。省略はPHP Constructor Defaultがある場合だけDefaultを使い、NullableだけではRequiredを解除しない。

Command ManifestはP18-004のSchema 1からSchema 2へ上げ、既存`commands`に加えて`operation_commands`を持つ。Operation EntryはApplication Build IDとOperation Manifestに対応するType ID、Definition、Value、Outcome、Strategy、Command Name／Description、順序付きOption Metadataを保存する。Source Path、Runtime Value、Credentialは保存しない。Schema 1／Missing／Invalid／Stale ArtifactではDiscovered Application CommandとOperation Commandを未登録にし、Framework `build:compile`による再生成を維持する。

`--json`の一行JSONは`schemaVersion: 1`を持つ。Completedは`status`とSafe `outcome`、Acceptedは`status`、`operationId`、`acceptedAt`、Rejectedは`status`、Optional `operationId`、`category`、`code`、Valueを含まない`violations`、Internal Errorは`status: error`、`code: internal_error`、Optional `operationId`だけを返す。Human Outputも同じ情報だけを表示する。Unknown／Missing／Invalid OptionとValue ValidationはExit 2、Completed／Acceptedは0、Authorization／Business RejectionとInternal／Transport Failureは1とする。

Console入口はHTTP Credentialを再利用しない。Public APIは次の最小Interfaceとする。

```php
namespace BlackOps\Console;

use BlackOps\Core\ActorRef;

interface ConsoleActorProvider
{
    public function actor(): ?ActorRef;
}
```

ApplicationはService ProviderでこのInterfaceをBindingできる。Frameworkは返されたActorをOrigin／Authorizationとし、Execution Actorを`new ActorRef('console-runtime', 'system')`として`ActorContext`を構成する。Provider未登録または`null`ではOrigin／Authorizationを持たず、Authorizationが必要なOperationは既存PolicyでDenyする。Provider FailureはSafe Internal Errorへ閉じる。CLI OptionからActor ID／Typeを受け取らない。

Build後のRuntimeはCommand Manifestと同じBuild IDのOperation Manifest／Compiled Containerだけを使い、Source ScanやAttribute Reflection Fallbackを行わない。Operation Definition、Validation、Authorization、Inline Dispatcher、Deferred Acceptance、Journal、Transaction、Outcome Normalization、Failure Reporting、Connection／Observation CleanupはHTTP Runtimeと同じ内部Composition責務を再利用し、Console固有にLifecycleを再実装しない。

## Session Authentication

Session Authenticationは`blackops/framework`同梱のOpt-in Capabilityとし、Public APIを`BlackOps\Auth\Session`配下に置く。Configuration、Service Binding、MigrationのないApplicationでは有効化しない。

Frameworkが所有する。

- 暗号学的に安全なOpaque Session Token生成
- Raw Tokenを保存しない一方向HashとConstant-time照合
- Issued At、Expires At、Last Used At、Revoked At
- Rotation、Logout Revocation、Expired Cleanup
- Doctrine DBAL Session StoreとForward-only Migration
- 別々のBearer／Cookie HTTP Authenticator Adapter
- Opaque `identity_id`を現在の`ActorRef`へ解決する`SessionIdentityProvider`
- Safe Failure、Clock／Random注入、Concurrent Rotation／Revocation Test

Public `SessionManager::authenticate()`はRaw Tokenを受け、Store Lookup／Conditional Touchと`SessionIdentityProvider::resolve()`を一つのFramework Serviceで完了して`?ActorRef`を返す。Bearer／Cookie AdapterがInternal Portへ`instanceof`する構造はとらない。Provider ThrowableはInvalid Sessionへ丸めない。

Opt-in登録はPublic `SessionServiceProvider::bearer()`／`::cookie()`で行い、ApplicationがInternal ImplementationをImportしない。Cookie名は検証済み`SessionCookieName`としてAdapterへ渡すが、Cookie発行／Attribute／CSRFは所有しない。

Applicationが所有する。

- User Entity／Repository／Provider
- Password Hash ParameterとCredential Verification
- Registration、Email Policy、Account State
- Role／PermissionとOperation Authorization
- Cookie Name／Attribute、CSRF、Login／Logout／Registration Route、UI

`php blackops make:auth`はBuilt-in Command／Generatorとして、上記Application責務の接続点とAPI Authentication Starterを生成する。User／Repository／Password／Registration Policy／Identity Provider、Register／Login／Logout Operation、Service Provider、Configuration、User／Session Migrationを含め、HTML／SvelteKit／Cookie発行／CSRFは生成しない。

生成先は`app/Domain/Identity`、`app/Infrastructure/Identity`、`app/Feature/Identity`へ分離する。DomainはBlackOps／Doctrine／Symfonyに依存せず、Email canonicalization、Password Hash／Verify／Rehash、Registration可否、Duplicate／Invalid Credential判断を`IdentityService`とDomain Policyへ置く。OperationはDomain FailureからStable Rejectionへの写像とSession発行／失効だけを担当する。

`config/auth.php`は同じ`Environment` SnapshotからRegistration Enabled、Session TTL、Touch Intervalを型付きで読み、`auth.services`を既存`app.services`の後へMergeする。File欠落時は既存Registrationを変えない。

Generator Version 1は`config/auth.php`へMarkerを持つ。Target 0件だけをFirst Runとし、全Target＋Current Markerは内容非比較のNo-op、Partial／Unknown StateはZero-write Errorとする。`--force`はFramework-owned `config/auth.php`、`AuthServiceProvider.php`、`ApplicationSessionIdentityProvider.php`だけを更新し、Domain、Repository、Operation、User／Session Migrationを上書きしない。

Register／LoginはPublic `EphemeralOutcome`を返すRoute付き明示Inline Operationとし、Passwordを含むReceived ValueとRaw Tokenを含む実OutcomeをCanonical Journal／Outcome Store／Statusへ保存しない。Lifecycleは空Dataで記録する。LogoutもCurrent Raw Token Inputを保存しないPropertyなしEphemeral Outcomeを返し、安全に失効させる。

GeneratorはAll-or-nothing Preflightと同一VersionのNo-op Successを保証する。既存Fileを無断上書きせず、`--force`でもApplication-owned User／Repository／Password／Policy／Operation／Migrationを置換しない。

## Community Board Migration

Phase 18の最後にCommunity Boardを新Contractへ移行する。

- `$_ENV` ConfigurationをEnvironment Closureへ置換する
- SvelteKitの`operationFetch`、Base URL、Call Option、Credential Header重複をBound Client Factoryへ移す
- Safe View Model、Form Error、Redirect、Session-to-Credential接続はApplicationへ残す
- Seed等のMaintenance Commandを`#[AsCommand]`とConstructor DIへ移す
- 一つの安全なOperationを`#[ConsoleCommand]`で公開し、Inline／Deferred ContractをConsumerで検証する
- IdentityのToken Lifecycle／DBAL Session Storeを`BlackOps\Auth\Session`へ移し、User／Password／Registration／UIをApplicationへ残す
- Applicationが直接ImportしないDependencyだけを`composer.json`から削除する
- Manual／Generated／Vendor／Runtime File数と主要Identity／Frontend配線行数をBefore／AfterでReportする

## Compatibility and Security

- Stable Releaseの既存Array Configuration、明示Command登録、Unbound Generated Operation APIを維持する
- Experimental `main`ではGenerated Artifact Schemaを上げ、旧ArtifactをFresh Checkで拒否できる
- Secret、Credential、Raw Token、Environment Value、Authorization HeaderをJournal、Outcome、Generated Artifact、Command Output、Reportへ出さない
- Build FailureはClass／Property／Configuration名を含めてよいが、Source全文、Raw Value、Absolute Pathを含めない
- Worker ModeでEnvironment、Header、Actor、Command InstanceがRequest／Attempt間に漏れない
- External PublicationとDeployを行わない

## Acceptance Criteria

- [ ] Typed EnvironmentとConfiguration Closureが一回だけ評価され、Array Configurationが回帰しない
- [ ] Quickstart／Skeleton／Community BoardのConfigurationから直接`$_ENV`参照がなくなる
- [ ] Bound ClientがSvelteKit Server `fetch`をAdapterなしで受け取り、全Generated Operationを型付きで呼べる
- [ ] Browser BundleとSafe ResultへCredential、Base URL、Raw Errorが出ない
- [ ] `#[AsCommand]`がBuild時Discoveryされ、Constructor DependencyをCompiled Containerから受け取る
- [ ] 明示Command登録が維持され、Name Collision／未解決DependencyがFail-fastする
- [x] `#[ConsoleCommand]`付きOperationだけがCLIへ現れ、Binding／Validation／Authorization／Inline／Deferred／Exit Codeを満たす
- [x] `BlackOps\Auth\Session`がFramework同梱のOpt-in CapabilityとしてSession Lifecycleを提供する
- [x] `make:auth`がApplication-owned接続点を安全に生成する
- [ ] Community Boardの手動Transport／Identity／Command配線が削減され、主要Journeyが回帰しない
- [ ] Application Composer Dependencyが直接Import規則と一致する
- [ ] Full Framework／Quickstart／Skeleton／Community Board／Frontend／Website Gateが成功する
- [ ] Documentation WebsiteとCommunity Boardを外部公開しず、Session Authentication用の別Package／Repositoryを作成しない

## Traceability

- Decisions: [D110 Application Ergonomics](../decisions/110-application-ergonomics.md)、[D111 Session Authentication Contract](../decisions/111-session-auth-package-contract.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
- Bootstrap: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
- Console: [Public Console Kernel Composition](48-public-console-kernel-composition.md)
- Frontend: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Reference Application: [Full-stack Reference Application](71-full-stack-reference-application.md)
