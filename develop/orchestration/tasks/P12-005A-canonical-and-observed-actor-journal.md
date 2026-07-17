# P12-005A: Canonical and Observed Actor Journal

Status: Accepted

## Goal

D095／Spec 06／Phase 12 Delivery Planに従い、ExecutionContextのActor ID／TypeだけをCanonical Journalへ保存し、Observed ProjectionではActor IDを既定Maskする。PostgreSQL Canonical Codec、Observed JSONL、旧Record Decodeを一貫させる。

## In Scope

- Public `JournalOperation`へのOptional ActorContext追加
- Journal Record Factory／BuilderからContext ActorのCanonical記録
- PostgreSQL Journal Record CodecのActor Encode／Decode
- Actor Fieldがない既存Canonical RecordのDecode互換
- Actor Objectの厳格なField／型検証
- Observed Journal OperationのActor ID既定Mask
- Actor TypeのObserved維持
- Observed JSONLへのMask済みActor出力
- CanonicalとObservedで同じOperation ID／Lifecycle Metadataを維持
- Core API／Security／Internal Journal文書の最小同期

## Out of Scope

- Deferred WorkerでのSystem execution Actor置換
- Deferred Worker再認可
- Retry／Backoff／Dead LetterのActor Context
- Credential、Role、Permission、Claimの保存
- Actor IDのHash／Omit Config
- Canonical Journalの暗号化／Access Control／Retention変更
- Documentation Website全体とQuickstart Example

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/decisions/031-sensitive-projection.md`
- `develop/decisions/056-journal-record-public-api.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

- `src/Journal/JournalOperation.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- `tests/Journal/JournalRecordTest.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Internal/Projection/ObservedJournalRecordProjectorTest.php`
- `tests/Logging/JsonlJournalObserverTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/core-api.md`
- `docs/guide/security.md`
- `docs/internal/journal-record.md`
- `docs/internal/sensitive-projection.md`
- `docs/internal/postgresql-journal-store.md`
- `develop/orchestration/reports/P12-005A-canonical-and-observed-actor-journal.md`
- `develop/STATE.md`

## Implementation Constraints

- `JournalOperation` Constructor末尾へOptional `?ActorContext`を追加し、既存6引数Call Siteを維持する
- Canonical Journalへ保存するActor Dataはorigin／authorization／execution各Actorの`id`と`type`だけとする
- ActorContextがないRecordはCanonical Codecで`actors: null`とし、旧PayloadのField欠落もActorContextなしとしてDecodeする
- Canonical Codecは`actors` Objectにorigin／authorization／execution以外のFieldを許可しない
- Actor Objectは`id`／`type`だけを許可し、空文字、不正型、Password／Token／Session／Credential／Role／Permission／Claim等の余分なFieldを拒否する
- Canonical Encode／DecodeはActor ID／Typeを変更しない。Canonical Journalは監査正本であり、MaskはObserved Projectionだけで行う
- `ObservedJournalRecordProjector`はCanonical `JournalOperation` Instanceをそのまま再利用せず、ActorContextがある場合は各Actor IDを文字列`[masked]`へ置き換えたOperationを構築する
- Observed ProjectionはActor Typeを維持し、origin／authorizationのnullを維持する
- JSONL EncoderはOperation内へ`actors`を出力し、Observed RecordにRaw Actor IDが出ないことをTestする
- Projection後のData Sensitive規則とRejection安全化を変更しない
- Canonical／ObservedのOperation ID、Type、Strategy、Correlation／Causation IDは同一値を維持する
- Record Schema Versionは後方互換なOptional Field追加として現在値を維持する
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] ActorContext付きEnvelopeから全Canonical Lifecycle RecordへActor ID／Typeが入る
- [ ] ActorなしRecordと既存6引数JournalOperation構築が従来どおり動く
- [ ] PostgreSQL Canonical JournalがActorContextをRound-tripする
- [ ] Actor Field欠落の旧Canonical PayloadをDecodeできる
- [ ] 余分なField、不正型、空Actor値をSafeにDecode拒否する
- [ ] Observed ProjectionがActor IDをすべて`[masked]`にし、Actor Typeとnull関係を維持する
- [ ] Observed ProjectionにRaw Actor ID、Credential、Permission Snapshotが含まれない
- [ ] JSONL Operation Actor出力がMask済みである
- [ ] Actor以外のCanonical／Observed Journal既存契約を維持する
- [ ] Guide／Internal DocsがCanonical正本とObserved Maskの責任分界を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src tests
docker compose run --rm app vendor/bin/phpunit tests/Journal/JournalRecordTest.php tests/Internal/Journal/JournalRecordFactoryTest.php tests/Internal/Projection/ObservedJournalRecordProjectorTest.php tests/Logging/JsonlJournalObserverTest.php tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php tests/Integration/MvpSampleEndToEndTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-005A-canonical-and-observed-actor-journal.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
