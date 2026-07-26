# P20-009D Journal Parameter Tables Report

## Summary

Observed JSONL直後のField列挙を5 Markdown Tableへ置換し、Encoder／Projector／Journal Data型へ同期した。Event固有dataは27 rowsで、operation.receivedの通常data.valueとEphemeral dataを別Variantとして説明している。P20-009EでRuntime Wire Shapeも修正され、GuideのContractと実出力が一致した。

## Changed Files

- `docs/guide/journal.md`
- `docs/website/tests/guide-code.test.mjs`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P20-009D-journal-parameter-tables.md`
- `develop/orchestration/reports/P20-009D-journal-parameter-tables.md`

## Decisions and Assumptions

- JSONL Example、Landing／Navigation／Theme、後続Section、Framework src／Journal Contractは変更していない。
- Event固有Data Tableだけ`Event`列を追加し、Parameterは実際の`data.*` Pathへ統一した。
- `operation.received`は通常のOperationValue ProjectionとEphemeral OutcomeのEmptyJournalData `{}`を区別した。
- `operation.rejected.data.reason.violations`は`array<object>`とし、各Itemの`field`／`rule`／`code`を列挙した。
- Failure／Reason MessageはSensitiveProjectionFilterが自動Redactするとは主張せず、SecretをMessageへ含めない責務として記載した。
- Correlation／Causation説明をRoot／子Operationの現行ExecutionContext契約へ具体化し、Regressionで固定した。
- OrchestratorはDesktop Light／DarkとMobile 390pxを実測し、Tableの局所横ScrollとPage Overflowなしを確認した。Worker Commitは行っていない。

## Commands and Results

- P20-009E Projector→Encoder targeted PHPUnit — PASS（13 tests、70 assertions）
- `mise exec -- pnpm --dir docs/website run test` — PASS（62 tests）
- `mise exec -- pnpm --dir docs/website run check` — PASS（Blume check 38 pages、0 errors／warnings／hints）
- `mise exec -- pnpm --dir docs/website run build` — PASS（39 pages、artifact／site check PASS）
- `docker compose run --rm app mago format --check src tests` — PASS（All files are already formatted）
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS（該当なし）
- `git diff --check` — PASS

Final accuracy correction: `operation.correlationId`／`operation.causationId` descriptions and matching regression assertions were corrected; website test 62／62 and `git diff --check` were rerun successfully.

Orchestrator Review:

- Playwright Chromium Desktop 1440px Light／Dark、Mobile 390px — PASS（6 Table、Parameter Table row counts 9／7／6／4／27、局所横Scroll、Page Overflowなし、Sidebar current）
- Historical Runtime Probe（P20-009D） — FAILとして記録したが、P20-009EでResolved。Empty Object／Identifier／DateTime Wire Shapeを修正し、Projector→Encoder Regressionを追加した。

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
- [x] Desktop Light／DarkとMobile 390pxでTableを読め、Page Overflowがない（Orchestrator実測）
- [x] WorkerはCommitしない

## Remaining Issues

P20-009D時点のRuntime ProbeはHistorical Findingであり、P20-009EのProduction修正とRegressionでResolved。現行の未解決事項はない。

P20-009Eで通常／Ephemeral Variantを別Rowへ分離済みであり、未解決事項はない。

Buildの既存Vite chunk-size warningとAstro route conflict warningは成功結果を妨げない既存警告として残る。

## Suggested Next Action

P20-010のTask-oriented Guide増強へ進む。現行の壊れた実出力を正としてDocument化する案は採用しない。

## P20-009E Correction Handoff

P20-009EでSensitive Projection／JSONL EncoderのRuntime不整合を修正し、Projector→Encoder Regressionを追加した。Empty Object／Nested Empty Outcomeは`{}`、Empty Listは`[]`、Framework IdentifierはUUIDv7 String、日時はUTC RFC3339 Microsecondsとして出力され、任意Application Stringableの`__toString()`はProjectionを迂回しない。Guideの`operation.received`通常／Ephemeral Variantも別Rowへ分離した。P20-009DをReview Pendingへ戻し、TODOを完了へ同期した。

## Orchestrator Acceptance

Documentation Reviewerの再ReviewでP1／P2／P3なしを確認した。OrchestratorはProjector→Encoder targeted PHPUnit、Website 62 tests、Desktop Light／Dark、Mobile 390pxを独立検証し、5 Parameter Table、27-row Event Data、Sidebar current、局所横Scroll、Page Overflowなしを確認した。P20-009DをAcceptedとする。
