# Authentication and HTTP Middleware

## Scope

Phase 12はPSR-15 HTTP Middleware、Authentication、ActorContext、Operation Authorization、Deferred再認可を提供する。

Operation Middleware、Console Middleware、Message Middleware、入口別Operation marker interfaceは提供しない。Operationは一種類のままとし、`#[Route]`、`#[ExecuteWith]`、`#[Authorize]`等のAttributeで入口、実行方式、認可Policyを宣言する。

## HTTP Middleware

HTTP MiddlewareはPSR-15 `Psr\Http\Server\MiddlewareInterface`をそのままPublic Contractとして使用する。BlackOps固有のmarker interfaceは追加しない。

MiddlewareはServer RequestからHTTP Responseまでを`next`で包む玉ねぎPipelineであり、RequestとResponseの両方を加工できる。

```php
final readonly class AddResponseHeader implements Psr\Http\Server\MiddlewareInterface
{
    public function process(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Server\RequestHandlerInterface $handler,
    ): Psr\Http\Message\ResponseInterface {
        return $handler->handle($request)->withHeader('X-Application', 'example');
    }
}
```

Applicationは`config/middleware.php`へMiddlewareのService IDまたはClass名を外側から内側の順で登録する。RuntimeはCompiled Containerから各Middlewareを解決し、登録順を変えずにPipelineを構成する。

```php
<?php

declare(strict_types=1);

return [
    'http' => [
        App\UserInterface\Http\Middleware\AddResponseHeader::class,
    ],
];
```

Phase 12ではGlobal HTTP Pipelineだけを必須とする。Operation単位の`#[UseMiddleware]`／`#[WithoutMiddleware]`、数値Priority、`before`／`after`依存解決、Dispatch／Execution Scopeは追加しない。ConfigがListでない、登録値がClass-stringでない、ServiceがPSR-15 Middlewareでない場合はBuildまたは起動時にFail-fastする。

## Authentication

Frameworkは次のPublic Contractを提供する。

```php
interface HttpAuthenticator
{
    public function authenticate(
        Psr\Http\Message\ServerRequestInterface $request,
    ): AuthenticationResult;
}
```

`AuthenticationResult`はFramework管理の`#[PublicApi] final readonly class`とし、次の三状態だけを表す。

- `anonymous()`：Credentialがない
- `authenticated(ActorRef $actor)`：Credentialが有効でActorを特定した
- `invalid(string $code)`：Credentialが存在するが無効である

`code`は外部へ公開可能な安定Codeとし、Credential、Token、Session ID、Backend Error Detailを含めてはならない。

Frameworkの`AuthenticationMiddleware`はAuthenticatorをConstructor Injectionし、結果を次のように扱う。

- AnonymousはActorを付けずに次へ渡す
- Authenticatedは`ActorRef`だけをFramework予約のTyped Request Attributeへ設定する
- InvalidはOperation IDを発行する前にSafeな401 Responseを返す
- Authenticatorが投げた予期外例外をInvalid Credentialへ丸めず、HTTP RuntimeのServer Error境界へ渡す

ApplicationはSession、Bearer Token、API Key、External IdP等のCredential解析と検証を実装する。Frameworkは特定Schemeの具象実装を標準依存へ追加しない。

Password、Session、Bearer Token、API Key、JWT Claim等のCredential DataをOperationValue、ExecutionContext、Journal、Log Context、Execution Transportへ含めてはならない。

## Actor Model

`BlackOps\Core\ActorRef`は`#[PublicApi] final readonly class`とし、Application内でActorを一意に指す非空の`id`と`type`だけを保持する。

```php
public function __construct(string $id, string $type);
public function id(): string;
public function type(): string;
```

`BlackOps\Core\ActorContext`は`#[PublicApi] final readonly class`とし、次を保持する。

```php
public function __construct(
    ?ActorRef $origin,
    ?ActorRef $authorization,
    ActorRef $execution,
);

public function origin(): ?ActorRef;
public function authorization(): ?ActorRef;
public function execution(): ActorRef;
```

- origin：Operationの原因となった主体
- authorization：現在の権限を評価する主体
- execution：実際に処理を実行するApplication／Worker主体

Anonymous HTTP Requestではoriginとauthorizationを`null`にする。User起点では認証済みActorをoriginとauthorizationへ設定する。System起点では明示したSystem Actorを使用する。

InlineからDeferredへ移る際はoriginとauthorizationを維持し、Worker Attempt開始時にexecutionだけを設定済みWorker System Actorへ置き換える。子Operationは親のoriginとauthorizationを維持し、実行主体だけを現在のRuntimeに合わせる。

Durable ContextとCanonical Journalへ保存できるActor DataはIDとTypeだけとする。Role、Permission、Credential、Token、Session、ClaimのSnapshotは保存しない。Observer ProjectionはActor IDをSensitive Dataとして扱い、既定ではMaskする。

## Authorization

Operationは`#[Authorize(PolicyClass::class)]`で認可Policyを宣言する。

```php
#[BlackOps\Core\Attribute\Authorize(CreateOrderPolicy::class)]
final readonly class CreateOrder implements BlackOps\Core\Operation
{
    public function handle(CreateOrderValue $value): OrderCreated
    {
        // ...
    }
}
```

`Authorize`はOperation Classだけを対象とする非Repeatableな`#[PublicApi]` Attributeであり、`AuthorizationPolicy`を実装するClass-stringを一つ受け取る。複数条件が必要な場合はApplication Policy内で合成する。

```php
interface AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision;
}
```

`AuthorizationRequest`はOperation、OperationValue、ExecutionContext、非nullのauthorization Actorを読み取り専用で提供する。PolicyはDIされたApplication Serviceから現在のActor、Role、Permission、Resource状態を取得する。

`AuthorizationDecision`は`allow()`、`unauthorized(string $code)`、`forbid(string $code)`の三状態を持つ。Codeは`RejectionReason`と同じ安定Code規則に従う。Policy Backendの接続障害やTimeoutをDecisionへ丸めてはならず、例外としてRuntimeへ伝える。

Authorizationは除外可能なMiddlewareではなく、Framework管理のOperation Lifecycle Stageである。

- `#[Authorize]`がないOperationはPolicy評価を行わない
- authorization Actorがない場合はPolicyを呼ばずUnauthorizedとしてRejectする
- UnauthorizedはJournalへ`operation.rejected`を記録し、HTTPでは401へ変換する
- ForbiddenはJournalへ`operation.rejected`を記録し、HTTPでは403へ変換する
- Allowの場合だけHandlerまたはDeferred配送へ進む
- Policyの予期外例外はSecurity Denialへ丸めない

Authorization拒否CodeはPublic ResponseとJournalへ保存できるが、CredentialやBackend Detailを含めてはならない。

## Deferred再認可

Deferred Operationは受付時とWorker実行時の両方で同じPolicyを評価する。

受付時はOperation ID発行、Binding、Validation、Received記録後、Execution Transportへ配送する前に評価する。WorkerではAttempt開始後、Handler実行前にauthorization ActorのID／Typeを使って最新状態を評価する。

- Actorが存在しない、無効、認証状態を失った場合はUnauthorizedのTerminal Rejected
- 認証済みActorが現在の権限を持たない場合はForbiddenのTerminal Rejected
- Actor RepositoryまたはPolicy Backendの接続障害、Timeout、その他予期外例外はAttempt Failure
- Attempt Failureは既存Supervision PolicyのRetry、Backoff、Dead Letterへ参加する

Workerのexecution Actorをauthorization Actorとして使用して元Actorの権限を強化してはならない。

## FrameworkとApplicationの責任分界

| 項目 | Framework | Application |
| --- | --- | --- |
| PSR-15 Pipeline構成 | 登録順で構成、型検証、Runtime接続 | Middleware実装と登録 |
| Credential Scheme | Credential非永続化Invariant | Session／Token／API Key等の解析と検証 |
| Authentication結果 | Anonymous／Authenticated／Invalidの安全な処理 | Actorの特定とSafe Code |
| Actor伝播 | Context／Transport／Journal Codec | Actor ID／TypeとSystem Actor設定 |
| Authorization | Attribute Discovery、Lifecycle、401／403、再認可 | Policyと現在権限の取得 |
| Backend障害 | Failure／Supervisionへ分類 | 一時障害を拒否Decisionへ丸めない |

## Traceability

- Decision: [D095 Phase 12 Middleware and Authorization Runtime](../decisions/095-phase-12-middleware-and-authorization-runtime.md)
- Context API: [ExecutionContext API](19-execution-context-api.md)
- Delivery Plan: [Phase 12 Delivery Plan](63-phase-12-delivery-plan.md)
