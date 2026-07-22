# P18-007 Community Board Migration and Phase Closeout Report

## Summary

Community BoardをPhase 18で追加したApplication Ergonomicsへ移行した。Identityを`Domain`／`Infrastructure`／`Feature`へ分離し、Register／Login／LogoutをEphemeral OperationとしてHTTP Runtimeへ統合した。Application独自のToken Lifecycle、Session Store、Authentication Router／Handlerを削除し、Framework Session CoreへToken Hash、TTL、Touch、Rotation、Revocation、Cleanupを委譲した。

SvelteKit Serverは生成された`createBlackOpsClient()`をRequestごとにBindingし、Board、Digest、Operation Status、Authenticationで共有する。Seed Commandは`#[AsCommand]`とConstructor DIへ移行し、`board:welcome`をOperation Consoleへ公開した。既存VolumeとFresh Databaseの双方をForward Migrationし、全Community Board Consumer、Framework、Website、Package Export Gateを完走した。外部Publication／DeployとWorker Commitは行っていない。

## Changed Files

- `examples/community-board/app/Domain/Identity/**`: Vendor非依存のUser、Repository Port、Password、Registration Policy、IdentityService
- `examples/community-board/app/Infrastructure/Identity/**`: DBAL User Repository、ID／Clock Adapter、Session Identity Provider
- `examples/community-board/app/Feature/Identity/{Register,Login,Logout}/**`: Route付きInline／Transactional／Ephemeral Operation
- `examples/community-board/app/AuthServiceProvider.php`、`config/auth.php`: Application IdentityとFramework Session CoreのBinding
- `examples/community-board/app/Console/CommunityBoardSeedCommand.php`、`config/app.php`: Command Discovery、Constructor DI、Environment Snapshot
- `examples/community-board/app/Feature/Welcome/ShowBoardWelcome/ShowBoardWelcome.php`: Operation Console公開
- `examples/community-board/migrations/Version20260722000100.php`: `board_sessions`から`blackops_sessions`へのForward Migration
- `examples/community-board/frontend/src/lib/server/**`: Bound Frontend Client、Generated Auth Operation、Canonical Cookie Boundary
- `examples/community-board/tests/**`、`tests/Consumer/community-board-*.sh`: Identity、Session、Command、Frontend、Migrationの回帰
- `README.md`、`examples/community-board/README.md`、`docs/guide/**`、`docs/website/tests/**`: Reader向けContract同期
- `develop/spec/{60,71,74,75}*.md`、`develop/TODO.md`、`develop/STATE.md`: Phase 18 Closeoutと後続課題
- 削除: `app/Identity/**`、Authentication専用`app/Http/**`、`SessionHttpAuthenticator`と対応Test

Framework Production `src/**`、Public API、Generator Stub、Quickstart Sourceは変更していない。

## Decisions and Assumptions

- User、Password Hash／Verify／Rehash、Email Canonicalization、Registration PolicyはApplication Domainの責務として残す。
- Raw Bearer Tokenの発行、Hash、TTL、Touch、Rotation、Revocation、CleanupはFramework Session Coreへ委譲する。
- Login時の現在Tokenが同一UserならRotateする。別Userの有効Tokenなら旧TokenをRevokeしてから新User用TokenをIssueする。Invalid／Expired Tokenは安全に無視してIssueする。
- `LoginValue::$currentToken`はOptional Sensitive Inputである。Nullable PropertyへString Validation Attributeを付与した場合のFramework Contractは未確定のため、Task内で`#[Length]`を付けず、後続TODOへ明記した。
- SvelteKit CookieはRaw TokenのBrowser保管と削除だけを所有する。非Canonical Cookieの削除は`resolveSessionToken()`へ一元化する。
- Generated Frontend Treeは`frontend:generate`所有のIgnored Artifactであり、Applicationは手書きType／Operation Clientを持たない。
- Direct DependencyはApplication Source、Migration、Bootstrap、Public Entry Pointが直接Importするものを残す。Transitive Dependencyへの依存へ変更しない。

## Final Identity / HTTP / Frontend / Command Architecture

### Identity and HTTP

`IdentityService`はDomain Portだけに依存する。DBAL Repository、Symfony UID、System Clock、Framework `SessionIdentityProvider`はInfrastructureへ置いた。Register／Login／Logout OperationはDomain Service呼出、Stable Rejection写像、Session発行／失効だけを担当する。

`bootstrap/http.php`はApplication HTTP Runtimeをそのまま返す。旧`AuthenticationRouter`、`AuthenticationHttpHandler`、専用JSON Response／Request Validation／DB Connection Factoryは存在しない。認証Endpointも通常Operationと同じDiscovery、Validation、Transaction、HTTP Projectionを通る。

### Frontend

`client.server.ts`がSvelteKit `event.fetch`、`BLACKOPS_BASE_URL`、Request単位Bearer Credentialを生成Root `createBlackOpsClient()`へ一度Bindingする。Board、Digest、Status、Register、Login、LogoutはBound ClientのOperation Objectを利用する。Deferred Digestだけが`status()`／`wait()`を使い、Ephemeral Auth Operationは`fetch()`だけを持つ。

Safe View Model、Form Error、Redirect、Cookie Attribute、Cookie削除はApplication-owned Server Moduleへ残した。Server Fetch、Header、Credential、Abort Signalは並列Request間で共有しない。

### Commands

`CommunityBoardSeedCommand`は`#[AsCommand(name: 'app:seed')]`でBuild-time Discoveryされ、Compiled Containerから`CommunityBoardSeeder`をConstructor Injectionする。`config/app.php`に手動Command登録はない。

`ShowBoardWelcome`は`#[ConsoleCommand('board:welcome', ...)]`を持ち、HTTPと同じOperation Lifecycleを通る。Global List／HelpではCommand ConstructorやDatabaseを解決せず、実行時にJournal／Outcomeを生成する。

## Before / After Measurement

BaselineはCommit `2d56c5f`、AfterはP18-007 Working Treeである。Generated、Vendor、Runtime Artifact、Test Fixtureは除外した。

| Metric | Before | After | Delta |
| --- | ---: | ---: | ---: |
| Identity／Authentication PHP semantic files | 27 | 29 | +2 |
| Identity／Authentication PHP LOC | 1,116 | 715 | -401 (-35.9%) |
| Frontend production Auth／Operation wiring files | 10 | 11 | +1 |
| Frontend production Auth／Operation wiring LOC | 1,146 | 1,011 | -135 |
| Application-owned `blackops/*.server.ts` | 3 | 4 | +1 common bound client |
| Custom Token Codec files | 2 | 0 | -2 |
| Custom Session Lifecycle／Store files | 4 | 0 | -4 |
| Custom Auth Router／Factory files | 3 | 0 | -3 |
| Custom Frontend `operationFetch` adapter files | 3 | 0 | -3 |
| HTTP Route Operations | 10 | 13 | +3 Auth Operations |
| Generated Frontend files | 14 | 17 | +3 Auth Operations |
| Direct Composer production dependencies | 10 | 10 | 0 |
| Frontend production＋development dependencies | 13 | 13 | 0 |

File数が2増えたのはIdentityをDomain／Infrastructure／Featureへ明示分離し、Register／Login／Logoutを3つの通常Operationとして追加したためである。一方、Identity／Authentication PHPは401 LOC、Frontend Wiringは135 LOC減少し、Application独自のSession／Router／Fetch責務は0になった。

Countingには次の同一分類をBaselineとWorking Treeへ適用した。

```bash
git ls-tree -r --name-only 2d56c5f -- examples/community-board/app
git show 2d56c5f:<path> | wc -l
find examples/community-board/app/{Domain/Identity,Infrastructure/Identity,Feature/Identity} -type f
find examples/community-board/frontend/src/lib/server -type f -name '*.ts'
wc -l <classified-files>
find examples/community-board/frontend/src/lib/server/blackops/generated -type f | wc -l
composer show --direct --no-dev
pnpm list --depth -1
```

Generated TreeのAfter 17 filesはFresh `frontend:generate`実行時の`Generated 17 frontend files`でも確認した。Application-ownedに編集したGenerator由来Fileはない。`client.server.ts`は生成物の外側にあるApplication Bindingである。

## Removed Framework Wiring and Remaining Application Responsibility

### Removed from the Application

- Bearer Token生成／Encoding、Token Hash、Session TTL／Touch／Rotate／Revoke／Cleanup
- `board_sessions` Runtime StoreとApplication Session Settings
- Authentication専用Router、Handler、Request Decoder、JSON Response Factory
- OperationごとのFetch、Base URL、Authorization Header、Abort Signal Adapter
- Seed Commandの手動登録、Command内Service／Environment生成

### Kept in the Application

- User Aggregate、Email Canonicalization、Password Policy、Registration Policy、Invalid Credential判断
- User Repository PortとDBAL Adapter、Session IdentityからUser Actorへの写像
- Domain FailureからStable Rejection Codeへの写像
- Safe View Model、Form UX、Redirect、Cookie Attribute、Cookie保管／削除
- Direct Vendor Importが必要なBootstrap、Migration、Console、UID、DBAL Adapter

## Existing Volume / Clean Install Migration Evidence

- Existing Community Board Volume: Forward Migrationを1件適用し、再実行は0件。Migration StatusはApplied 6／Pending 0。
- Existing Data: `board_sessions`から`blackops_sessions`へSession DataをCopyした後、旧TableをDrop。新Tableが存在し、旧Tableが存在しないことを確認。
- Fresh Database: 全6 Migrationを空Databaseへ適用し、再実行0件、SeedとHTTP Journeyを完走。
- Down MigrationはRotation Stateを破壊し得るため明示Irreversibleとした。Migration Historyの過去Fileは変更していない。

## Authentication / Session Lifecycle / Secret Evidence

- Registration、Duplicate、Disabled Policy、Login、Unknown Email／Wrong Password同一401、Current User、Logoutを実HTTPで確認。
- Session Touch、Same-user Rotation、Expiry、Revocation、Cleanupを確認。
- Dedicated Login TestでSame-user Rotate、Different-user Current Token Revoke＋Issue、Invalid Current Token Issueを3 tests／12 assertionsで確認。
- Account-switch修正後のIdentity ConsumerはCommunity Board PHP 49 tests／550 assertionsを含めて完走。
- PasswordとRaw TokenがDatabase検索、Build Artifact、Journal／Outcome、Generated Tree、Log、Command Outputへ残らないGuardが成功。
- Auth OutcomeはEphemeralであり、Status／Wait APIとOutcome Store Rowを持たない。

## Bound Client / Command Discovery / Operation Console Evidence

- Frontend Vitest 7 files／43 tests。Bound Fetch、Credential、Header、Signal、Concurrent Request Isolation、Invalid Cookie削除を確認。
- `svelte-check`: 0 errors／0 warnings。Frontend Build成功。
- `app:seed`はGlobal Listで発見され、固有HelpとConstructor DI実行が成功。Pre-migration FailureはSafe ErrorでSecret／Throwable Detailを出さない。
- `board:welcome`はGlobal List／Help／実行に成功し、HTTPと同じTyped Outcome、Journal／Outcome、Exit Codeを確認。

## Direct Dependency Audit

削除可能なDirect Dependencyはなかった。

| Dependency | Direct use |
| --- | --- |
| `blackops/framework` | Application Runtime／Attributes／Contracts |
| `doctrine/dbal` | 9 Application／Migration／Test files、Repository Adapter |
| `doctrine/migrations` | Application Migration classes |
| `vlucas/phpdotenv` | Bootstrap Environment loading |
| `nyholm/psr7`, `nyholm/psr7-server` | Public HTTP Entry Point |
| `laminas/laminas-httphandlerrunner` | Public HTTP Response emission |
| `symfony/console` | `#[AsCommand]` Application Command |
| `symfony/uid` | Application ID adapters |

Composer production direct dependenciesはPHPを含め10、FrontendはProduction 3＋Development 10を維持した。Lockfile変更はない。

## Commands and Results

### Community Board and Consumer

- Community Board PHPUnit: PASS、49 tests／550 assertions
- Frontend Vitest: PASS、7 files／43 tests
- Frontend `svelte-check`: PASS、0 errors／0 warnings
- Frontend Build／Generate／Fresh Check／Runtime: PASS
- `tests/Consumer/community-board-foundation.sh`: PASS
- `tests/Consumer/community-board-identity.sh`: PASS
- `tests/Consumer/community-board-post-comment.sh`: PASS
- Community Board Product Journey: PASS
- Deferred Digest Journey: PASS
- Browser Journey: PASS
- `tests/Consumer/community-board-clean-install.sh`: PASS
- Existing Volume Forward Migration／Fresh Migration: PASS

### Framework and Package

- Root／Quickstart／Community Board `composer validate --strict`: PASS
- Full PHPUnit: PASS、1,662 tests／6,673 assertions
- `mago format --check src tests`: PASS
- Community Board `mago format --check`: PASS
- `mago lint`: PASS
- `mago analyze`: PASS
- Deptrac: PASS、0 violations／2,793 allowed
- Quickstart Setup／E2E: PASS
- Skeleton Create-project Smoke／Publication Dry-run: PASS
- Framework Update Generator: PASS
- Framework Git／Composer Package Export: PASS

### Website and Guards

- Website Reader Test: PASS、42 tests
- Website Content／Diagram Check: PASS
- Astro Check: PASS、16 files、0 errors／0 warnings／0 hints
- Website Build: PASS、31 pages
- Navigation／Search／Artifact Guard: PASS、30 indexed pages
- Management ID、Domain Vendor Neutrality、Forbidden Environment、Manual Fetch Adapter、Old Session Runtime、Raw Secret、`git diff --check`: PASS
- Login差分のFocused Mago Format: PASS
- Framework `src/**`／Quickstart Source差分: 0
- External Publication／Deploy: 未実施（Task Contractどおり）

`mago format --check src tests examples`は、Task開始前から存在する`examples/quickstart/app/ApplicationServiceProvider.php`の範囲外Format違反1件で失敗する。Task必須の`src tests`とCommunity Board対象は成功し、禁止されたQuickstart Sourceは変更していない。

## Acceptance Criteria

- [x] Framework Session Coreへ移行し、Application独自Token Lifecycle／Session Storeを削除
- [x] IdentityをDomain／Infrastructure／Featureへ分離し、Operationを薄く維持
- [x] Register／Login／LogoutとBrowser Journeyを回帰
- [x] Bound Clientで重複Fetch／Base URL／Credential配線を削減
- [x] Seed CommandをBuild-time Discovery＋Constructor DIへ移行
- [x] Operation Console Journeyが共通Lifecycle、Journal、Outcomeを通過
- [x] Existing Volume／Clean Install MigrationとSession Lifecycleを確認
- [x] Direct Dependency、Raw Secret、Worker Reuse、Generated Artifact境界を固定
- [x] Before／After計測と責任分界を記録
- [x] Framework／Community Board／Frontend／Consumer／Website Gate成功
- [x] Phase 18 Spec／Roadmap／TODO／DocumentationをCompleteへ同期
- [x] External Publication／Deployなし、Worker Commitなし

## Remaining Issues

- Nullable PropertyへString系Validation Attributeを付与した場合、`null`をSkipするかWrong-targetとするかのValidation Contractは未確定である。`develop/TODO.md`へ後続課題として記録した。
- 全Examples一括Mago Formatには既存Quickstart 1件の範囲外違反がある。P18-007ではQuickstart Source変更禁止のため修正していない。
- Active Implementation Blockerはない。

## Suggested Next Action

Orchestrator CodexがIdentity／Migration／Frontend／Command責任分界、account-switch時の旧Session Revoke、Reportの計測根拠を独立Reviewする。問題がなければP18-007をTask単位でCommitし、Phase 18をAcceptedとしてPhase 19 Idempotency／Outbox計画へ進む。

## Orchestrator Review

Accepted。

- Identity DomainのVendor import 0、Application／Configの直接Environment参照0、旧Session／Router／`operationFetch`残存0を独立確認
- account-switchは旧Session Revoke後に新Session Issue、Same-user Rotate／Invalid-token Issueを専用3 tests／12 assertionsで固定
- Existing VolumeはP18-006D受入後にApplied 6／Pending 0を独立確認
- Migration、Bound Client request isolation、Canonical Cookie削除、Command Discovery／Operation Consoleの責任境界を確認
- `git diff --check`、Management ID／Domain Vendor／Environment／Old Session／Manual Fetch／Artifact Guard成功
- 実装Commit `d084574`へCommunity Board、Frontend、Consumerだけを分離。Framework `src/**`／Quickstart Source／外部Publication差分なし
