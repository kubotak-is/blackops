# Authorization

Authorizationは、認証済みActorがOperationまたはResourceを実行できるかを判定するApplication Policyです。BlackOpsは`#[Authorize]`、Actor Context、Typed Request／Decision、Build-time DI登録の境界を提供します。Role、Permission、Tenant、Resource所有権の検索結果はApplicationが返します。

## InlineとDeferred

InlineではHandler実行前にPolicyを評価します。Deferredでは受付時のActor Contextを保存し、Workerが実行を開始する前に再認可します。受付後に権限が失われた場合、Workerは副作用を実行せずRejectedへ遷移します。

Status Queryの認可はOperation実行時のPolicyと別です。Unknown OperationとDenyを同じ404へ丸め、OutcomeやJournal Detailを読む前に判定します。詳しい責任分界は[Status参照の認可](security.md#status参照の認可)を確認してください。

## Applicationの責任

ApplicationはCurrent Actor、Origin Actor、Tenant、Resourceを使ったPolicyを実装し、最小情報のDecisionだけをFrameworkへ渡します。認可をAuthentication、TLS、Database暗号化、Credential Rotationの代替として扱わないでください。

## Policyを実装してBindingする

OperationへPolicyを宣言し、`AuthorizationRequest`から必要なResource IDだけを受け取ります。

```php
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/invoices/{invoiceId}')]
#[Authorize(ApplicationPolicy::class)]
final readonly class UpdateInvoice implements Operation
{
    public function handle(UpdateInvoiceValue $value, ExecutionContext $context): InvoiceOutcome
    {
        return new InvoiceOutcome(/* Application implementation */);
    }
}

final readonly class ApplicationPolicy implements AuthorizationPolicy
{
    public function __construct(private InvoiceRepository $repository) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $value = $request->value();
        if (!$value instanceof UpdateInvoiceValue) {
            return AuthorizationDecision::unauthorized('authorization.invalid_value');
        }

        return $this->repository->canUpdate($request->actor(), $value->invoiceId())
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::forbid('invoice_forbidden');
    }
}
```

PolicyがRepositoryやPermission Serviceを必要とする場合は、Application Service ProviderでInterfaceをContainerへBindingしてから`php blackops build:compile`を実行します。

```php
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(InvoiceRepository::class, DoctrineInvoiceRepository::class);
    }
}
```

ActorがないRequestではPolicyを呼ばず`authentication_required`のUnauthorizedになります。Authentication済みのPolicyは`AuthorizationDecision::unauthorized($code)`または`AuthorizationDecision::forbid($code)`を返します。Anonymous、Unauthorized、Forbiddenを同じ成功応答へ丸めず、HTTP応答とJournalの公開情報を分けます。

Deferred受付ではOrigin Actor Contextを保存し、Workerが副作用を始める直前にPolicyを再評価します。Actorが無効化された場合はRejectedへ遷移させ、HandlerやOutboxへ到達させません。Status Queryは実行Policyを再利用せず、`OperationStatusAuthorizer`を`Status` endpointへBindingします。

```php
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class InvoiceStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        $current = $request->currentActor();
        $origin = $request->originActor();
        if ($current === null || $origin === null) {
            return OperationStatusAuthorizationDecision::deny();
        }
        if ($current->type() !== 'user' || $origin->type() !== 'user') {
            return OperationStatusAuthorizationDecision::deny();
        }
        return ($current->id() === $origin->id() && $current->type() === $origin->type())
            ? OperationStatusAuthorizationDecision::allow()
            : OperationStatusAuthorizationDecision::deny();
    }
}
```

`OperationStatusAuthorizer::class`をApplication Service Providerで`InvoiceStatusAuthorizer::class`へBindingします。

```php
$services->autowire(OperationStatusAuthorizer::class, InvoiceStatusAuthorizer::class);
```

RequestのCurrent／Origin ActorとDecisionを使うStatus専用Policyであり、Operationの`#[Authorize]`とは別の責務です。
