# P20-009D: Journal Parameter Tables

Status: Accepted

## Goal

Journal PageのJSONL Parameter説明を文章の列挙からTableへ変更し、Field Path、型／Nullable、意味、Event固有`data`を利用者が走査しやすくする。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/127-journal-documentation.md`
- `develop/spec/94-journal-documentation.md`
- `develop/orchestration/tasks/P20-009C-journal-documentation.md`
- `src/Journal/JournalEvent.php`
- `src/Journal/Data/*.php`
- `src/Journal/EmptyJournalData.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Logging/JsonlJournalRecordEncoder.php`

## In Scope

- Observed JSONL直後のParameter説明をMarkdown Tableへ変更
- Top-level、Operation、Actors、Attempt、Event固有Dataを別Tableで説明
- Type／Nullable／意味の実装整合
- 10 Lifecycle Eventと空`data`を含むRegression
- Desktop／Mobile Table表示とPage Overflow確認
- Specification／TODO／STATE／Report同期

## Out of Scope

- JSONL Example、Journal Schema、Event、Encoder、Projectionの変更
- Landing、Sidebar、Content Map、Website Themeの変更
- OpenTelemetry、Replay、Retention、Security本文の変更
- Stable `1.1.0` Artifact変更
- Commit／Push／PR／External Publication

## Files Allowed to Change

- `docs/guide/journal.md`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/94-journal-documentation.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009D-journal-parameter-tables.md`

## Table Contract

Tableは次の5 Groupへ分け、各Tableに`Parameter`、`Type`、`説明`を持たせる。

1. Top-level Record
2. `operation`
3. `operation.actors`／Actor
4. `attempt`
5. Event固有`data`

Typeは`string`、`integer`、`object`、`object | null`、`string | null`等、JSON Consumerが判断できる表現とする。日時はUTC RFC 3339 Microseconds、IdentifierはUUIDv7 Stringであることを説明する。

Event固有`data` Tableは現在の10 Eventをすべて列挙し、実装上27 rowsを持つ。`operation.received`は通常／Ephemeralの2 Variantを別Rowで固定する。

- `operation.received`（通常）: `data.value`（OperationValue Projection）
- `operation.received`（Ephemeral）: `data`（EmptyJournalData、`{}`）
- `operation.accepted`: `{}`
- `attempt.started`: `{}`
- `attempt.succeeded`: `{}`
- `attempt.failed`: `errorType`, `errorMessage`, `retryable`
- `attempt.retry_scheduled`: `failedAttemptId`, `nextAttemptNumber`, `scheduledAt`, `delayMilliseconds`
- `operation.completed`: `outcome`
- `operation.rejected`: `reason.category`, `reason.code`, `reason.violations`
- `operation.failed`: `errorType`, `errorMessage`, `retryable`
- `operation.dead_lettered`: `finalAttemptId`, `finalAttemptNumber`, `reasonType`, `reasonMessage`, `movedAt`

Observed Projection後のFieldであり、Sensitive値やRaw Rejection Valueを説明へ戻さない。

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。

## Acceptance Criteria

- [x] Field列挙の文章が5つのMarkdown Tableへ置き換わる
- [x] 各TableがParameter、Type、説明を持つ
- [x] Top-level 9 FieldがEncoderと一致する
- [x] Operation 7 FieldがEncoderと一致する
- [x] Actors全体と各ActorのNullable境界が明確である
- [x] Attempt全体と3 FieldのNullable境界が明確である
- [x] Event固有Data Tableが10 Eventを省略しない
- [x] Empty Data、Sensitive Projection、Outcome Store境界を維持する
- [x] Website Test／Check／Buildが成功する
- [x] Desktop Light／DarkとMobile 390pxでTableを読め、Page Overflowがない
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-009D-journal-parameter-tables.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
