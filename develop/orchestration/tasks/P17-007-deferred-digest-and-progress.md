# P17-007: Deferred Digest and Progress

Status: Accepted

## Goal

`examples/community-board/`へApplication-owned Weekly Digest Domainを追加し、認証済みUserがSvelteKit Form Actionから`GenerateWeeklyDigest` Deferred Operationを202で受理し、Server-only `.status()`／`.wait()`を通じてaccepted／running／retry_scheduled／completed／failedを確認し、Typed Outcomeから保存済みDigestを表示できるようにする。

集計業務ロジックは`app/Domain/Board`のDomainServiceへ置き、OperationはAttempt Gate、Actor、DomainService、Outcome／Rejection変換だけを担当する。Development／Test限定First-attempt Failure Adapterで、Production Business Logicを汚さず実Worker Retryを証明する。

## In Scope

- `board_digests` Application Migration
- ISO Week Domain Value／Digest Model／Repository Port／DigestService
- Doctrine DBAL Digest Repository
- `GenerateWeeklyDigest` Deferred Transactional Operation
- `ShowDigest` Inline Operation
- Application-owned Operation Status Authorizer
- Production No-op／Development-Test First-attempt `DigestAttemptGate`
- Generated Frontend Contract、Generate／Fresh Check
- SvelteKit Server-only Digest Wrapper
- Digest Start Form、Progress Page、Same-origin Wait Endpoint、Digest Detail
- accepted／running／retry_scheduled／completed／failedのSafe State Projection
- PHP／TypeScript Unit／Integration Test
- Real HTTP＋Real Worker Deferred Digest E2E
- Existing Community Board Consumer Regression、CI、README、Report、TODO、STATE同期

## Out of Scope

- Framework `src/**`、Public API、Status／Wait Runtime変更
- Post／Comment／Identity Schemaまたは既存Domain挙動変更
- Idempotency Key、同一User／Weekの重複排除、Upsert
- Digest一覧、削除、Retention、Download、Email／Notification
- Post Title／Body／Preview、Comment本文をDigestへ保存すること
- BrowserからBlackOps Status Resource／PHP EndpointへのDirect Fetch
- Production Business Logicの意図的Failure、OperationValueのFailure Flag
- Final Visual Design、Taste Skill、Reicon、Animation、Screenshot、Browser Automation
- `examples/quickstart/**`、Skeleton Source、Publication Workflow変更
- Documentation Website Content／Publication／Deploy、External Hosting

## Relevant Specifications and Decisions

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/105-community-board-deletion-policy.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/107-community-board-deferred-digest.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/orchestration/tasks/P17-006-generated-operations-and-sveltekit-product-journey.md`

## Files Allowed to Change

### Reference Application

- `examples/community-board/app/Domain/Board/**`
- `examples/community-board/app/Feature/Digest/**`
- `examples/community-board/app/Infrastructure/Persistence/**`
- `examples/community-board/app/Infrastructure/Deferred/**`
- `examples/community-board/app/Security/**`
- `examples/community-board/app/ApplicationServiceProvider.php`
- New `examples/community-board/migrations/Version*.php`
- `examples/community-board/config/**`（Digest Compositionに必要なApplication-owned Configurationだけ）
- `examples/community-board/.env.example`
- `examples/community-board/compose.yaml`
- `examples/community-board/frontend/src/**`
- `examples/community-board/tests/**`
- `examples/community-board/README.md`

### Consumer and CI

- New `tests/Consumer/community-board-digest.sh`
- Existing `tests/Consumer/community-board-*.sh`（Server-only Import／Artifact／共存Guardの最小同期だけ）
- `.github/workflows/ci.yml`

### Documentation and Orchestration

- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-007-deferred-digest-and-progress.md`
- `develop/spec/71-full-stack-reference-application.md`／`72-phase-17-delivery-plan.md`（Acceptance同期または実装不能な矛盾だけ）

上記以外が必要なら実装を広げず、ReportのBlockerとしてOrchestratorへ返す。特にFramework `src/**`、Root PHP `tests/**`、`examples/quickstart/**`を変更しない。

## Canonical Database Contract

New Application Migrationは`public.board_digests`を作る。

```text
id                 UUID primary key
requested_user_id  UUID not null -> board_users(id) ON DELETE RESTRICT
week               CHAR(8) not null
content            VARCHAR(255) not null
post_count         INTEGER not null
comment_count      INTEGER not null
created_at          TIMESTAMPTZ not null
```

- `id`はApplication-owned UUIDv7
- `week`はCanonical `YYYY-Www`
- `content`はDomainServiceが件数から生成する決定的Plain Text
- Countは0以上のCheck Constraintを持つ
- `content`はBlank禁止、1から255 CharacterのCheck Constraintを持つ
- Requested UserとCreated AtでOwner Detail Queryに必要なIndexを持つ
- Weekの集計Queryに必要なPost／Comment `created_at` Index不足を発見した場合、このMigrationでApplication-owned Indexを追加してよい
- `(requested_user_id, week)`のUnique Constraintを追加しない
- Status、Operation ID、Attempt ID、Credential、Post／Comment Contentを保存しない
- Existing Migrationを変更せず、新しいForward Migrationだけを追加する

User削除とDigest Retentionは未決であり、CascadeやCleanupを追加しない。

## Domain Contract

`app/Domain/Board/**`へ少なくとも次の責務を置く。細かなFile分割はDependency Ruleを守る限り調整できる。

```text
DigestService
DigestRepository
IsoWeek
DigestSnapshot / GeneratedDigest
DigestNotFound
InvalidDigestWeek
```

### `IsoWeek`

- InputはASCII `YYYY-Www`
- Weekは01から53の形だけでなく、そのISO Yearに実在することを検証する
- Canonical文字列とUTC Rangeを返す
- Rangeは月曜00:00:00以上、翌月曜00:00:00未満
- PHP Default Timezone、Host Locale、Current Dateへ依存しない
- Year boundary、Leap Year、Valid／Invalid Week 53をUnit Testする
- Parser ErrorやRaw InputをException Messageへ含めない

### `DigestService`

- `generate(requestedUserId, week)`はIsoWeekを解釈し、Repositoryから実行時点で存在するPost／CommentをUTC Rangeで数える
- 全認証Userが同じBoard Feedを参照できるInitial Scopeのため、集計対象はRequested User自身の投稿だけではなく、その時点で参照可能なBoard全体とする
- Post Countは`board_posts.created_at`、Comment Countは`board_comments.created_at`をRangeで数える。Hard Delete済みRowは存在しないため対象外
- Contentは次のCanonical Grammarで件数から決定的に作る

```text
Weekly digest for {week}: {postCount} {post|posts} and {commentCount} {comment|comments}.
```

- SingularはCount 1だけ、0と2以上はPlural
- 新しいUUID、Clock時刻、Counts、Contentを一つのImmutable Digestとして保存する
- 同じUser／Weekでも成功Requestごとに新しいID／Rowを作る
- `show(digestId, requestedUserId)`はUUID形式とOwnerを同じ`DigestNotFound`へ閉じる
- Generated／Shown Domain Resultは必要Fieldだけを持つ

Domain LayerはBlackOps、Doctrine、Symfony、Infrastructure、Feature、Http、Securityへ依存しない。既存再帰Architecture GuardをDigest Fileにも適用する。

## Infrastructure Contract

- `DoctrineDigestRepository`は`DigestRepository`を実装し、DBAL Connectionだけへ依存する
- Count QueryはUTC half-open RangeをParameter Bindingし、Post／Comment本文やTitleを読み出さない
- Digest InsertとGenerate成功Terminal Outcomeは同じ`#[Transactional]` Connection Scopeに入る
- Findは`id`と`requested_user_id`を同じQuery条件にし、Unknown／Non-ownerを区別しない
- DB値の型／Count／UTC DateをFail Closedで変換する
- Repository Integration TestはEmpty／Boundary／Hard Delete除外／Owner／Multiple Same Week／Rollbackを検証する

## Operation Contract

| Method／Path | Operation Type | Strategy | Result |
|---|---|---|---|
| `POST /digests` | `board.digest.weekly.generate` | Deferred | `DigestGenerated` |
| `GET /digests/{digestId}` | `board.digest.show` | Inline | `DigestShown` |

### `GenerateWeeklyDigest`

- `#[ExecuteWith(Deferred::class)]`
- `#[Authorize(AuthenticatedUserPolicy::class)]`
- `handle()`へ`#[Transactional]`
- ValueはBody `week: string`
- `#[NotBlank]`、`#[Regex('/^[0-9]{4}-W(?:0[1-9]|[1-4][0-9]|5[0-3])$/D')]`
- DomainがSemantic Invalid Weekを`InvalidDigestWeek`で返した場合は`OperationRejectedException::validation('board.digest.invalid_week')`のSafe 422へ変換する。Generated Validation ResultのViolationが空でもBFFはこのStable Codeを`week` Field Errorへ明示投影し、Raw Parser Errorを使わない
- `ExecutionContext::attempt()`を必須とし、Attempt番号を`DigestAttemptGate`へ渡した後にDigestServiceを呼ぶ
- Outcomeは`digestId`、`week`、`postCount`、`commentCount`、`createdAt`だけを持つ
- Requested User ID、Content、Credential、Attempt／Journal MetadataをOutcomeへ含めない

### `ShowDigest`

- `#[Authorize(AuthenticatedUserPolicy::class)]`
- ValueはPath `digestId: string`
- Current Actor IDをDigestServiceへ明示する
- Invalid UUID、Unknown、Non-ownerを同じ`board.digest.not_found` 404へ変換する
- Outcomeは`digestId`、`week`、`content`、`postCount`、`commentCount`、`createdAt`
- Requested User IDをOutcomeへ含めない

OperationはISO Range計算、Count、Content Grammar、Owner Query、ID／Clock生成を実装しない。

## Digest Attempt Gate Contract

Application-owned PortはOperation実行境界側に置き、Domainへ入れない。

```php
interface DigestAttemptGate
{
    public function beforeGeneration(int $attemptNumber): void;
}
```

- Production DefaultはNo-op Adapter
- Development／Test AdapterはAttempt 1だけApplication-owned Exceptionを投げ、そのExceptionは`RetryableException`を実装する
- Attempt 2以降は通過する
- Invalid／AttemptなしはOperation側がSafeなInvariant Failureとして扱い、ProductionでInline実行へFallbackしない
- Selector Configurationは`DIGEST_FAIL_FIRST_ATTEMPT`、Default `false`
- `true`／`false`だけをCanonical値として受理し、不正値はBuild／BootstrapでFail-fastする
- `ApplicationServiceProvider`がBuild／Bootstrap時にAdapter Instanceを選び、Request／Attemptごとに`$_ENV`を読まない
- FlagはPHP Application Environmentだけへ渡し、Frontend Environment、OperationValue、Manifest、Generated Tree、Outcome、Journal Dataへ含めない
- Production No-opとFirst-attempt AdapterをUnit Testし、Real Worker E2EはFlag `true`でRetryを発生させる

## Deferred Authorization Contract

- Existing Authentication MiddlewareがHTTP Current UserをActorRefへ変換する
- Generate受付はAuthenticated Policyを通る
- WorkerはDurable Origin／Authorization Userを復元し、同じPolicyをAttemptごとに再評価する
- Application-owned `OperationStatusAuthorizer`をDIへ登録する
- Status AuthorizerはOperation Type `board.digest.weekly.generate`だけを扱い、Current Actor／Origin Actorが双方`user`かつ同じIDの場合だけAllowする
- Anonymous、Invalid Session、Actor Type／ID不一致、Unknown Operation TypeはDenyする
- Status HTTP SurfaceはUnknownとDenyを同じ404へ閉じる
- Show DigestもOwner QueryでUnknown／Non-ownerを同じ404へ閉じる
- AuthorizationにSession Token、User Record、Emailを使わずActorRefだけを使う

## SvelteKit Server-only Contract

Generated Digest ModuleをImportできるのは`frontend/src/lib/server/blackops/*.server.ts`だけとする。既存`board.server.ts`／`operations.server.ts`と同じInjected Fetch／Base URL／Bearer境界を使い、Global Mutable Clientを作らない。

Application-owned Digest Wrapperは少なくとも次を提供する。

- `startWeeklyDigest(fetch, token, week)` -> accepted Operation IDまたはSafe Failure
- `loadDigestStatus(fetch, token, operationId)` -> Safe Status Snapshot
- `waitForDigest(fetch, token, operationId, signal, maxWaitMilliseconds)` -> TerminalまたはSafe Snapshot
- `loadDigest(fetch, token, digestId)` -> Safe Digest Detail

### Safe Status View

```text
accepted        operationId, state, retryAfterSeconds
running         operationId, state, retryAfterSeconds
retry_scheduled operationId, state, retryAfterSeconds
completed       operationId, state, digestId
failed          operationId, state, safe message
```

- rejected／failed／dead_letteredはBrowser向け`failed`へ縮約してよいが、Testで元State別Mappingを固定する
- completed OutcomeはDigest IDだけをNavigationへ使い、Raw Generated ResultをPage Dataへ渡さない
- 401はCookie削除と固定Login Redirect
- Unknown／Deny 404、Expired 410、Malformed IDを同じSafe 404へ縮約する
- Poll TimeoutはFailure Pageにせず、`.status()`を一回呼んでCurrent Safe Snapshotへ戻す
- Abort、Transport、Internal、Malformed ResponseはCredential／URL／Raw ErrorなしのSafe unavailableへ縮約する
- Retry Hintは正のSafe Integerだけを最大5秒へClampしてBrowserへ渡す

## Canonical SvelteKit Routes

| Method | Route | Responsibility |
|---|---|---|
| `GET` | `/digests` | Authenticated Generate Form。Default WeekはServerでCurrent UTC ISO Week |
| `POST` | `/digests` default action | Generate、成功時`303 /digests/operations/{operationId}` |
| `GET` | `/digests/operations/[operationId]` | `.status()`のSSR Progress View。CompletedならDigestへ303 |
| `GET` | `/digests/operations/[operationId]/wait` | Same-origin JSON BFF。有限`.wait()`、Timeout後`.status()` |
| `GET` | `/digests/[digestId]` | `ShowDigest.fetch()`の保存済みDetail |

- Progress Pageはaccepted／running／retry_scheduled／failedをTextと`role="status"`等のSemantic Stateで区別する
- Minimal Client Scriptは同じOriginのWait Endpointだけを呼び、Terminal時は固定Digest Routeへ遷移する
- JavaScriptなしでもPage Reload／明示Refresh LinkでStatusを更新できる
- ClientはBlackOps Base URL、Generated Operation、Authorization Header、Cookie値を知らない
- Wait Endpointは`Cache-Control: private, no-store`、JSON Content Type、Safe DTOだけを返す
- WaitはRequest Abort Signalと1,500msから5,000ms内の固定有限Deadlineを使う。値をBrowser入力で無制限に変更させない
- User入力Redirect URL、Operation Type、Base URL、Credentialを受け取らない
- P17-008のVisual Design／Reiconは先取りしない

## Testing Contract

### PHP

- IsoWeek Canonical／UTC Range／Year Boundary／Invalid Week 53
- DigestService Empty／Singular／Plural Content、ID／Clock、Same Week Multiple ID、Owner／Invalid ID
- Doctrine Repository Count Boundary、Hard Delete除外、Owner concealment、Multiple Same Week、Rollback
- Migration Table／FK／Check／Index／No User-Week Unique
- Operation Metadata／Route／Strategy／Transactional／Validation／Outcome
- Attemptなし、Attempt 1 Gate、Retryable Exception、Attempt 2 Success
- Status Authorizer Same User Allow、Anonymous／Actor mismatch／Unknown Type Deny
- Service Provider No-op Default、true Adapter、不正Config Fail-fast
- Domain recursive Architecture Guard
- Build Artifact／Frontend Manifestに2 Digest OperationsとStructured Outcome

### Frontend

- Generated Fetch 202、Status全State、Wait Completed／Poll Timeout／Abort／TransportをFake Fetchで検証する
- Per-call BearerとPrivate Base URLをServer内だけで注入する
- Start 422、401、404／410、500／MalformedをSafe DTOへ投影する
- Retry Hint Clamp、Operation ID／Digest ID Fixed Location、Default UTC Week
- Wait Timeout後にStatus Snapshotへ戻る
- Failed StateからRaw Backend Code、Operation Error、Attempt ID、Actor、Credentialを除く
- Same-origin Wait Endpoint ResponseへServer-only情報を含めない
- Existing 26 Vitestを回帰させ、Svelte Check／Production Buildを成功させる

## Real HTTP and Worker Journey

New `tests/Consumer/community-board-digest.sh`は独立Compose Project／Clean PostgreSQLで少なくとも次を完走する。

1. PHP／Frontend locked install済み前提、Setup、Migration、Compile、Generate、Fresh、PHPUnit、Frontend Check／Test／Build
2. `DIGEST_FAIL_FIRST_ATTEMPT=true`でBuild／Compositionし、PostgreSQL、HTTP、SvelteKitを起動する
3. SvelteKit Form ActionでAliceを登録し、Current UTC ISO Weekを取得する
4. AliceがPost 1件を作り、BobがComment 1件を追加する
5. Invalid WeekをSvelteKit Digest Actionへ送り、Safe 422 Field Errorを確認する
6. Valid Weekを送り、202由来Operation IDのProgress Routeへ303する
7. Worker前のProgress Page／Wait Endpointがaccepted／Poll Timeout後Safe Snapshotを返す
8. Workerを1 iteration実行し、Attempt 1がretry_scheduledになり、Progress Pageへ表示される
9. Retry Available後にWorkerを1 iteration実行し、completed Typed OutcomeからDigest Detailへ到達する
10. DigestがPost 1、Comment 1、Canonical Singular Contentを表示し、Databaseに同Operation由来Rowが1件だけある
11. 同じUser／Weekを再生成して別Digest ID／Rowになる
12. 元PostをHard Delete後に再生成すると新Digestは0／0、既存2 Digestは1／1のImmutable Snapshotを維持する
13. BobはAliceのStatusとDigest Detailを参照できず、Unknown／Denyと同じSafe 404になる
14. Malformed Operation ID、Expired Status Fixture、Abort／Timeout／Backend-downをSafe Surfaceへ写す
15. Canonical Journalでaccepted、attempt.failed、retry_scheduled、attempt 2、completedとOrigin／Authorization／Worker Execution Actorを確認する
16. Credential、Password、Failure Flag、Post／Comment Content MarkerがTransport Context／Outcome／Generated Tree／Client Build／SSR／Action／JSON／Logへ不適切に残らないことをSurface別に検査する

- Post／Comment ContentはApplication TableとCanonical OperationValueへ存在し得る。Digest Row／Outcome／Status／Digest Browser Surfaceへ複製されないことを検査し、全Repository横断の誤った不在Assertionをしない
- Known Marker FixtureでSensitive Guard自身のFailureを証明する
- PostgreSQL管理ログとApplication／Browser Surfaceを分離する
- Curl／Wait／Workerは有限Timeout／Iterationsを持つ
- Failure時もCompose ResourceとGenerated／Runtime／Dependency／Build ArtifactをCleanupする
- Existing Foundation、Identity、Post／Comment、Product Journeyを回帰させる

## CI and Artifact Contract

- Community Board CIへDigest Journeyを追加し、必要ならJob Timeoutを実測に基づき最小限増やす
- Generated import allowlistへDigest Server-only Wrapperだけを追加する
- Client Build GuardへDigest Generated Class、Private Env、Authorization／Bearer、Failure Flagを追加する
- `.env`、Vendor、Node Modules、Generated Tree、Build、SvelteKit、Log、PHPUnit Cacheを追跡しない
- New MigrationとConsumer ScriptをTracking Guardへ追加する
- Reicon／別Icon Libraryを追加しない
- Task完了前にDependency／Generated／Runtime ArtifactをCleanupする

## Acceptance Criteria

- [x] D107のUTC ISO Week／Immutable Count Snapshot／Multiple Row／Failure Adapterを実装する
- [x] `board_digests` MigrationがOwner、Content、Counts、Created Atを安全に保存する
- [x] DigestServiceがWeek、集計、Content、ID／Clock、Owner取得のDomain Logicを所有する
- [x] DomainはBlackOps／Doctrine／Symfony／Infrastructure／Featureへ依存しない
- [x] `GenerateWeeklyDigest`がDeferred／Transactional／Authenticatedで202を返す
- [x] Worker Attempt 1 RetryとAttempt 2 Completedが実Runtimeで成立する
- [x] `ShowDigest`がOwnerだけへ保存済みSafe Detailを返す
- [x] Status AuthorizerがCurrent／Origin User一致だけを許可する
- [x] Production No-opとDevelopment／Test Failure AdapterをBuild時に明示選択する
- [x] Generated `.fetch()`／`.status()`／`.wait()`をSvelteKit Server-only Wrapperから使う
- [x] Start／Progress／Wait Endpoint／Digest DetailがSvelteKit Same-originで完走する
- [x] accepted／running／retry_scheduled／completed／failedをSafe Viewへ投影する
- [x] Unknown／Deny／Expired／Malformed／Timeout／Abort／Transport／Internalを安全に扱う
- [x] Browser BundleへGenerated Source、Internal URL、Credential、Failure Flagが入らない
- [x] PHP／Frontend TestとReal HTTP＋Worker Digest Journeyが成功する
- [x] Existing Community Board 4 Consumer E2Eが回帰しない
- [x] Framework `src/**`、Root PHP tests、Quickstart／Skeleton Sourceを変更しない
- [x] Reicon／Visual Designをまだ追加しない
- [x] Required Quality GateとArtifact Cleanupが成功する
- [x] WorkerはCommitしない

## Required Commands

実際のMigration名やScript補助CommandはReportへ記録する。

```bash
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
mise exec -- pnpm --dir examples/community-board/frontend install --frozen-lockfile

docker compose -f examples/community-board/compose.yaml run --rm app php bin/setup
docker compose -f examples/community-board/compose.yaml up -d postgres
docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:generate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:check
docker compose -f examples/community-board/compose.yaml run --rm app vendor/bin/phpunit

mise exec -- pnpm --dir examples/community-board/frontend run check
mise exec -- pnpm --dir examples/community-board/frontend run test
mise exec -- pnpm --dir examples/community-board/frontend run build

bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --exit-code -- src examples/quickstart
git diff --exit-code -- tests ':(exclude)tests/Consumer/community-board-*.sh'
! git ls-files \
  examples/community-board/.env \
  examples/community-board/vendor \
  examples/community-board/var \
  examples/community-board/frontend/node_modules \
  examples/community-board/frontend/src/lib/server/blackops/generated \
  examples/community-board/frontend/.svelte-kit \
  examples/community-board/frontend/build
```

## Completion Report

`develop/orchestration/reports/P17-007-deferred-digest-and-progress.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Domain／Infrastructure／Operation Dependency Boundary
- Database and Immutable Snapshot Contract
- Failure Adapter and Composition Boundary
- Deferred Actor／Status Authorization Evidence
- Generated Fetch／Status／Wait and BFF Mapping
- Real Worker Retry／Completed／Digest Journey
- Sensitive／Client Bundle／Artifact Guard Evidence
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
