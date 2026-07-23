# Application Runtime and Bootstrap

## Purpose

BlackOpsはInstalled ApplicationのEnvironment File Bootstrap、Classic SAPI、FrankenPHP Worker Mode、UUIDv7生成に共通する定型配線をFirst-party Runtime Capabilityとして提供する。Default ApplicationはVendor Runtime Classを直接Importせず、Application固有CodeをConfiguration、Domain Policy、Infrastructure Adapterへ集中させる。

DBAL Query、Repository、Migrationは本Contractの対象ではない。ApplicationがDoctrine DBAL／Migrations APIを直接利用する場合、ApplicationのDirect Dependencyを維持する。

## Environment File Bootstrap

`BlackOps\Application\ApplicationBuilder`へ次のPublic Methodを追加する。

```php
withEnvironmentFile(?string $path = null): self
```

引数省略時は`<application-base>/.env`をOptional Environment Fileとして扱う。Methodは現在のProcess Environmentを先に取得し、存在するEnvironment Fileの値を不足Keyだけへ加え、Process Environmentを常に優先する。結果は`array<string, string>`へ正規化し、既存Public `Environment`へ一度だけ渡す。

- Environment Fileが存在しない場合はLocal／ProductionともFailureにしない
- 存在するPathがDirectory、Unreadable、Parse不能、不正なName／Valueを含む場合はSafe `ApplicationBootstrapException`にする
- ErrorへEnvironment Value、Credential、File内容、ParserのRaw Throwableを含めない
- Parse／Shape検証に成功したResolved SnapshotはBootstrap時に一度だけ`$_ENV`へ同期する。Process Environmentの値を優先し、`putenv()`は変更しない。Parse／Shape Failureでは`$_ENV`を部分更新しない
- `create()`ごとにEnvironment Sourceを一度だけ解決し、同じApplication Instanceでは再読込しない
- Request、Operation、Console Command、Worker IterationごとにFileまたはProcess Environmentを再読込しない
- Environment値をConfiguration Snapshot、Compiled Container、Manifest、Generated Source、Logへ保存しない
- Dotenvの具体実装はFramework Internalとし、Public SignatureへVendor型を露出しない

既存`withEnvironment(?array $variables = null)`を維持する。Array指定はTest、External Secret Loader、Custom Bootstrapが解決済み値を渡す境界であり、引数省略はProcess EnvironmentだけをSnapshotする。`withEnvironmentFile()`と`withEnvironment()`を同じBuilderへ複数回指定した場合は、最後に明示したEnvironment Sourceが置き換える。`withConfiguration()`との呼出順はConfiguration評価結果を変えない。

Default Bootstrapは次の形とする。

```php
use BlackOps\Application\Application;

return Application::configure(dirname(__DIR__))
    ->withEnvironmentFile()
    ->withConfiguration()
    ->create();
```

ApplicationがVault、Cloud Secret Manager、独自Dotenv実装等を使う場合は、外部Loaderで値を解決して`withEnvironment($variables)`へ渡し、利用したPackageをApplication Direct Dependencyへ明示する。

## Framework-owned SAPI Runtime

FrameworkはPublic `BlackOps\Http\SapiRuntime`を提供する。

```php
namespace BlackOps\Http;

use BlackOps\Application\Application;

final class SapiRuntime
{
    public static function run(Application $application): void;
    public static function runWorker(Application $application): void;
}
```

Classic Entrypointは次の形とする。

```php
use BlackOps\Application\Application;
use BlackOps\Http\SapiRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
SapiRuntime::run($application);
```

FrankenPHP Worker Entrypointは最後の呼出だけを`SapiRuntime::runWorker($application)`へ変える。EntrypointはNyholm、Laminas、FrankenPHP Loop、Safe Error Response、Environment Restore、GCを実装しない。

### Classic Contract

`run()`はFramework既定PSR-17 Factory、PHP GlobalからのServer Request生成、`Application::http()` Handler呼出し、SAPI Response Emitを一回だけ行う。

- Request生成、Handler、Response Emitの予期しないThrowableをSafe Failure境界へ閉じる
- Header送信前ならStatus 500、`application/json`、固定Body `{"status":"error","code":"internal_error"}`を返す
- Header送信後またはSafe Response Emit失敗時はResponse Body、Throwable Message、Traceを追加出力しない
- Error Logは固定文脈と必要最小限のThrowable Classだけを記録し、Credential、Request Body、Header Value、Raw Messageを含めない
- Migration、Build、Authentication、CORS、Application固有Error Projectionを追加しない

### FrankenPHP Worker Contract

`runWorker()`はApplication、Handler、Request Factory、Emitter、Process Environment BaselineをLoop開始前に一度だけ構成し、`frankenphp_handle_request()`へRequest Callbackを渡す。

各RequestはClassicと同じRequest生成、Handler、Emit、Safe 500 Contractを使う。Request成功／失敗の両方で次を実行する。

- `$_ENV`をLoop開始時のstring-key／string-value Snapshotへ復元する
- Frameworkの既存Request／Execution／Observation／Connection Cleanupを完了する
- `gc_collect_cycles()`を実行する
- Request固有Objectを次Iterationへ保持しない

一RequestのThrowableでWorker Loopを終了しない。FrankenPHP Functionが利用できない場合はLoopへ入らず、安全なRuntime Failureにする。FrameworkはApplication Serviceの任意PropertyやStatic Stateを推測してResetしない。

`Application::http(): RequestHandlerInterface`は削除または非推奨化しない。Custom Server Adapter、Test、Application-owned Outer Routerは同じPSR-15 Handlerを直接利用できる。Vendor RuntimeをApplicationが直接組み立てることも許可し、その場合は利用PackageをApplication Direct Dependencyへ明示する。

## Public UUIDv7 Generator

Frameworkは次のPublic Contractを提供する。

```php
namespace BlackOps\Identifier;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface Uuidv7Generator
{
    public function generate(): string;
}
```

Framework Application ContainerはDefault Serviceを`Uuidv7Generator`へBindingする。DefaultはCanonical lowercase RFC 4122 UUIDv7文字列を返し、Symfony UID等のVendor型をPublic Signatureへ露出しない。

ContainerはDefault／明示OverrideのいずれもFramework-owned Validation Adapterを通し、形式・Version・Variantが不正な値をApplication Dataへ渡さず固定Runtime Failureへ閉じる。TestではContractを実装した決定的GeneratorをService Providerから注入できる。

- 一回の`generate()`は新しいUUIDv7文字列を一つ返す
- Default Serviceの無効な結果はApplication Dataへ渡さずFramework Runtime Failureにする
- ApplicationはService ProviderでContractを明示Overrideでき、Testでは決定的Generatorを注入できる
- GeneratorはEntity、Repository、Persistence、Domain ID型、Prefix、Serializationを所有しない
- Domain層はBlackOps Generatorへ依存しない

Application Infrastructure AdapterはGeneratorをConstructor Injectionし、Domain固有Interfaceへ変換する。

```php
use BlackOps\Identifier\Uuidv7Generator;

final readonly class RandomBoardIdentifier implements BoardIdentifier
{
    public function __construct(private Uuidv7Generator $uuids) {}

    public function generate(): string
    {
        return $this->uuids->generate();
    }
}
```

Framework内部のOperation ID、Attempt ID、Journal ID等は既存の型付きIdentifier Factoryを維持してよい。Public Generator導入を理由にPublic ID型を文字列へ置換せず、Internal Test Clock Contractも破壊しない。

## Dependency Boundary

Default Quickstart／Skeleton／Community BoardがVendorを直接Importしなくなった場合、Consumer GateとComposer Strictの成功後に次のDirect Dependencyを削除する。

- `vlucas/phpdotenv`
- `nyholm/psr7`
- `nyholm/psr7-server`
- `laminas/laminas-httphandlerrunner`
- `symfony/uid`

Framework Root PackageはDefault Runtime実装に必要なPackageを所有する。ApplicationがCustom Bootstrap、Custom SAPI Adapter、Symfony UID固有機能を直接利用する場合は、そのApplicationのDirect Dependencyへ明示する。

`doctrine/dbal`と`doctrine/migrations`はApplication Repository、Seeder、MigrationがVendor APIを直接利用するため削除しない。Dependencyを隠すだけのQuery Builder、Repository Base Class、ORM、Migration DSLを追加しない。

## Security and Failure Boundary

- Environment値、`.env`内容、DSN、Token、Cookie、Authorization Header、Request BodyをError、Log、Artifactへ反射しない
- Safe HTTP 500はApplication Throwable DetailとOperation内部情報を含めない
- Worker Failure後も次Requestへ前RequestのEnvironment Mutation、Actor、Tenant、Request、Credentialを引き継がない
- RuntimeはAuthentication、Authorization、CORS、Domain Error文言を推測しない
- Public UUID GeneratorはCryptographic Policy、Business Uniqueness、Entity Lifecycleを保証しない
- Documentation Website／Community Boardを外部公開しない

## Verification

- Environment File Missing／Present／Process Override／Invalid／Secret-safe Failure／Single Snapshot
- `withEnvironment(array)`／Process-only／External Loader互換とConfiguration Call Order
- Classic Request生成／Response Emit／Safe 500／Headers-sent Failure
- Worker複数Request、例外後Request、Environment Restore、Execution Cleanup、Connection Lifecycle、GC、Memory Growth、`max_requests` Restart
- `Application::http()` Custom Adapter／Test互換
- Public API Inventory、Namespace Architecture、Default UUIDv7 Shape／Uniqueness／Override
- Auth GeneratorとCommunity Board Domain／Infrastructure Dependency Boundary
- Quickstart／Skeleton／Community BoardのDirect Import／Composer Dependency Audit
- Existing Volume、Clean Install、Framework Update、Package Export、Website、Full Quality Gate

## Traceability

- Decision: [D114 Application Runtime and Bootstrap Dependency Boundary](../decisions/114-application-runtime-and-bootstrap-dependency-boundary.md)
- Installed Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- Public Bootstrap: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
- HTTP Runtime: [Public HTTP Runtime Configuration](47-public-http-runtime-configuration.md)
- Application Ergonomics: [Application Ergonomics](74-application-ergonomics.md)
