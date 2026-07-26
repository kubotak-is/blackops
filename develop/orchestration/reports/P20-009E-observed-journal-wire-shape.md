# P20-009E Observed Journal Wire Shape Report

## Summary

ProjectorからJSONL EncoderまでのObserved Journal出力をD128のWire Contractへ同期した。Empty Objectは{}、Empty Listは[]を維持し、Framework IdentifierはUUIDv7 String、DateTimeはUTC RFC3339 Microsecondsへ正規化する。任意Application Stringableは__toString()でSensitive Projectionを迂回できず、公開PropertyのProjectionを通過する。Projector→EncoderのRaw JSON Regressionと、通常／Ephemeral operation.received Guide Tableの分離を追加した。

## Changed Files

- src/Internal/Projection/SensitiveProjectionFilter.php
- src/Logging/JsonlJournalRecordEncoder.php
- tests/Internal/Projection/ObservedJournalRecordProjectorTest.php
- docs/guide/journal.md
- docs/website/tests/guide-code.test.mjs
- develop/TODO.md
- develop/STATE.md
- develop/orchestration/tasks/P20-009D-journal-parameter-tables.md
- develop/orchestration/reports/P20-009D-journal-parameter-tables.md
- develop/orchestration/tasks/P20-009E-observed-journal-wire-shape.md
- develop/orchestration/reports/P20-009E-observed-journal-wire-shape.md

## Decisions and Assumptions

- Framework Identifierは既知の8 Identifier型だけをScalar化し、任意の同名／Stringable ObjectはScalar化しない。
- Empty stdClass markerはNested Empty OutcomeとSensitive Omit後の空ObjectをJSON ObjectとしてEncodeする。Top-level Empty Journal DataはEncoderが空配列をObjectへ変換する。
- Unsupported Application Objectはnullへ正規化し、任意のStringableを文字列化しない。
- 既存のActor Mask、Sensitive Omit／Mask／Hash、予約Key Omit、Rejected Raw Value除外は維持した。
- P20-009DはRuntime修正後のReview Pendingへ戻し、TODOとReportを同期した。Worker Commitは行っていない。

## Commands and Results

- docker compose run --rm app vendor/bin/phpunit tests/Internal/Projection tests/Logging/JsonlJournalObserverTest.php — PASS（13 tests、70 assertions）
- docker compose run --rm app vendor/bin/phpunit — PASS（1,883 tests、7,608 assertions、1 deprecation）
- mise exec -- pnpm --dir docs/website run test — PASS（62 tests）
- mise exec -- pnpm --dir docs/website run check — PASS（38 pages、0 errors／warnings／hints）
- mise exec -- pnpm --dir docs/website run build — PASS（39 pages、artifact／site check PASS）。既存のchunk-size／route conflict warningあり
- docker compose run --rm app mago format --check src tests — PASS（All files are already formatted）
- docker compose run --rm app mago lint src/Internal/Projection/SensitiveProjectionFilter.php src/Logging/JsonlJournalRecordEncoder.php tests/Internal/Projection/ObservedJournalRecordProjectorTest.php — PASS（4 existing single-class-per-file warnings、Production新規error／warningなし）
- docker compose run --rm app mago lint src tests — FAIL（既存baselineを含む1,646 issues: 147 errors、1,423 warnings、12 notes、64 help）。変更したProjection／Encoderの新規complexity errorは解消済み。Docker socket permission denied時はescalated再実行で完了した
- docker compose run --rm app mago analyze src tests — FAIL（既存baseline 1,026 issues: 362 errors、3 warnings、1 note、660 help）
- docker compose run --rm app vendor/bin/deptrac analyse --no-progress — FAIL（既存vendor Nikic parserのunexpected token (）
- ! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php' — PASS（該当なし）
- git diff --check — PASS

## Acceptance Criteria

- [x] Empty Journal Data／Nested Empty OutcomeはRaw JSON Object {}。
- [x] Empty ListはRaw JSON Array []。
- [x] Retry／Dead Letter IdentifierはUUIDv7 String。
- [x] Retry／Dead Letter日時はUTC RFC3339 Microseconds。
- [x] Arbitrary StringableはSensitive Filterを迂回しない。
- [x] Sensitive Projectionと予約Key／Actor／Rejected境界を維持。
- [x] Projector→Encoder Regressionを追加。
- [x] Guideで通常／Ephemeral operation.receivedを別Row化。
- [x] Targeted／Full PHPUnit、Website Test／Check／Buildを実行。
- [x] Mago Format、Lint／Analyze、Deptrac、Management-ID Guard、Diff Check結果を記録。
- [x] P20-009DをReview Pendingへ同期。
- [x] Worker Commitなし。

## Remaining Issues

Mago lint／analyzeとDeptracは既存Repository baselineにより非0。Production Codeの変更差分に起因する新規Lint errorは残っていない。Website buildのchunk-size／route conflict warningは既存警告であり、artifact／site checkは成功している。

## Suggested Next Action

P20-010のTask-oriented Guide増強へ進む。Mago／Deptrac baselineは別Taskで扱う。

## Orchestrator Acceptance

OrchestratorはProjector→Encoder targeted PHPUnit、Targeted Mago Lint、Website 62 tests、Playwright ChromiumのDesktop Light／DarkとMobile 390pxを独立検証した。Empty Object、Nested Empty Outcome、Empty List、Retry／Dead Letter Identifierと日時、Stringable Sensitive境界をReviewし、Documentation Reviewerの最終再ReviewでP1／P2／P3なしを確認した。P20-009EをAcceptedとする。

## Documentation Reviewer Correction

P20-009DのTable Contract／ReportをP20-009E後の現行状態へ統合し、Event固有dataを27 rows、operation.received通常／Ephemeralを別Variantとして明記した。旧Runtime ProbeはHistorical FindingからResolvedへ更新した。Specification 94のTraceabilityを実在するSpecification 22／23／24へ修正し、Journal GuideのEmptyJournalData表現を件数依存なしへ変更した。Website test 62、check 38 pages、git diff --checkを再実行しPASS。Review Pending、Commitなし。
