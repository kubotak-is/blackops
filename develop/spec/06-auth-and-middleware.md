# Authentication and Middleware

## Middleware

プロトコル固有のAdapter Middlewareと、入口に依存しないOperation Middlewareを分離する。どちらも `next` の前後で処理できる玉ねぎ構造とする。

### Adapter Middleware

`AdapterMiddleware` はメソッドを持たない共通marker interfaceとする。入口別interfaceがこれを継承し、専用型を定義する。

```php
interface AdapterMiddleware
{
}

interface HttpMiddleware extends AdapterMiddleware, Psr\Http\Server\MiddlewareInterface
{
}

interface ConsoleMiddleware extends AdapterMiddleware
{
    public function process(
        ConsoleInput $input,
        ConsoleHandler $next,
    ): ConsoleOutput;
}
```

HttpMiddlewareはHTTP Responseを、ConsoleMiddlewareはExit Codeや標準出力を加工できる。

### Operation Middleware

Operation Middlewareは、Operation EnvelopeからOperation Resultまでを包み、HTTP、Console、Internalへ共通して適用する。

Middlewareは次のScopeを宣言する。

| Scope | 対象 |
| --- | --- |
| Dispatch | Operation受付からInline実行またはExecution Transportへの配送 |
| Execution | Handlerの実行 |
| Both | DispatchとExecutionの両方 |

Global MiddlewareをConfigで登録し、Operation Attributeで追加、除外、優先度を指定できる。Manifest Compilerが最終順序を確定し、不正な構成をCIで検出する。

## Authentication

Credential AuthenticationはAdapter Middlewareで行い、Credentialを除いたActorContextだけをOperation Envelopeへ渡す。

Password、Session、Bearer TokenなどのCredentialをOperationValue、ExecutionContext、Journal、Execution Transportへ含めない。

## Authorization

Operation Definitionは `#[Authorize(...)]` でAuthorization Policyを宣言する。

PolicyはOperation Envelopeを受け取り、ActorとOperationValueを使って判断する。拒否は `OperationResult::rejected(...)` として表し、各Adapterが外部レスポンスへ変換する。

## Deferred再認可

Deferred Operationは受付時とWorker実行時の両方で認可する。

WorkerではTokenを保存せず、authorizationActorのIDから最新権限を取得する。

Actorの役割：

- `originActor`：操作の原因となった主体
- `executionActor`：現在Operationを実行しているプロセス主体
- `authorizationActor`：権限判定の対象

ユーザー起点の子OperationではoriginActorをauthorizationActorとして伝播する。System起点ではSystem ActorをauthorizationActorとする。

WorkerのSystem ActorはexecutionActorとして監査するが、元ユーザーの権限を暗黙に強化しない。

## 入口別Operation

共通Operationを基底とし、`HttpOperation`、`ConsoleOperation` など入口別のmarker interfaceを提供する。内部Operationは基本のOperationを実装する。

初期バージョンの入口はHttp、Console、Internalとする。

外部Brokerから新しいOperationを開始するMessage Adapterは後続拡張とし、Deferred配送用のExecution Transportとは区別する。
