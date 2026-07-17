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

A

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

A

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

Operation以外でも使いたいんですよね、トランザクション。
どういうことかというと、ユーザーは業務処理を例えばCommandサービスなどを作ってそこからDomainサービスを実行する。このときCommandにトランザクションを張り、OperationはCommandサービスを呼ぶ形になる。
みたいなユーザー側で設計を自由に行える柔軟性を持たせたい。アノテーション方式は良いが、Operation専用という形にはしたくないので、AOP的な形が良いかと思う。RayAOPなど導入したらどうだろうか

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

A
UnitOfWorkのような仕組みもほしい。LaravelでいうところのAfterCommitのようなもの。
特定のメソッドにアノテーション着けると、それはトランザクションがコミットされるときに実行できる。みたいな

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

A

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

A

[/ANSWER]

## Follow-up Audit: General Method Interception and After Commit

Question 3とQuestion 4の回答により、Transaction境界はOperation専用ではなく、Application Service／Command Serviceを含む任意のDI管理Serviceへ適用する要件へ広がった。また、Transaction内で呼ばれた処理を最外Commit後まで遅延するAfter Commit Scopeが必要になった。

Ray.Aop 2.19.1は2026年1月にもReleaseされ、PHP 8.2以上、PHP 8 Attribute、Public Method Interceptor、`readonly` ClassのWeavingを提供している。依存はPHPとTokenizerだけで、Ray.Diは必須ではない。一方、生成Classは対象Classを継承してMethodをOverrideするため、`final class`と`final method`はInterceptできない。標準の`Aspect::newInstance()`は書込み可能なTemporary DirectoryへProxyをRuntime生成する。

BlackOpsはSymfony DIのCompile済みContainerを正本にしているため、Ray.Diへ置き換えない。採用する場合はBlackOpsの`build:compile`がRay.Aop Compilerを呼び、ProxyをBuild Artifactとして生成し、Symfony ContainerがそのProxyをAutowireする統合が必要になる。Runtime Temporary Generation、Source Scan、Direct `new`で生成したInstanceへの暗黙Interceptは許可しない。

ここでいうAfter Commit ScopeはDoctrine ORMのIdentity Map／Change Trackingを持つUnit of Workではない。DBAL Transactionに紐づくCallback Queueであり、RepositoryのSQLを自動Flushしない。

## Question 7: 任意Serviceへの`#[Transactional]`適用方式

Operation、Command Service、Application Serviceへ同じAttributeを適用する仕組みをどう提供するか。

### Options

- A: `ray/aop`を導入し、BlackOpsのBuild時Symfony DI Compilerへ統合する。`#[Transactional]`はDI管理ServiceのClassまたはPublic Methodへ付与できる。対象Class／Methodは非`final`を必須とし、違反は`build:compile`で明示Errorにする
- B: Ray.Aopを使わず、Interfaceへ対するFramework独自Decoratorだけを生成する。実装Classは`final`にできるが、対象ServiceごとにInterface Bindingを必須とする
- C: Method Interceptionを導入せず、Public `TransactionManager::transactional()`の明示Callback APIだけを提供する

### Recommendation

Aを推奨する。

```php
#[Transactional]
readonly class CreateOrderCommand
{
    public function execute(CreateOrderInput $input): OrderId
    {
        // Repositoryを呼ぶApplication ServiceのTransaction境界
    }
}
```

利用者が求めるAOP型の書き味を既存Libraryで実現でき、Interceptor EngineをBlackOpsで再実装せずに済む。Ray.Diは導入せず、既存のSymfony DI、ServiceProvider、Compile済みContainerを維持する。Proxyは`var/build`配下へ決定的に生成し、Production RuntimeでSourceを読み直さない。

制約として、Intercept対象はContainerから解決されるPublic Methodだけである。Direct `new`で生成したObject、Static／Private Method、`final class`、`final method`は対象外とし、Attributeを付けたまま無視せずCompile Errorにする。`readonly`はRay.Aopが対応するため許可する。

[ANSWER]

[/ANSWER]

## Question 8: Operationと一般ServiceのTransaction保証差

同じ`#[Transactional]`をOperationと一般Serviceへ付けた場合、Canonical Journal／OutcomeとのCommit境界をどう扱うか。

### Options

- A: Operation Definition／`handle()`のAttributeはFramework固定Operation Lifecycleが所有し、同一Connectionなら成功Terminal Journal／Outcomeまで同じTransactionへ含める。一般Service MethodのAttributeはRay.Aop InterceptorがMethod Return時にCommitする。OperationがTransactional Commandを呼ぶだけの場合、業務更新とOperation Terminalの原子性は保証しない
- B: すべてRay.AopのMethod Return時にCommitし、Operation Terminalとの原子性追加保証を廃止する
- C: 一般ServiceだけAttributeを許可し、Operationへの`#[Transactional]`は禁止する

### Recommendation

Aを推奨する。

同じAttributeとRequired Semanticsを使いながら、BlackOpsが所有するOperation Entry PointだけはJournal／Outcomeとの強い保証を提供できる。利用者はTransaction境界をCommand Serviceに置く自由を持つが、その場合はCommand Commit後にOperation Terminalが保存されるという保証差を明示的に受け入れる。

```text
Transactional Operation:
  begin -> handler/command -> terminal journal/outcome -> commit -> after-commit callbacks

Non-transactional Operation -> Transactional Command:
  command begin -> command return -> commit -> after-commit callbacks
  -> operation terminal journal/outcome
```

[ANSWER]

[/ANSWER]

## Question 9: `#[AfterCommit]`の呼出Semantics

Attributeを付けたMethodを、いつQueueへ登録していつ実行するか。

### Options

- A: DI管理Proxyの`#[AfterCommit]`付きPublic `void` Methodが呼ばれた時点でInvocationと引数を現在のTransaction ScopeへQueueする。最外Transaction Commit後に登録順で一度実行し、Rollbackでは破棄する。Transaction外で呼ばれた場合は即時実行する
- B: `#[AfterCommit]`付き引数なしMethodを持つ全ServiceをFrameworkが発見し、すべてのCommit後に自動実行する
- C: Attributeを提供せず、`AfterCommitQueue::add(callable)`だけを明示的に呼ぶ

### Recommendation

Aを推奨する。

```php
readonly class OrderNotification
{
    #[AfterCommit]
    public function send(OrderId $orderId): void
    {
        // このMethod Callは最外Commit成功後まで遅延する
    }
}
```

Methodは通常どおり業務Codeから呼ぶ。Frameworkが無条件に全Hookを実行するのではなく、そのTransactionで実際に要求されたInvocationだけをQueueする。Nested Required ScopeのQueueは最外Scopeへ合流し、Rollback-onlyまたはRollback時にすべて破棄する。戻り値を後から返せないためReturn Typeは`void`に限定し、Reference Parameter、Generator、Static MethodはCompile Errorにする。

[ANSWER]

[/ANSWER]

## Question 10: After Commit失敗とDurability

Database Commit後にCallbackがThrowableを投げた場合をどう扱うか。

### Options

- A: 全CallbackをBest-effortで実行し、失敗をOperation／Correlation ID付きApplication LogとFailure Reporterへ記録する。Database Commit、Operation Terminal Outcome、他Callbackを巻き戻さず、自動Retryもしない。確実な配送はPhase 17のTransactional Outboxを使う
- B: 最初の失敗で`AfterCommitExecutionException(committed: true)`を呼出元へ投げる。DatabaseはCommit済みであり、呼出元が重複を避けて処理する
- C: Reliableな失敗処理を同時提供できるPhase 17までAfter Commit機能を延期する

### Recommendation

Aを推奨する。

Commit済みOperationをFailedとして自動Retryすると、業務更新を二重実行する危険がある。After CommitはProcess Crash時にも失われ得る同期Best-effort Hookとして明示し、Email、Webhook、Message Publish等でDelivery保証が必要な用途はOutboxへ分離する。Callbackは一つの失敗で後続を止めず、Failure Reporterがすべての失敗を観測できるようにする。

[ANSWER]

[/ANSWER]

## Proposed Consequences

- Runtime Connection ParametersをBuild Artifactへ保存せず、Compiled ContainerへSynthetic Runtime Serviceを注入する必要がある
- `DatabaseManager`とDefault `Connection`をPHP Public APIとして追加する
- Connection Name、Default、Framework Store参照、SchemaをBuild／Runtime両方で検証する
- `ray/aop`をRuntime Dependencyとして追加し、ProxyをRuntimeではなくBuild時に生成する
- `#[Transactional]`をOperation Discovery／ManifestとSymfony DI Service Compileの両方へ追加する
- Operation TransactionはInline／Deferred共通の固定Lifecycle Stage、一般Service TransactionはCompiled Method Interceptorになる
- `#[AfterCommit]`とTransaction Scope Callback Queueを追加し、Long-running Process境界で必ず破棄／検証する
- Intercept対象の非`final`、Public Method、Container管理境界をBuild ErrorとGuideで明示する
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
- [Ray.Aop](https://github.com/ray-di/Ray.Aop)
- [Doctrine DBAL Transactions](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/transactions.html)
