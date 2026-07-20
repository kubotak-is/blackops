# D105: Community Board Deletion Policy

Status: Decided

## Context

P17-005では、Post／Comment Migration、Repository、List／Show／Create／Update／Delete Post、Add Comment Operationを実装する。確定済み仕様はPost OwnerだけがUpdate／Deleteでき、Unknown ResourceとUnauthorized Resourceを同じ404へ閉じることを要求している。

一方、`develop/spec/71-full-stack-reference-application.md`はHard Delete／Soft DeleteとRetentionを未決のままDecisionへ返している。Delete方式は次へ影響する。

- `board_posts`／`board_comments`のSchemaとForeign Key
- Feed／Detail／Digestが削除済みDataを集計するか
- Delete後の404と再作成・復元の有無
- User Contentの保持期間と将来のRetention
- Repository Queryへ常時Delete Filterが必要か

Phase 17はReference Applicationであり、Moderation、Admin UI、User削除、法的保持、Content RecoveryはInitial Scopeに含めていない。Framework JournalはOperation Lifecycleを記録するが、Application Contentの復元Storeではない。

## Decision Drivers

- 初見の利用者が追いやすいApplication-owned DBAL Repositoryを示す
- Delete後のFeed／Detail／Digest挙動を曖昧にしない
- PostとCommentの参照整合性を一つのTransactionで保証する
- 未使用のRecovery／Moderation／Retention機構を先回りして作らない
- 将来Soft Deleteが必要になった場合に、明示的なSchema MigrationとDecisionで追加できる

## Question 1: Post削除とCommentの扱い

### Options

- A: PostをHard Deleteし、配下CommentもForeign KeyのCascadeで同じTransaction内に削除する。削除後はFeed／Detail／Digestの対象外とし、復元機能は持たない
- B: PostとCommentへ`deleted_at`を追加してSoft Deleteする。通常QueryとDigestから除外するが、Databaseには保持する
- C: Post本文だけをTombstoneへ置き換え、Commentは残して削除済みThreadとして表示する

### Recommendation

Aを推奨する。

Phase 17のInitial ScopeにはRecovery、Moderation、Retention Policy、削除済みThread UIがない。Bは全Repository QueryへFilterを要求し、保持期限を決めないままUser Contentを残す。CはProduct ScopeとAuthorization／表示仕様を広げる。AならOwner Authorization、`#[Transactional]`、Foreign Key整合性を最小で明確に示せる。

将来ModerationやRecoveryが必要になった時点で、Soft DeleteまたはTombstoneを新しいDecision／Migrationとして追加できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

PostはApplication TableからHard Deleteし、配下CommentもForeign KeyのCascadeで同じTransaction内に削除する。削除後はFeed／Detail／Digestの対象外とし、復元機能はInitial Scopeに持たない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- `board_comments.post_id`は`board_posts.id`へのForeign Keyとし、Post削除時にCascade Deleteする
- Delete OperationはOwnerだけを許可し、成功時は`EmptyOutcome`を返す
- Unknown PostとNon-owner Postは同じSafe 404へ閉じる
- 削除後のPost Detailは404、FeedとDigestはPost／Commentを集計しない
- Comment単体のDelete／Edit、Post Recovery、Deleted Content Admin UIはInitial Scopeに追加しない
- Hard Deleteの対象はApplication-owned `board_posts`／`board_comments`である。Canonical Journalは別のFramework Retention Contractに従い、OperationValueを保持し得るため、Post削除をJournal Scrubbingとして扱わない
- Framework Journal／DiagnosticsはDelete OperationのLifecycleを保持するが、Applicationは削除したPost本文やComment本文の復元機能を提供しない
- User削除とApplication Data Retentionは別Decisionまで未決のままとする

[/CONSEQUENCES]

## Traceability

- Decision: [D103 Full-stack Reference Application](103-full-stack-reference-application.md)
- Application Contract: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
- Delivery Plan: [Phase 17 Delivery Plan](../spec/72-phase-17-delivery-plan.md)
- Current Task: P17-005 Post and Comment Operations
