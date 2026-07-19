# P17-003: Identity, Session, and BFF Boundary

Status: Ready

## Goal

`examples/community-board/`へApplication-ownedなUser／Session境界を実装し、Register、Login、Authenticated Current User、LogoutをSvelteKit Form Actionから完走させる。PasswordとRaw Session CredentialをBlackOps Operation、Journal、Outcome、Generated Contract、Browser JavaScriptへ入れず、BlackOps Runtimeへは検証済み`ActorRef`だけを渡す。

## In Scope

- `board_users`／`board_sessions` Application Migration
- User／SessionのApplication-owned RepositoryとService
- Application-owned Authentication Router
- `POST /auth/users`、`POST /auth/sessions`、`DELETE /auth/sessions/current`
- Authentication Route以外を`Application::http()`へ委譲するOuter PSR-15 Router
- Classic Front ControllerとFrankenPHP Workerの同一Composition
- Password Hash／Verify／Rehash、Opaque Session Token Hash、Expiry、Revocation、Login時Rotation
- Bearer Sessionを`ActorRef`へ変換する`HttpAuthenticator`
- Authenticated Actorだけを許可するAuthorization Policy
- `ShowCurrentUser` Inline OperationとServer-only Generated Wrapper
- SvelteKit Register／Login／Logout Form ActionとHttpOnly Cookie
- Current Sessionを安全なPage View Modelへ投影するSvelteKit Server-only境界
- CSRF／Origin、No-store、Safe Error、Cookie、Sensitive Data Guard
- PHP／SvelteKit Unit Test、Real HTTP Identity Consumer E2E、CI更新
- README、Report、TODO、STATE同期

## Out of Scope

- Post／Comment Table、Repository、Operation、Page
- Digest、Deferred Operation、Status／Wait UI
- Owner Authorization、Role、Admin、Tenant、OAuth、MFA、Password Reset、Email Verification
- Remember-me、Refresh Token、Device管理、Session一覧
- Final Visual Design、Taste Skill、Reicon、Animation、Screenshot、Browser Automation
- Framework `src/**`とRoot Public Contract変更
- `examples/quickstart/**`、Skeleton Source、Publication Workflow変更
- Documentation Website Content／Publication／Deploy
- External Hosting、Managed Identity Provider、Mail Service

## Relevant Specifications and Decisions

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/configuration.md`
- `docs/guide/security.md`
- `docs/guide/runtime-bootstrap.md`

## Files Allowed to Change

### Reference Application

- `examples/community-board/**`

Generated／Dependency／Runtime Artifactは作業中に生成してよいが、Task完了前にIgnore／Cleanup／Tracking Guardを固定する。

### Consumer and CI

- New `tests/Consumer/community-board-identity.sh`
- `tests/Consumer/community-board-foundation.sh`（Identity導入で必要になる互換修正だけ）
- `.github/workflows/ci.yml`

### Documentation and Orchestration

- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-003-identity-session-and-bff-boundary.md`
- `develop/spec/71-full-stack-reference-application.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/72-phase-17-delivery-plan.md`（Task境界の誤りを発見した場合だけ）

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。特に`src/**`、既存Root `tests/**`、`examples/quickstart/**`を変更しない。

## Identity Data Contract

### `public.board_users`

最低限、次を持つ。

```text
id                 UUID primary key
email_canonical    VARCHAR(254) unique, ASCII lowercase comparison value
email_display      VARCHAR(254)
display_name       VARCHAR(80)
password_hash      VARCHAR(255)
created_at         TIMESTAMPTZ
updated_at         TIMESTAMPTZ
```

- User IDはSymfony UIDのUUIDv7をApplication Codeから発行する
- Emailはtrim後に`FILTER_VALIDATE_EMAIL`で検証し、ASCII lowercase canonical値で一意判定する
- Display用EmailとCanonical比較値を分ける
- Emailは2回目の登録可否を漏らす用途に使わず、UIへは安定した`identity.email_unavailable`を返す
- Initial ScopeではUser削除、Soft Delete、Email変更を実装しない

### `public.board_sessions`

最低限、次を持つ。

```text
id                 UUID primary key
user_id            UUID foreign key
token_hash          CHAR(64) unique
issued_at           TIMESTAMPTZ
expires_at          TIMESTAMPTZ
revoked_at          TIMESTAMPTZ nullable
```

- Raw Tokenは`random_bytes(32)`由来のBase64url without paddingとする
- Databaseには`hash('sha256', $rawToken)`のlowercase hexだけを保存する
- 256-bit Random Tokenに対するHashであり、TokenをPasswordとして扱わない
- Session TTLは8時間をCanonical Defaultとし、正の有限秒だけをConfigurationで許可する
- Login成功ごとに新しいTokenを発行し、BFFが既存Sessionを提示した場合は同じTransaction内で既存Sessionを失効してRotationする
- LogoutはCurrent Token HashをServer側で失効し、既に失効／期限切れでも安全に完了できる
- Expiry／Revocation済みSessionはAuthenticationへ使用できない
- Initial Scopeでは期限切れRowのRetention／Cleanup Policyを決めない

## Password Contract

- Passwordは12文字以上128文字以下とする
- PHPの`PASSWORD_ARGON2ID`でHashし、`password_verify()`で照合する
- Login成功時に`password_needs_rehash()`を評価し、必要なら保存Hashを更新する
- Plaintext PasswordをPropertyへ長期保持せず、Response、Exception Message、Log、Journal、Operation Value／Outcome、Generated Sourceへ含めない
- Unknown EmailとPassword不一致は同じStatus／Code `401 authentication.invalid_credentials`へ投影する
- Hash AlgorithmをRuntimeが提供しない場合はBootstrap／TestでFail-fastし、別Algorithmへ暗黙Fallbackしない

## Authentication HTTP Contract

Authentication RouteはOperationではなくApplication-owned PSR-15 Handlerが所有する。

| Method / Path | Input | Success | Expected Failure |
|---|---|---|---|
| `POST /auth/users` | JSON `email`, `displayName`, `password` | `201`, safe user object + raw `sessionToken` | `400`, `409`, `415`, `422` |
| `POST /auth/sessions` | JSON `email`, `password`; optional current Bearer for rotation | `200`, safe user object + new raw `sessionToken` | `400`, `401`, `415`, `422` |
| `DELETE /auth/sessions/current` | Bearer raw session token | `204` | malformed protocol only; missing／unknown／expired token is idempotent `204` |

- JSON以外は`415 identity.unsupported_media_type`
- Malformed JSON／Object以外／Unknown FieldはRaw Detailなしの`400 identity.invalid_request`
- Validation Failureは`422 identity.validation_failed`とField単位のSafe Codeを返す
- Validation ResponseへRejected Password Valueを含めない
- Duplicate Emailは`409 identity.email_unavailable`
- 全Authentication Responseに`Cache-Control: private, no-store`と`Pragma: no-cache`を付ける
- Success User Objectは`id`、`email`、`displayName`だけを持つ
- Raw `sessionToken`はRegistration／Login成功Responseにだけ含め、PHP側でLogしない
- Authentication Routerの404／405／500はRaw Exception、SQL、Absolute Path、Credentialを含めない
- `/auth/*`以外は既存BlackOps Handlerへ委譲し、`/welcome`を壊さない
- Classic `public/index.php`とFrankenPHP `public/worker.php`は同じApplication-owned Handler Factory／Compositionを使用する
- Authentication RouteのDBAL Connectionは必要なRequestでだけApplication-ownedに作り、Request終了時にCloseする。BlackOps Operation側のConnectionはFramework DI／Lifecycleを使う

## BlackOps Authentication and Authorization Contract

- `AuthenticationMiddleware`をGlobal HTTP Pipelineへ登録する
- Application `HttpAuthenticator`は`Authorization: Bearer <rawToken>`だけを受理する
- Header欠落はAnonymous、Malformed／Unknown／Expired／Revoked Tokenは`401 authentication.invalid_session`
- Valid Sessionは`new ActorRef($userId, 'user')`へ変換し、Raw TokenやUser RecordをRequest Attributeへ渡さない
- AuthenticatorのRepositoryはDefault `Doctrine\DBAL\Connection`をConstructor Injectionする
- `AuthenticatedUserPolicy`等のApplication PolicyはActor Type `user`だけをAllowし、Anonymousは安定したUnauthorized Codeへする
- `ShowCurrentUser`は`GET /me`、Operation Type `board.identity.current.user.show`、Inline、`#[Authorize]`付きとする
- `ShowCurrentUser`は`ExecutionContext`のAuthorization Actor IDをRepositoryへ明示してSafe Userを返す
- Outcomeは`id`、`email`、`displayName`だけを持ち、Token、Password Hash、Session ID／Expiryを持たない
- `/me`はFrontend Generate／Check対象とし、Generated ContractへCredential Fieldを追加しない

## SvelteKit BFF and Cookie Contract

- BrowserはSvelteKit Originだけへ接続し、PHP Authentication Route／BlackOps Routeへ直接Fetchしない
- Registerは`/register`、Loginは`/login`の`+page.server.ts` Form Actionを使う
- LogoutはServer-side Form Actionを使い、GETやClient-only Handlerで状態変更しない
- SvelteKitのDefault Origin Checkを無効化しない
- Server ActionはApplication-owned `.server.ts` Auth Clientを経由し、Internal Base URLとRaw TokenをBrowserへ返さない
- Cookie名は`community_board_session`
- Cookieは`HttpOnly`、`SameSite=Strict`、`Path=/`とし、Productionでは`Secure=true`を必須にする
- Local HTTPだけは明示`SESSION_COOKIE_SECURE=false`を許可し、未指定のProduction ConfigurationでSecureなしにしない
- `Max-Age`はBackend Session TTL以下とし、Logout／Invalid SessionでCookieを削除する
- Registration／Login成功時はPHP ResponseのRaw TokenをCookieへ移し、Page Data／Action Dataへ含めない
- SvelteKit Server-only BlackOps Wrapperは呼出単位で`Authorization` Headerを注入し、Global Mutable ClientやGenerated Configurationへ保存しない
- Root Layoutまたは同等のServer Loadは`ShowCurrentUser.fetch()`を使い、BrowserへSafe Current User Viewだけを返す
- Invalid／Expired SessionはCookieを削除し、Login導線へ安全に投影する
- 422 Field Errorは入力値を必要最小限だけ戻す。PasswordはAction Dataへ戻さない
- Redirect targetは固定Routeだけを使い、User入力URLへのOpen Redirectを実装しない

## Security and Sensitive Guards

- Raw Session Token MarkerをFixtureで生成し、次へ残らないことを確認する
  - PostgreSQLの`board_sessions.token_hash`以外のColumn
  - BlackOps Journal／Outcome／Transport Payload
  - Generated TypeScript Tree
  - SvelteKit Client Build／SSR HTML／Action Response
  - Log、Exception、Report、Tracked Source
- Password Markerも同じSurfaceに残らないことを確認する
- PHP Authentication ResponseからTokenを受け取るのはSvelteKit Serverだけであり、Consumer TestはBrowser CookieからHttpOnly／SameSite／Pathを確認する
- Auth Failure、Duplicate Email、Malformed JSON、Database FailureでRaw Body／SQL／Internal URL／Absolute PathをBrowserへ返さない
- `.env`、Runtime Artifact、Generated Tree、Dependency、Build Artifactを追跡しない
- Reiconを含むIcon LibraryをこのTaskで追加しない

## Testing and CI Contract

- Example-owned PHPUnitまたは同等に明示されたPHP Test入口で、Email normalization、Password verification／rehash、Token hash、Expiry、Revocation、Rotation、Safe Errorsを検証する
- SvelteKit VitestでAuth Client、Cookie設定、Field Error投影、Password非反射、Invalid Session処理を検証する
- Real HTTP Consumer E2Eは次を完走する
  1. Register Page取得
  2. Form ActionでUser登録
  3. HttpOnly Cookie取得
  4. Authenticated `/me`を介したSafe Current User表示
  5. LogoutとServer-side Revocation
  6. 旧Cookieで`/me`が認証されない
  7. Loginで新Cookie取得
  8. Rotation後に旧Cookieが無効、新Cookieが有効
  9. Expired Sessionが拒否されCookieが除去される
- PHP Debug PortからAuthentication HTTP ContractとBlackOps `/me`の401／200も検証する
- Foundation Consumer E2Eを回帰させない
- CIのCommunity Board JobでIdentity Testを追加し、失敗時もContainer／ArtifactをCleanupする

## Acceptance Criteria

- [ ] User／Session MigrationがApplication-owned Tableを作る
- [ ] Registration、Login、LogoutがApplication-owned Authentication Routerで完走する
- [ ] Outer RouterがAuthentication以外をBlackOps Handlerへ委譲する
- [ ] Classic／FrankenPHPが同じCompositionを使う
- [ ] PasswordがArgon2idでHash／Verify／RehashされPlaintextを保存しない
- [ ] Raw Session TokenをDBへ保存せずHash、Expiry、Revocation、Rotationを実装する
- [ ] `HttpAuthenticator`がValid Sessionだけを`ActorRef`へ変換する
- [ ] `ShowCurrentUser`がAuthorization付きOperationとしてSafe Userだけを返す
- [ ] SvelteKit Form ActionがRegister／Login／Logoutを実装する
- [ ] HttpOnly／SameSite Strict／Path／Secure Configurationを満たす
- [ ] Browser JavaScript、Generated Contract、Journal、OutcomeへCredentialが入らない
- [ ] CSRF Origin Check、No-store、Safe Error、Password非反射を検証する
- [ ] PHP／SvelteKit TestとReal HTTP Identity Consumer E2Eが成功する
- [ ] Foundation Consumer E2Eが回帰しない
- [ ] Generated／Dependency／Build Artifactを追跡しない
- [ ] Quickstart／Skeleton Sourceを変更しない
- [ ] Framework `src/**`を変更しない
- [ ] Required Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

実際のTest Script名を変更する必要がある場合、ReportへCanonical Commandを記録する。

```bash
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir examples/community-board/frontend install --frozen-lockfile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:generate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:check
docker compose -f examples/community-board/compose.yaml run --rm app vendor/bin/phpunit
pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
git diff --exit-code -- examples/quickstart
! git ls-files \
  examples/community-board/.env \
  examples/community-board/vendor \
  examples/community-board/var/build \
  examples/community-board/var/log \
  examples/community-board/frontend/node_modules \
  examples/community-board/frontend/src/lib/server/blackops/generated \
  examples/community-board/frontend/.svelte-kit \
  examples/community-board/frontend/build | grep -q .
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P17-003-identity-session-and-bff-boundary.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Identity／Session Schema
- Password／Token／Rotation Contract
- Authentication Router／BlackOps Delegation Composition
- SvelteKit Cookie／Server-only Boundary
- Sensitive Data Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
