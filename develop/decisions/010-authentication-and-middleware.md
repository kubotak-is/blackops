# D010: AuthenticationとMiddleware

Status: Decided

## Context

ExecutionContextはActor ID、Actor Type、許可された属性を保持できる。一方、Password、Session、Bearer TokenなどのCredentialはContext、Journal、Deferred Transportへ含めない。

この設計対話では、CredentialからActorContextを生成するAuthentication、Operationの実行可否を判断するAuthorization、横断処理を担うMiddlewareの境界を決める。

## Question 1: Middleware Pipelineの分離

HTTP固有の処理と、Operation共通の処理を同じMiddleware Pipelineで扱うか。

### Options

- A: すべて一つのMiddleware Pipelineで扱う
- B: Input MiddlewareとOperation Middlewareへ分離する
- C: HTTP MiddlewareだけをFWが提供し、Operation Middlewareは提供しない

### Recommendation

Bを推奨する。

```text
HTTP Request
  -> Input Middleware
     - Proxy / CORS
     - Content Type
     - Credential Authentication
  -> Route解決
  -> Binding / Validation
  -> Operation Envelope
  -> Operation Middleware
     - Authorization
     - Audit
     - Transaction
     - Metrics
  -> Execution Strategy
```

Input MiddlewareはHTTPなどの入力プロトコルを扱い、Operation MiddlewareはHTTP、CLI、Message、内部発行に共通して適用する。

[ANSWER]

B、疑問なんですが、Laravelのミドルウェアだとレスポンスの加工もできる、つまり玉ねぎ構造になってますがこのFWではInputのみですかね？だとするとミドルウェアと呼称するよりHooksやインターセプターと呼んだほうが適切かもしれません。

[/ANSWER]

## Question 2: Authenticationの責務

Bearer TokenやSessionからActorContextを生成する処理をどこへ置くか。

### Options

- A: Handler内で認証する
- B: Input Middlewareで認証し、Credentialを除いたActorContextだけをEnvelopeへ渡す
- C: OperationValueへTokenをBindingする

### Recommendation

Bを推奨する。

```text
Authorization Header
  -> Authenticator
  -> ActorContext
     - actorId
     - actorType
     - allowed attributes
  -> Credentialは破棄
```

Handler、Journal、Execution TransportをCredentialから切り離せる。

[ANSWER]

B

[/ANSWER]

## Question 3: Authorizationの宣言

Operationが必要とする認可Policyをどのように宣言するか。

### Options

- A: Operation Definitionへ `#[Authorize(...)]` Attributeを付与する
- B: Handler内で自由に認可する
- C: Route Configだけに認可ルールを書く

### Recommendation

Aを推奨する。

```php
#[Authorize(CreateOrderPolicy::class)]
final class CreateOrder implements Operation
{
}
```

PolicyはOperation Envelopeを受け取り、ActorとOperationValueの両方を使って判断する。Routeを持たない内部Operationにも同じ認可モデルを適用できる。

[ANSWER]

A

[/ANSWER]

## Question 4: Middlewareによる処理中断

Operation Middlewareが認可拒否などを判断した場合、どう中断するか。

### Options

- A: HTTP Responseを直接返す
- B: `OperationResult::rejected(...)` を返し、各Adapterが外部レスポンスへ変換する
- C: 必ず例外をthrowする

### Recommendation

Bを推奨する。

MiddlewareをHTTPから独立させられる。Authorization Policyの拒否は `OperationRejected` としてJournalへ記録し、Web Adapterは401または403、CLIはExit Codeなどへ変換する。

[ANSWER]

B

[/ANSWER]

## Question 5: Deferred Operationの認可

HTTP受付からWorker実行までの間に、Actorの権限が変更・剥奪される可能性をどう扱うか。

### Options

- A: 受付時だけ認可し、Workerはその判断を信頼する
- B: Worker実行時に必ず最新権限で再認可する
- C: 受付時認可を必須とし、OperationごとにWorkerでの再認可を追加指定できる

### Recommendation

Cを推奨する。

通常は受付時の認可結果と改ざん保護されたActorContextを使う。送金や管理操作など、実行時点の最新権限が必要なOperationでは `#[ReauthorizeOnExecution]` を指定する。

再認可時もTokenは保存せず、Actor IDから現在の権限を取得する。

[ANSWER]

B

[/ANSWER]

## Question 6: 内部OperationのActor

認証済みActorを持つ親Operationから、内部の子Operationを発行した場合、ActorContextをどうするか。

### Options

- A: 常に親Actorをそのまま伝播する
- B: Actorを伝播せず、すべてSystem Actorへ置き換える
- C: 元Actorを保持しつつ、実行主体としてSystem Actorも別に記録する

### Recommendation

Cを推奨する。

```text
originActor: user:123
executionActor: system:worker
```

「誰の操作が原因か」と「実際にどの主体が実行したか」を分けて監査できる。

[ANSWER]

C

[/ANSWER]

## Question 7: Middlewareの登録と順序

Middlewareの順序をどのように決めるか。

### Options

- A: ConfigでGlobal Middlewareを登録し、Operation Attributeで追加・除外・優先度を指定する
- B: OperationごとにMiddleware配列をすべて宣言する
- C: FWが固定順序を持ち、ユーザーは変更できない

### Recommendation

Aを推奨する。

Manifest Compilerが最終的なPipeline順序を確定し、循環、重複、不正な順序をCIで検出する。

[ANSWER]

A

[/ANSWER]

## Follow-up 1: Middlewareの玉ねぎ構造

`Input Middleware` という名前は前処理だけに見えるため、プロトコル境界を包むものを **Adapter Middleware** と呼ぶ。

### Adapter Middleware

Adapter Middlewareは、入力から外部レスポンスまでを `next` で包む玉ねぎ構造とする。

```mermaid
flowchart LR
    A["HTTP Request"]
    B["Middleware A: before"]
    C["Middleware B: before"]
    D["Operation Dispatch"]
    E["Middleware B: after"]
    F["Middleware A: after"]
    G["HTTP Response"]
    A --> B --> C --> D --> E --> F --> G
```

HTTP Adapter Middlewareでは、CORS Headerの追加、Response圧縮、例外のHTTP変換など、レスポンス側の加工もできる。

```php
interface AdapterMiddleware
{
    public function process(
        AdapterRequest $request,
        AdapterHandler $next,
    ): AdapterResponse;
}
```

HTTP以外のCLIやMessage Adapterも、それぞれのRequest/Response相当を包むMiddlewareを持てる。

### Operation Middleware

Operation Middlewareも、Operation EnvelopeからOperation Resultまでを `next` で包む玉ねぎ構造とする。

```php
interface OperationMiddleware
{
    public function process(
        OperationEnvelope $operation,
        OperationHandler $next,
    ): OperationResult;
}
```

Deferred Operationでは、次の二つのタイミングが存在する。

| Scope | 包む対象 | 例 |
| --- | --- | --- |
| Dispatch | Operation受付からExecution Transportへの配送 | 受付認可、配送監査 |
| Execution | WorkerによるHandler実行 | 再認可、Transaction、実行Metrics |

Middlewareは `Dispatch`、`Execution`、`Both` のScopeを宣言する。Inline StrategyではDispatchとExecutionが同じプロセス内で連続するが、Pipeline上は区別する。

Hooksは処理の通知には向くが、後続処理の中断や結果変換を統一的に表現しにくい。Interceptorも近い概念だが、PHP Web開発者に馴染みがあり、玉ねぎ構造を明示できるMiddlewareを正式名称とする案である。

### Question

このMiddlewareモデルを採用するか。

### Options

- A: Adapter MiddlewareとOperation Middlewareを、どちらも玉ねぎ構造として採用する
- B: AdapterだけMiddlewareとし、Operation側は前後Hooksにする
- C: 両方をInterceptorと呼ぶ

### Recommendation

Aを推奨する。

[ANSWER]

A、
AdapterMiddlewareは直感的じゃないので、HTTP Middlewareと呼称してもいいかもしれなし。実際HTTP向けに作ったOperatorはCLIで使わなそう。
ディレクトリ構造も合わせて考えるといいかも。LaravelだとHttpとConsoleは別のディレクトリで管理するのでOperatorの概念は同じだがI/Fに専用設計された詳細Operator、Middlewareを使う形にしたほうがいいかもですね

[/ANSWER]

## Follow-up 2: Deferred再認可の対象Actor

回答により、Deferred OperationはWorker実行時に必ず最新権限で再認可する。

内部Operationには二種類のActorを記録する。

```text
originActor    操作の原因となったユーザーやシステム
executionActor 現在Operationを実行しているプロセス主体
```

WorkerのSystem Actorだけで認可すると、元ユーザーが持たない権限で処理できてしまう。そこで、認可対象を別の `authorizationActor` として明確にする。

既定規則：

- ユーザー起点の子Operationでは、originActorをauthorizationActorとして伝播する
- Cronなどシステム起点では、System ActorをauthorizationActorとする
- WorkerのexecutionActorは監査対象だが、元ユーザーの代わりに権限を強化しない
- WorkerはauthorizationActorのIDから最新権限を取得して再認可する

### Question

このActorの役割分担を採用するか。

### Options

- A: 採用する
- B: 常にoriginActorで認可する
- C: 常にexecutionActorで認可する

### Recommendation

Aを推奨する。

[ANSWER]

A

[/ANSWER]

## Follow-up 3: Adapterごとの公開APIとディレクトリ

`Adapter Middleware` はアーキテクチャ上の総称に留め、アプリケーション開発者が実装する公開インターフェースは入口ごとに専用化する。

```text
Adapter Middleware（総称）
  ├─ HttpMiddleware
  ├─ ConsoleMiddleware
  └─ MessageMiddleware

Operation Middleware（入口共通）
```

各Middlewareは、それぞれの入口に適した型を扱う。

```php
interface HttpMiddleware
{
    public function process(
        HttpRequest $request,
        HttpHandler $next,
    ): HttpResponse;
}

interface ConsoleMiddleware
{
    public function process(
        ConsoleInput $input,
        ConsoleHandler $next,
    ): ConsoleOutput;
}
```

HttpMiddlewareはHTTP Responseの加工を行える。ConsoleMiddlewareはExit Codeや標準出力を加工できる。両者を無理に同じRequest/Response型へ抽象化しない。

Operationについても、共通のOperationを入口別のmarker interfaceで分類できるようにする。

```php
interface Operation {}
interface HttpOperation extends Operation {}
interface ConsoleOperation extends Operation {}
interface MessageOperation extends Operation {}
```

HTTP向けOperationは `HttpOperation` を実装してRouteを持ち、Console向けOperationは `ConsoleOperation` を実装してCommand Metadataを持つ。内部Operationは基本の `Operation` だけを実装する。

同じ業務処理をHTTPとCLIの両方から呼びたい場合、二つの入口Operationを直接共用するのではなく、共通のDomain Serviceを呼ぶか、共通の内部Operationを発行する。

想定するアプリケーション構造：

```text
app/
  Operation/
    Http/
      CreateOrder/
    Console/
      RebuildIndex/
    Message/
      ImportOrder/
    Internal/
      SendNotification/
  Middleware/
    Http/
    Console/
    Message/
    Operation/
```

### Question

入口ごとの専用OperationとMiddlewareを採用するか。

### Options

- A: 共通Operationを基底とし、Http/Console/Messageの専用marker interfaceとMiddlewareを提供する
- B: Operationは一種類のままにし、Attributeだけで入口を判定する
- C: Operationとは別にHttp EndpointやConsole Commandクラスを設ける

### Recommendation

Aを推奨する。

Operation Envelope、Journal、Handler、Execution Strategyは共通化しつつ、HTTP RequestとConsole Inputなどのプロトコル固有型を無理に統合せずに済む。

[ANSWER]

A、Messageは何を想定していますか？

interface HttpMiddleware extends AdapterMiddleware
とかで共通Middlewareの型だけは持っている状態にするといいかもですね。

ディレクトリ構造こんな感じがいいかなー
```
app/
   UserInterface/
      Http/
         Operation/
            [Feature]/
               Command/
               Query/
         Middleware/
         ...
      Console/
         Operation/
         Middleware/
         ...
      Internal/
         Operation/
         Middleware/
         ...
      Shared/
         ...
   Domain/
      [FreeSpace]
   Infrastructure/
      [FIXME]
config/
   [FIXME]
```

[/ANSWER]

## Follow-up 4: Message Adapterと初期スコープ

Message Adapterは、SQS、Kafka、RabbitMQなどの外部メッセージを入力として受信し、新しいOperationを開始する入口を指す。

```text
External Message
  -> Message Middleware
  -> Message Binding
  -> Message Operation
  -> Operation Pipeline
```

Deferred OperationをWorkerへ渡すExecution Transportとは区別する。

```text
Message Adapter      外部メッセージから新しいOperationを開始する
Execution Transport  受け付け済みOperationをWorkerへ配送する
```

同じKafkaやSQSを利用する場合でも、アーキテクチャ上のロールと失敗時の扱いは異なる。

### Adapter Middlewareの共通型

`AdapterMiddleware` はメソッドを強制しない共通marker interfaceとする。入口ごとの専用interfaceがこれを継承し、専用型を使った玉ねぎ構造を定義する。

```php
interface AdapterMiddleware
{
}

interface HttpMiddleware extends AdapterMiddleware
{
    public function process(
        HttpRequest $request,
        HttpHandler $next,
    ): HttpResponse;
}

interface ConsoleMiddleware extends AdapterMiddleware
{
    public function process(
        ConsoleInput $input,
        ConsoleHandler $next,
    ): ConsoleOutput;
}
```

### Question

初期バージョンで提供する入口をどこまでにするか。

### Options

- A: Http、Console、Internalを初期対象とし、Message Adapterは拡張として後から追加する
- B: Http、Console、Message、Internalをすべて初期対象にする
- C: HttpとInternalだけを初期対象にする

### Recommendation

Aを推奨する。

Message AdapterのConsumer Group、Acknowledgement、Poison Messageなどは独立した設計量がある。Execution Transportを先に成立させ、外部Message入口は後から同じOperation Pipelineへ接続する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. プロトコル固有のAdapter Middlewareと、入口に依存しないOperation Middlewareを分離する。
2. Adapter MiddlewareとOperation Middlewareは、どちらも `next` の前後で処理できる玉ねぎ構造とする。
3. `AdapterMiddleware` はメソッドを持たない共通marker interfaceとする。
4. `HttpMiddleware`、`ConsoleMiddleware` などの入口別interfaceが `AdapterMiddleware` を継承し、各プロトコル専用のRequest、Handler、Response型を定義する。
5. Operation Middlewareは、Operation EnvelopeからOperation Resultまでを包む。
6. Operation Middlewareは `Dispatch`、`Execution`、`Both` のScopeを宣言する。
7. Credential AuthenticationはAdapter Middlewareで行い、Credentialを除いたActorContextだけをOperation Envelopeへ渡す。
8. Password、Session、Bearer TokenなどのCredentialをOperationValue、ExecutionContext、Journal、Execution Transportへ含めない。
9. Operation Definitionは `#[Authorize(...)]` AttributeでAuthorization Policyを宣言する。
10. Authorization PolicyはOperation Envelopeを受け取り、ActorとOperationValueを使って判断する。
11. Authorization拒否は `OperationResult::rejected(...)` として表し、各Adapterが外部レスポンスへ変換する。
12. Deferred Operationは受付時とWorker実行時の両方で認可する。
13. Workerでの再認可はTokenを保存せず、authorizationActorのIDから最新権限を取得して行う。
14. ActorをoriginActor、executionActor、authorizationActorへ分けて記録する。
15. ユーザー起点の子OperationではoriginActorをauthorizationActorとして伝播する。
16. System起点のOperationではSystem ActorをauthorizationActorとする。
17. WorkerのSystem ActorはexecutionActorとして監査するが、元ユーザーの権限を暗黙に強化しない。
18. Global MiddlewareをConfigで登録し、Operation Attributeで追加、除外、優先度を指定できるようにする。
19. Manifest Compilerが最終的なMiddleware Pipeline順序を確定し、循環、重複、不正な順序をCIで検出する。
20. 共通Operationを基底とし、`HttpOperation`、`ConsoleOperation` など入口別のmarker interfaceを提供する。内部Operationは基本のOperationを実装する。
21. 初期バージョンの入口はHttp、Console、Internalとする。
22. 外部Brokerから新しいOperationを開始するMessage Adapterは後続拡張とし、Deferred配送用のExecution Transportとは区別する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- HttpMiddlewareはHTTP Responseを、ConsoleMiddlewareはExit Codeや標準出力を、それぞれの型を保ったまま加工できる。
- Operation MiddlewareはHTTP、Console、Internalのすべてに共通して認可、監査、Transaction、Metricsなどを適用できる。
- Deferredでは配送時とWorker実行時のPipelineを明確に区別できる。
- HandlerとJournalからCredentialを排除し、遅延配送時の認証情報漏えいを防ぎやすくなる。
- Deferred実行までにユーザー権限が剥奪された場合、Workerでの再認可によって処理を拒否できる。
- originActor、executionActor、authorizationActorにより、原因、実行主体、権限主体を個別に監査できる。
- Authenticator、Authorization Policy、Actor Resolver、各種Middleware interfaceを設計・実装する必要がある。
- Worker再認可時にActorが削除済み、または権限基盤が停止している場合のFailure ReasonとSupervision Policyを決める必要がある。
- Message AdapterはExecution Transport完成後に、Consumer Group、Acknowledgement、Poison Messageを含めて別途設計する。
- 推奨ディレクトリ構造と、入口別Operationの配置をD011で決める。

[/CONSEQUENCES]
