# P18-006A: Session Authentication Core Report

## Summary

Framework同梱かつ明示Opt-inのSession Authentication Coreを`BlackOps\Auth\Session`へ実装した。ApplicationはOpaque Identity IDと現在のAccount Stateを解決するProviderだけを所有し、Frameworkは32-byte CSPRNG Token、SHA-256 Hash保存、Absolute TTL、Conditional Touch、Rotation、Revocation、Cleanup、PostgreSQL Store、Bearer／Cookie Adapterを所有する。

未登録ApplicationへSession Service、Authenticator、Migrationを追加しない。User、Password、Registration、Role／Permission、Cookie発行／Attribute、CSRF、Route／UIもFrameworkへ取り込んでいない。Community Board、外部Publication／Deploy、Commitは変更／実行していない。

## Changed Files

- Public Session API: `src/Auth/Session/**`
- Internal Lifecycle／PostgreSQL Adapter: `src/Internal/Auth/Session/**`
- Opt-in object registration: `src/Internal/DependencyInjection/ServiceObjectFactory.php`、`SymfonyServiceRegistry.php`
- Migration Template: `resources/stubs/auth-session-migration.php.stub`
- PHPUnit: `tests/Auth/Session/**`、`tests/Internal/Auth/Session/**`
- Dependency boundary: `deptrac.yaml`
- Guide／Internal Documentation: `docs/guide/README.md`、`application-bootstrap.md`、`core-api.md`、`security.md`、`docs/internal/README.md`、`session-authentication.md`
- Website inventory: `docs/website/tests/reader-experience.test.mjs`
- Specifications／Plan: `develop/spec/17-core-api.md`、`44-public-application-bootstrap-api.md`、`47-public-http-runtime-configuration.md`、`74-application-ergonomics.md`、`75-phase-18-delivery-plan.md`
- Orchestration: `develop/TODO.md`、`develop/STATE.md`、本Report

## Final Public API and Registration

Public `#[PublicApi]`は次の10型である。

- `SessionIdentityProvider`、`SessionConfiguration`、`SessionManager`
- `RawSessionToken`、`IssuedSession`、`InvalidSessionException`
- `SessionCookieName`
- `BearerSessionAuthenticator`、`CookieSessionAuthenticator`
- `SessionServiceProvider`

`SessionManager`は`issue()`、`authenticate()`、`rotate()`、`revoke()`、`cleanup()`を提供する。`authenticate()`はRaw Credentialを受け、Store Lookup／TouchとApplication-owned `SessionIdentityProvider`によるCurrent Actor解決を完了し、`?ActorRef`だけを返す。Session ID、Identity ID、Token Hash、Stored Row、Internal PortはPublic APIへ出さない。

Applicationは`SessionServiceProvider::bearer(IdentityProvider::class, $configuration)`または`SessionServiceProvider::cookie(IdentityProvider::class, $cookieName, $configuration)`を`services`／`withServices()`へ追加する。Public Providerは既存`ServiceRegistry::set()`／`autowire()`だけを使う。Internal DI AdapterはSecretを持たない`SessionConfiguration`と`SessionCookieName`だけをScalar Constructor Definitionへ変換し、Compiled Container Dumpを可能にした。

## Token, Identity, Lifecycle, and Schema

Tokenは`random_bytes(32)`からCanonical 43文字Base64urlを生成する。ParserはAlphabet、Length、32-byte Decode、Canonical Re-encodeを確認し、StoreにはSHA-256 Lowercase Hexだけを渡す。`RawSessionToken`はPublic Constructor／Property、String／JSON変換を持たず、明示的な`reveal()`だけで値を取得する。`IssuedSession`はToken、Issued At、Expires Atだけを返す。

Identity IDは空、Control Character、255 byte超過をInsert前に拒否する。Session RowはOpaque `identity_id`だけを保持し、User Table Foreign Key、Actor Type、Password、Role／Permission、Cookieを持たない。RequestごとにIdentity Providerが現在のActor／Account Stateを解決する。Provider ThrowableはInvalid Credentialへ丸めない。

IssueはHashだけをTransaction Insertする。ExpiryはDefault 8時間のAbsolute TTLでTouchしても延長しない。AuthenticateはActive／UnexpiredかつDefault 5分のThreshold対象を`UPDATE ... RETURNING`で原子的にTouchし、更新対象がない場合はThresholdより新しいActive Rowを通常`SELECT`する。両経路でHashを再確認する。Rotationは旧Rowを`FOR UPDATE`でLockし、Successor Insertと旧RowのRevocation／Linkを一Transactionで行う。RevokeはLock後のConditional UpdateでIdempotentにする。CleanupはCutoffより古いExpired／Revoked Rowだけを削除し、現在ActiveなRowを残す。

Migration Templateは`public.blackops_sessions`へUUID Primary Key、Opaque Identity、Unique Hash、Issued／Expires／Last-used／Revoked、Unique Successor LinkとSelf Foreign Key `ON DELETE SET NULL`、Identity／Expiry／Revocation Index、Hash／時刻Constraintを作る。RuntimeはSchemaを自動作成せず、Down MigrationはData Lossを避けるためIrreversibleとした。

## HTTP Matrix

| Input／Backend | Bearer／Cookie Result | Failure boundary |
| --- | --- | --- |
| Credentialなし | Anonymous | Operation側の認可へ進む |
| Canonical Active Token＋Active Identity | Authenticated | `ActorRef`だけを返す |
| 複数Bearer Header、Scheme／Token形式不正 | Invalid | `authentication.invalid_session` |
| Unknown／Expired／Revoked／Rotated | Invalid | 同じCode／Message |
| Identity Providerがnull | Invalid | 同じCode／Message |
| Database／Clock／Provider Throwable | Propagate | 上位Safe Internal Failure Boundary |

BearerとCookieは別Classであり、Combined Credential Parserを作っていない。Cookie AdapterはCookieを発行せず、Cookie Attribute／CSRF／Redirect／UIを知らない。

## Concurrency and Sensitive Evidence

実PostgreSQL Integration TestでIssue、Touch Threshold、Expiry、Rotate、Revoke、Cleanup、Schema Shapeを確認した。Touch対象の同じSessionへ別Connection／別Processから同時Authenticationし、両Processが同じActorを解決してDeadlockなく成功し、`last_used_at`が正しい時刻になることを固定した。このTestは5回連続でも成功した。

別Processの同時Rotationでは先行者だけがSuccessorを確定し、後行者は同じSafe `InvalidSessionException`となる。Rollback経路にOrphan Successorを残さず、旧TokenはCommit後にAuthenticationできない。同時Revoke／RotateでもActive Successorは一つ以下である。

Database検査は保存値がRaw Tokenと異なる64文字Hashであること、Schemaに禁止Columnがないことを確認する。Public Value ObjectのDebug表示、Exception、HTTP Result、Compiled Container Dump、Documentation／ReportへRaw Token、Hash、Identity値を出していない。Long-running Service PropertyへRaw Token、Actor、Stored Rowを保持せず、既存`DatabaseManager` Connection Lifecycleを共有する。

## Registration On and Off Evidence

RegistrationありではBearer／Cookieを別々にCompileし、`SessionManager`、Configuration、Identity Provider、選択AuthenticatorをCompiled Containerから解決した。Dump済みContainerからも同じ構成を復元できる。

RegistrationなしではSession型をGlobal Auto-registerせず、既存Application Container／HTTP／Migrationへ影響しない。Full PHPUnit、Quickstart Setup／E2E、Skeleton Create-project、Framework Update Generatorで既存Consumer回帰を確認した。`examples/community-board/**`の差分は0である。

## Decisions and Assumptions

Task Packetが参照した`develop/spec/47-dependency-injection-boundary.md`は存在しないため、Orchestrator確認に従い実在する`develop/spec/47-public-http-runtime-configuration.md`を同期した。

Task PacketのHTTP Adapter向けInternal Authentication Port案は、Public AdapterからInternal Contractを型判定できない。Orchestrator ReviewによりPublic `SessionManager::authenticate(): ?ActorRef`へContractを修正し、Default ManagerがLookup／Touch／Identity Resolutionを所有する構成にした。Provider Throwableを伝播するFailure境界は維持している。

Public RegistrationはOrchestrator確認済みの`SessionServiceProvider::bearer()`／`::cookie()`とした。`deptrac.yaml`は新Public `BlackOps\Auth` Namespaceを既存Architecture検査へ含めるため、Orchestratorが許可した最小Scope ExtensionとしてAuth Layerを追加した。

公開ガイドの新規単独ページはWebsite IA／Navigation変更を必要とするため作らず、既存Application BootstrapとSecurityへ統合した。Internal Designは独立ページにした。

## Commands and Results

- Focused PHPUnit: OK (48 tests, 210 assertions)
- Concurrent Authentication PostgreSQL Test 5回連続: 各回OK (1 test, 18 assertions)、両Process成功、Deadlockなし、Actor／Touch時刻一致
- `docker compose run --rm app vendor/bin/phpunit`: OK (1614 tests, 6429 assertions)
- `docker compose run --rm app mago format --check src tests`: success、all files formatted
- Focused `mago lint`（Session／DI変更範囲）: success、no issues
- `docker compose run --rm app mago analyze`: success、no issues
- Full `mago lint src tests`:既存のTask範囲外Testを中心に110 errors／1175 warnings／35 helpでfailure。今回変更範囲のfocused lintは0 issueであり、範囲外Fileは変更していない
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress`: success、0 violations、0 skipped／uncovered／warnings／errors、2732 allowed
- Root／Quickstart `composer validate --strict`: valid
- `bash tests/Consumer/quickstart-setup.sh`: success
- `bash tests/Consumer/quickstart-e2e.sh`: success
- `bash tests/Consumer/skeleton-create-project.sh`: success。別Consumerと並行した初回はDocker Resource差分Guardで非0となり、単独再実行で成功
- `bash tests/Consumer/framework-update-generators.sh`: success
- Website `pnpm test`: 42／42 success
- Website `pnpm build`: success、31 routes／30 public pages、Artifact／Navigation／Accessibility／Search Check成功。既存Vite chunk-size warningだけを確認
- Management ID Guard、Community Board Scope Guard、`git diff --check`: success
- Generated Website Content、Build、Dependency Artifact: cleanup済み

## Acceptance Criteria

- [x] `BlackOps\Auth\Session`のPublic Surfaceを10型に限定し、Internal Typeを露出しない
- [x] Raw Tokenを発行／Rotation時だけ返し、Database／Failure／Observation Surfaceへ残さない
- [x] Absolute TTL、Conditional Touch、Idempotent Revoke、Retention Cleanupが決定的に動作する
- [x] Concurrent Rotationで有効なSuccessorを一つにし、旧Tokenを再利用できない
- [x] Session RowはOpaque Identityだけを保持し、Application Providerが現在のActor／Account Stateを解決する
- [x] Bearer／Cookie AdapterがAnonymous／Authenticated／Invalid／Infrastructure Failureを分離する
- [x] Session未登録Existing ApplicationのBuild／Runtime／Migrationを変えない
- [x] User／Password／Registration／Role／Cookie Attribute／CSRF／UIをFrameworkが所有しない
- [x] Quickstart／Skeleton／Framework Update／Full PHPUnit／Architecture Gateが回帰する
- [x] Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Remaining Issues

Active Implementation Blockerはない。Full Repository Mago lintには今回変更範囲外の既存違反が残るが、Session／DI変更範囲のfocused lintとformat／analyzeは成功している。

Application-owned Identity Provider、Login／Logout接続点、Migrationの一度だけのPublishを生成する`make:auth`とFresh Consumerは後続Task Scopeである。Community BoardのSession Core移行も後続Taskまで行わない。

## Suggested Next Action

OrchestratorがPublic API最小性、Registration on／off、Token／Identity／Failure境界、PostgreSQL Transaction／Concurrency、Migration、DI Artifact、既存Consumer回帰をReviewする。Accept後はPhase 18 Delivery Planに従いSession Generator Taskへ進む。

## Orchestrator Review

Reviewed At: 2026-07-22T11:35:57+09:00

Status: Accepted

Public API、Opt-in Registration、Token／Identity／Failure境界、PostgreSQL Transaction／Schema／Migration、DI Artifact、Documentation／Inventoryを独立Reviewした。Public Bearer／Cookie AdapterがInternal Authentication PortをRuntime型判定する初期案は、Public `SessionManager::authenticate(): ?ActorRef`がLookup／Touch／Identity Resolutionを所有するContractへ修正し、Internal型露出と実行時Fallbackを除去した。

`PostgreSqlSessionStore::authenticate()`の初期`SELECT ... FOR SHARE`後UPDATEは、Touch対象への同時RequestでShare Lock Upgrade Deadlockを起こし得るため受け入れなかった。Active／Unexpired／Touch対象のConditional `UPDATE ... RETURNING`を先に行い、更新不要時だけActive RowをSELECTする形に修正した。別Connection／`pcntl_fork`の同時Authenticationで両CallerがActorを取得し、Deadlockなし、`last_used_at`が一つの時刻へ収束することを固定した。

Orchestratorはfocused PHPUnit 48 tests／210 assertions、`mago format --check src tests`、通常`mago lint` 0 issue、Deptrac 0 violations／0 uncovered／2732 allowed、Management ID Guard、Community Board差分0、`git diff --check`、Artifact cleanupを独立再確認した。Worker実行のFull PHPUnitは1614 tests／6429 assertions、全Consumer／Composer／Website Gateも成功している。明示的に`src tests`全体を対象にするMago lintは既存Test Baselineを含むため非0だが、Repository標準の`mago lint`と変更範囲lintは成功した。

P18-006AのAcceptance Criteriaはすべて満たされ、Remaining Implementation Blockerはない。
