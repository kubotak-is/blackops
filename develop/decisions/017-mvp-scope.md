# D017: MVP Scope

Status: Decided

## Context

D001〜D016で、Operation、Journal、Execution Strategy、HTTP、DI、Logging、Supervision、Transactionの基本方針が決まった。

すべてを同時に実装すると検証が遅れるため、FWの価値を最短で証明できるVertical SliceをMVPとして定義する。

## Question 1: MVPの到達点

最初のMVPで何を証明するか。

### Options

- A: Http Inline Operationだけを動かす
- B: Http InlineとDeferred Operationを両方動かし、Journalで追跡する
- C: Outbox、OTel、CloudWatchまで含むProduction Ready版を作る

### Recommendation

Bを推奨する。

```text
HTTP Request
  -> Operation
  -> Journal
  ├─ Inline -> Handler -> Outcome -> HTTP Response
  └─ Deferred -> Transport -> HTTP 202
                    -> Worker -> Handler -> Outcome
```

本FWの核である、同じOperation Modelによる同期／非同期実行と追跡性を一度に検証できる。

[ANSWER]

B

[/ANSWER]

## Question 2: MVPのSample Operation

どのユースケースでVertical Sliceを検証するか。

### Options

- A: 意味のないHello Worldだけにする
- B: InlineとDeferredの違いが分かる小さな業務例を作る
- C: 注文管理など本格的なApplicationを作る

### Recommendation

Bを推奨する。

例：

```text
GET /welcome
  -> ShowWelcome（Inline）
  -> WelcomeShown
  -> HTTP 200

POST /reports
  -> GenerateReport（Deferred）
  -> HTTP 202 + Operation ID
  -> WorkerがReportGeneratedまで処理
```

DB Domain Modelを作り込まず、Route、Binding、Handler、Outcome、Responder、Worker、Journalを検証できる。

[ANSWER]

B

[/ANSWER]

## Question 3: Deferred Transport

MVPで使うExecution Transportをどうするか。

### Options

- A: InMemory Transportだけを使う
- B: 単一Processを越えてWorkerが読める軽量なLocal Transportを使う
- C: 最初からSQSを必須にする

### Recommendation

Bを推奨する。

InMemoryだけではHTTP ProcessとWorker Processを分けられない。SQLiteまたはFilesystemを使うLocal Transport Adapterを用意し、後からSQS／Kafkaへ置き換えられるPortを検証する。

SQLiteとFilesystemのどちらを採用するかは実装設計で決める。

[ANSWER]

B

[/ANSWER]

## Question 4: MVPのLogger

ロギングをどこまでMVPへ含めるか。

### Options

- A: Loggerは後回しにする
- B: JSON Linesへの構造化LoggerとLifecycle Journal自動記録を含める
- C: OTelとCloudWatch Adapterまで含める

### Recommendation

Bを推奨する。

```text
var/log/application.jsonl
```

PSR-3 FW Logger、Execution Scope、Operation ID等の自動付与、Lifecycle Journalの自動生成をLocal Fileで検証する。外部Adapterは後から追加する。

[ANSWER]

B

[/ANSWER]

## Question 5: ManifestとDI

MVPでProduction向けCompileまで実装するか。

### Options

- A: 開発時の動的Reflectionだけにする
- B: 動的Discoveryに加え、Operation ManifestとSymfony DI ContainerのCompileを含める
- C: ManifestだけCompileし、DIは簡易Factoryで代用する

### Recommendation

Bを推奨する。

Runtime PerformanceとBuild時検証は設計上の重要な柱である。重複Route、Attribute、Handler型の不整合をMVP段階で検出できるようにする。

[ANSWER]

B

[/ANSWER]

## Question 6: MVPから除外するもの

初期MVPでは次を除外してよいか。

- Transactional Outbox実装
- SQS／Kafka
- OpenTelemetry／CloudWatch
- 認証／認可
- Sensitive Payload暗号化
- Coalesce／Scheduled Strategy
- Dead Letter管理UI
- Message Adapter
- ORM

### Options

- A: すべて除外する
- B: 認証／認可だけMVPへ含める
- C: 別の項目をMVPへ追加する

### Recommendation

Aを推奨する。

各Portと拡張点は壊さないが、外部Infrastructure実装はVertical Slice成立後に追加する。

[ANSWER]

A

[/ANSWER]

## Question 7: MVPのCLI

初期CLIへ何を含めるか。

### Options

- A: Worker起動だけ
- B: Manifest Compile、Container Compile、Worker起動、Operation一覧を含める
- C: Code GeneratorやMigrationまで含める

### Recommendation

Bを推奨する。

```text
operation:list
operation:compile
container:compile
worker:run
```

開発、Build、Deferred実行に必要な最小の運用面を検証できる。

[ANSWER]

B、将来的にはOperationやMiddlewareの雛形作成もやりたいですねー

[/ANSWER]

## Question 8: Definition of Done

MVP完了条件を次でよいか。

- PHP 8.5で実行できる
- SampleのInline／Deferred Operationが動く
- 全Lifecycle JournalをOperation IDで追跡できる
- HTTP 200／202とOperation IDが返る
- Worker再起動後も未処理Deferred Operationを実行できる
- Handler例外をAttemptFailedとして記録できる
- 最低一回のRetryを実行できる
- Sensitive Filterの最小実装がある
- Manifest／Container Compileが成功する
- Unit TestとIntegration Testが通る

### Options

- A: この条件を採用する
- B: 条件を減らす
- C: 条件を追加する

### Recommendation

Aを推奨する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. MVPはHttp Inline OperationとDeferred Operationを両方実行し、Journalで追跡できることを証明する。
2. SampleはInlineとDeferredの違いが分かる小さな業務例とする。
3. Inline Sampleとして `GET /welcome` のShowWelcome Operationを提供する。
4. Deferred Sampleとして `POST /reports` のGenerateReport Operationを提供する。
5. Deferred受付はHTTP 202とOperation IDを返し、Workerが後から処理する。
6. MVPのExecution Transportは、HTTP ProcessとWorker Processを越えて利用できる軽量なLocal Transportとする。
7. Local Transportの具体実装をSQLiteまたはFilesystemから実装設計で選択する。
8. MVPへPSR-3 FW Logger、Execution Scope、JSON Lines構造化Log、Lifecycle Journal自動記録を含める。
9. MVPへ開発時の動的Discovery、Operation Manifest Compile、Symfony DI Container Compileを含める。
10. MVPからTransactional Outbox実装、SQS／Kafka、OpenTelemetry／CloudWatch、認証／認可、Sensitive Payload暗号化、Coalesce／Scheduled Strategy、Dead Letter管理UI、Message Adapter、ORMを除外する。
11. MVPのCLIは `operation:list`、`operation:compile`、`container:compile`、`worker:run` を提供する。
12. Operation、Middleware等の雛形生成CLIはMVP後の拡張とする。
13. MVP完了条件を次のとおりとする。
    - PHP 8.5で実行できる
    - SampleのInline／Deferred Operationが動く
    - 全Lifecycle JournalをOperation IDで追跡できる
    - HTTP 200／202とOperation IDが返る
    - Worker再起動後も未処理Deferred Operationを実行できる
    - Handler例外をAttemptFailedとして記録できる
    - 最低一回のRetryを実行できる
    - Sensitive Filterの最小実装がある
    - Manifest／Container Compileが成功する
    - Unit TestとIntegration Testが通る

[/DECISION]

## Consequences

[CONSEQUENCES]

- 本FWの差別化要素であるOperation Model、同期／非同期、追跡性を最初の成果物で検証できる。
- 外部Cloud ServiceなしでLocal開発とCIを完結できる。
- Port設計をSQS／Kafka、OTel、Outbox等の後続Adapterで検証できる。
- Local TransportとしてSQLiteまたはFilesystemのどちらを採用するか決める必要がある。
- MVPを実装可能なMilestoneとPackage構成へ分解する必要がある。
- Sample ApplicationとIntegration Testを、FW Coreと分離して管理する必要がある。

[/CONSEQUENCES]
