# Authentication

この章はRepository `main` PreviewでSession Starterの生成とHTTP境界を確認する手順です。AuthenticationはStable `1.1.0`には含まれません。[Repository main Preview](mvp-sample.md#repository-main-preview)を完了したProject Rootから実行し、問題は[Troubleshooting](troubleshooting.md)で確認します。

## Application-owned Starterを生成する

FrameworkはSession CoreとAuthentication Middlewareの境界だけを提供します。User、Password Hash、Registration Policy、Session Transport、Cookie／CSRFはApplicationの責任です。

```bash
docker compose build app http
docker compose up -d postgres
docker compose run --rm app composer require --no-update doctrine/dbal:^4.4 doctrine/migrations:^3.9
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php blackops make:auth
```

`make:auth`は二回目以降に既存Starterを検出して上書きしません。生成物を確認してからApplicationの実装を決めます。

```text
主要な生成物（抜粋）:
app/Domain/Identity/User.php
app/Infrastructure/Identity/DoctrineUserRepository.php
app/Feature/Identity/Register/Register.php
app/Feature/Identity/Login/Login.php
app/Feature/Identity/Logout/Logout.php
app/AuthServiceProvider.php
config/auth.php
migrations/Version20260722000000.php
migrations/Version20260722000100.php
```

Generatorは動作するApplication-owned Starterを生成します。生成済み実装をReviewし、ProductionのPassword／Registration Policyに合わせて置換または拡張します。

```php
// app/Domain/Identity/User.php
final readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
    ) {}
}

// app/Domain/Identity/PasswordHasher.php
final readonly class PasswordHasher
{
    private string $dummyHash;

    public function __construct()
    {
        $this->dummyHash = $this->hash(base64_encode(random_bytes(32)));
    }

    public function hash(#[SensitiveParameter] string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verifyCredential(#[SensitiveParameter] string $password, ?string $knownHash): bool
    {
        $verified = password_verify($password, $knownHash ?? $this->dummyHash);

        return $knownHash !== null && $verified;
    }
}

// app/Feature/Identity/Register/Register.php
#[Route(method: 'POST', path: '/auth/register')]
#[OperationType('auth.register')]
readonly class Register implements Operation
{
    public function __construct(
        private IdentityService $identity,
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(RegisterValue $value): RegistrationCompleted
    {
        try {
            $user = $this->identity->register($value->email, $value->displayName, $value->password);
        } catch (DuplicateEmail) {
            throw OperationRejectedException::conflict('auth.email_unavailable');
        } catch (RegistrationDisabled) {
            throw OperationRejectedException::forbidden('auth.registration_disabled');
        }

        return $this->outcome($this->sessions->issue($user->id));
    }

    private function outcome(IssuedSession $session): RegistrationCompleted
    {
        return new RegistrationCompleted(
            $session->token()->reveal(),
            $session->issuedAt()->format(DATE_ATOM),
            $session->expiresAt()->format(DATE_ATOM),
        );
    }
}
```

`config/auth.php`のPreview既定は`SessionServiceProvider::bearer(ApplicationSessionIdentityProvider::class, ...)`です。`AUTH_REGISTRATION_ENABLED`、`AUTH_SESSION_TTL_SECONDS`（既定28800）、`AUTH_SESSION_TOUCH_INTERVAL_SECONDS`（既定300）を決め、全ClientでToken Channelを統一します。

環境変数の責務とCookie／Bearerの選択は[HTTP Authenticationの境界](security.md#http-authenticationの境界)を参照してください。既存Starterを意図的に更新する場合は`php blackops make:auth --force`を使います。生成済みDomain／Operation／Migrationは上書きしません。

```php
return static fn(Environment $env): array => [
    'generator_version' => 1,
    'services' => [
        new AuthServiceProvider($env->bool('AUTH_REGISTRATION_ENABLED', true)),
        SessionServiceProvider::bearer(
            ApplicationSessionIdentityProvider::class,
            new SessionConfiguration(
                ttlSeconds: $env->positiveInt('AUTH_SESSION_TTL_SECONDS', 28_800),
                touchIntervalSeconds: $env->positiveInt('AUTH_SESSION_TOUCH_INTERVAL_SECONDS', 300),
            ),
        ),
    ],
];
```

`config/middleware.php`へ`AuthenticationMiddleware::class`がすでに登録されていることを確認し、重複登録しません。`/welcome`は`make:auth`を実行しても自動ではBearer保護されません。検証には生成されたAuth Operationか、次の節で作成するProtected Operationを使います。

## Protected `GET /me`を追加する

生成物へ依存しない最小のProtected OperationをApplicationへ追加します。`ActorContext`にはCredentialではなく認証済みActorのID／Typeだけが入ります。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Identity\Me;

use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Http\Attribute\Route;
use RuntimeException;

final readonly class MeValue implements OperationValue {}

final readonly class MeShown implements Outcome
{
    public function __construct(public string $actorId) {}
}

#[Route(method: 'GET', path: '/me')]
#[OperationType('identity.me')]
#[Authorize(SampleUserAuthorizationPolicy::class)]
final readonly class ShowMe implements Operation
{
    public function handle(MeValue $value, ExecutionContext $context): MeShown
    {
        $actor = $context->actorContext()?->authorization();
        if ($actor === null) {
            throw new RuntimeException('Authenticated actor is required.');
        }

        return new MeShown(actorId: $actor->id());
    }
}
```

## Autoload、Migration、Build、HTTP

ApplicationのBindingとPolicyを実装したら、Artifactを再生成してHTTPを再起動します。

```bash
docker compose run --rm app composer dump-autoload --classmap-authoritative
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
docker compose up -d http
```

Frontendを持たないBackend-only Applicationは最後の二つを省略できます。Worker Modeへ新しいArtifactを読ませるためHTTPはBuild後に再起動します。

## Register、Login、Logoutの期待結果

Tokenは現在のShellだけで保持し、Report、Journal、Generated Client、Repositoryへ保存しません。

```bash
curl -i -X POST http://127.0.0.1:8080/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","displayName":"Ada","password":"correct horse battery staple"}'
# 200、43文字のOpaque Token

curl -i -X POST http://127.0.0.1:8080/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","displayName":"Ada","password":"correct horse battery staple"}'
# 409、code: auth.email_unavailable

curl -i -X POST http://127.0.0.1:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"correct horse battery staple"}'
# 200、新しい43文字のToken

curl -i -X POST http://127.0.0.1:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"wrong horse battery staple"}'
# 401、code: auth.invalid_credentials

curl -i -X POST http://127.0.0.1:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"short"}'
# 422、code: validation.failed（violation: validation.length）

curl -i -X POST http://127.0.0.1:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com"}'
# 422、code: validation.failed（violation: binding.required）
```

401はValidationを通過した形式上妥当なPasswordが一致しない場合だけです。短いPasswordは宣言的Validation、欠落したPasswordはBindingで422になります。層の違いは[Value and Validation](validation.md)で確認できます。

Bearer PreviewでProtected Operationを呼び、同じTokenをLogout JSONへ渡します。

```bash
curl -i http://127.0.0.1:8080/me \
  -H 'Authorization: Bearer <token-from-register-or-login>'
# 200、{"actorId":"<registered-user-id>"}（生成されたUser UUIDv7）

curl -i http://127.0.0.1:8080/me
# 401、code: authorization.authentication_required

curl -i -X POST http://127.0.0.1:8080/auth/logout \
  -H 'Content-Type: application/json' \
  -d '{"token":"<token-from-register-or-login>"}'
# 200、{}

curl -i -X POST http://127.0.0.1:8080/auth/logout \
  -H 'Content-Type: application/json' \
  -d '{"token":"<same-token>"}'
# 200、{}（冪等）

curl -i http://127.0.0.1:8080/me \
  -H 'Authorization: Bearer <same-token>'
# 401、code: authentication.invalid_session
```

有効Tokenの`GET /me`は200、TokenなしまたはLogout済みは401になります。`/welcome`はAuthenticationを明示的に付けない限り匿名のままです。Raw Password、Session Token、Cookie、CSRF Token、Authorization HeaderはOperation Value、Outcome、Journal、Log、Task Reportへ出力しません。Cookie方式を選ぶ場合は`CookieSessionAuthenticator`と[Security](security.md)の責務表を確認してください。
