# P19-007: Community Board Reliability Journey

Status: In Progress

## Goal

Community BoardのDigest FormへServer-generated Idempotency Keyを接続し、同じKeyの再送を同じOperation IDと一つのDigestへ収束させる。Comment作成TransactionからPost所有者向けNotificationをTransactional Outboxへ登録し、Relay停止中、Relay再開、Worker配送、同じChild Operation IDの再配送、所有者だけの一覧表示を実PostgreSQLとBrowserで完走する。

## In Scope

- Digest FormのServer-generated Idempotency Key、同じKeyの再送、別Keyの別Operation
- Validation／Unavailable後のKey維持とFresh FormでのKey更新
- `AddComment` Transactionから`NotifyPostOwner`をTransactional Outboxへ登録
- Application-owned Notification Domain、Repository、Operation、Migration
- Recipient-owned Notification一覧を返すServer-only BFFとSvelteKit Page
- Relay停止前後、Child Operation固定Identity、Worker実行、重複配送のConsumer／Browser検証
- Application Migration追加に伴うFresh Install期待値同期

## Out of Scope

- `src/**` Framework Production Code
- P19-006 Observer Replay実装
- Email、Push、WebSocket、External Notification Delivery
- Notification既読状態、Cursor Pagination、一般的なInbox機能
- Comment本文Snapshot、Credential、Raw Idempotency KeyのNotification保存
- Community Board全体のUI Redesign
- Permanent Relay Service追加
- External Publication／Deploy

Framework Capability不足が判明した場合は`src/**`へ実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Relevant Specifications

- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/orchestration/reports/P19-003-http-php-duplicate-lifecycle-retention.md`
- `develop/orchestration/reports/P19-004-transactional-outbox-persistence.md`
- `develop/orchestration/reports/P19-005-relay-runtime-and-blackops-cli.md`

## Files Allowed to Change

- `examples/community-board/app/ApplicationServiceProvider.php`
- `examples/community-board/app/Domain/Board/AddedComment.php`
- `examples/community-board/app/Domain/Board/BoardService.php`
- `examples/community-board/app/Domain/Notification/**`
- `examples/community-board/app/Feature/Comment/AddComment/AddComment.php`
- `examples/community-board/app/Feature/Notification/**`
- `examples/community-board/app/Infrastructure/Persistence/DoctrineNotificationRepository.php`
- `examples/community-board/migrations/**`
- `examples/community-board/tests/Board/**`
- `examples/community-board/tests/Support/InMemoryBoardRepository.php`
- `examples/community-board/tests/Support/InMemoryNotificationRepository.php`
- `examples/community-board/frontend/src/lib/server/blackops/digest.server.ts`
- `examples/community-board/frontend/src/lib/server/blackops/digest.server.test.ts`
- `examples/community-board/frontend/src/lib/server/blackops/notification.server.ts`
- `examples/community-board/frontend/src/lib/server/blackops/notification.server.test.ts`
- `examples/community-board/frontend/src/routes/digests/+page.server.ts`
- `examples/community-board/frontend/src/routes/digests/+page.svelte`
- `examples/community-board/frontend/src/routes/notifications/**`
- `examples/community-board/frontend/src/routes/+layout.svelte`
- `examples/community-board/frontend/e2e/community-board.spec.ts`
- `tests/Consumer/community-board-digest.sh`
- `tests/Consumer/community-board-browser.sh`
- `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/community-board-product-journey.sh`
- `CHANGELOG.md`
- `docs/guide/community-board.md`
- `develop/spec/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/**`
- `develop/orchestration/reports/**`
- `develop/STATE.md`

Generated Frontend Clientは既存Generatorで再生成する。生成Sourceの手編集は行わない。許可外Fileが必要な場合は変更せずBlockerを返す。

## Contract

### Digest Idempotency

- GET `/digests`はCryptographically SecureでPrintableなServer-generated Idempotency Keyを一つ生成し、Hidden Form Fieldとして返す
- KeyはServer-only BFFからGenerated Clientの`fetch(..., { idempotencyKey })`へ渡し、Browser Bundle、Log、Journal、Database、ErrorへRaw値を保存しない
- 最初の有効SubmitはOperation `O1`を受け付ける。同じActor／Week／Keyを順次再送すると同じ`O1`を返し、Worker完了後のDigestは一件だけとする
- 同じActor／WeekでもFresh Formの別Keyは別Operation `O2`と別Digestを作る。既存の「User／Weekで一意」制約は追加しない
- 同じKeyでWeekを変えた場合は安全なConflictとし、元Operation IDやFingerprintを漏らさない
- Validation Error、Transport Unavailable、結果不明の失敗では同じKeyをFormへ返す。成功後またはFresh GETだけが新しいKeyを発行する
- Authentication／AuthorizationはReplay Lookupより前に実行し、失効Sessionや別Actorは元Operationの存在を発見できない
- 同時二重Clickで`idempotency_in_progress`が返る場合を許容するが、最初の受付完了後の同じKey再送は同じOperationへ収束する

### Comment Notification Outbox

- Post所有者Aliceに対し、別User BobがCommentを追加した場合だけ`NotifyPostOwner`をTransactional Outboxへ一件登録する
- Self-commentはNotificationを作らず、Outbox登録もしない
- `AddComment`はComment保存とOutbox登録を同じFramework管理Transaction／同じNamed Connection Instanceで行う
- Comment保存失敗時はOutbox Rowを作らない。Outbox登録失敗時はCommentもRollbackする
- Outbox Child Operationは元Comment実行Contextを継承する。NotificationのRecipient AuthorizationはChildのCurrent ActorではなくValue内のRecipient IDを正本とする
- Relay停止中はCommentがCommitted、OutboxがPending、Notificationは0件である
- One-shot `outbox:relay:run`後、`worker:run`で固定Child Operation IDを処理し、Alice所有のNotificationを一件作る
- Relay／Child Operationを同じIdentityで再配送してもNotificationは一件へ収束する

### Notification Storage and Query

- NotificationはApplication-owned Tableに次だけを保存する
  - Notification ID
  - Recipient User ID
  - Source Post ID
  - Source Comment ID
  - Child Delivery Operation ID
  - Created At
- Child Delivery Operation IDへUnique Constraintを置き、Application側でも同じChild配送を冪等化する
- `recipient_user_id, created_at, notification_id`のLatest-first Query用Indexを持つ
- Post／CommentへForeign Keyを張らない。Source削除後も通知配送を完了でき、UIは対象欠落を安全に扱う
- Comment本文、Post本文、Author名Snapshot、Token、Credential、Raw Idempotency Keyを保存しない
- 一覧は既定上限50件のLatest-firstとし、本TaskではCursor／Read Stateを追加しない
- Safe TextはComment本文を含まない固定文とする

### Notification Operations and BFF

- `NotifyPostOwner`はDeferred Self-handled Transactional Operationとし、Child Operation IDをRepositoryのDelivery Identityとして使う
- `ListNotifications`はAuthenticated RecipientだけのNotificationを返し、ValueのRequested User IDとAuthorization Actorを一致させる
- BobはAliceのNotificationを列挙できず、Missing／Unauthorizedを区別する情報を返さない
- SvelteKit BFFは既存Server-only Client FactoryとSession Token境界を維持し、BrowserへToken／Base URL／Generated Client Credentialを出さない
- `/notifications`はAliceの安全な一覧だけを表示し、Navigationから到達できる

### Migration and Seed

- 既存Migrationを変更せずAdditive Application Migrationを追加する
- DownはNotification Table／Indexだけを除去する
- Fresh Installの総Migration件数を10から11へ同期する
- Seedは引き続き`3 users / 3 posts / 4 comments`で、Notificationは0件とする
- 同じSeedを再実行しても件数とIdentityが安定する

### Browser Journey

- AliceとBobの独立Browser Sessionを使う
- AliceがPostを作成し、BobがCommentする
- Relay／Worker前はAliceのNotificationが0件である
- Test ControllerがOne-shot RelayとWorkerを実行後、Aliceは安全なNotificationを一件確認できる
- BobはAliceのNotificationを見られない
- 同じChild Identityの再配送後もAliceのNotificationは一件である
- Digest Formの同じHidden Keyを順次二回Submitし、同じOperation IDと一つのDigestへ収束する
- Fresh GETの新Keyでは別Operation IDと二つ目のDigestを確認する
- Existing Accessibility、Responsive、Credential、Generated Artifact、Browser Bundle Guardを維持する

## Required Failure Matrix

| Case | Required Result |
| --- | --- |
| Digest first submit | `O1`を受付 |
| Same actor/week/key replay | 同じ`O1`、Digest一件 |
| Same key/different week | Safe conflict、元Identity非開示 |
| Fresh key/same actor/week | 別`O2`、別Digest |
| Validation／Unavailable | 同じForm Keyを維持 |
| Revoked／different actor | Replay存在を開示しない |
| Comment with relay stopped | Comment committed、Outbox pending、Notification 0 |
| Comment persistence failure | Outbox 0 |
| Outbox registration failure | Comment rollback |
| Self-comment | Outbox／Notification 0 |
| Relay then worker | Alice Notification 1 |
| Duplicate child delivery | Notification 1 |
| Bob lists Alice notification | Unavailable／empty safe result |
| Source deleted before delivery | Notification delivery succeeds without body snapshot |
| Fresh migration/seed | 11 migrations、3/3/4、Notification 0 |

## Acceptance Criteria

- [ ] Digest same-key replayが同じOperation ID／一件のDigestへ収束する
- [ ] Digest fresh-key submitが別Operation ID／別Digestになる
- [ ] Raw Idempotency KeyがStorage／Journal／Log／Browser Bundleへ残らない
- [ ] CommentとOutbox登録が同じTransactionへ参加し、両方向のRollbackが検証される
- [ ] Relay停止／再開／Worker／Duplicate Child Deliveryが実PostgreSQLで検証される
- [ ] Notification StoreがChild Operation IDで冪等化し、RecipientだけへLatest-firstで返す
- [ ] Self-comment、Source削除、Unauthorized Recipientが安全に処理される
- [ ] Additive Migration up/down、Fresh Install 11件、Seed 3/3/4/0が成功する
- [ ] Server-only BFF、Frontend Unit、Browser二Session Journeyが成功する
- [ ] Existing Framework Full PHPUnit、Mago、Deptrac、Frontend、Consumer Gateが回帰しない
- [ ] `src/**`、External Delivery、External Publication／Deployへ差分がない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-browser.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/community-board/app examples/community-board/tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-007-community-board-reliability-journey.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Digest Idempotency Matrix
- Comment／Outbox Transaction Matrix
- Relay／Worker／Duplicate Delivery Matrix
- Notification Authorization／Sensitive Matrix
- Migration／Seed Evidence
- Frontend／Browser Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
