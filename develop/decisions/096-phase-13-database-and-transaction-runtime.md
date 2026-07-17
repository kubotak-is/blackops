# D096: Phase 13 Database and Transaction Runtime

Status: Proposed

## Context

Phase 13は、Applicationが業務Databaseへ接続し、Repository／Application ServiceへDoctrine DBAL ConnectionをConstructor Injectionし、必要なOperationだけを明示Transactionで実行できるRuntimeを提供する。

既存設計では次が確定している。

- D013でPSR-11／Symfony DI、Constructor Autowiring、Long-running ProcessでのDatabase Health Check／Resetを採用した
- D016でTransactionをOperation単位に任意適用し、特殊な場合はHandler内のManual Transactionも許可すると決めた
- D057でDoctrine DBALをConnection／Transactionの標準基盤とし、Doctrine ORMは採用しないと決めた
- D085でFrankenPHP Worker ModeをDefaultとし、Request間でConnectionを安全に再利用／回復する境界を導入した
- D095で汎用Operation Middlewareを導入せず、Framework固定Lifecycle Stageとして横断処理を実装する方針を確定した

Current Runtimeは`config/database.php`の単一`connection`と`schema`だけを読み、FrameworkのCanonical Journal、Deferred Transport、Outcome、Migrationへ一つのDBAL Connectionを使用する。Compile済みContainerへRuntime Connectionを注入するPublic Contract、Named Connection、`#[Transactional]`、Worker Attempt用のApplication Connection Lifecycleはまだない。

D016のTransaction Operation Middlewareという実装方式はD095と衝突する。Transactionの利用者体験は維持しつつ、汎用MiddlewareではなくFramework固定のTransaction Lifecycleとして置き換える必要がある。

## Confirmed Scope

- Doctrine DBAL ConnectionとTransaction境界を提供する
- ORM、Active Record、Repository基底Class、Query Builder Wrapperは標準化しない
- RepositoryとApplication ServiceはConstructor Injectionする
- Transactionは明示AttributeまたはHandler内のManual Transactionだけで開始する
- CredentialをBuild Artifactへ保存せず、Runtime ConfigurationからConnectionを遅延生成する
- Transactional Outbox Persistence／RelayはPhase 17へ残す
- 異なるDatabaseまたは外部APIを一つの原子的Transactionとして保証しない

## Audit Findings

1. Compile済みSymfony ContainerはBuild時にDatabase Credentialを持てないため、Runtime生成したDatabase ServiceをSynthetic Serviceとして注入する境界が必要である。
2. Named Connectionを`Doctrine\DBAL\Connection`の型だけで区別できない。Default Connectionの直接Injectionと、名前で選択するManagerの両方を定義する必要がある。
3. Current HTTP RuntimeはFramework ConnectionだけをRequest前にHealth Checkし、ThrowableまたはActive Transaction時にCloseする。Application Connectionにも同じProcess Boundaryが必要である。
4. Current WorkerはHandler実行中にFramework Transactionを保持しない。`#[Transactional]`付きOperationだけを明示例外として扱い、Attempt Startedは先にCommitしたまま、Handlerと成功Terminal書込みの境界を決める必要がある。
5. 同じPhysical Connectionを業務更新とCanonical Journal／Outcomeが共有する場合だけ、成功時の原子的Commitを追加保証できる。別Connectionでは二相Commitを装わず、保証差を公開する必要がある。

## Question 1: Named Connection Configuration

`config/database.php`でApplication ConnectionとFramework Store Connectionをどう表現するか。

### Options

- A: `default`、`connections`、`framework.connection`、`framework.schema`を持つ。Legacyの単一`connection`／`schema`は一つのDefault Connectionへ正規化する互換Shorthandとして維持する
- B: 新しいNamed形式だけを受け付け、Legacy形式をBreaking Errorにする
- C: Framework用`database.php`とApplication用の別Config Fileへ分離する

### Recommendation

Aを推奨する。

```php
return [
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['POSTGRES_HOST'],
            'dbname' => $_ENV['POSTGRES_DB'],
            'user' => $_ENV['POSTGRES_USER'],
            'password' => $_ENV['POSTGRES_PASSWORD'],
        ],
        'analytics' => [
            // another DBAL parameter set
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => 'blackops',
    ],
];
```

ApplicationとFramework Storeが同じConnection名を参照すれば同じDBAL Connection Instanceを共有できる。別名なら保証もResource Lifecycleも分離できる。Stable 1.1 Skeletonの既存Configを壊さず段階移行するため、Legacy形式は少なくともExperimentalな次回ReleaseでShorthandとして維持する。

このConfigはD085のConfiguration SnapshotとしてProcess起動時に一度だけ評価する。Request／Operationごとに`$_ENV`を再読込せず、解決済みConnection ParameterだけをRuntimeのLazy Connection Factoryが参照する。Secretを含むSnapshotはBuild Artifactへ書き出さない。

[ANSWER]

[/ANSWER]

## Question 2: ApplicationからのConnection利用API

Default／Named ConnectionをRepositoryへどう注入するか。

### Options

- A: Public `BlackOps\Database\DatabaseManager`を提供し、`connection(?string $name = null): Doctrine\DBAL\Connection`で選択する。Default Connectionは`Doctrine\DBAL\Connection`型でも直接Autowireできる
- B: Constructor Parameterへ`#[Connection('analytics')]`を付け、Named Connectionを直接Injectionする。Managerは提供しない
- C: ConnectionごとのService IDをApplication Service Providerで毎回手動Bindingする

### Recommendation

Aを推奨する。

```php
final readonly class OrderRepository
{
    public function __construct(
        private Doctrine\DBAL\Connection $connection,
    ) {}
}

final readonly class AnalyticsRepository
{
    public function __construct(
        private BlackOps\Database\DatabaseManager $databases,
    ) {}

    public function connection(): Doctrine\DBAL\Connection
    {
        return $this->databases->connection('analytics');
    }
}
```

通常のRepositoryはDefault Connectionをそのまま受け取れる。複数Connectionが必要なServiceだけManagerを使うため、LaravelのDB Managerに近い直感的なSurfaceになる。Parameter Attribute Injectionは将来追加できるが、最初のVertical SliceでCompiler Passを増やさない。

[ANSWER]

[/ANSWER]

## Question 3: `#[Transactional]`のLifecycleと保証

汎用Operation Middlewareを追加せず、Transactionをどの範囲へ適用するか。

### Options

- A: `#[Transactional(connection: 'app')]`をCompiled Metadataへ保存し、Authorization後にFramework固定Transaction Lifecycleを開始する。Handler成功時は可能なら成功Terminal Journal／Outcomeまで同じTransactionへ含め、Rejected／ThrowableはRollback後に既存Lifecycleへ記録する
- B: Handler呼出だけをTransactionで包み、FrameworkのTerminal Journal／Outcomeは常にCommit後の別Transactionにする
- C: Attributeを提供せず、すべてHandler内のManual Transactionにする

### Recommendation

Aを推奨する。

```php
#[Transactional(connection: 'app')]
final readonly class CreateOrder implements Operation
{
    public function handle(CreateOrderValue $value): OrderCreated
    {
        // begin / commit / rollbackはFrameworkが管理する
    }
}
```

固定順序は次とする。

```text
Attempt StartedをCommit
Authorizationを評価
Transaction Begin
Handlerを実行
  Success -> Terminal Journal／Outcomeを保存 -> Commit
  Rejected -> Rollback -> Operation Rejectedを別Transactionで記録
  Throwable -> Rollback -> Failure／Supervisionを別Transactionで記録
```

選択したApplication ConnectionとFramework Store Connectionが同じManager内の同一Connection Instanceなら、業務更新と成功Terminal Journal／Outcomeを原子的にCommitできる。別ConnectionならAttributeはApplication Transactionだけを保証し、Framework Terminal書込みとの原子性は保証しない。二相CommitやExactly-onceを装わない。

D016の「Transaction Operation Middleware」は、この専用Lifecycleへ置き換える。

[ANSWER]

[/ANSWER]

## Question 4: Nested OperationとManual Transaction

同じConnection上でTransactionが既に存在する場合をどう扱うか。

### Options

- A: Framework管理Transaction内のNested `#[Transactional]`はRequired Semanticsで外側へ参加する。Inner Rejected／ThrowableはTransactionをRollback-onlyにする。異なるNamed Connectionは独立Transactionとして許可するが原子性を保証しない。Framework外で開始済みのManual TransactionへAttributeが遭遇した場合はOwnership不明としてFail-fastする
- B: 同じConnectionのNested呼出は常にSavepointを作り、Inner Failureを親が握りつぶしてCommitできる
- C: Transaction中のNested OperationとManual Transactionをすべて禁止する

### Recommendation

Aを推奨する。

Required Semanticsなら、通常のNested Operationで不完全な一部更新をCommitしにくい。Savepointは「Innerが失敗してもOuterを続行する」という別の業務意味を持つため、最初から暗黙採用しない。短い範囲、Savepoint、複数Connectionが必要なOperationは`#[Transactional]`を外し、Handler内の明示Manual Transactionを使う。

Manual Transactionは引き続き許可するが、同じOperationでAttribute管理と混在させない。

[ANSWER]

[/ANSWER]

## Question 5: Long-running ProcessのConnection Lifecycle

FrankenPHP Worker ModeとDeferred WorkerでApplication Connectionをどう再利用するか。

### Options

- A: Connectionを名前ごとにLazy生成する。Request／Attempt開始時に生成済みConnectionをHealth Checkし、正常終了時はActive Transactionがないことを検証して再利用する。Throwable、Rollback失敗、Transaction Leak、Health Check失敗では該当ConnectionをCloseし、次回利用時に再接続する
- B: Request／Attempt終了ごとに全Application Connectionを必ずCloseする
- C: FrameworkはApplication ConnectionのLifecycleを管理せず、各Repositoryへ任せる

### Recommendation

Aを推奨する。

Current FrankenPHP Runtimeの安全境界をNamed Connectionへ一般化できる。正常なConnectionはProcess内で再利用しつつ、失敗または未完了Transactionを次Request／Attemptへ持ち越さない。未使用Connectionは生成せず、SecretをBuild Artifactへ保存しない。

Deferred WorkerではFramework Main ConnectionとHeartbeat Connectionを引き続き分離する。Application RepositoryへHeartbeat Connectionを公開しない。

[ANSWER]

[/ANSWER]

## Question 6: Phase 13のOutbox範囲

Transaction Runtimeと同時にTransactional Outbox／Relayまで実装するか。

### Options

- A: Phase 13はConnection、DI、Transaction、Lifecycle保証までとし、Outbox Persistence／Relay／ReplayはRoadmapどおりPhase 17へ残す
- B: Phase 13でPostgreSQL Outbox Persistenceだけを実装し、RelayはPhase 17へ残す
- C: Phase 13でOutbox Persistence、Relay、Replayまで実装する

### Recommendation

Aを推奨する。

Phase 13は業務DB利用の基本Surfaceを安定させる。OutboxはIdempotency、at-least-once Relay、Replay、Failure運用と同時に設計する方が安全であり、Phase 17のまとまりを維持する。同一Connection時のCanonical Journal／Outcomeとの原子性と、別Connection時に保証が下がる事実はPhase 13で明示する。

[ANSWER]

[/ANSWER]

## Proposed Consequences

- Runtime Connection ParametersをBuild Artifactへ保存せず、Compiled ContainerへSynthetic Runtime Serviceを注入する必要がある
- `DatabaseManager`とDefault `Connection`をPHP Public APIとして追加する
- Connection Name、Default、Framework Store参照、SchemaをBuild／Runtime両方で検証する
- `#[Transactional]`をDiscovery、Manifest、Registry Metadataへ追加する
- Transactionは汎用Operation MiddlewareではなくInline／Deferred共通の固定Lifecycle Stageになる
- 同一Connectionだけに成立する原子的保証と、別Connectionで成立しない保証をGuideとTestで明示する
- HTTP Request、Deferred Attempt、Nested Dispatch、Manual Transaction、Worker Crashの回帰Testが必要になる
- Worker ModeでApplication ConnectionのHealth Check／Reset／ReconnectをConsumer E2Eへ追加する
- ORMとOutbox RelayをPhase 13へ持ち込まない

## References

- [D013 PHP Runtime and Dependency Injection](013-runtime-and-dependency-injection.md)
- [D016 Durable Journal and Transaction](016-durable-journal-transaction.md)
- [D057 Database Access and Migration Library](057-database-access-and-migration-library.md)
- [D085 HTTP Configuration Snapshot Lifecycle](085-http-configuration-snapshot-lifecycle.md)
- [D095 Phase 12 Middleware and Authorization Runtime](095-phase-12-middleware-and-authorization-runtime.md)
- [Runtime and DI Specification](../spec/09-runtime-and-di.md)
- [Durable Journal and Transactions Specification](../spec/11-durable-journal-and-transactions.md)
- [PostgreSQL Transaction Boundaries](../spec/36-postgresql-transaction-boundaries.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
