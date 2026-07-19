# P17-003 Identity, Session, and BFF Boundary Report

## Summary

`examples/community-board/`へApplication-ownedなUser／Session境界を実装した。`POST /auth/users`、`POST /auth/sessions`、`DELETE /auth/sessions/current`はOuter PSR-15 Routerが所有し、それ以外を既存BlackOps Handlerへ委譲する。Classic Front ControllerとFrankenPHP Worker Modeは同じ`bootstrap/http.php` Compositionを使用する。

PasswordはArgon2id、Sessionは256-bit Opaque TokenのSHA-256 Hash、8時間Default TTL、Expiry、Revocation、Login Rotationを実装した。BlackOps Global Authentication MiddlewareにはApplication `HttpAuthenticator`をBindingし、Valid Sessionだけを`ActorRef($userId, 'user')`へ変換する。Authorized Inline `ShowCurrentUser` OperationはSafe Userだけを返す。

SvelteKitはRegister／Login／Logout Form Action、HttpOnly Session Cookie、Server-only Auth Client、Generated `ShowCurrentUser` Wrapper、Current User Layout Loadを持つ。BrowserへRaw Token、Password、Internal Base URLを返さない。CIへExample PHPUnit、Vitest、Real HTTP Identity Consumer Journeyを追加した。FrankenPHP、Classic、Deferred Workerは同じ非root UID／GIDでRuntime Artifactを共有し、起動順に依存しない。Post、Comment、Digest、Final Visual Design、Reicon、Framework Production Code、Quickstartは変更していない。

## Changed Files

- Identity／Session: `examples/community-board/app/Identity/**`
- Authentication／Authorization: `examples/community-board/app/Security/**`
- Application Router／Composition: `examples/community-board/app/Http/**`、`bootstrap/http.php`、`public/index.php`、`public/worker.php`
- Authorized Operation: `examples/community-board/app/Feature/Identity/ShowCurrentUser/**`
- DI／Middleware: `ApplicationServiceProvider.php`、`config/app.php`、`config/middleware.php`
- Migration: `examples/community-board/migrations/.gitignore`、`Version20260720023000.php`
- PHP Test: `examples/community-board/phpunit.xml`、`examples/community-board/tests/**`
- SvelteKit BFF／Session: `frontend/src/lib/server/auth/**`、`frontend/src/lib/server/blackops/operations.server.ts`
- SvelteKit Routes: `frontend/src/routes/+layout.*`、`register/**`、`login/**`、`logout/**`、`me/**`、Landing更新
- Runtime／Dependency: `.env.example`、`compose.yaml`、`composer.json`、`composer.lock`
- Consumer／CI: `tests/Consumer/community-board-identity.sh`、`.github/workflows/ci.yml`
- Reader／Orchestration: `examples/community-board/README.md`、`develop/TODO.md`、`develop/STATE.md`、Task PacketのOperation Type訂正

## Decisions and Assumptions

- Task Packet初版のOperation Type `board.identity.current-user.show`はPublic `OperationType` Contractが許可する`[a-z0-9]+` Segmentに反し、`build:compile`がFail-fastした。Orchestrator判断によりCanonical IDを`board.identity.current.user.show`へ訂正し、Task Packetと実装を同期した。Framework `src/**`は変更していない。
- Authentication RouteだけがApplication-owned ConnectionをRequest単位で生成し、`finally`でCloseする。BlackOps Operation／AuthenticatorはDefault DBAL `Connection`のFramework DI／Lifecycleを使用する。
- Authentication JSONは未知Field、Malformed／Non-object、Media Type、Validationを分離する。Public ResponseはStable CodeとSafe Field Violationだけを持ち、Raw Detailを持たない。
- Authentication RouteのDatabase／Unexpected Failureは`500 identity.internal_error`へ閉じる。Exception、SQL、Path、CredentialをResponse／Logへ展開しない。
- SvelteKit Auth ClientはBackendから受理するFailure／Field CodeをWhitelistし、未知Responseを`identity.unavailable`へ閉じる。
- Secure Cookieは未指定時`true`である。Local plain HTTPだけCompose／`.env.example`から`SESSION_COOKIE_SECURE=false`を明示する。
- SvelteKit Default Origin Checkは無効化していない。Redirect先は`/me`、`/login`、`/`の固定Routeだけである。
- Application Codeが直接ImportするDBAL、Doctrine Migrations、Symfony UIDはCommunity Boardの`composer.json`へDirect Dependencyとして宣言した。
- Migration DirectoryはPHP MigrationをIgnore対象から除外し、Local Consumerは未追跡かつ非IgnoreのStaging Candidate、CIはTracked FileであることをFail-fastする。
- FrankenPHP、Classic、Deferred Workerは`HOST_UID:HOST_GID`で統一する。FrankenPHP固有のCaddy Config／DataだけをContainer内`/tmp/caddy`へ分離し、共有`var/log/journal.jsonl`のOwnershipを起動順非依存にした。
- Final Visual DesignとReiconはTask Packetどおり後続Taskへ延期した。

## Identity / Session Schema

`public.board_users`:

- `id UUID PRIMARY KEY`: ApplicationがSymfony UID `UuidV7`で発行
- `email_canonical VARCHAR(254) UNIQUE`: trim後、ASCII lowercase比較値
- `email_display VARCHAR(254)`: trim済み表示値
- `display_name VARCHAR(80)`
- `password_hash VARCHAR(255)`
- `created_at`／`updated_at TIMESTAMPTZ`

`public.board_sessions`:

- `id UUID PRIMARY KEY`: Application UUIDv7
- `user_id UUID`: `board_users.id` Foreign Key
- `token_hash CHAR(64) UNIQUE`
- `issued_at`／`expires_at TIMESTAMPTZ`
- `revoked_at TIMESTAMPTZ NULL`
- User IndexとActive Token Partial Index

MigrationのDownはSession、Userの順でTableを削除する。期限切れRowのRetentionはInitial Scope外のため追加していない。

## Password / Token / Rotation Contract

- Bootstrap Compositionで`PASSWORD_ARGON2ID`と`password_algos()`を検証し、利用不能ならFail-fastする。Fallback Algorithmはない。
- Passwordは12から128 Unicode Characterを受理し、`password_hash(PASSWORD_ARGON2ID)`、`password_verify()`、成功Login時`password_needs_rehash()`を使用する。
- 未知EmailでもInstance-local Dummy Argon2id Hashに対して1回`password_verify()`を実行する。既知Emailの誤Passwordと同じ`authentication.invalid_credentials`へ閉じ、User存在によるVerify省略を防ぐ。
- Raw Session Tokenは`random_bytes(32)`をBase64url without paddingへ変換した43 Character値である。
- Databaseへ保存するのは`hash('sha256', $rawToken)`のlowercase 64 hexだけである。
- TTLはDefault 28,800秒で、Environmentは正のIntegerだけを受理する。
- Login成功時、提示されたCurrent Bearerを同一TransactionでRevokeしてから新Sessionを作る。
- LogoutはMissing／Unknown／Expired／Already Revokedを安全に完了し、Malformed Bearerだけを400にする。
- Authentication Queryは`revoked_at IS NULL AND expires_at > now`を満たすSessionだけをUserへ結合する。

## Authentication Router / BlackOps Delegation Composition

- Outer `AuthenticationRouter`はExact `/auth/users`、`/auth/sessions`、`/auth/sessions/current`だけを所有し、安全な404／405を返す。
- `/auth/*`以外はBlackOps Handlerへそのまま委譲し、`/welcome`を維持した。
- Classic `public/index.php`とWorker `public/worker.php`はいずれも`bootstrap/http.php`から同じHandlerを取得する。
- Default HTTP、Classic、Deferred Workerは同じ非root UID／GIDで起動し、同じJournalを開けることをDefault先行のReal Runtime E2Eで確認した。
- Global `AuthenticationMiddleware`はBearer欠落をAnonymous、Malformed／Unknown／Expired／Revokedを`401 authentication.invalid_session`へ分類する。
- Valid SessionからRequest境界へ渡すのはUser ID／Typeだけの`ActorRef`であり、Raw Token、Session Record、User RecordはAttributeへ保存しない。
- `ShowCurrentUser`は`GET /me`、`board.identity.current.user.show`、Inline、`#[Authorize(AuthenticatedUserPolicy::class)]`である。Execution ContextのAuthorization Actor IDをRepositoryへ渡し、Outcomeは`id`、`email`、`displayName`だけを持つ。

## SvelteKit Cookie / Server-only Boundary

- `/register`、`/login`、`/logout`はServer Form Actionであり、BrowserはPHP Originへ直接Fetchしない。
- PHP Auth SuccessのRaw TokenはServer Action内で`community_board_session` Cookieへ移し、Action Data／Page Dataには含めない。
- Cookieは`HttpOnly`、`SameSite=Strict`、`Path=/`、Backend TTL以下の`Max-Age`を持つ。Secure Defaultはtrueで、Localだけfalseを明示する。
- Root LayoutはCookieがあるときだけGenerated `ShowCurrentUser.fetch()`をServer側で呼び、呼出単位のAuthorization Headerを注入する。Global Mutable Client Configurationはない。
- Invalid／Expired SessionではCookieを削除し、Safe Anonymous Viewへ投影する。Transport／5xxではInternal URLやRaw Errorを返さない。
- 422 Action DataはEmail／Display NameとWhitelist済みField Codeだけを返す。Passwordは戻さない。

## Sensitive Data Evidence

Real HTTP Identity Consumerは実行ごとにPassword Markerと3世代のRaw Tokenを生成し、次を検査した。

- PostgreSQL Full Data Dump: Password Marker／Raw Tokenなし。`board_sessions.token_hash`は期待SHA-256と一致しRaw Tokenとは不一致。
- BlackOps Build／Journal／Log Artifact: Markerなし。
- Generated TypeScript: `password`、`sessionToken`、Cookie名、Markerなし。Current User OperationにCredential Fieldなし。
- SvelteKit Client Build: Raw Token、Password Marker、Internal Base URL、Database Environment、Absolute Pathなし。
- SSR HTML／Form Action Response／PHP Current User Response: Raw Token／Password Markerなし。
- Container Log: Raw Token／Password Markerなし。
- Duplicate、Malformed JSON、Unsupported Media Type、Invalid Credential、Database Failure Response: Stable Safe CodeだけでRaw Body／SQL／Internal URL／Absolute Pathなし。
- Cookie Header: HttpOnly、SameSite Strict、Path、Max-Ageを確認。Production-safe Secure DefaultはVitestで確認。
- CSRF: Untrusted OriginのForm POSTは403で拒否。

## Commands and Results

```text
docker compose -f examples/community-board/compose.yaml config
Result: 成功。Worker／Classic／Frontend／PostgreSQL topology valid。

docker compose -f examples/community-board/compose.yaml build app http frontend
Result: 3 image build成功。

docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir examples/community-board/frontend install --frozen-lockfile
Result: Composer valid、69 PHP packages、100 Frontend packagesのlocked install成功。

docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
Result: 成功。Framework 2 + Application 1、合計3 migrations。

docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:generate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:check
Result: Build成功、5 frontend files生成、fresh成功。

docker compose -f examples/community-board/compose.yaml run --rm app vendor/bin/phpunit
Result: OK (17 tests, 71 assertions)。既知Hash、未知User用Dummy Hash、正しいPasswordのVerify回帰を含む。

pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
Result: Svelte check 0 errors／0 warnings、Vitest 16 passed、adapter-node build成功。

bash tests/Consumer/community-board-identity.sh
Result: Community Board identity journey passed。Default HTTPを先に起動した後にClassicとDeferred Workerを起動し、3 Runtime Writerが同じJournalを開けること、Register／Cookie／Current User／Logout／Rotation／Expiry／CSRF／Safe Failure／Sensitive Guardを確認。全curlは有限Connect／Request Timeoutを持つ。

bash tests/Consumer/community-board-foundation.sh
Result: Community Board foundation journey passed。Welcome／Unavailable／Server-only／Tracking回帰成功。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全成功、Issueなし。

git check-ignore／git ls-files Migration Tracking Guards
Result: MigrationはIgnore対象外のStaging Candidate。CIではTracked Fileを必須化。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1430 tests, 5679 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2530 / Warnings 0 / Errors 0。

Quickstart Diff／Tracked Artifact／Management ID／git diff --check Guards
Result: 全成功。
```

Dependency、Generated Tree、Build、Runtime、`.env` Artifactは最終検証後に削除した。

## Acceptance Criteria

- [x] Application-owned User／Session Migration
- [x] Register／Login／Logout Authentication Router
- [x] Non-authentication RequestのBlackOps Delegation
- [x] Classic／Worker同一Composition
- [x] Argon2id Hash／Verify／RehashとPlaintext非保存
- [x] Opaque Token Hash、TTL、Expiry、Revocation、Rotation
- [x] Valid Sessionだけを`ActorRef`へ変換する`HttpAuthenticator`
- [x] Authorized `ShowCurrentUser` Safe Outcome
- [x] SvelteKit Register／Login／Logout Form Action
- [x] HttpOnly／SameSite Strict／Path／Secure Default／Max-Age
- [x] Browser、Generated Contract、Journal、OutcomeのCredential非露出
- [x] CSRF Origin、No-store、Safe Error、Password非反射
- [x] PHP／SvelteKit Unit TestとReal HTTP Identity E2E
- [x] Foundation Consumer回帰
- [x] Generated／Dependency／Build Artifact非追跡とCleanup
- [x] Quickstart／Skeleton Source非変更
- [x] Framework `src/**`非変更
- [x] Required Quality Gate成功
- [x] Worker Commitなし

## Orchestrator Review and Independent Verification

Orchestrator Reviewで次を検出し、WorkerへCorrectionを返した。

- `migrations/.gitignore`が新規PHP Migrationを除外していたため、`!*.php`とLocal／CI Tracking Guardを追加した
- 未知EmailだけArgon2id VerifyをShort-circuitしていたため、Instance-local Dummy Hashでも必ず1回Verifyするよう修正した
- Applicationが直接ImportするDBAL、Doctrine Migrations、Symfony UIDをDirect Dependencyとして宣言した
- FrankenPHPがRoot所有Journalを作るとClassic／Deferred Workerが開けなかったため、3 Runtimeを同じ非root UID／GIDへ統一し、Caddy固有Dataだけを`/tmp`へ分離した
- Consumer E2EをDefault HTTP、Classic、Deferred Workerの順で起動し、全curlへ有限Timeoutを追加した

Correction後、OrchestratorがClean Dependency InstallからIdentity Consumer E2Eを再実行し、Example PHPUnit 17 tests／71 assertions、Svelte Check 0 errors／0 warnings、Vitest 16、Production Build、Migration 3、Frontend 5 files／Fresh、全Identity Journey／Sensitive Guardの成功を確認した。

Root品質GateはCommunity Board Dependency ArtifactをCleanupしたClean Treeで再実行し、Mago format／lint／analyze、PHPUnit 1430 tests／5679 assertions、Deptrac違反0、Quickstart Diff、Migration Tracking、Management ID、Artifact、`git diff --check` Guardが成功した。E2E直後の最初のMago実行だけはExample `vendor/blackops/framework`のPath Symlinkを辿ったため停止し、Dependency ArtifactをCleanupしてCanonical Gateを再実行した。

## Remaining Issues

Blockerなし。期限切れSession RowのRetention、Password Reset、Email Verification、MFA、Session一覧等はTask Scope外である。Post／Comment、Deferred Digest、Final Visual Design、Reiconも後続Taskへ残す。

## Suggested Next Action

OrchestratorがAccepted差分をCommit／Pushする。その後、Post／Comment Inline Operation、Validation、Authorization、Transactionを扱うP17-004 Task Packetを作成する。
