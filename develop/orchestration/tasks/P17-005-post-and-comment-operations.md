# P17-005: Post and Comment Operations

Status: Ready

## Goal

`examples/community-board/`へApplication-ownedなPost／Comment Domainを実装し、認証済みUserがPost Feed、Post Detail、Create、Update、Delete、Add CommentをBlackOps Inline Operationとして完走できるようにする。OperationValue Validation、存在を秘匿するOwner Authorization、Doctrine DBAL Repository、`#[Transactional]`、Structured Outcome、Generated Frontend Contractを同じReal HTTP Journeyで証明する。

## In Scope

- `board_posts`／`board_comments` Application Migration
- Application-owned Board Repository、Clock、UUIDv7 ID Generator
- List／Show／Create／Update／Delete PostとAdd Comment Inline Operation
- Paginated Post Summary、Post Detail、Comment ListのStructured Outcome
- Native Path／Query／Body BindingとOperationValue Validation
- Authenticated AuthorizationとOwner-only Update／Delete
- Unknown／Malformed／Non-owner Resourceを同じSafe 404へ閉じる境界
- Post Hard DeleteとComment Foreign Key Cascade
- Mutation Operationの`#[Transactional]`
- Unit／Integration／Real HTTP Consumer Test
- Operation／HTTP／Frontend Manifest Build／Generate／Fresh Check
- Community Board CI、README、Report、TODO、STATE同期

## Out of Scope

- SvelteKit Feed／Detail／Form Page、Form Action、Browser-facing View Model
- Generated Operation WrapperへのPost／Comment接続
- Digest Table、Generate／Show Digest、Deferred Worker Journey、Status／Wait UI
- Seed Data、Browser Automation、Screenshot、Final Visual Design、Taste Skill、Reicon
- Comment Edit／Delete、Post Restore、Soft Delete、Tombstone、Moderation、Admin UI
- User削除、Application Data Retention、Canonical Journal Scrubbing
- Scalar List／Map／Nested OperationValue Input、File Upload、Rich Text
- Framework `src/**`、Root Public API、Deptrac Ruleの変更
- `examples/quickstart/**`、Skeleton Source、Publication Workflow変更
- Documentation Website Content／Publication／Deploy

## Relevant Specifications and Decisions

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/104-structured-outcome-contract.md`
- `develop/decisions/105-community-board-deletion-policy.md`
- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/53-typed-self-handled-operation-invocation.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/spec/73-structured-outcome-contract.md`

## Files Allowed to Change

### Reference Application

- `examples/community-board/app/**`
- New `examples/community-board/migrations/Version*.php`
- `examples/community-board/tests/**`
- `examples/community-board/README.md`
- `examples/community-board/config/**` only when existing operation discovery／service composition requires it
- `examples/community-board/composer.json`／`composer.lock` only if an already-direct runtime dependency needs metadata correction; do not add a new library

Do not edit SvelteKit Product Source under `examples/community-board/frontend/src/**`. Ignored generated files may be produced for verification and must be cleaned before handoff.

### Consumer and CI

- New `tests/Consumer/community-board-post-comment.sh`
- `tests/Consumer/community-board-foundation.sh` and `tests/Consumer/community-board-identity.sh` only for unavoidable compatibility or shared cleanup corrections
- `.github/workflows/ci.yml`

### Documentation and Orchestration

- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-005-post-and-comment-operations.md`
- `develop/spec/71-full-stack-reference-application.md`／`develop/spec/72-phase-17-delivery-plan.md` only if implementation reveals a non-semantic clarification or contradiction

上記以外が必要なら実装を広げずReportのBlockerとして返す。特に`src/**`、既存Root PHP Test、`examples/quickstart/**`、Website Sourceを変更しない。

## Database Contract

Identity Migrationの次にApplication Migrationを追加する。

### `public.board_posts`

```text
id          UUID primary key
author_id   UUID not null -> board_users.id ON DELETE RESTRICT
title       VARCHAR(120) not null
body        TEXT not null
created_at  TIMESTAMPTZ not null
updated_at  TIMESTAMPTZ not null
```

- `title`は1..120文字、`body`は1..10000文字のDatabase Checkを持つ
- Feed用に`created_at DESC, id DESC`、Ownership Query用に`author_id`のIndexを持つ

### `public.board_comments`

```text
id          UUID primary key
post_id     UUID not null -> board_posts.id ON DELETE CASCADE
author_id   UUID not null -> board_users.id ON DELETE RESTRICT
body        TEXT not null
created_at  TIMESTAMPTZ not null
```

- `body`は1..2000文字のDatabase Checkを持つ
- Detail用に`post_id, created_at ASC, id ASC`、User参照用に`author_id`のIndexを持つ
- Post Hard Delete時に配下Commentを同一Transaction内でCascade Deleteする

Migration `down()`はComment、Postの順にDropする。User Foreign KeyにCascadeを付けず、User削除は未決のままにする。

IDはApplication-owned UUIDv7、時刻はApplication-owned UTC Clockから発行する。TestはSequence IDとFrozen Clockを注入できるようにし、ProductionでRuntime SampleやDatabase Defaultへ責務を隠さない。

## Operation Contract

Canonical Operation／HTTP Surfaceを次で固定する。

| Operation | Type ID | HTTP | Value | Outcome |
|---|---|---|---|---|
| `ListPosts` | `board.post.list` | `GET /posts` | Query `page`, `perPage` | `ListPostsOutcome` |
| `ShowPost` | `board.post.show` | `GET /posts/{postId}` | Path `postId` | `ShowPostOutcome` |
| `CreatePost` | `board.post.create` | `POST /posts` | Body `title`, `body` | `PostCreated` |
| `UpdatePost` | `board.post.update` | `PUT /posts/{postId}` | Path `postId`; Body `title`, `body` | `PostUpdated` |
| `DeletePost` | `board.post.delete` | `DELETE /posts/{postId}` | Path `postId` | `EmptyOutcome` |
| `AddComment` | `board.comment.add` | `POST /posts/{postId}/comments` | Path `postId`; Body `body` | `CommentAdded` |

- 全OperationはInlineで`#[Authorize(AuthenticatedUserPolicy::class)]`を持つ
- Value Sourceは`#[FromPath]`、`#[FromQuery]`、`#[FromBody]`で明示し、Implicit Mixed Sourceへ依存しない
- Create／Update／Delete／Add Commentの`handle()`へDefault Connectionの`#[Transactional]`を付ける
- AOP Proxy生成に必要なOperation Class制約を守り、Transactional Operationを`final`にしない
- HandlerはTyped Valueを直接受け取り、成功時のNative Outcomeだけを返す
- Business Rejectionは`OperationRejectedException`を使い、手書き`OperationResult`を返さない

## Validation and Canonical Input

- Post `title`: `#[NotBlank]`、`#[Length(min: 1, max: 120)]`
- Post `body`: `#[NotBlank]`、`#[Length(min: 1, max: 10000)]`
- Comment `body`: `#[NotBlank]`、`#[Length(min: 1, max: 2000)]`
- List `page`: `#[FromQuery]`、`#[Range(min: 1, max: 10000)]`、Default `1`
- List `perPage`: `#[FromQuery]`、`#[Range(min: 1, max: 50)]`、Default `20`
- `postId`はString Path ParameterとしてBindし、Application BoundaryでUUID構文を検証する。Malformed UUIDはDatabase Errorへ流さず`board.post.not_found`の404へ閉じる
- Unknown／Missing Body Field、Wrong Native Type、Unknown Body Fieldは既存400 Binding Surfaceを使う
- Validation FailureはOperation ID付き422とField単位Violationを返し、Rejected ContentやSQL DetailをError Codeへ埋め込まない
- Length値はAttributeとDatabase Constraintの正本を一致させる。SvelteKit UIへの重複定義はP17-006でGenerated Contractから扱う

## Structured Outcome Contract

少なくとも次のShapeをPublic PHP Typeとして実装する。Class名は固定し、NamespaceはFeature-first Layoutの範囲で決定的に配置する。

```text
ListPostsOutcome implements Outcome
  posts: #[ListOf(PostSummary::class)] list<PostSummary>
  page: int
  perPage: int
  total: int

PostSummary implements OutcomeData
  id: string
  authorId: string
  authorDisplayName: string
  title: string
  bodyPreview: string
  createdAt: string
  updatedAt: string
  commentCount: int

ShowPostOutcome implements Outcome
  post: PostDetail
  comments: #[ListOf(CommentDetail::class)] list<CommentDetail>

PostDetail implements OutcomeData
  id: string
  authorId: string
  authorDisplayName: string
  title: string
  body: string
  createdAt: string
  updatedAt: string

CommentDetail implements OutcomeData
  id: string
  postId: string
  authorId: string
  authorDisplayName: string
  body: string
  createdAt: string

PostCreated implements Outcome
  postId: string
  createdAt: string

PostUpdated implements Outcome
  postId: string
  updatedAt: string

CommentAdded implements Outcome
  commentId: string
  postId: string
  createdAt: string
```

- DTOは`final readonly OutcomeData`、Rootは`final readonly Outcome`とする
- `bodyPreview`はPostgreSQLのCharacter-aware `left(body, 240)`等で最大240文字にし、UTF-8 Byte途中で切断しない
- Feedは`created_at DESC, id DESC`、Commentは`created_at ASC, id ASC`でTie-breakを含む決定的順序にする
- Page外は空Listを返し、`total`はFilter前の全公開Post件数を返す
- TimestampはUTCの明示的で安定したISO 8601 Stringへ投影する
- JSON String、`mixed`、自由Map、Frontend Generate Opt-outを使わない

## Authorization and Resource Concealment

- Anonymous／Invalid Sessionは既存Authentication／Authorization境界で401にする
- Actor Type `user`だけをAuthenticatedとして扱う
- List／Show／Create／Add CommentはAuthenticated Userへ許可する
- Update／DeleteはCurrent ActorがPost Authorのときだけ成功する
- Update／DeleteはTransaction内でPost RowをLockし、存在確認とOwnership確認を同じSnapshotで行う
- Unknown Post、Malformed UUID、Non-owner Postは全て`OperationRejectedException::notFound('board.post.not_found')`へ閉じ、Status、Code、JSON Shapeを一致させる
- `AuthorizationDecision::forbid()`による403でOwnershipの存在を漏らさない。`#[Authorize]`はAuthentication Gateを担当し、Application-owned Owner CheckをMutation Transaction内で行う
- Add CommentはTransaction内で対象PostをLock／確認する。DeleteとのRaceでOrphan CommentやRaw Foreign Key Errorを返さない
- Show unknownとAdd Comment unknownも同じ`board.post.not_found`を使う
- RepositoryはCurrent ActorをGlobalから読まず、Operationから明示User IDを受け取る

## Transaction and Deletion Contract

- CreateはPost Insert、UpdateはOwner Check＋Update、DeleteはOwner Check＋Post Delete＋Comment Cascade、Add CommentはPost確認＋Comment InsertをOperation Transactionへ含める
- Transactional Operationの成功Terminal Journal／OutcomeはApplication Default Connection上の業務更新と原子的にCommitする既存Framework Contractを使う
- Validation／Authentication／Authorization Rejectionでは業務Rowを変更しない
- Handler Failure／Constraint Failureでは業務更新をRollbackし、Raw SQL／Connection値をHTTPへ出さない
- Delete成功は204 `EmptyOutcome`。削除後のShow／Update／Delete／Add Commentは404になる
- Delete後のFeedと後続Digest QueryはPost／Commentを対象外にする
- Hard Deleteは`board_posts`／`board_comments`だけを対象とする。Canonical Journalは別Retention Contractに従い、Post削除をJournal Scrubbingとして扱わない

## Repository and DI Contract

- Doctrine DBALとDefault `Doctrine\DBAL\Connection` Constructor Injectionを使う
- `BoardRepository` Interfaceと`DoctrineBoardRepository`実装をApplication Codeとして持つ
- ORM、Active Record、Framework Repository Base Class、Generic CRUD Serviceを導入しない
- ApplicationServiceProviderでRepository、Clock、ID GeneratorのInterface Bindingを明示する
- SQL ResultをOperationへ直接Arrayで返さず、Application-owned Read Modelへ厳密に変換する
- UUID／Timestamp／CountのDBAL Driver表現を検証し、Silent `mixed` CastをOperation Outcomeまで持ち込まない
- QueryはParameter Bindingを使い、IdentifierやBodyをSQLへ連結しない

## Testing and CI Contract

Example PHPUnitで少なくとも次を恒久化する。

- Validation Attribute／Limit／Default Pagination Contract
- Structured OutcomeのNested DTO／Typed List Shape
- Repository Feed／Detail Ordering、Pagination、Count、Empty Page
- Create／Update／Delete／CommentとUUID／UTC Timestamp
- Owner成功、Non-owner／Unknown／Malformed UUIDの同一404
- Delete CascadeとDelete／Add Comment競合の整合性
- Transaction Rollback時に部分更新が残らないこと
- Service Provider Build、Operation／HTTP／Frontend Manifestの6 Operation Type／Route／Schema

新しい`tests/Consumer/community-board-post-comment.sh`はReal PostgreSQL／HTTPで次を完走する。

1. Migration、Build Compile、Frontend Generate／Check
2. AliceとBobをApplication-owned Authentication Routeで登録
3. Anonymous Listが401
4. AliceのInvalid CreateがOperation ID付き422になりField Errorを返す
5. AliceがPostを作成し、Feed／DetailのStructured JSONで参照する
6. BobのUpdate／DeleteとUnknown Postが同じSafe 404になる
7. BobがCommentを追加し、DetailのTyped Comment Listへ現れる
8. AliceがPostをUpdateし、Feed／Detailへ反映される
9. AliceがDeleteして204を受け、Post／Comment RowがCascadeで消える
10. Delete後のShow／Add Commentが404、Feedが空になる
11. Generated Frontend Treeに6 OperationとReadonly DTO／ReadonlyArrayが生成され、Fresh Checkが通る
12. SQL、Absolute Path、Database Credential、Session Token、Password HashをResponse／Generated Tree／Logへ反射しない

Foundation／Identity Consumer E2Eも回帰させない。CIのCommunity Board JobへPost／Comment Testを追加し、Migration Tracking GuardとCleanup Guardを新Migration／Artifactへ同期する。

## Acceptance Criteria

- [ ] Post／Comment MigrationがForeign Key、Index、Length、Cascade Contractを持つ
- [ ] Application-owned Repository、Clock、UUIDv7 GeneratorがDIされる
- [ ] 6つのInline Operation Type／Route／Value／OutcomeがCanonical Contractどおりである
- [ ] Post／Comment Validationが422 Field Errorを返す
- [ ] Feed／DetailがStructured Outcomeと決定的Pagination／Orderingを返す
- [ ] Authenticated UserだけがPost／Comment Operationを利用できる
- [ ] Update／DeleteがOwnerだけに成功し、Non-owner／Unknown／Malformedを同じ404へ閉じる
- [ ] Mutationが`#[Transactional]`で業務更新と成功LifecycleをCommitする
- [ ] Post Hard DeleteがCommentをCascadeし、復元／Soft Deleteを追加しない
- [ ] Add CommentとDeleteのRaceでOrphan／Raw Constraint Errorを返さない
- [ ] Build Compile、Frontend Generate／Checkが6 OperationのStructured Contractを扱う
- [ ] Example PHPUnitとReal HTTP Post／Comment E2Eが成功する
- [ ] Foundation／Identity Consumer E2Eが回帰しない
- [ ] CI、README、Migration Tracking、Artifact Cleanupが同期する
- [ ] Framework `src/**`、Quickstart／Skeleton、SvelteKit Product Pageを変更しない
- [ ] Required Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

実際のTest Class／Script名を補足してよいが、Example、Real HTTP、Root Full Gateを省略しない。

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
bash tests/Consumer/community-board-post-comment.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
git diff --exit-code -- src examples/quickstart
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

Generated／Dependency／Build／Runtime ArtifactはTask完了前にCleanupする。`vendor`／`node_modules`はIgnoredであっても、Generated Source、Build Output、`.env`、Log、PHPUnit CacheはHandoff時に残さない。

## Expected Report

`develop/orchestration/reports/P17-005-post-and-comment-operations.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Database Schema／Delete Cascade
- Operation／Route／Validation Matrix
- Structured Outcome／Frontend Manifest Evidence
- Authorization／Resource Concealment Evidence
- Transaction／Race／Rollback Evidence
- Real HTTP Consumer Journey
- Sensitive／Artifact／Scope Guards
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
