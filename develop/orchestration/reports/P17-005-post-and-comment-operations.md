# P17-005 Post and Comment Operations Report

## Summary

Community BoardへApplication-ownedなPost／Comment Domainと6つの認証必須Inline Operationを実装した。Post／CommentのMigration、DomainService、Repository Port、Doctrine DBAL Adapter、UTC Clock、UUIDv7 Generator、Structured Outcome、Validation、Owner-only Mutation、Hard Delete Cascade、Real HTTP Journeyを追加した。

Architecture変更に従い、業務ロジックは`app/Domain/Board/BoardService`へ集約した。OperationはValue／ExecutionContextから入力とActor IDを受け、DomainServiceを呼び、Domain ResultをOutcomeへ写像し、`PostNotFound`だけをSafe Public 404へ変換する。Domain LayerはBlackOps、Doctrine DBAL、Symfony UIDへ依存しない。

## Changed Files

- `examples/community-board/migrations/Version20260720214000.php`
- `examples/community-board/app/Domain/Board/**`
- `examples/community-board/app/Infrastructure/{Persistence,Clock,Identifier}/**`
- `examples/community-board/app/Feature/Post/**`
- `examples/community-board/app/Feature/Comment/**`
- `examples/community-board/app/Feature/BoardTime.php`
- `examples/community-board/app/Security/AuthenticatedUser.php`
- `examples/community-board/app/ApplicationServiceProvider.php`
- `examples/community-board/tests/Board/**`
- `examples/community-board/tests/Support/{FrozenBoardClock,SequenceBoardIdGenerator,InMemoryBoardRepository}.php`
- `tests/Consumer/community-board-post-comment.sh`
- `.github/workflows/ci.yml`
- `examples/community-board/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P17-005-post-and-comment-operations.md`

## Decisions and Assumptions

- `app/Domain/Board`はBoardService、Domain Result／Read Model、Domain Exception、Repository／Clock／Identifier Portを所有する。
- `app/Infrastructure`はDoctrine DBAL Repository、System Clock、Symfony UUIDv7 Generatorを所有する。
- Operation LayerだけがBlackOps Attribute、ExecutionContext、Outcome、Public Rejectionを扱う。
- Domain Architecture Testは`app/Domain/Board`を再帰走査し、BlackOps、Doctrine、Symfony、Infrastructure、Feature、Http、Security、PHP Attributeへの依存を拒否する。
- Domain Timestampは`DateTimeImmutable`で保持し、HTTP境界でUTCの`Y-m-d\TH:i:s.u\Z`へ写像する。Database Timestampへの変換はPersistence Adapterが所有する。
- Ownership FailureはDomainで`PostNotFound`へ統一し、Operation境界で`board.post.not_found`へ変換する。
- SvelteKit Product Source、最終Visual Design、ReiconはTask Scope外である。User指定のReiconは後続Frontend Design Taskへ引き継ぐ。

## Database Schema／Delete Cascade

- `board_posts`: UUID Primary Key、User Foreign Key `ON DELETE RESTRICT`、title 1..120、body 1..10000、UTC Timestamp、Feed／Author Indexを追加した。
- `board_comments`: UUID Primary Key、Post Foreign Key `ON DELETE CASCADE`、User Foreign Key `ON DELETE RESTRICT`、body 1..2000、Detail／Author Indexを追加した。
- `down()`はComment Table、Post Tableの順で削除する。
- PostgreSQL Integration TestでPost削除後にComment Rowが0になることを確認した。

## Operation／Route／Validation Matrix

| Type | HTTP | Validation | Outcome |
|---|---|---|---|
| `board.post.list` | `GET /posts` | page 1..10000、perPage 1..50 | `ListPostsOutcome` |
| `board.post.show` | `GET /posts/{postId}` | Domain UUID boundary | `ShowPostOutcome` |
| `board.post.create` | `POST /posts` | title 1..120、body 1..10000 | `PostCreated` |
| `board.post.update` | `PUT /posts/{postId}` | UUID、title 1..120、body 1..10000 | `PostUpdated` |
| `board.post.delete` | `DELETE /posts/{postId}` | Domain UUID boundary | `EmptyOutcome`／204 |
| `board.comment.add` | `POST /posts/{postId}/comments` | UUID、body 1..2000 | `CommentAdded` |

全Operationへ`AuthenticatedUserPolicy`を指定した。Create／Update／Delete／Add Commentの`handle()`は非final Operation上の`#[Transactional]`である。

## Structured Outcome／Frontend Manifest Evidence

- Feedは`created_at DESC, id DESC`、Commentは`created_at ASC, id ASC`でTie-breakを固定した。
- `PostSummary`、`PostDetail`、`CommentDetail`をReadonly `OutcomeData`としてApplication境界に置き、Domain Read Modelから明示変換する。
- Feed PreviewはPostgreSQL `left(body, 240)`でUTF-8 Character単位に制限する。
- Build Artifact Testは6 Operation、6 Route、Inline Strategy、Mutation Transaction Connection、Nested DTO、Typed Listを確認する。
- `frontend:generate`は11 filesを生成し、`frontend:check`はFreshに成功した。Generated Moduleは`ReadonlyArray<PostSummary>`／`ReadonlyArray<CommentDetail>`を持つ。

## Authorization／Resource Concealment Evidence

- Authentication Gateは既存Policy、Actor Type／ID取得は`AuthenticatedUser`が担当する。
- BoardServiceがUpdate／DeleteのRow Lock、存在確認、Ownership判定、Repository操作順序を所有する。
- Show／Update／Delete／Add CommentのMalformed／Unknown、Update／DeleteのNon-ownerはDomain `PostNotFound`を経て同じ404 Codeへ変換される。
- DomainService TestとReal HTTP E2EでNon-owner／Unknown／Malformedが同じSafe Surfaceとなり、業務Rowを変更しないことを確認した。

## Transaction／Race／Rollback Evidence

- CreateはID／時刻発行とInsert、UpdateはLock／Owner Check／時刻発行／Update、DeleteはLock／Owner Check／Delete、Add CommentはPost Lock／ID・時刻発行／Insertの順をBoardServiceが所有する。
- Doctrine Repository Testで同じConnection上のUpdate Rollback後に元のPost／Commentが残ることを確認した。
- 2 Connectionと有限`lock_timeout`を使い、Delete中のAdd Comment経路がPost Lockを待ち、Orphan Commentを生成できないことを確認した。
- Post Foreign Key CascadeでDelete成功Transaction内にCommentも削除される。

## Real HTTP Consumer Journey

`tests/Consumer/community-board-post-comment.sh`はReal PostgreSQL／FrankenPHPで次を完走した。

1. Setup、Migration 4、Build、Frontend Generate／Check、Example PHPUnit
2. Alice／Bob登録とBearer Session取得
3. Anonymous 401、Invalid Create 422 Field Violation
4. Alice Create、Feed／Detail Structured JSON
5. Bob Update／DeleteとUnknown／Malformedの同一404
6. Bob Add Comment、Alice Update、Detail反映
7. Alice Delete 204とPost／Comment Row 0
8. Delete後Show／Add Comment 404とEmpty Feed
9. Response／Generated Tree／Application LogのSQL、Absolute Path、DB Credential Guard
10. 全Container Logを含むSession Token、Password、Password Hash Guard

Foundation JourneyとIdentity Journeyも同じ最終実装で再成功した。

## Sensitive／Artifact／Scope Guards

- Session Token、Password、Password HashはHTTP Response、Generated Tree、Application Log、全Container Logを対象に検査し、Marker一致時に明示`exit 1`するGuardが成功した。
- SQL、Absolute Path、Database ConfigurationはHTTP Response、Generated Tree、Application `var/log`、HTTP Application Container Logだけを検査する。意図的なRace Testが出すPostgreSQL管理LogのSQL StatementはPublic Application Surfaceへ混在させない。
- Guard Helper自身は一時Fixtureへ既知Markerを置き、実際にFailureを返すことをE2E内で確認する。
- Parameter Bindingのみを使い、Rejected Value、SQL、Connection情報をPublic Error Codeへ含めない。
- Framework `src/**`、`examples/quickstart/**`、SvelteKit Product Source `frontend/src/**`は変更していない。
- `.env`、Vendor、Node Modules、Generated Source、Build、Log、PHPUnit CacheはHandoff前に削除する。

## Commands and Results

```text
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
  success

docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir examples/community-board/frontend install --frozen-lockfile
  success

php bin/setup
php blackops database:migrate
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
vendor/bin/phpunit
  migrations: 4
  generated: 11 files; fresh
  PHPUnit: OK (33 tests, 388 assertions)

pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
  Svelte: 0 errors, 0 warnings
  Vitest: 4 files, 16 tests passed
  adapter-node build: success

bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
  all journeys passed

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
  Composer: valid
  Mago: formatted; no lint/analyze issues
  PHPUnit: OK (1471 tests, 5810 assertions)
  Deptrac: 0 violations, 0 skipped, 0 uncovered, 0 warnings, 0 errors
```

最初の再検証では、並行していたDefault Compose Migrationによる一時`schema_migrations`競合と、Consumer Cleanup後のGenerated Tree不在を検出した。Default Volumeを再作成し、Generateを前置した直列Commandで再実行して成功した。Production CodeのFailureではない。

Orchestrator Review後、`set -e`下の反転`rg`へ依存していたSensitive Guardを明示的なMatch／Search Error Failureへ修正した。PostgreSQL管理LogとApplication Surfaceを分離し、Known Marker Fixture、再帰Domain Architecture Guardを追加した。修正後のPost／Comment E2E、bash syntax、Example PHPUnit 33 tests／388 assertionsが再成功した。

Orchestrator独立検証でも、修正版Post／Comment Real HTTP E2E、Example PHPUnit 33 tests／388 assertions、Mago format／lint／analyze、Root PHPUnit 1471 tests／5810 assertions、Deptrac 0違反が成功した。独立E2E用Vendorを除去した後、Generated／Runtime／Dependency Artifactが残らないことを再確認した。

## Acceptance Criteria

- [x] Migration、Foreign Key、Index、Length、Cascade
- [x] Domain／Infrastructure分離とBoardServiceへのDomain Logic集約
- [x] Repository、Clock、UUIDv7 Generator Port／Adapter DI
- [x] 6 Inline Operation／Route／Value／Outcome
- [x] Validation 422とStructured Feed／Detail
- [x] Authentication、Owner-only Mutation、Safe 404 Concealment
- [x] Mutation Transaction、Rollback、Delete／Comment Race
- [x] Hard Delete Cascade
- [x] Build／Frontend Generate／Fresh Check
- [x] Example PHPUnit、3 Real HTTP E2E、SvelteKit回帰
- [x] CI、README、Migration Tracking、Cleanup同期
- [x] Framework／Quickstart／SvelteKit Product Source Scope保持
- [x] Required Root Quality Gate
- [x] Worker Commitなし

## Remaining Issues

なし。Browser-facing Post／Comment Page、Generated Operation Wrapper接続、Final Visual Design、Reiconは後続TaskのScopeである。

## Suggested Next Action

P17-006 Server-only BFF／Post JourneyのTask Packetを確定し、Generated Operation ObjectをSvelteKit Product Journeyへ接続する。Final Frontend DesignではUser指定どおりReiconを使用する。
