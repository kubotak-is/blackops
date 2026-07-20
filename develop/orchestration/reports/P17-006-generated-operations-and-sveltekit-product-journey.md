# P17-006 Generated Operations and SvelteKit Product Journey Report

## Summary

Community Boardの6 Generated OperationをApplication-ownedなSvelteKit Server-only Wrapperへ接続し、認証済みUserがFeed、Create、Detail、Comment、Edit、Deleteを標準HTML Formで完走できるProduct Journeyを実装した。

Generated ResultはBrowserへ透過せず、Completed OutcomeだけをJSON serialize可能なReadonly View Modelへ明示投影する。401、404、409、422、Internal、Malformed、Transportを固定Message／Field Mapへ縮約し、Raw Credential、Internal URL、Operation ID、Backend Code／Detail、Author IDをPage／Action Dataへ渡さない。

## Changed Files

- `examples/community-board/frontend/src/lib/presentation.ts`
- `examples/community-board/frontend/src/lib/server/blackops/board.server.ts`
- `examples/community-board/frontend/src/lib/server/blackops/board.server.test.ts`
- `examples/community-board/frontend/src/lib/server/board-route.server.ts`
- `examples/community-board/frontend/src/routes/+layout.svelte`
- `examples/community-board/frontend/src/routes/+page.svelte`
- `examples/community-board/frontend/src/routes/posts/**`
- `tests/Consumer/community-board-product-journey.sh`
- `tests/Consumer/community-board-foundation.sh`
- `tests/Consumer/community-board-identity.sh`
- `.github/workflows/ci.yml`
- `examples/community-board/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P17-006-generated-operations-and-sveltekit-product-journey.md`

## Server-only Import／Credential Boundary

- Canonical Generated Outputはignored tree `frontend/src/lib/server/blackops/generated/`のまま維持した。
- Generated ModuleをImportするProduct Sourceは`lib/server/blackops/board.server.ts`と既存`operations.server.ts`だけである。
- Route Server ModuleはApplication-owned WrapperだけをImportし、`.svelte`／Browser共有ModuleはGenerated Source、Private Env、Credential InjectionをImportしない。
- Generated OperationへSvelteKit `fetch`、Private Base URL、Bearer Headerを呼出しごとに注入する。Global Mutable Client、Module-level Token、User入力Redirectはない。
- ActionはCookie欠落時だけ固定`/login`へRedirectし、Backend 401はOperation ResultからCookie削除と固定Login Redirectへ写す。Backend unavailableはCookieを削除せず`fail(503)`へ写す。
- Client Build GuardでGenerated Path／Class名、Private Env、Base URL、Cookie名、Authorization／Bearer Injectionがないことを確認した。

## Page／Action Route Contract

| Method | Route | Result |
|---|---|---|
| GET | `/posts?page={page}` | Authenticated Feed、`perPage=20`、invalid pageは1へ正規化 |
| GET／POST | `/posts/new` | Create Form、成功時`303 /posts/{postId}` |
| GET | `/posts/{postId}` | Detail、Comments、Server計算Owner Boolean |
| POST | `/posts/{postId}?/comment` | Comment、成功時同Detailへ303 |
| POST | `/posts/{postId}?/delete` | Owner Delete、成功時`303 /posts` |
| GET／POST | `/posts/{postId}/edit` | Owner Edit、成功時Detailへ303 |

各Pageは固有Title、単一H1、Navigation、Label付きForm、Submit Buttonを持つ。Empty Feed、Validation、Not Found、Unavailable、Owner ActionをTextで識別可能にし、Field Errorを`aria-describedby`でControlへ関連付けた。Date表示はInvalid Dateでも例外にせず固定Fallbackを出す。

## Safe Result Mapping Matrix

| Generated／Transport Result | Browser Projection |
|---|---|
| Completed | Feed／Detail／Comment／Editの必要Fieldだけを新しいReadonly DTOへcopy |
| 401 | Cookie削除、固定`303 /login` |
| 403／404 | Author／Existenceを区別しない404 `This post could not be found.` |
| 409 | 409 retryable general message |
| 422 | 422 general messageと許可Fieldだけのsafe message map |
| 400／500／Malformed／Transport／Missing Base URL | 503 fixed unavailable message |

`authorId`はWrapper内の`post.authorId === currentUser.id`にだけ使い、Browserへ`owner` Booleanとして渡す。Comment／FeedのAuthor IDも除去する。Generated ContractがRuntimeのCanonical Length値を公開しないためProduct HTMLへ`maxlength`を重複定義しなかった。Action再表示値だけをDomain Validationとは独立した100 Unicode CharacterのResponse BudgetへClampし、Canonical最大長以下に保つ。

## Real HTTP Product Journey Evidence

`tests/Consumer/community-board-product-journey.sh`は独立ProjectとClean PostgreSQL Volumeで次を完走した。

1. Setup、Migration 4、Compile、Generated 11 files、Fresh Check、Frontend Check／Test／Build
2. SvelteKit Form ActionによるAlice登録とHttpOnly Cookie
3. Empty Feed、invalid pagination fallback、Create 422とclamped replay
4. Create 303、Detail／Feed／Body反映
5. Bob登録、Comment 303、Detail反映、Owner Action非表示
6. Bob直接Edit／Deleteの同一Safe 404
7. Alice Edit 303とDetail反映
8. Alice Delete 303、Empty Feed、Post／Comment消失
9. Deleted／Malformed IDの同一Safe 404
10. Missing／Invalid CookieのLogin RedirectとCookie削除
11. Backend停止時のLoad 503とAction `fail(503)`
12. Client Build／SSR／Action／Frontend LogのSensitive GuardとKnown Marker Failure Fixture

既存`community-board-foundation.sh`、`community-board-identity.sh`、`community-board-post-comment.sh`も最終実装で再成功した。Foundation／IdentityのImport Guardは2つのApplication-owned Server-only Wrapperを許可する最小互換修正だけを行った。

## Browser Bundle／Sensitive／Artifact Guard Evidence

- Client Buildへ`BLACKOPS_BASE_URL`、`http://http`、Cookie名、Generated Path／Operation Class、Private Env、Authorization／Bearer Codeがない。
- SSR HTML、Action Response、Frontend Log、Client BuildへAlice／Bob Token、Password、Internal URLがない。
- Browser／Application SurfaceへSQL、Absolute Path、Operation ID、Author ID、Raw Backend Code／Detailがない。
- Sensitive Guardは既知Marker FixtureでFailureを返すことを自己検証する。
- `.env`、Vendor、Node Modules、Generated Tree、Build、SvelteKit、Log、PHPUnit Cacheは追跡されず、Handoff前に除去する。

## Decisions and Assumptions

- Product Route LoadはRoot LayoutのCurrent User Projectionを利用し、Raw TokenはCookieからServer内だけで取得する。
- ActionはCookie存在だけを先に確認し、Authentication／Availabilityの正本を対象Generated Operation Resultに置く。これによりBackend unavailableをsafe `fail(503)`へ写し、余分なCurrent User Requestを行わない。
- Direct Edit LoadはDetail ViewのOwner Booleanがfalseなら、Unknown／Malformed／Unauthorizedと同じ404へ閉じる。Mutation Authorizationの正本はPHP DomainService／Operationである。
- Paginationはpositive safe integerだけを受け、その他は1へ戻す。Canonical Backend upper boundをProduct Sourceへ複製しない。
- Final Visual Design、Icon、AnimationはScope外である。User指定を`develop/TODO.md`とREADMEへ記録し、P17-008で`reicon.dev`をIcon Sourceとして使用する。

## Commands and Results

```text
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
composer validate --strict / install --frozen inputs
php bin/setup / database:migrate / build:compile / frontend:generate / frontend:check
  success; migrations 4; generated 11 files; fresh

docker compose -f examples/community-board/compose.yaml run --rm app vendor/bin/phpunit
  OK (33 tests, 388 assertions)

mise exec -- pnpm --dir examples/community-board/frontend run check
mise exec -- pnpm --dir examples/community-board/frontend run test
mise exec -- pnpm --dir examples/community-board/frontend run build
  0 errors, 0 warnings; 5 files / 26 tests passed; adapter-node build success

bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
  all journeys passed

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
  Composer valid; Mago clean; PHPUnit OK (1471 tests, 5810 assertions)
  Deptrac: 0 violations, 0 skipped, 0 uncovered, 0 warnings, 0 errors
```

初回host検証ではDockerと一時pnpm store由来の`node_modules` provenance不一致を検出した。Ignored dependency treeを削除し、Canonical frozen installで再構築して全Frontend／E2Eを成功させた。Default Compose Migrationは過去Taskのstale local volumeで一度Bootstrap Failureとなったが、VolumeをCleanにして単独再実行しMigration 4が成功した。独立E2EのClean Databaseは全実行で成功している。

## Acceptance Criteria

- [x] 6 Generated OperationをApplication-owned Server-only Wrapperから使用
- [x] Generated Source、Private Env、Credential InjectionをServer-only境界へ限定
- [x] Feed、Detail、Create、Edit、Delete、Commentの標準HTML Journey
- [x] 401、404、409、422、Internal／Malformed／TransportのSafe Projection
- [x] Server計算Owner UIとPHP Authorization境界
- [x] Pagination normalization、Form replay clamp、固定Redirect
- [x] Browser Bundle／Rendered／Action／Log Sensitive Guard
- [x] Frontend Check、26 Vitest、Production Build
- [x] Real HTTP Product Journeyと既存3 Consumer E2E
- [x] PHP Domain／Infrastructure／Operation／Migration、Framework、Quickstart Scope保持
- [x] CI、README、TODO、STATE同期
- [x] Reicon／Icon Library未導入、P17-008へ明示引継ぎ
- [x] Required Quality Gate
- [x] Worker Commitなし

## Remaining Issues

なし。Deferred Weekly Digest／Status UIはP17-007、Final Visual Design／ReiconはP17-008、Real Browser／Screenshot／Guide CloseoutはP17-009のScopeである。

## Orchestrator Review and Independent Verification

Orchestratorは全差分をReviewし、Generated Importが`board.server.ts`と既存`operations.server.ts`だけに限定されること、Route／Svelte ComponentがGenerated Source／Private Env／Credential Injectionへ依存しないこと、PHP `app/Domain/**`／`app/Infrastructure/**`／Operation／MigrationとFramework `src/**`に差分がないことを確認した。Safe Result Mapping、Owner Booleanへの縮約、固定Redirect、非所有者／未知／Malformed IDの同一404、Client Build Guardにも修正要求はなかった。

独立してClean Dependency Install後にMigration 4、Build Compile、Generated 11 files、Fresh Checkを実行した。Community Board PHPUnitは33 tests／388 assertions、Svelte Checkは0 errors／0 warnings、Vitestは5 files／26 tests、adapter-node Buildは成功した。`community-board-product-journey.sh`を再実行し、SvelteKitを入口にAlice／BobのRegister、Feed、Validation、Create、Detail、Comment、Owner／Non-owner、Edit、Delete、Session、Backend-down、Browser／Sensitive Guardが成功した。

RootではMago format／lint／analyze、PHPUnit 1471 tests／5810 assertions、Deptrac違反0を独立再実行した。Shell Syntax、Management ID、Scope、Tracking、Artifact、`git diff --check` Guardも成功し、TaskをAcceptedとした。

## Suggested Next Action

P17-006を独立Commit／Pushし、P17-007 Deferred Weekly Digest／Status UIのTask Packetを確定する。
