# Public HTTP Runtime Configuration

## Purpose

`Application::http()` は、Accepted Application Configuration SnapshotからCompile済みArtifact、PostgreSQL Canonical Journal、Inline Dispatcher、Deferred Acceptorを構成し、PSR-15 `RequestHandlerInterface` を返す。

Installed ApplicationはInternal Runtime Class、Identifier Factory、Codec、Journal Factory、Deferred Acceptance Orchestratorを直接生成しない。

## Build Artifact Configuration

`config/app.php` の `build` Sectionは次のKeyを持つ。

```php
use BlackOps\Application\Environment;

return static fn (Environment $env): array => [
    'build' => [
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

PathはApplicationが解決した絶対Pathを渡す。FrameworkはHTTP起動時にArtifact Pathを推測せず、相対PathをCurrent Working Directory基準で解決しない。

`container_namespace` は空文字を許容する。それ以外のPath、Class、Namespace KeyはStringで、必須値は空にできない。

Artifact Fileの不存在、不正Format、未対応Version、Application Build ID不一致、Container Class不一致はBootstrap ErrorとしてFail-fastする。Source Discovery、Reflection Scan、Container CompileへFallbackしない。

## Database Configuration

`config/database.php` はDefault／Named Doctrine DBAL Connection Parametersと、Framework Storeが使用するConnection／Schemaを返す。

```php
return [
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $env->string('POSTGRES_HOST'),
            'port' => $env->positiveInt('POSTGRES_PORT'),
            'dbname' => $env->string('POSTGRES_DB'),
            'user' => $env->string('POSTGRES_USER'),
            'password' => $env->string('POSTGRES_PASSWORD'),
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => 'blackops',
    ],
];
```

Environment VariableとConnection Parameterの対応はApplication Configが所有する。FrameworkはEnvironment Variable名をHard Codeしない。Configuration Closureは`create()`時のPublic Readonly Environmentから型付き値を一度取得し、Environment自体やCredentialをCompiled Artifactへ保存しない。

`default`と`framework.connection`は`connections`内のNameを参照する。各ConnectionはString Key Parameter Map、`framework.schema`は安全なPostgreSQL Identifierとして検証できる非空Stringでなければならない。既存の単一`connection`／`schema`形式は互換Shorthandとして受理する。Error MessageへPassword、DSN Credential、Token等の値を含めない。

HTTP RuntimeはDatabaseManagerから`framework.connection`を解決し、Deferred Acceptance Transaction、PostgreSQL Sender、Canonical Journal Storeで共有する。DatabaseManagerとDefault DBAL ConnectionはSynthetic ServiceとしてCompiled Containerへ設定し、Application RepositoryからConstructor Injectionできる。Connection生成は実際にNameを要求する時点まで遅延する。

## Runtime Composition

`Application::http()` は次をFramework内で構成する。

- Production Runtime Artifact Loader
- Named DatabaseManagerとRuntime Synthetic Service Injection
- Compiled Operation Registry／HTTP Route Registry／PSR-11 Container
- System ClockとUUIDv7 Identifier Factory
- PostgreSQL Canonical Journal Store
- Reflection JSON Operation Codec
- Inline Dispatcher
- PostgreSQL Deferred Operation Sender
- Deferred Acceptance Orchestrator
- Deferred HTTP Operation Acceptor
- Nyholm PSR-17 Response／Stream Factory
- PSR-15 Operation Request Handler

Inline RouteとDeferred Routeは同じCompile済みOperation／HTTP Manifestを使う。Operation MetadataのExecution StrategyがDeferredの場合だけDeferred Acceptorへ渡す。

Opt-in Session Authenticationを登録したApplicationでは、Compiled Containerの`SessionManager`がDefault DBAL Connectionを使い、選択したBearerまたはCookie AuthenticatorがRaw TokenをCurrent `ActorRef`へ解決する。Unknown／Expired／Revoked／Rotated／Identity Missingは同じ`authentication.invalid_session`とし、Provider／Database FailureはInvalid Credentialへ丸めない。Request終了時の既存Connection Health／Transaction Cleanupを共有し、Raw Token、Actor、Stored SessionをProcess Serviceに保持しない。

## Application API

`Application` は次をPublic APIとして追加する。

```php
public function http(): Psr\Http\Server\RequestHandlerInterface;
```

同じApplication Instanceで複数回呼び出した場合、同じ構成済みHandler Instanceを返す。Config、Environment、Artifactを再読込しない。

PSR-11 Container、DBAL Connection、Internal Runtime Composition、Raw ConfigurationのGetterは追加しない。

## Process Safety

`http()` は次を行わない。

- Database MigrationまたはDDL
- Build Artifact Compile
- Source Discovery
- Worker／Scheduler起動
- Retention Plan／Purge
- `.env` 読込

Database SchemaとBuild ArtifactはDeployment Stepで事前に準備する。

## Failure Contract

Config、Artifact、Container、Connection Parameterの不備は `ApplicationBootstrapException` としてFail-fastする。既存の安全なFramework ExceptionをPrevious Exceptionとして保持できる。

Request処理中のJournal／Deferred Transport Failureは既存のRuntime Exception Contractを維持し、HTTP Runtime構成時のConfig Errorと混同しない。

## Verification

- Invalid／Missing Build ConfigをSecret非露出のBootstrap Errorとして拒否する
- Invalid Database ConfigをCredential非露出で拒否する
- Artifact Build ID不一致とMissing ArtifactでSource DiscoveryへFallbackしない
- `http()` の複数回呼出が同じHandlerを返す
- Inline HTTP Routeが200 Responseを返す
- Deferred HTTP Routeが202とOperation IDを返し、State／Journalを一Transactionで保存する
- HTTP起動でMigrationとBuildが実行されない
- Application Public SignatureへInternal型が露出しない

## Traceability

- Bootstrap API: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
- Installed Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- Transaction Boundary: [PostgreSQL Transaction Boundaries](36-postgresql-transaction-boundaries.md)
