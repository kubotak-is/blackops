# P18-006A: Session Authentication Core

Status: Accepted

## Goal

Framework同梱のOpt-in Session Authentication Coreを`BlackOps\Auth\Session`として実装する。ApplicationはOpaqueなIdentity IDを渡してSessionを発行し、FrameworkはRaw Token非保存、Absolute TTL、Last-used Touch、Rotation、Revocation、Cleanup、Doctrine DBAL Store、Bearer／Cookie HTTP Authenticationを一貫して所有する。

User、Password、Registration、Account State、Role／Permission、Cookie発行／Attribute、CSRF、Route／UIはApplication責務に残す。Session Configuration／Service Binding／MigrationのないExisting ApplicationではCapabilityを有効化せず、現行HTTP／Console／Worker Lifecycleを変えない。

## In Scope

- `BlackOps\Auth\Session`配下の最小Public API
- 32-byte CSPRNG／Base64url Raw Token、SHA-256 Hash、Safe Token Value Object
- Default 8時間Absolute TTLとDefault 5分Last-used Touch Interval
- Opaque `identity_id`とApplication-owned `SessionIdentityProvider`
- Issue、Authenticate、Rotate、Revoke、Cleanup Lifecycle
- Doctrine DBAL PostgreSQL Store、Transaction／Row Lock／Conditional Update
- `blackops_sessions` Forward Migration Template
- Bearer／Cookieを別々扱う`HttpAuthenticator` Adapter
- Safe Failure／Sensitive Surface／Worker Reuse／Connection Cleanup
- Unit／実PostgreSQL Integration Test、Permanent Internal Consumer Fixture
- Public API Inventory、Guide／Internal Documentation、Specification／Report／STATE

## Out of Scope

- `make:auth` Command／Generator／Application Scaffold／Migration Publish（P18-006B）
- Community Board Source／Configuration／Migration／Frontend変更（P18-007）
- User Table／Entity／RepositoryのFramework所有
- Password Hash／Verification、Registration、Email Policy、Account State Policy
- Role／Permission／Operation Authorization Policy
- Cookie発行、Cookie Attribute、CSRF、HTML／SvelteKit UI
- JWT、OAuth、OpenID Connect、Social Login、MFA、Remember-me
- Sliding TTL、Refresh Token、Device Management、Session List UI
- Session Authentication用の別Composer Package／Repository／Packagist／Tag／Release
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Decisions and Specifications

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/111-session-auth-package-contract.md`
- `develop/spec/17-core-api.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/61-phase-11-application-bootstrap-and-discovery.md`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Public Contract

Public APIは`#[PublicApi]`を持ち、`BlackOps\Auth\Session`配下に限定する。少なくとも次の責務を提供する。型名の微調整が必要な場合はPublic Surfaceを増やさずReportで根拠を説明する。

```php
namespace BlackOps\Auth\Session;

use BlackOps\Core\ActorRef;

interface SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef;
}

final readonly class SessionConfiguration
{
    public function __construct(
        int $ttlSeconds = 28_800,
        int $touchIntervalSeconds = 300,
    );
}

final readonly class RawSessionToken
{
    public function reveal(): string;
}

final readonly class IssuedSession
{
    public function token(): RawSessionToken;
    public function issuedAt(): \DateTimeImmutable;
    public function expiresAt(): \DateTimeImmutable;
}

interface SessionManager
{
    public function issue(string $identityId): IssuedSession;
    public function authenticate(string $rawToken): ?ActorRef;
    public function rotate(string $rawToken): IssuedSession;
    public function revoke(?string $rawToken): void;
    public function cleanup(\DateTimeImmutable $retentionCutoff): int;
}
```

- `RawSessionToken`はPublic Constructor、Public Property、`__toString()`、`JsonSerializable`を持たない。Raw Token取得は明示的な`reveal()`に限定する。
- `IssuedSession`はSession ID、Identity ID、Token Hashを露出しない。
- `SessionManager::rotate()`のMalformed／Expired／Revoked／Already Rotated／Unknownは、状態差をMessageへ出さない一つのSafe Public Exceptionへ閉じる。
- `revoke()`はnull、Malformed、Unknown、Expired、RevokedでもIdempotentに完了する。
- `SessionManager::authenticate()`がLookup／Conditional Touch／`SessionIdentityProvider`のActor解決までを所有し、`?ActorRef`だけを返す。Token Hash、Identity ID、Stored Session、Internal PortをPublic APIへ出さない。
- Clock／Random／IdentifierのTest PortはInternalに留め、ApplicationがSecurity Primitiveを交換するPublic Extension Pointを作らない。

## Token and Identity Contract

- TokenはCSPRNG 32 byteをpaddingなしBase64url化したCanonical 43文字とする。
- Storeは`hash('sha256', $rawToken)`の64文字Lowercase Hexだけを保存し、Raw TokenをSQL Parameter Log／Exception／Debug Surfaceへ出さない。
- Identity IDは空、Control Character、上限超過を発行前に拒否する。DatabaseはUser TableへForeign Keyを張らない。
- `SessionIdentityProvider::resolve()`がnullを返す場合はInvalid Sessionと同じHTTP Surfaceにする。Provider ThrowableはUnauthorizedに丸めずInfrastructure Failureとして上位Failure Boundaryへ渡す。

## Lifecycle Contract

- `issue()`はSession ID、Token、Issued At、Expires Atを一回だけ生成し、同一TransactionでHashだけをInsertする。
- ExpiryはIssued AtにTTLを加えたAbsolute Timeとし、Authenticationで延長しない。
- AuthenticationはActive／Unexpired Sessionだけを解決し、`last_used_at <= now - touchInterval`の場合だけConditional Updateする。
- `rotate()`はActive RowをLockし、旧SessionのRevocationとSuccessor Insertを一つのTransactionで行う。Concurrent Callerの先行者だけが成功し、後行者はSafe Exceptionになる。古いTokenはCommit後ただちに使えない。
- `revoke()`はActive Rowの`revoked_at`だけを一度設定する。Concurrent Revoke／RotateでActive Successorが二つにならない。
- `cleanup(cutoff)`は`expires_at < cutoff`または`revoked_at < cutoff`のRowだけを削除し、現在ActiveなRowを削除しない。削除数を返す。

## PostgreSQL Schema Contract

FrameworkはP18-006BでApplicationへPublishするImmutable Migration Templateを先に所有する。P18-006AではTest FixtureからこのTemplateと同じSchemaを実行する。

`public.blackops_sessions`:

- `id UUID PRIMARY KEY`
- `identity_id VARCHAR(255) NOT NULL`
- `token_hash CHAR(64) NOT NULL UNIQUE`
- `issued_at TIMESTAMPTZ NOT NULL`
- `expires_at TIMESTAMPTZ NOT NULL`
- `last_used_at TIMESTAMPTZ NOT NULL`
- `revoked_at TIMESTAMPTZ NULL`
- `rotated_to_id UUID NULL`、同Tableへ`ON DELETE SET NULL`のForeign Key、一つのSuccessorだけを許可
- Active Token Lookup、Identity、Expiry／Revocation Cleanupに必要なIndex

SchemaはUser TableへForeign Keyを持たず、Raw Token、Actor Type、Password、Role／Permission、Cookieを保持しない。

## HTTP Adapter Contract

- Public `BearerSessionAuthenticator` と `CookieSessionAuthenticator` は別Classとし、どちらも既存`HttpAuthenticator`を実装する。Public `SessionManager`だけへ依存し、Internal Portの`instanceof`やRuntime Fallbackを持たない。
- BearerはAuthorization HeaderがなければAnonymous、複数Header／Scheme／Token形式不正は`authentication.invalid_session`とする。
- CookieはConstructorでApplication-owned Cookie名を受け、CookieがなければAnonymous、存在するがToken形式不正なら同じInvalid Codeとする。
- Active SessionとIdentity ProviderのActorが揃った場合だけ`AuthenticationResult::authenticated()`を返す。Unknown／Expired／Revoked／Rotated／Identity Missingはすべて同じInvalid Codeにする。
- AdapterはCookieを発行せず、Cookie Attribute／CSRF／Redirect／UIを知らない。BearerとCookieを同時に解釈するCombined Adapterを作らない。

## Opt-in and Dependency Injection

- Session Core ClassをFramework ContainerへGlobal Auto-registerしない。ApplicationがPublic Session Service Provider／Bindingを明示的に追加した場合だけ、`SessionManager`、Store、Configuration、選択したAuthenticatorを解決できる。
- ApplicationはInternal ClassをImportせずに、Public Configuration、Identity Provider、Bearer／Cookie Adapterの選択だけを配線できるPublic Registration Surfaceを使う。
- Sessionを登録しないQuickstart／Skeleton／Existing ConsumerのBuild Artifact、Container Service、Migration Count、HTTP結果を変えない。

## Sensitive and Failure Contract

- Raw Token、Token Hash、Authorization／Cookie Header、Identity IDをLog、Journal、Outcome、Exception Message、Command Output、Reportへ出さない。Test Failure OutputでもRaw Token全文字を出さない。
- Malformed／Unknown／Expired／Revoked／Rotated／Identity MissingをExternal Message／Code／Timing Intentで区別しない。
- Database／Clock／Random／Identity Provider FailureをInvalid Credentialとして隠蔽せず、上位のSafe Internal Failure Boundaryへ渡す。
- Long-running Worker／FrankenPHPでRequest間にRaw Token、Actor、Stored Session、Connection Stateを保持しない。

## Allowed Files

- `src/Auth/Session/**`
- `src/Internal/Auth/Session/**`
- SessionのPublic Registrationに必要な`src/Application/**`、`src/Internal/Application/**`、`src/Internal/DependencyInjection/**`の最小差分
- `resources/stubs/auth-session-migration.php.stub`
- `tests/Auth/Session/**`、`tests/Internal/Auth/Session/**`、必要な既存Application／DI／HTTP Testの最小差分
- P18-006A専用の`tests/Consumer/**`または`tests/Fixture/**`
- `develop/spec/17-core-api.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/47-public-http-runtime-configuration.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/75-phase-18-delivery-plan.md`
- `docs/guide/**`、`docs/internal/**`、Public API Inventory同期に必要な`docs/website/tests/reader-experience.test.mjs`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-006A-session-authentication-core.md`

`examples/community-board/**`は変更禁止とする。Quickstart／SkeletonはRegression Test実行対象だが、Session Scaffoldの追加はP18-006Bまで行わない。必要なAllowed Fileの追加があればProduction Codeを広げずReportでOrchestratorへ返す。

## Required Verification

1. Token／Configuration／Value Object／Safe Exception Unit Test
2. Bearer／Cookie Adapter MatrixとSensitive Surface Test
3. 実PostgreSQL Issue／Authenticate／Touch／Expiry／Rotate／Revoke／Cleanup Integration Test
4. Concurrent Rotation／Revoke Test、Token Hash／Raw Token Non-persistence Guard
5. Session Registrationあり／なしのCompiled Container／HTTP Bootstrap Test
6. Existing Full PHPUnit、Quickstart Setup／E2E、Skeleton Create-project、Framework Update Generator
7. Root／Quickstart `composer validate --strict`
8. Website Reader Test／Build（Public API数とDocumentationを変更した場合）
9. Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`
10. Community Board差分0、Generated／Dependency／Runtime Artifact Cleanup

## Acceptance Criteria

- [ ] `BlackOps\Auth\Session`のPublic Surfaceが最小でInternal Typeを露出しない
- [ ] Raw Tokenは発行／Rotation時に一度だけ返され、Database／Failure／Observation Surfaceへ残らない
- [ ] Absolute TTL、Conditional Touch、Idempotent Revoke、Retention Cleanupが決定的に動作する
- [ ] Concurrent Rotationで有効なSuccessorが一つだけになり、旧Tokenを再利用できない
- [ ] Session RowはOpaque Identityだけを保持し、Application Providerが現在のActor／Account Stateを解決する
- [ ] Bearer／Cookie AdapterがAnonymous／Authenticated／Invalid／Infrastructure Failureを正しく分離する
- [ ] Sessionを登録しないExisting ApplicationがBuild／Runtime／Migrationの影響を受けない
- [ ] User／Password／Registration／Role／Cookie Attribute／CSRF／UIをFrameworkが所有しない
- [ ] Quickstart／Skeleton／Framework Update／Full Quality Gateが回帰する
- [ ] Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Completion Report

`develop/orchestration/reports/P18-006A-session-authentication-core.md`にAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public APIとOpt-in Registration Surface
- Token／Identity／Lifecycle／Schema／HTTP Matrix
- Concurrent Rotation／RevocationとSensitive Evidence
- Session Registrationあり／なしのConsumer Evidence
- Allowed Scope外の必要性が発生した場合のBlocker
- Commandsと実結果、未実行理由、Remaining Issue
