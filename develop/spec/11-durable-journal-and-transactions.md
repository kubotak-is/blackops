# Durable Journal and Transactions

## Durableの保証範囲

Durable PolicyはLocal Storeへの耐久記録を基本保証とする。

業務DBとJournal／Outbox Storeが同じTransactionへ参加できる場合だけ、原子的なCommitを追加保証する。異なるDatabaseや外部APIを含む処理について、FWは一律の原子性を保証しない。

## Database Configuration

`config/database.php`のCanonical形式はDefault、Named Connection、Framework Store参照を分離する。

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
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => 'blackops',
    ],
];
```

既存の単一`connection`／`schema`形式は、一つのDefault ConnectionとFramework Storeへ正規化する互換Shorthandとして受理する。Default、Framework参照、Connection Name、Parameter Map、SchemaはSecret値をErrorへ含めず検証する。

ConfigはProcess起動時にConfiguration Snapshotへ一度だけ読み込み、Request／OperationごとにEnvironmentを再評価しない。Connectionは名前ごとに遅延生成する。

## Transaction Attribute

`#[Transactional(connection: 'app')]`はOperationだけでなく、DI管理されたApplication Service／Command ServiceのClassまたはPublic Methodへ付与できる。

```php
#[Transactional(connection: 'app')]
readonly class CreateOrderCommand
{
    public function execute(CreateOrderInput $input): OrderId
    {
        // Frameworkがbegin／commit／rollbackを管理する
    }
}
```

一般ServiceではCompiled Method InterceptorがMethod呼出をbegin／commit／rollbackで包む。Operation Definitionまたは`handle()`に付与した場合はFramework固定Operation Lifecycleが所有し、汎用Operation Middlewareを導入しない。

## Operation Transaction Boundary

Transactional Operationは次の順序で実行する。

```text
Attempt StartedをCommit
Authorizationを評価
Application Transactionを開始
Handlerを実行
  Success -> 同一ConnectionならTerminal Journal／Outcomeを保存 -> Commit
  Rejected -> Rollback -> Rejectedを別Transactionで記録
  Throwable -> Rollback -> Failure／Supervisionを別Transactionで記録
Commit成功後にAfter Commit Queueを実行
```

Inlineの予期しないThrowableでは、Application TransactionをRollbackした後、同じOperation IDとAttempt IDで`AttemptFailed`、`OperationFailed`を別Transactionに記録する。Deferred受付のAttempt開始前Throwableでは受付TransactionをRollbackし、同じOperation IDで`OperationReceived`、`OperationFailed`だけを別Transactionに記録する。

Rollback、Failure Journal、Loggerが追加で失敗しても、最初のThrowableをPrimary Failureとして維持する。Failure Journal自体が保存できない場合も別のOperation IDへ置き換えない。

Application ConnectionとFramework Store Connectionが同じDatabaseManager内の同一Connection Instanceなら、業務更新と成功Terminal Journal／Outcomeを同じTransactionでCommitする。別ConnectionではApplication MethodのTransactionだけを保証し、二相CommitまたはExactly-onceを保証しない。

Non-transactional OperationがTransactional Commandを呼ぶ場合、CommandはMethod Return時にCommitし、その後でOperation Terminalを保存する。この構成では業務更新とOperation Terminalの原子性を保証しない。

## Nested and Manual Transactions

同じConnectionのNested `#[Transactional]`はRequired Semanticsで最外Transactionへ参加する。Inner Rejected／ThrowableはScopeをRollback-onlyにし、Outerが握りつぶしてもCommitさせない。

異なるNamed ConnectionのTransactionは独立して実行できるが、Connection間の原子性は保証しない。Attribute管理Transactionが所有者不明の開始済みManual Transactionへ遭遇した場合はFail-fastする。

複数DB、短いTransaction範囲、Savepoint等を明示制御する処理では、Attributeを外してDoctrine DBALのManual Transactionを使える。同じMethodでAttribute管理とManual管理を混在させない。

## After Commit Scope

DI管理Serviceの`#[AfterCommit]`付きPublic `void` MethodをTransaction内で呼ぶと、Invocationと引数を現在のScopeへQueueする。最外Commit成功後に登録順で一度実行し、RollbackまたはRollback-onlyでは破棄する。Transaction外で呼ばれた場合は即時実行する。

```php
readonly class OrderNotification
{
    #[AfterCommit]
    public function send(OrderId $orderId): void
    {
        // 最外Commit成功後に実行される
    }
}
```

After Commit Methodは`void`を返し、Static Method、Reference Parameter、Generatorを許可しない。Nested Required ScopeのQueueは最外Scopeへ合流する。

Callbackは同期Best-effortであり、Process Crash時のDeliveryを保証しない。失敗は相関情報付きApplication LogとFailure Reporterへ記録し、後続Callback、Commit済みDatabase、Operation Outcomeを変更せず、自動Retryしない。確実なDeliveryにはTransactional Outboxを使う。

## Transactional Outbox

Transactional OutboxのPortと拡張余地を初期設計に含める。具体的なPersistence AdapterとRelay実装は初期Vertical Slice後に追加する。

```text
DB Transaction Begin
  -> 業務データを更新
  -> OutboxへJournal／子Operation Recordを保存
DB Transaction Commit

Outbox Relay
  -> 未送信Recordを取得
  -> 外部Observer／Transportへ配送
  -> 送信済みに更新
```

## Deferred Transport

Deferred配送は別Adapterとして二経路を表現できるようにする。

- Direct Transport：業務DB更新を伴わず、外部TransportへのPublish成功を受付条件とする
- Outbox Transport：業務DB更新と子Operation発行を同じTransactionで結び付ける

Operationは論理的なDeferred Strategyだけを指定し、Direct／OutboxのTransport選択はConfigへ委ねる。

## Journal Record Identity

各Journal Recordは次を持つ。

- Record ID：UUIDv7
- Operation ID
- Attempt ID：Optional
- Operation内Sequence
- Event

Record IDはObserverの冪等取り込みに使用する。Sequenceは同一Operation内の順序と欠落候補の検知に使用する。

## Relay Delivery

Outbox Relayの配送保証はat-least-onceとする。

送信成功後、送信済み更新前にProcessが停止すると重複配送される。Record IDによる冪等取り込みを前提とする。
