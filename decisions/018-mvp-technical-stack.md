# D018: MVP Technical Stack

Status: Decided

> Local Execution TransportとしてPDO SQLiteを採用した部分はD040によって置き換えられた。MVPのReference TransportはPostgreSQLとし、Docker Composeで起動する。他の技術選定は有効である。

## Context

D017でMVPの範囲が決まった。実装開始前に、Local Execution Transport、HTTP Contract、Router、UUID、Console、Logger、Serialization、Test基盤を選定する。

FW固有の価値がない部分は、相互運用可能で安定したComponentを利用する。

## Question 1: Local Execution Transport

MVPのDeferred OperationをProcess間で保持するLocal Transportをどう実装するか。

### Options

- A: SQLite
- B: Filesystem上の1 Operation＝1 JSON File
- C: InMemoryのみ

### Recommendation

Aを推奨する。

SQLiteならClaim、Lease、Retry時刻、Attempt数、並行Worker間の排他をTransactionで表現できる。Filesystem方式よりQueue状態の検索とAtomic Updateが扱いやすい。

MVPではPDO SQLiteを使用し、ORMは導入しない。

[ANSWER]

A

[/ANSWER]

## Question 2: HTTP Contract

Http AdapterのRequest、Response、Middleware Contractをどうするか。

### Options

- A: PSR-7、PSR-15、PSR-17を採用する
- B: Symfony HttpFoundationを採用する
- C: すべて独自実装する

### Recommendation

Aを推奨する。

- PSR-7：HTTP Message
- PSR-15：Server Request HandlerとMiddleware
- PSR-17：HTTP Message Factory

D010の `HttpMiddleware` は独自メソッドを再定義せず、PSR-15 `MiddlewareInterface` を継承するmarker interfaceとして整理できる。

```php
interface HttpMiddleware extends Psr\Http\Server\MiddlewareInterface
{
}
```

[ANSWER]

A

[/ANSWER]

## Question 3: PSR-7実装

標準のHTTP Message実装をどうするか。

### Options

- A: 軽量なNyholm PSR-7を標準採用する
- B: Laminas Diactorosを標準採用する
- C: FW独自実装を作る
- D: Contractだけを要求し、標準実装を持たない

### Recommendation

Aを推奨する。

FW内部はPSR Interfaceだけへ依存し、標準Skeletonでは軽量な実装を提供する。将来別実装へ交換可能にする。

[ANSWER]

A

[/ANSWER]

## Question 4: Router

Operation ManifestのRouteを実行時にMatchする実装をどうするか。

### Options

- A: FastRouteを利用し、Compile済みDispatcher DataをManifestへ含める
- B: Symfony Routingを利用する
- C: FW独自Routerを実装する

### Recommendation

Aを推奨する。

Route宣言とOperation Metadataの正本はFW側に保ち、Manifest CompilerがFastRoute用のDispatcher Dataを生成する。実行時はRoute探索ではなくCompile済みDataを使う。

[ANSWER]

A

[/ANSWER]

## Question 5: UUIDv7

Operation ID、Attempt ID、Journal Record IDの生成をどうするか。

### Options

- A: Symfony UID Componentを利用する
- B: UUID Libraryを独自実装する
- C: DatabaseのUUID関数を使う

### Recommendation

Aを推奨する。

Symfony UIDはUUIDv7生成とTest用のMock Factoryを提供する。既にSymfony DIを採用するため、Component間の親和性も高い。

FW固有の `OperationId` 等でSymfony UIDを包み、Domain APIへComponent型を直接露出しない。

[ANSWER]

A

[/ANSWER]

## Question 6: CLI

MVPのConsole Applicationをどう実装するか。

### Options

- A: Symfony Console Componentを利用する
- B: 独自CLI Parserを実装する
- C: Composer Scriptだけで実装する

### Recommendation

Aを推奨する。

Command、Help、Exit Code、Test Utility、進捗表示を再実装せず、Operation関連CLIへ集中できる。

[ANSWER]

A

[/ANSWER]

## Question 7: Logger Backend

FW Loggerが委譲するMVP標準PSR-3実装をどうするか。

### Options

- A: Monolog 3を利用する
- B: JSON Lines専用Loggerをすべて独自実装する
- C: Logger Backendをユーザー必須設定にする

### Recommendation

Aを推奨する。

FWはExecution Context付与、Journal Record生成、Sensitive Filter、Schema整形を担当し、File Handler、Buffer、Level等はMonologを利用する。

[ANSWER]

A

[/ANSWER]

## Question 8: Operation Serialization

OperationValueとJournal RecordのSerializationをどう実装するか。

### Options

- A: FW固有のCodec Contractを作り、MVPではReflectionベースのJSON Codecを提供する
- B: Symfony Serializerへ直接依存する
- C: PHP `serialize()` を使う

### Recommendation

Aを推奨する。

```php
interface OperationCodec
{
    public function encode(OperationEnvelope $operation): JournalPayload;

    public function decode(JournalPayload $payload): OperationEnvelope;
}
```

Type ID、Schema Version、Upcaster、Sensitive MetadataをFWが制御する必要がある。PHP `serialize()` はクラス構造へ密結合し、安全性と他言語連携にも不向きである。

[ANSWER]

A

[/ANSWER]

## Question 9: Test Framework

FW CoreのTest基盤をどうするか。

### Options

- A: PHPUnitを採用する
- B: Pestを採用する
- C: 独自Test Runnerを作る

### Recommendation

Aを推奨する。

Library／Frameworkとして標準的で、依存先Componentとの親和性が高い。Sample Applicationの読みやすい受け入れTestだけ、将来Pestを選択可能にしてもよい。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. MVPのLocal Execution TransportはPDO SQLiteで実装し、ORMを使用しない。
2. Http AdapterのContractとしてPSR-7、PSR-15、PSR-17を採用する。
3. `HttpMiddleware` はPSR-15 `MiddlewareInterface` を継承するmarker interfaceとする。
4. MVP標準のPSR-7／PSR-17実装としてNyholm PSR-7を採用する。
5. FW内部はPSR Interfaceへ依存し、具体実装を交換可能にする。
6. RouterにはFastRouteを採用する。
7. Operation Manifest CompilerがFastRoute用のCompile済みDispatcher Dataを生成し、Runtime Manifestへ含める。
8. UUIDv7生成にはSymfony UID Componentを採用する。
9. `OperationId`、`AttemptId`、`JournalRecordId` 等のFW固有型でSymfony UIDを包み、公開APIへComponent型を直接露出しない。
10. CLIはSymfony Console Componentで実装する。
11. FW LoggerのMVP標準BackendとしてMonolog 3を採用する。
12. FWはExecution Context付与、Journal Record生成、Sensitive Filter、Schema整形を担い、実際のFile出力、Buffer、Level処理をMonologへ委ねる。
13. Operation SerializationにはFW固有のOperation Codec Contractを定義する。
14. MVPではReflectionベースのJSON Codecを提供する。
15. PHP `serialize()` はOperation／Journalの正規形式に使用しない。
16. Test FrameworkにはPHPUnitを採用する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- SQLiteによって別ProcessのHTTPとWorker、Claim、Lease、RetryをLocal環境で検証できる。
- PSR-7／15／17により既存MiddlewareとHTTP Message実装を利用できる。
- D010で定義したHttp Middlewareの公開ContractをPSR-15へ合わせて更新する。
- FastRouteのDispatcher DataをOperation Manifestへ統合するCompilerが必要になる。
- Symfony UIDのMock Factoryを利用し、ID依存Testを決定的にできる。
- Symfony ConsoleによってMVPの運用Commandへ集中できる。
- MonologをSinkとして再利用しつつ、FW固有の構造化SchemaとContext保証を維持できる。
- Operation CodecがType ID、Schema Version、Upcaster、Value Hydrationを担う。
- SQLite Schema、Claim Algorithm、Lease回収、Codecの対応型、HTTP SAPI Bridgeを実装設計で詰める必要がある。

[/CONSEQUENCES]

## Sources

- [PSR-7 HTTP Message Interfaces](https://www.php-fig.org/psr/psr-7/)
- [PSR-15 HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/)
- [PSR-17 HTTP Factories](https://www.php-fig.org/psr/psr-17/)
- [Symfony UID Component](https://symfony.com/doc/current/components/uid.html)
- [Symfony Console Component](https://symfony.com/doc/current/components/console.html)
- [Monolog](https://seldaek.github.io/monolog/)
- [FastRoute Repository](https://github.com/nikic/FastRoute)
