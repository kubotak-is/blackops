# Application Ergonomics

## Goal

Installed ApplicationがBlackOpsのPublic Contractを利用するために繰り返しているEnvironment、Frontend Transport、Console登録、Session Authenticationの定型実装をFrameworkまたは任意Integrationへ移し、Application CodeをDomain、Policy、UI Projectionへ集中させる。

Phase 18はHeadless CoreをSvelteKit、Doctrine DBAL、Session Authenticationへ固定しない。Framework-neutralな生成物、Build-time Discovery、型付きConfiguration、任意Packageを使い、Community Boardで手動配線の削減と安全境界を検証する。

## Scope

- Public Readonly `Environment` Snapshotと型付きAccessor
- ArrayまたはEnvironment Closureを返すConfiguration File
- Framework-neutralなGenerated Frontend Bound Client Factory
- Composer直接依存とFirst-party Integrationの責任分界
- Symfony `#[AsCommand]`のBuild-time DiscoveryとCompiled Container DI
- 明示的な`#[ConsoleCommand]` Operation Adapter
- 任意の`blackops/session-auth` Packageと`make:auth` Generator
- Quickstart、Composer Skeleton、Community Board、Guide、Consumer Gate

次は対象外とする。

- SvelteKit固有Package、Hook、Store、Form Helper
- DBAL Query Builder、Repository Base Class、ORM Wrapper
- JWT、OAuth、OpenID Connect、Social Login、MFA
- User Model、Password Policy、Registration Policyの標準化
- 全Operationの自動Console公開
- Consoleの位置引数、対話Prompt、Table Renderer、Shell Completion
- Session Auth PackageのPackagist／GitHub外部Publication
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
    public function string(string $name, ?string $default = null): string;
    public function optionalString(string $name): ?string;
    public function int(string $name, ?int $default = null): int;
    public function positiveInt(string $name, ?int $default = null): int;
    public function bool(string $name, ?bool $default = null): bool;
}
```

- Constructorへ渡す値は`array<string, string>`だけを受理し、外部MutationからCopyして保持する
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

Source Path Configuration、Artifact Schema、Stale Cleanupは既存Operation Discoveryと同じBuild Lifecycleを使い、Application Source外を暗黙に走査しない。

## Operation Console Adapter

Public Attributeは`BlackOps\Core\Attribute\ConsoleCommand`へ置き、Operation ClassだけをTargetにする。

```php
#[ConsoleCommand('board:digest')]
final readonly class GenerateWeeklyDigest
{
    public function handle(GenerateWeeklyDigestValue $value): DigestGenerated;
}
```

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

Console入口はHTTP Credentialを再利用しない。Authorizationを要求するOperationではApplication-owned Console Actor ProviderがActorを供給し、未登録／失敗時はDenyする。CLI Optionから任意Actor IDを受け取ってなりすまさない。Console Actor Public APIの最小ShapeはTask実装前に既存ActorContextと整合させ、Task Packetで固定する。

## Session Authentication Integration

任意Package名は`blackops/session-auth`とする。Framework Coreの`require`へ追加しない。

Packageが所有する。

- 暗号学的に安全なOpaque Session Token生成
- Raw Tokenを保存しない一方向HashとConstant-time照合
- Issued At、Expires At、Last Used At、Revoked At
- Rotation、Logout Revocation、Expired Cleanup
- Doctrine DBAL Session StoreとForward-only Migration
- Bearer／CookieからCredentialを抽出するHTTP Authenticator Adapter
- Safe Failure、Clock／Random注入、Concurrent Rotation／Revocation Test

Applicationが所有する。

- User Entity／Repository／Provider
- Password Hash ParameterとCredential Verification
- Registration、Email Policy、Account State
- Role／PermissionとOperation Authorization
- Cookie Name／Attribute、CSRF、Login／Logout／Registration Route、UI

`php blackops make:auth`は上記Application責務の接続点を生成する。既存Fileを無断上書きせず、`--force`でもUser Domain Fileを置換しない。生成物は動く最小例とし、Package Internal ClassのCopyをApplicationへ作らない。

Package SourceはFramework Repository内の独立Composer Package境界で開発し、Community BoardはLocal Path Repositoryで検証できる。GitHub／Packagist Publication、Version Tag、External InstallはUserが明示した別Taskまで行わない。

## Community Board Migration

Phase 18の最後にCommunity Boardを新Contractへ移行する。

- `$_ENV` ConfigurationをEnvironment Closureへ置換する
- SvelteKitの`operationFetch`、Base URL、Call Option、Credential Header重複をBound Client Factoryへ移す
- Safe View Model、Form Error、Redirect、Session-to-Credential接続はApplicationへ残す
- Seed等のMaintenance Commandを`#[AsCommand]`とConstructor DIへ移す
- 一つの安全なOperationを`#[ConsoleCommand]`で公開し、Inline／Deferred ContractをConsumerで検証する
- IdentityのToken Lifecycle／DBAL Session Storeを`blackops/session-auth`へ移し、User／Password／Registration／UIをApplicationへ残す
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
- [ ] `#[ConsoleCommand]`付きOperationだけがCLIへ現れ、Binding／Validation／Authorization／Inline／Deferred／Exit Codeを満たす
- [ ] `blackops/session-auth`がCore非依存の任意PackageとしてSession Lifecycleを提供する
- [ ] `make:auth`がApplication-owned接続点を安全に生成する
- [ ] Community Boardの手動Transport／Identity／Command配線が削減され、主要Journeyが回帰しない
- [ ] Application Composer Dependencyが直接Import規則と一致する
- [ ] Full Framework／Quickstart／Skeleton／Community Board／Frontend／Website Gateが成功する
- [ ] Documentation Website、Community Board、Session Auth Packageを外部公開しない

## Traceability

- Decision: [D110 Application Ergonomics](../decisions/110-application-ergonomics.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
- Bootstrap: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
- Console: [Public Console Kernel Composition](48-public-console-kernel-composition.md)
- Frontend: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Reference Application: [Full-stack Reference Application](71-full-stack-reference-application.md)
