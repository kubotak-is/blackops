# P17-006: Generated Operations and SvelteKit Product Journey

Status: Accepted

## Goal

`examples/community-board/`のGenerated Operation ObjectをSvelteKit Serverだけから利用し、認証済みUserがFeed、Post Detail、Create、Edit、Delete、CommentをBrowserから完走できるようにする。BlackOps HTTP ResultをApplication-ownedなSafe View Model／Form Action Resultへ投影し、Generated Source、Internal Base URL、Raw Session Credential、Framework Internal ErrorをBrowserへ渡さない。

## In Scope

- Server-only Generated Frontend ContractのBuild／Generate／Fresh Check
- Application-owned `.server.ts` Board Client／Result Mapper
- Feed、Post Detail、Create、Edit、Delete、CommentのPage Server Load／Form Action
- Pagination Queryの安全な正規化
- 422 Field Error、401 Session Failure、404、409 Conflict、Transport／Internal FailureのSafe Projection
- OwnerだけにEdit／Delete導線を表示するSafe View Model
- Browser Bundle／Client Import／Rendered ResponseのServer-only Guard
- SvelteKit Server-side TypeScript Test
- SvelteKitを入口にするReal HTTP Product Journey
- Community Board CI／README、Report、TODO、STATE同期

## Out of Scope

- PHP Domain、DomainService、Repository、Infrastructure、Operation、Migrationの変更
- Framework `src/**`とPublic API変更
- Deferred Digest、Status／Wait、Progress UI
- Final Visual Design、Taste Skill、Reicon、Animation、Screenshot、Browser Automation
- Optimistic UI、Client-side Cache、Infinite Scroll、Search、Sort、Moderation
- Framework固有Svelte Adapter、Generated Form Action、Global Mutable Client
- BrowserからBlackOps PHP EndpointへのDirect Fetch
- `examples/quickstart/**`、Skeleton Source、Publication Workflow変更
- Documentation Website Content／Publication／Deploy、External Hosting

## Relevant Specifications and Decisions

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/106-board-domain-layering.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/orchestration/tasks/P17-003-identity-session-and-bff-boundary.md`
- `develop/orchestration/tasks/P17-005-post-and-comment-operations.md`

## Files Allowed to Change

### Reference Application Frontend

- `examples/community-board/frontend/src/**`
- `examples/community-board/frontend/package.json`と`pnpm-lock.yaml`（P17-006のTest／Buildに不可欠な既存Dependency同期だけ。Icon Libraryは追加しない）
- `examples/community-board/README.md`

### Consumer and CI

- New `tests/Consumer/community-board-product-journey.sh`
- `tests/Consumer/community-board-foundation.sh`、`community-board-identity.sh`、`community-board-post-comment.sh`（共存に必要な最小互換修正だけ）
- `.github/workflows/ci.yml`

### Documentation and Orchestration

- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-006-generated-operations-and-sveltekit-product-journey.md`
- `develop/spec/71-full-stack-reference-application.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/72-phase-17-delivery-plan.md`（Task境界の誤りを発見した場合だけ）

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。特に`src/**`、`examples/community-board/app/**`、`examples/community-board/migrations/**`、Root PHP `tests/**`、`examples/quickstart/**`を変更しない。

## Server-only Dependency Boundary

- Canonical Generated Outputは`frontend/src/lib/server/blackops/generated`を維持する
- Generated Operation／TypeをImportできるのは`frontend/src/lib/server/blackops/*.server.ts`またはその配下の`.server.ts`だけとする
- Routeの`+page.server.ts`／`+layout.server.ts`はApplication-owned `.server.ts` WrapperだけをImportし、Generated Sourceを直接Importしない
- `.svelte`、`+page.ts`、`+layout.ts`、Browser共有Module、Client EntryからGenerated Source、`$env/dynamic/private`、Credential Injection CodeをImportしない
- Generated Operationは呼出しごとにInjected Fetch、`BLACKOPS_BASE_URL`、Bearer Credentialを受け取る。Global Mutable Client、Module-level Credential、Generated Config書換えを使わない
- BrowserはSvelteKit OriginへだけRequestし、BlackOps Base URLを知らない
- Existing `operations.server.ts`は分割してよいが、Welcome／Current Userの既存ContractとTestを回帰させない

## Canonical Product Routes

| Method | SvelteKit Route | Responsibility |
|---|---|---|
| `GET` | `/posts?page=1` | Authenticated Feed。Canonical `perPage=20` |
| `GET` | `/posts/new` | Authenticated Create Form |
| `POST` | `/posts/new` default action | Create Post、成功時`303 /posts/{postId}` |
| `GET` | `/posts/[postId]` | Authenticated Detail／Comments |
| `POST` | `/posts/[postId]?/comment` | Add Comment、成功時同Detailへ`303` |
| `POST` | `/posts/[postId]?/delete` | Owner Delete、成功時`303 /posts` |
| `GET` | `/posts/[postId]/edit` | Owner Edit Form |
| `POST` | `/posts/[postId]/edit` default action | Update Post、成功時`303 /posts/{postId}` |

- Root Landingは既存Welcomeを維持し、認証済みUserへ`/posts`導線を追加する
- Global NavigationへFeed導線を追加してよいが、P17-008の最終Visual Designは先取りしない
- Page LoadとActionは標準HTML FormでJavaScriptなしでも完走する
- User入力のRedirect URLは受け取らず、上表の固定RouteだけへRedirectする
- Post IDはRoute ParameterをそのままBlackOps Wrapperへ渡してよい。Malformed／Unknown／Unauthorized Ownerは同じSafe 404へ投影する

## Authentication and Session Behavior

- Product Routeは全て認証必須とする
- Session Cookie欠落時は`303 /login`へRedirectする
- Backendが401を返した場合はSession Cookieを削除し、Loadは`303 /login`、ActionはCredentialを反射せず安全にLogin導線へ戻す
- Backend unavailableはCookieを削除せず、Safeな一時障害View／Action Errorを返す
- Root LayoutのCurrent UserとProduct LoadでCredentialをPage Dataへ入れない
- `owner`は`post.authorId === currentUser.id`からServer側で計算し、BrowserへはBooleanと必要なSafe User Fieldだけを渡す
- Owner以外にはEdit／Delete導線を表示しない。ただしAuthorizationの正本はPHP DomainService／Operation境界であり、UI非表示をSecurity境界にしない

## Generated Operation Mapping

Application-owned Wrapperは次のGenerated Objectを使う。

| Generated Operation | BFF use |
|---|---|
| `ListPosts.fetch()` | Feed Load、`page`と`perPage=20` |
| `ShowPost.fetch()` | Detail Load、Edit Load |
| `CreatePost.fetch()` | Create Form Action |
| `UpdatePost.fetch()` | Edit Form Action |
| `DeletePost.fetch()` | Detail Delete Action |
| `AddComment.fetch()` | Detail Comment Action |

- Completed Outcomeだけを画面向けReadonly DTOへ明示的に写す
- Date StringはそのままSafe DTOへ渡してよいが、UI表示でInvalid Dateが例外を起こさないようにする
- Array／Nested ObjectはGenerated Decoderが検証した値から新しいReadonly View Modelへ投影し、Framework Result UnionをPage Dataへ透過しない
- Form ActionはGenerated Validation MetadataのField名／Constraintを正本とする。Title 120、Post Body 10000、Comment Body 2000の別定義をClient Product Sourceへ増やさない
- HTMLの`maxlength`等を出す場合はGenerated ContractからApplication-owned Server Projectionへ導出する。定数の手書き重複は不可

## Safe Result Projection

### Load Result

- Completed: 必要なFeed／Detail／Edit View Model
- 401: Cookie削除と固定Login Redirect
- 404: `404`と`board.post.not_found`に対応する一般向けMessage。Actor、Operation ID、Raw Bodyを返さない
- 409: `409`と一般向けConflict Message
- 422: Query Binding Failureなら安全なBad Request／Pagination fallback。Raw Valueを無制限に反射しない
- Internal／Transport／Unexpected: `503`またはTask内で統一したSafe unavailable surface。Internal URL、SQL、Absolute Path、Credential、Raw Bodyを含めない

### Action Result

- Completed／Void: Canonical Redirect
- 422: `fail(422, ...)`でFieldごとのSafe Code／Messageと必要最小限の入力だけを返す
- Create／Updateでは`title`と`body`、Commentでは`body`を再表示してよい。値はStringだけに絞り、Response Sizeを各Canonical最大長以下へClampする
- 401: Cookie削除、Credential非反射、固定Login導線
- 404: `fail(404, ...)`またはSvelteKit `error(404, ...)`をRouteごとに一貫して使う
- 409: `fail(409, ...)`と再試行可能な一般向けMessage
- Internal／Transport／Unexpected: `fail(503, ...)`のSafe Message
- Operation ID、Journal ID、Correlation／Causation ID、Exception、Raw Backend DetailをBrowser Action Dataへ返さない

## Page Data and Form Data Contract

- Feed Item: `id`, `authorDisplayName`, `title`, `bodyPreview`, `createdAt`, `updatedAt`, `commentCount`
- Feed Page: `posts`, `page`, `perPage`, `total`, `hasPrevious`, `hasNext`
- Detail Post: Feed Fieldに加えて`body`, `owner`
- Comment: `id`, `authorDisplayName`, `body`, `createdAt`
- Edit Load: `postId`, `title`, `body`
- Form Failure: `success: false`, stable `message`, field error map,許可された再表示値だけ
- Page Dataへ`authorId`を渡す必要がある場合はOwner判定以外の明確なUI用途をReportへ記録する。通常は`owner`へ縮約する
- View Model／Action DataはJSON serialize可能で、Class Instance、Response、Error、Generated Result Objectを含めない

## Minimal Product UI

- P17-006は機能を完走する最小Semantic UIとし、P17-008のDesign Directionを先取りしない
- 各Pageに固有`<title>`、1つの`h1`、Label付きForm、Submit Button、Navigationを置く
- Empty Feed、Load Failure、Validation Error、Not Found、Owner ActionをTextで識別できる
- Validation Errorは`aria-describedby`または同等の関連付けを行う
- DeleteはGETで実行せずPOST Form Actionだけを使う
- Icon、Icon Font、General-purpose UI Library、CDN Assetを追加しない

## Testing and CI Contract

### Vitest／Svelte Check

- 6 Generated Operationの正しいMethod／Path／Query／Body／Bearer注入をFake Server Fetchで検証する
- Feed／DetailのNested DTO／ReadonlyArrayをSafe View Modelへ写す
- 422 Field Error、401、404、409、500、Malformed Response、Transport Throwを安全に投影する
- Raw Credential、Internal URL、Raw Backend Body、SQL／Absolute Path Markerが返却値へ残らない
- Owner Booleanと非Owner Viewを検証する
- Form Input Clamp、Pagination正規化、固定Redirectを検証する
- Existing Welcome／Identity Testを維持する
- `pnpm run check`、`pnpm run test`、`pnpm run build`を成功させる

### Real HTTP Product Journey

New `tests/Consumer/community-board-product-journey.sh`はClean Databaseと実SvelteKit Serverを使い、少なくとも次を完走する。

1. Application Setup、Migration、Build Compile、Frontend Generate／Check
2. PostgreSQL、PHP HTTP、SvelteKit Frontend起動
3. SvelteKit `/register` Form ActionでAliceを登録しHttpOnly Cookieを得る
4. Alice CookieでEmpty Feedを表示する
5. `/posts/new`へ不正入力をPOSTし、422 Field Errorと安全な再表示値を確認する
6. 有効なPostを作り、303 Redirect先Detail、Feed、Post本文を確認する
7. SvelteKit Form ActionでBobを登録し、同じPostへCommentを追加する
8. Bob DetailではComment表示、Edit／Delete非Owner導線なしを確認する
9. Alice Edit Page／ActionでPostを更新し、Detail／Feedへ反映される
10. Bobの直接Edit／Delete Actionが同じSafe 404となり、存在／Owner情報を漏らさない
11. Alice Delete Actionが303 Feedへ戻り、PostとCommentが消える
12. Deleted／Malformed IDがSafe 404になる
13. Missing／Invalid CookieがLoginへ戻り、Backend停止時はInternal URL／CredentialなしのSafe Failureになる
14. SSR HTML、Action Response、Client Build、Container LogへRaw Token／Password／Internal Base URL／Generated Import Path／SQL／Absolute Path Markerが残らないことをSurface別に検査する

- Curlは有限Timeoutを持つ
- Sensitive Guardは既知Marker Fixtureで自身が失敗できることを証明する
- PostgreSQL管理ログの期待されるSQLとBrowser／Application Surfaceを分離する
- Failure時もCompose ResourceとRuntime／Generated／Build ArtifactをCleanupする
- Foundation、Identity、Post／Comment Consumer Scriptを回帰させる
- CIのCommunity Board JobへProduct Journeyを追加する

## Browser Bundle and Artifact Guards

- Client Buildに`BLACKOPS_BASE_URL`の値、Bearer Token、Session Cookie名を含めない
- Client Module Graph／Build Outputに`blackops/generated`、Generated Operation Class名、`$env/dynamic/private`、Authorization Header Injection Codeを含めない
- Server buildがGenerated Moduleを含むこと自体は許可する。Client／Browser Artifactと区別して検査する
- `.env`、`vendor/`、`node_modules/`、`var/**`、Generated Tree、`.svelte-kit/`、`build/`を追跡しない
- Task完了前に生成物をCleanupする

## Acceptance Criteria

- [x] 6 Generated Operation ObjectをApplication-owned `.server.ts` Wrapperから使う
- [x] Generated SourceとCredential InjectionがSvelteKit Server-only境界に留まる
- [x] Feed、Detail、Create、Edit、Delete、CommentのPage Server Load／Form Actionが完走する
- [x] 422、401、404、409、Internal／TransportをSafe View／Action Dataへ投影する
- [x] Authenticated／Owner UIとBackend Authorization境界が一致する
- [x] Standard HTML FormでJavaScriptなしのProduct Journeyが成立する
- [x] Browser Bundle／Client ImportへGenerated Source、Internal Base URL、Credentialが入らない
- [x] Vitest、Svelte Check、Production Buildが成功する
- [x] Real HTTP Product JourneyがSvelteKitを入口に全操作を完走する
- [x] Foundation、Identity、Post／Comment Consumer E2Eが回帰しない
- [x] PHP Domain／Infrastructure／Operation／Migrationを変更しない
- [x] Framework `src/**`、Quickstart／Skeleton Sourceを変更しない
- [x] Reicon／別Icon Libraryをまだ追加しない
- [x] Generated／Dependency／Runtime／Build Artifactを追跡しない
- [x] Required Quality Gateが成功する
- [x] WorkerはCommitしない

## Required Commands

実際のTest Script名を変更する必要がある場合、ReportへCanonical Commandを記録する。

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

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --exit-code -- src examples/quickstart examples/community-board/app examples/community-board/migrations
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

`develop/orchestration/reports/P17-006-generated-operations-and-sveltekit-product-journey.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Server-only Import／Credential Boundary
- Page／Action Route Contract
- Safe Result Mapping Matrix
- Real HTTP Product Journey Evidence
- Browser Bundle／Sensitive／Artifact Guard Evidence
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
