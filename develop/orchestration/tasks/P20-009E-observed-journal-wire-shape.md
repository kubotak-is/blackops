# P20-009E: Observed Journal Wire Shape

Status: Accepted

## Goal

Observed JournalのProjectorからJSONL Encoderまでの実出力を、空Object、Framework Identifier、日時の確定Wire Contractへ一致させ、P20-009DのJournal Parameter TableをRuntime Evidence付きで完了する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/031-sensitive-projection.md`
- `develop/decisions/128-observed-journal-wire-shape.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/94-journal-documentation.md`
- `develop/orchestration/tasks/P20-009D-journal-parameter-tables.md`
- `src/Internal/Projection/SensitiveProjectionFilter.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Logging/JsonlJournalRecordEncoder.php`

## In Scope

- Empty Journal DataをJSON Object `{}`としてEncode
- Nested Empty OutcomeをJSON Object `{}`としてEncode
- Retry／Dead Letter Data内のFramework IdentifierをUUIDv7 StringとしてEncode
- Retry／Dead Letter Data内の日時をUTC RFC 3339 MicrosecondsとしてEncode
- ObjectとListの空Shapeを区別
- 任意のApplication `Stringable`がSensitive Projectionを迂回しないRegression
- ProjectorからEncoderまでのEnd-to-end Unit Regression
- Journal Guideの`operation.received`通常／Ephemeral Variantを別Rowへ分離
- P20-009DのTask／Report／TODO／STATE同期

## Out of Scope

- Canonical Journal Schema／PostgreSQL Codec変更
- Journal Event集合変更
- Public Operation／Outcome／Value API変更
- 任意のApplication `Stringable`をSafe Scalarとして許可
- Landing、Navigation、Theme、OpenTelemetry本文変更
- Stable `1.1.0` Artifact変更
- Commit／Push／PR

## Files Allowed to Change

- `src/Internal/Projection/SensitiveProjectionFilter.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- Projection／Loggingに対応する`tests/**/*.php`
- `docs/guide/journal.md`
- `docs/website/tests/guide-code.test.mjs`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/94-journal-documentation.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P20-009D-journal-parameter-tables.md`
- `develop/orchestration/reports/P20-009D-journal-parameter-tables.md`
- This Task Packet
- `develop/orchestration/reports/P20-009E-observed-journal-wire-shape.md`

## Security Boundary

- Arbitrary `Stringable`をそのままEncoderへ渡さない。
- Framework管理Identifierだけを安全なUUIDv7 Scalarとして扱う。
- `DateTimeInterface`は日時Scalarとして扱う。
- Application ObjectはPublic PropertyのSensitive Projectionを通す。
- `#[Sensitive]`、予約Key Omit、Actor Mask、Rejected Raw Value除外を維持する。

## Required Regression

ProjectorへCanonical `JournalRecord`を渡し、その結果をEncoderへ渡してJSON Decodeした値を検証する。

1. `EmptyJournalData`: `data === {}`であり`[]`ではない
2. `AttemptRetryScheduledData`
   - `failedAttemptId`: UUIDv7 String
   - `scheduledAt`: UTC RFC 3339 Microseconds
   - Integer Field維持
3. `OperationDeadLetteredData`
   - Nullable `finalAttemptId`
   - UUIDv7 String
   - `movedAt`: UTC RFC 3339 Microseconds
4. `OperationCompletedData(new EmptyOutcome())`: `data.outcome === {}`
5. Empty Listを持つApplication Value／Outcome: `[]`を維持
6. Sensitive Propertyを持つApplication `Stringable`: `__toString()`の秘密値が出力されず、通常Projectionが適用される

JSON Object／Arrayの区別はAssociative Decodeだけでは失われるため、Raw JSONまたはObject Decodeでも検証する。

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection tests/Logging/JsonlJournalObserverTest.php
docker compose run --rm app vendor/bin/phpunit
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。

## Acceptance Criteria

- [x] Empty Journal DataがRaw JSONで`"data":{}`になる
- [x] Nested Empty OutcomeがRaw JSONで`"outcome":{}`になる
- [x] Empty ListはRaw JSONで`[]`を維持する
- [x] Retry／Dead Letter IdentifierがUUIDv7 Stringになる
- [x] Retry／Dead Letter日時がUTC RFC 3339 Microsecondsになる
- [x] Arbitrary StringableがSensitive Filterを迂回しない
- [x] Sensitive Omit／Mask／Hashと予約Key Omitを退行させない
- [x] Projector→Encoder Regressionが追加される
- [x] Journal Tableが通常ReceivedとEphemeral Receivedを別Rowで説明する
- [x] Targeted／Full PHPUnitが成功する
- [x] Website Test／Check／Buildが成功する
- [x] Mago Format／Lint／Analyze、Deptrac、Management-ID Guard、Diff Check結果を記録する
- [x] P20-009Dを再Review可能な状態へ戻す
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-009E-observed-journal-wire-shape.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
